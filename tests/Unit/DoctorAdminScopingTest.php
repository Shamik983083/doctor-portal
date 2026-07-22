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

    /**
     * THE BUG THIS SUITE MISSED (Devin msg 2234).
     *
     * A waiting case has clinician_id NULL, and `whereIn` never matches NULL, so
     * a Doctor Admin saw an empty intake queue: they could see work already
     * handed to their doctors but not the work waiting to be handed out. The
     * earlier tests all asserted on SQL SHAPE, so every one of them still passed
     * while the screen a Doctor Admin uses most showed nothing.
     */
    public function test_a_doctor_admin_can_see_unassigned_cases(): void
    {
        $sql = strtolower($this->sqlFor(PatientCase::visibleTo($this->userSeeing([4, 7]))));

        $this->assertStringContainsString('clinician_id" is null', $sql,
            'unassigned cases must be reachable, or the intake queue is empty for a Doctor Admin');
        $this->assertStringContainsString('or', $sql,
            'the unassigned clause is an alternative to the id list, not an additional filter');
    }

    /**
     * THE GROUPING, which is the part that silently leaks if it is dropped.
     *
     * Callers chain conditions onto this scope. Ungrouped, `in (...) or is null`
     * followed by `and status = ?` binds as `in (...) OR (is null AND status = ?)`,
     * and the first branch then matches every case in the system regardless of
     * status. Asserting the parenthesis is asserting the difference between
     * scoped and not scoped.
     */
    public function test_the_unassigned_clause_is_grouped_so_later_conditions_cannot_leak(): void
    {
        $sql = strtolower($this->sqlFor(
            PatientCase::visibleTo($this->userSeeing([4, 7]))->where('status', 'support')
        ));

        $this->assertMatchesRegularExpression('/\(.*clinician_id.*or.*is null.*\)/s', $sql,
            'the id list and the null check must sit inside one group');
        $this->assertStringContainsString('and "status" = ?', $sql,
            'the chained condition must apply to the whole group, not to one branch of it');
    }

    /**
     * The exemption must not become a hole. An admin over nobody sees nothing,
     * and "nothing" includes the unassigned pool.
     */
    public function test_an_admin_over_no_doctors_still_sees_no_unassigned_cases(): void
    {
        $sql = strtolower($this->sqlFor(PatientCase::visibleTo($this->userSeeing([]))));

        $this->assertStringContainsString('0 = 1', $sql);
        $this->assertStringNotContainsString('is null', $sql,
            'an empty assignment must not be handed the intake queue as a consolation prize');
    }

    /**
     * A case the admin can see must have a patient the admin can open, or the
     * queue lists rows that 404 when clicked.
     */
    public function test_patients_behind_unassigned_cases_are_reachable(): void
    {
        $sql = strtolower($this->sqlFor(\App\Models\Patient::visibleTo($this->userSeeing([4, 7]))));

        $this->assertStringContainsString('clinician_id" is null', $sql,
            'the patient scope must track the case scope, or an unassigned case has an unopenable patient');
    }

    public function test_the_same_rules_apply_to_the_clinician_list(): void
    {
        $this->assertStringNotContainsString('"id" in', $this->sqlFor(Clinician::visibleTo($this->userSeeing(null))));

        $scoped = Clinician::visibleTo($this->userSeeing([2]));
        $this->assertStringContainsString('id', $this->sqlFor($scoped));
        $this->assertSame([2], $scoped->getBindings());

        $this->assertStringContainsString('0 = 1', $this->sqlFor(Clinician::visibleTo($this->userSeeing([]))));
    }

    /**
     * Patients are PHI. A Doctor Admin sees a patient only through a case that
     * belongs to one of their doctors.
     */
    public function test_patients_are_reachable_only_through_a_case_with_one_of_my_doctors(): void
    {
        $unrestricted = \App\Models\Patient::visibleTo($this->userSeeing(null));
        $this->assertStringNotContainsString('exists', strtolower($this->sqlFor($unrestricted)),
            'a super admin sees every patient with no subquery restriction');

        $scoped = \App\Models\Patient::visibleTo($this->userSeeing([4, 7]));
        $sql = strtolower($this->sqlFor($scoped));
        $this->assertStringContainsString('exists', $sql, 'scoping is applied through the case relation');
        $this->assertStringContainsString('clinician_id', $sql);
        $this->assertSame([4, 7], $scoped->getBindings());
    }

    public function test_an_admin_over_no_doctors_sees_no_patients(): void
    {
        $sql = $this->sqlFor(\App\Models\Patient::visibleTo($this->userSeeing([])));

        $this->assertStringContainsString('0 = 1', $sql,
            'an admin assigned nobody must see no patients, not every patient');
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
