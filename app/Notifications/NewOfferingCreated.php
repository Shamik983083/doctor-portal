<?php

namespace App\Notifications;

use App\Models\Offering;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOfferingCreated extends Notification
{
    use Queueable;

    public function __construct(private Offering $offering) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $partner = $this->offering->partner?->name ?? 'Unknown partner';
        $status  = $this->offering->approval_status ?? 'pending';

        return [
            'type'         => 'new_offering',
            'title'        => 'New offering requires review',
            'body'         => '"' . $this->offering->name . '" submitted by ' . $partner . ' — status: ' . ucfirst($status) . '.',
            'url'          => '/admin/offerings/' . $this->offering->id . '/edit',
            'offering_id'  => $this->offering->id,
        ];
    }
}
