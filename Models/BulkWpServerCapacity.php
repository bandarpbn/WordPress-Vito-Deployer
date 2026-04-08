<?php

namespace App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property int $max_sites
 */
class BulkWpServerCapacity extends Model
{
    protected $table = 'bulk_wp_server_capacities';

    protected $fillable = [
        'server_id',
        'max_sites',
    ];

    protected $casts = [
        'max_sites' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function getCurrentSiteCount(): int
    {
        return $this->server->sites()->count();
    }

    public function getRemainingCapacity(): int
    {
        return max(0, $this->max_sites - $this->getCurrentSiteCount());
    }

    public function isFull(): bool
    {
        return $this->getRemainingCapacity() <= 0;
    }
}
