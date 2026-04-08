<?php

namespace App\Vito\Plugins\VitoDeploy\BulkWordPress\Events;

use App\Vito\Plugins\VitoDeploy\BulkWordPress\Models\BulkWpSite;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteProvisionStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BulkWpSite $bulkWpSite,
        public int $doneCount = 0,
        public int $totalCount = 0,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('bulk-wordpress.'.$this->bulkWpSite->batch_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'site_id' => $this->bulkWpSite->id,
            'domain' => $this->bulkWpSite->domain,
            'status' => $this->bulkWpSite->status,
            'step' => $this->bulkWpSite->current_step,
            'error' => $this->bulkWpSite->error,
            'progress' => [
                'done' => $this->doneCount,
                'total' => $this->totalCount,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'site.status.updated';
    }
}
