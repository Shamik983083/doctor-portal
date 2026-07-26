<?php

namespace App\Providers;

use App\Adapters\Sms\MockSmsAdapter;
use App\Adapters\Sms\TwilioSmsAdapter;
use App\Contracts\KarenInterface;
use App\Contracts\SmsAdapter;
use App\Models\Message;
use App\Models\PatientCase;
use App\Services\Karen\MockKarenService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // F20: Karen automated-outreach service.
        $this->app->singleton(KarenInterface::class, function () {
            if (config('services.karen.enabled', false)) {
                // Real implementation registered here once built.
            }
            return new MockKarenService();
        });

        // Phase 1d: SMS adapter scaffold.
        // Both gates must be open for live delivery; otherwise mock logs and returns true.
        $this->app->singleton(SmsAdapter::class, function () {
            if (
                config('sms.enabled', false) &&
                config('sms.baa_confirmed', false) &&
                config('sms.driver') === 'twilio'
            ) {
                return new TwilioSmsAdapter(
                    config('sms.twilio.sid', ''),
                    config('sms.twilio.token', ''),
                    config('sms.twilio.from', ''),
                );
            }

            return new MockSmsAdapter();
        });
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Passport::tokensExpireIn(now()->addDays(1));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Broadcast::routes(['middleware' => ['web', 'auth']]);

        require base_path('routes/channels.php');

        $this->composeClinicianSidebar();
    }

    /**
     * Feed the clinician sidebar its live badge counts (Devin msg 2253: match
     * the design preview, which shows a count on every nav item).
     *
     * Bound to the clinician layout so every clinician page gets the same
     * numbers without each controller recomputing them. Real data only, so an
     * empty queue reads zero rather than a mocked figure.
     */
    private function composeClinicianSidebar(): void
    {
        View::composer(['layouts.clinician', 'layouts.clinician-exact'], function ($view) {
            $clinician = Auth::user()?->clinician;

            // No clinician record (an admin on the shared clinician routes):
            // render the nav with zeros rather than 500 on a null.
            if (! $clinician) {
                $view->with('clinicianNav', $this->emptyClinicianNav());
                return;
            }

            $open = ['waiting', 'assigned', 'support'];

            // The unclaimed queue: waiting cases the provider could pick up.
            $queueCount = PatientCase::where('status', 'waiting')->count();

            // Mine: everything currently on my plate.
            $mineBase = PatientCase::where('clinician_id', $clinician->id)
                ->whereIn('status', ['assigned', 'support', 'processing']);

            $myCases      = (clone $mineBase)->count();
            $myEscalations = PatientCase::where('clinician_id', $clinician->id)
                ->where('status', 'support')->count();

            // "Support thread open": support cases with at least one unread inbound message.
            $supportThreadOpen = PatientCase::where('clinician_id', $clinician->id)
                ->where('status', 'support')
                ->whereHas('messages', fn ($q) => $q->where('direction', 'inbound')->where('is_read', false))
                ->count();

            // Refills badge (Devin msg 2285): check-ins for patients this
            // clinician has seen. Same shape as the Refills screen query.
            $seenPatientIds = PatientCase::where('clinician_id', $clinician->id)
                ->where('status', 'completed')->pluck('patient_id')->filter()->unique();
            $refills = PatientCase::where('is_refill', true)
                ->where(function ($q) use ($clinician, $seenPatientIds) {
                    $q->where('clinician_id', $clinician->id);
                    if ($seenPatientIds->isNotEmpty()) { $q->orWhereIn('patient_id', $seenPatientIds); }
                })->count();

            // Messages waiting on me: unread inbound on cases assigned to me.
            $messages = Message::query()
                ->join('cases', 'cases.id', '=', 'messages.case_id')
                ->where('cases.clinician_id', $clinician->id)
                ->where('messages.direction', 'inbound')
                ->where('messages.is_read', false)
                ->count();

            // Triage bands scoped to this clinician's open cases.
            $triage = PatientCase::where('clinician_id', $clinician->id)
                ->whereIn('status', $open)
                ->selectRaw('triage, COUNT(*) as c')
                ->groupBy('triage')
                ->pluck('c', 'triage');

            $view->with('clinicianNav', [
                'queue'       => $queueCount,
                'myCases'     => $myCases,
                'refills'     => $refills,
                'messages'    => $messages,
                'escalations' => $myEscalations,
                'support'     => $supportThreadOpen,
                'red'         => (int) $triage->get('red', 0),
                'yellow'      => (int) $triage->get('yellow', 0),
                'green'       => (int) $triage->get('green', 0),
            ]);
        });
    }

    private function emptyClinicianNav(): array
    {
        return [
            'queue' => 0, 'myCases' => 0, 'refills' => 0, 'messages' => 0, 'escalations' => 0,
            'support' => 0, 'red' => 0, 'yellow' => 0, 'green' => 0,
        ];
    }
}
