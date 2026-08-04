<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrescriptionApprovalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PatientCase $case,
        public readonly Message $message,
    ) {}

    public function envelope(): Envelope
    {
        $partnerName = $this->case->partner?->name ?? 'Doctor Portal';

        return new Envelope(
            subject: 'Your prescription has been approved — ' . $partnerName,
        );
    }

    public function content(): Content
    {
        $case        = $this->case;
        $message     = $this->message;
        $patient     = $case->patient;
        $clinician   = $case->clinician;
        $partner     = $case->partner;

        return new Content(
            view: 'emails.prescription-approval',
            with: [
                'firstName'     => $patient?->first_name ?? 'there',
                'messageBody'   => $message->body,
                'clinicianName' => $clinician?->full_name ?? 'Your clinician',
                'partnerName'   => $partner?->name ?? 'Doctor Portal',
                'caseRef'       => $case->external_id
                                    ?: strtoupper(substr($case->uuid, 0, 8)),
                'approvedAt'    => now()->format('l, j F Y'),
            ],
        );
    }
}
