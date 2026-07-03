<?php

namespace App\Console\Commands;

use App\Jobs\SendWebhookJob;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class RecoverOrphanedWebhooksCommand extends Command
{
    protected $signature   = 'webhooks:recover';
    protected $description = 'Re-dispatch pending webhook deliveries that were never picked up by a worker';

    public function handle(): void
    {
        $orphans = WebhookDelivery::where('status', WebhookDelivery::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(10))
            ->whereDoesntHave('webhook', fn($q) => $q->where('status', '!=', 'active'))
            ->get();

        foreach ($orphans as $delivery) {
            SendWebhookJob::dispatch($delivery->id)->onQueue('webhooks');
        }

        $this->info("Recovered {$orphans->count()} orphaned webhook delivery(s).");
    }
}
