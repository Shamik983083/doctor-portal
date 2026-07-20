<?php

namespace Tests\Unit;

use App\Models\Clinician;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

/**
 * Two-tier admin (Devin msg 2117): super_admin sees everything, a Doctor Admin
 * sees only the doctors they are over.
 *
 * The rule most worth defending here is the one that reads backwards: an admin
 * assigned NO doctors must see NOTHING. The natural-looking
 * `if (! $ids) return $query;` would hand a half-configured admin the entire
 * system, which is the exact opposite of scoping. These tests pin that.
 *
 * Database-free, like the other suites in this repo: there is one model factory
 * (UserFactory), so anything relying on Clinician::factory() could not run. The
 * scopes are pure query construction, so the SQL is asserted directly.
 */
class DoctorAdminScopingTest extends TestCase
{
    /** A stand-in user whose visible ids we control, with no roles table needed. */
    private function userSeeing(?array $ids): User
    {
        return new class($ids) extends User {
            public function __construct(private ?array $ids) { parent::__construct(); }
            public function visibleClinicianIds(): ?array { return $this->ids; }
        };
    }

    private function sqlFor(Builder $query): string
    {
        return $query->toSql();
    }

    public function test_a_super_admin_query_is_not_restricted(): void
    {
        $query = PatientCase::visibleTo($this->userSeeing(null));

        $this->assertStringNotContainsString('clinician_id', $this->sqlFor($query),
            'a super admin must see every case, with no clinician restriction applied');
    }

    public function test_a_doctor_admin_is_restricted_to_their_doctors(): void
    {
        $query = PatientCase::visibleTo($this->userSeeing([4, 7]));

        $this->assertStringContainsString('clinician_id', $this->sqlFor($query));
        $this->assertSame([4, 7], $query->getBindings());
    }

    /**
     * THE IMPORTANT ONE. An admin over nobody sees nothing, not everything.
     */
    public function test_an_admin_over_no_doctors_sees_nothing_not_everything(): void
    {
        $query = PatientCase::visibleTo($this->userSeeing([]));
        $sql   = $this->sqlFor($query);

        $this->assertStringContainsString('clinician_id', $sql,
            'an empty assignment must still apply a restriction');
        $this->assertStringContainsString('0 = 1', $sql,
            'whereIn with an empty list must resolve to a false condition, matching no rows');
    }

    public function test_the_same_rules_apply_to_the_clinician_list(): void
    {
        $this->assertStringNotContainsString('"id" in', $this->sqlFor(Clinician::visibleTo($this->userSeeing(null))));

        $scoped = Clinician::visibleTo($this->userSeeing([2]));
        $this->assertStringContainsString('id', $this->sqlFor($scoped));
        $this->assertSame([2], $scoped->getBindings());

        $this->assertStringContainsString('0 = 1', $this->sqlFor(Clinician::visibleTo($this->userSeeing([]))));
    }

    /** A null user (no request context) must not silently mean "see everything" by accident. */
    public function test_a_null_user_is_treated_as_unrestricted_deliberately(): void
    {
        // Documented behaviour: routes are already behind auth middleware, so a
        // null user here means a console or system context, not an anonymous web
        // request. Pinned so a future change has to be deliberate.
        $this->assertStringNotContainsString('clinician_id', $this->sqlFor(PatientCase::visibleTo(null)));
    }
}
