<?php

namespace Tests\Unit;

use App\Models\Partner;
use App\Models\PartnerEhrSetting;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Services\Ehr\EhrGatewayManager;
use App\Services\Ehr\HealthieEhrAdapter;
use App\Services\EhrRecordService;
use RuntimeException;
use Tests\TestCase;

/**
 * The rule these tests exist to defend: Healthie data MUST NOT cross between
 * storefronts. The same human arriving through two storefronts is two records,
 * and nothing from the first may be reused for the second.
 *
 * That rule is easy to state, easy to agree with, and quietly broken by a
 * one-line convenience change six months later. That is what this is for.
 *
 * DELIBERATELY DATABASE-FREE. This repo has exactly one model factory
 * (UserFactory), so tests that lean on Partner::factory() or PatientCase::factory()
 * could not run at all. Everything asserted here is pure payload-construction
 * logic, so the models are built in memory and their relations set directly. No
 * migrations, no fixtures, nothing to keep in sync.
 */
class EhrTenantSegregationTest extends TestCase
{
    private function service(): EhrRecordService
    {
        return new EhrRecordService(new EhrGatewayManager());
    }

    private function partner(int $id, string $uuid, string $name): Partner
    {
        $partner = new Partner(['uuid' => $uuid, 'name' => $name, 'slug' => strtolower($name)]);
        $partner->id   = $id;
        $partner->uuid = $uuid;

        return $partner;
    }

    private function patient(int $id, Partner $partner, string $email, ?string $externalId): Patient
    {
        $patient = new Patient([
            'partner_id'  => $partner->id,
            'email'       => $email,
            'external_id' => $externalId,
            'first_name'  => 'Sam',
            'last_name'   => 'Okafor',
        ]);
        $patient->id   = $id;
        $patient->uuid = 'patient-uuid-' . $id;

        return $patient;
    }

    /** Relations are set explicitly so nothing here touches a database. */
    private function caseFor(Partner $partner, Patient $patient): PatientCase
    {
        $case = new PatientCase(['uuid' => 'case-uuid-1', 'partner_id' => $partner->id, 'status' => 'approved']);
        $case->id         = 1;
        $case->uuid       = 'case-uuid-1';
        $case->partner_id = $partner->id;
        $case->patient_id = $patient->id;
        $case->setRelation('partner', $partner);
        $case->setRelation('patient', $patient);
        $case->setRelation('clinician', null);

        return $case;
    }

    /** The same person from two storefronts must never produce the same vendor key. */
    public function test_same_person_from_two_storefronts_gets_two_distinct_patient_keys(): void
    {
        $a = $this->partner(1, 'partner-uuid-a', 'StorefrontA');
        $b = $this->partner(2, 'partner-uuid-b', 'StorefrontB');

        // Same human: same email, and both storefronts happen to issue the same
        // customer id. This is the exact collision the namespacing has to survive.
        $patientA = $this->patient(10, $a, 'same@example.com', 'CUST-1');
        $patientB = $this->patient(11, $b, 'same@example.com', 'CUST-1');

        $service = $this->service();
        $keyA = $service->scopedPatientKey($this->caseFor($a, $patientA), $patientA);
        $keyB = $service->scopedPatientKey($this->caseFor($b, $patientB), $patientB);

        $this->assertNotSame($keyA, $keyB, 'identical people from different storefronts must not share a vendor key');
        $this->assertStringContainsString('partner-uuid-a', $keyA);
        $this->assertStringContainsString('partner-uuid-b', $keyB);
    }

    /** The payload must carry the company, and the namespaced key, never the raw one. */
    public function test_payload_carries_the_company_and_a_namespaced_patient_key(): void
    {
        $partner = $this->partner(1, 'partner-uuid-a', 'StorefrontA');
        $patient = $this->patient(10, $partner, 'a@example.com', 'CUST-9');
        $case    = $this->caseFor($partner, $patient);

        $payload = $this->service()->buildPayload($case, null, []);

        $this->assertSame(1, $payload['company']['partner_id']);
        $this->assertSame('partner-uuid-a', $payload['company']['partner_uuid']);

        $this->assertNotSame('CUST-9', $payload['patient']['external_id'],
            'the raw storefront id must never be the vendor matching key on its own');
        $this->assertStringContainsString('partner-uuid-a', $payload['patient']['external_id']);
        $this->assertSame('CUST-9', $payload['patient']['source_external_id']);
    }

    /** A case whose patient belongs to another partner is a leak. It must fail loudly. */
    public function test_a_partner_mismatch_between_case_and_patient_is_refused(): void
    {
        $a = $this->partner(1, 'partner-uuid-a', 'StorefrontA');
        $b = $this->partner(2, 'partner-uuid-b', 'StorefrontB');

        $patientOfB = $this->patient(11, $b, 'b@example.com', 'CUST-2');
        $caseOfA    = $this->caseFor($a, $patientOfB);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cross one storefront/i');

        $this->service()->buildPayload($caseOfA, null, []);
    }

    /** An unattributed record is the one most likely to be merged. Refuse it. */
    public function test_a_case_with_no_partner_is_refused(): void
    {
        $partner = $this->partner(1, 'partner-uuid-a', 'StorefrontA');
        $patient = $this->patient(10, $partner, 'a@example.com', 'CUST-9');
        $case    = $this->caseFor($partner, $patient);

        $case->partner_id = null;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no partner/i');

        $this->service()->buildPayload($case, null, []);
    }

    /** Declined medications must survive into the chart, not just approved ones. */
    public function test_declined_medications_are_carried_into_the_payload(): void
    {
        $partner = $this->partner(1, 'partner-uuid-a', 'StorefrontA');
        $patient = $this->patient(10, $partner, 'a@example.com', 'CUST-9');
        $case    = $this->caseFor($partner, $patient);

        $payload = $this->service()->buildPayload($case, null, [
            ['name' => 'Semaglutide', 'decision' => 'approve', 'months' => ['L1 2.5 mg', 'L2 5 mg']],
            ['name' => 'Zofran',      'decision' => 'deny'],
        ]);

        $this->assertCount(1, $payload['medications']);
        $this->assertSame('Semaglutide', $payload['medications'][0]['name']);
        $this->assertCount(1, $payload['declined']);
        $this->assertSame('Zofran', $payload['declined'][0]['name']);
    }

    /** The real adapter must stay unreachable until both flags are set deliberately. */
    public function test_the_healthie_adapter_cannot_be_resolved_without_both_flags(): void
    {
        config(['ehr.adapter' => 'healthie', 'ehr.enabled' => true, 'ehr.sandbox_validated' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sandbox_validated/');

        (new EhrGatewayManager())->resolve(1);
    }

    /**
     * Even with both platform flags set, a real adapter cannot be resolved
     * without naming a company. This is the signature that makes a shared
     * credential impossible rather than merely discouraged.
     */
    public function test_a_real_adapter_cannot_be_resolved_without_naming_a_company(): void
    {
        config(['ehr.adapter' => 'healthie', 'ehr.enabled' => true, 'ehr.sandbox_validated' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/specific company|no shared credential/i');

        (new EhrGatewayManager())->resolve(null);
    }

    /** And the mock is always safe, so staging can exercise the pipeline. */
    public function test_the_mock_adapter_resolves_with_no_flags_set(): void
    {
        config(['ehr.adapter' => 'mock', 'ehr.enabled' => false, 'ehr.sandbox_validated' => false]);

        $this->assertSame('mock', (new EhrGatewayManager())->resolve(1)->key());
        $this->assertFalse((new EhrGatewayManager())->pushEnabled(1));
    }

    /**
     * The last line of defence: a payload built for one company can never be
     * pushed with another company's credential, even if something upstream
     * resolved the wrong settings.
     */
    public function test_the_adapter_refuses_a_payload_belonging_to_another_company(): void
    {
        $settings = new PartnerEhrSetting([
            'provider' => 'healthie', 'api_key' => 'k', 'endpoint' => 'https://example.test/graphql',
            'is_enabled' => true, 'sandbox_validated' => true,
        ]);
        $settings->partner_id = 2;

        $adapter = new HealthieEhrAdapter($settings);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cross one storefront/i');

        // Payload says partner 1, credentials belong to partner 2.
        $adapter->createRecord(['company' => ['partner_id' => 1], 'note' => ['text' => 'x']]);
    }

    /** Healthie's documented auth headers, including the conditional shard. */
    public function test_auth_headers_match_healthie_documented_shape(): void
    {
        $settings = new PartnerEhrSetting(['api_key' => 'SECRET', 'provider' => 'healthie']);

        $headers = $settings->authHeaders();
        $this->assertSame('Basic SECRET', $headers['Authorization']);
        $this->assertSame('API', $headers['AuthorizationSource']);
        $this->assertArrayNotHasKey('AuthorizationShard', $headers,
            'the shard header is only sent when the account is actually sharded');

        $sharded = new PartnerEhrSetting([
            'api_key' => 'SECRET', 'provider' => 'healthie', 'authorization_shard' => 'SHARD-1',
        ]);
        $this->assertSame('SHARD-1', $sharded->authHeaders()['AuthorizationShard']);
    }
}
