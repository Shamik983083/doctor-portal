<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Message;
use App\Models\PatientCase;
use Illuminate\Http\Request;

class MessagesController extends Controller
{
    /**
     * DA4: Two-mode admin messaging inbox.
     *
     *  patient  — portal-channel threads on cases in this admin's scope (read-only).
     *             Lets admins monitor patient↔clinician conversations without interrupting them.
     *  provider — internal-channel messages between this admin and clinicians on specific cases.
     *             The primary escalation-response channel: admin reviews a case and writes
     *             directly to the assigned clinician; clinician reads and replies in their
     *             case view; admin sees the full thread here.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $tab  = $request->input('tab') === 'provider' ? 'provider' : 'patient';

        // Subquery for visibleTo() — reused below to avoid re-evaluating the scope.
        $visibleCaseIds = PatientCase::visibleTo($user)->select('id');

        // ── Case list (left pane) ────────────────────────────────────────────
        if ($tab === 'patient') {
            $cases = PatientCase::visibleTo($user)
                ->whereHas('messages', fn ($q) => $q->where('channel', 'portal'))
                ->with(['patient', 'partner'])
                ->withCount([
                    'messages as unread_count' => fn ($q) => $q
                        ->where('channel', 'portal')
                        ->where('direction', 'inbound')
                        ->where('is_read', false),
                ])
                ->withMax('messages as last_message_at', 'created_at')
                ->orderByDesc('last_message_at')
                ->paginate(25)
                ->withQueryString();
        } else {
            $cases = PatientCase::visibleTo($user)
                ->whereHas('messages', fn ($q) => $q->where('channel', 'internal'))
                ->with(['patient', 'clinician.user', 'partner'])
                ->withMax('messages as last_message_at', 'created_at')
                ->orderByDesc('last_message_at')
                ->paginate(25)
                ->withQueryString();
        }

        // ── Last-message preview per case (one query, not N) ────────────────
        $latestByCase = Message::whereIn('case_id', $cases->pluck('id'))
            ->when($tab === 'patient', fn ($q) => $q->where('channel', 'portal'))
            ->when($tab === 'provider', fn ($q) => $q->where('channel', 'internal'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('case_id')
            ->map(fn ($g) => $g->first());

        // ── Selected case thread (right pane) ────────────────────────────────
        $selected  = null;
        $thread    = collect();
        $clinicians = collect();

        if ($cases->isNotEmpty()) {
            // Try the current page first (avoids an extra DB round-trip in most opens).
            if ($request->filled('case')) {
                $selected = $cases->firstWhere('uuid', $request->input('case'));

                // Case is valid but landed on a different pagination page
                // (e.g. user bookmarked the URL and the page has since shifted).
                if (!$selected) {
                    $fallback = PatientCase::visibleTo($user)
                        ->where('uuid', $request->input('case'))
                        ->when($tab === 'patient', fn ($q) => $q->whereHas('messages', fn ($q) => $q->where('channel', 'portal')))
                        ->when($tab === 'provider', fn ($q) => $q->whereHas('messages', fn ($q) => $q->where('channel', 'internal')))
                        ->with(['patient', 'clinician.user', 'partner'])
                        ->first();
                    $selected = $fallback;
                }
            }

            $selected = $selected ?: $cases->first();

            if ($selected) {
                if ($tab === 'patient') {
                    $thread = $selected->messages()
                        ->where('channel', 'portal')
                        ->with(['clinician.user'])
                        ->orderBy('created_at')
                        ->get();

                    // Mark portal-inbound as read now that an admin is viewing the thread.
                    $selected->messages()
                        ->where('channel', 'portal')
                        ->where('direction', 'inbound')
                        ->where('is_read', false)
                        ->update(['is_read' => true, 'read_at' => now()]);

                } else {
                    // Show the full thread (portal + internal) so the admin sees the
                    // conversation in context — clinician replies land as portal-channel
                    // messages, and they must appear alongside the admin's internal notes.
                    $thread = $selected->messages()
                        ->with(['clinician.user', 'user'])
                        ->orderBy('created_at')
                        ->get();

                    // Clinicians available to this admin (used by compose form to
                    // confirm recipient; defaults to case's assigned clinician).
                    $clinicians = Clinician::visibleTo($user)->with('user')->get();
                }
            }
        }

        // ── Tab badge counts ─────────────────────────────────────────────────
        // Scoped to visibleTo() via the subquery; a Doctor Admin never sees
        // unread counts from cases outside their group.
        $patientUnread = Message::whereIn('case_id', $visibleCaseIds)
            ->where('channel', 'portal')
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->count();

        $providerCaseCount = PatientCase::visibleTo($user)
            ->whereHas('messages', fn ($q) => $q->where('channel', 'internal'))
            ->count();

        return view('admin.messages.index', compact(
            'cases', 'tab', 'latestByCase', 'selected', 'thread',
            'clinicians', 'patientUnread', 'providerCaseCount', 'user'
        ));
    }

    /**
     * POST: send an internal-channel message to the clinician on a specific case.
     *
     * Validates that the case is in the admin's scope (visibleTo()) and that
     * the case has an assigned clinician to receive the message.
     */
    public function sendInternal(Request $request)
    {
        $request->validate([
            'case_uuid' => 'required|string',
            'body'      => 'required|string|max:5000',
        ]);

        $user = $request->user();

        // Case must be visible to this admin — 404 rather than 403 (same
        // reasoning as admin.cases.show: don't confirm existence of out-of-scope records).
        $case = PatientCase::visibleTo($user)
            ->where('uuid', $request->input('case_uuid'))
            ->firstOrFail();

        if (!$case->clinician_id) {
            return back()->with('error', 'This case has no assigned clinician. Assign a clinician before sending an internal message.');
        }

        Message::create([
            'case_id'     => $case->id,
            'user_id'     => $user->id,
            'clinician_id'=> $case->clinician_id,
            'partner_id'  => $case->partner_id,
            'direction'   => 'outbound',
            'channel'     => 'internal',
            'sender_type' => 'admin',
            'body'        => $request->input('body'),
        ]);

        return redirect()
            ->route('admin.messages.index', ['tab' => 'provider', 'case' => $case->uuid])
            ->with('success', 'Message sent to ' . ($case->clinician->full_name ?? 'the clinician') . '.');
    }
}
