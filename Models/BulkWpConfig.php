<?php

namespace App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $plugins
 * @property string|null $themes
 * @property array|null $defaults
 * @property string|null $sidebar_widget
 * @property int $max_sites_per_server
 * @property int $max_concurrent_per_server
 * @property int $max_concurrent_global
 * @property int $max_retries
 */
class BulkWpConfig extends Model
{
    protected $table = 'bulk_wp_configs';

    protected $fillable = [
        'user_id',
        'name',
        'plugins',
        'themes',
        'defaults',
        'sidebar_widget',
        'max_sites_per_server',
        'max_concurrent_per_server',
        'max_concurrent_global',
        'max_retries',
    ];

    protected $casts = [
        'defaults' => 'array',
        'max_sites_per_server' => 'integer',
        'max_concurrent_per_server' => 'integer',
        'max_concurrent_global' => 'integer',
        'max_retries' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(BulkWpSite::class, 'config_id');
    }

    public function getPluginsArray(): array
    {
        if (empty($this->plugins)) {
            return [];
        }

        return array_filter(array_map('trim', preg_split('/[\n,]+/', $this->plugins)));
    }

    public function getThemesArray(): array
    {
        if (empty($this->themes)) {
            return [];
        }

        return array_filter(array_map('trim', preg_split('/[\n,]+/', $this->themes)));
    }

    public function getDefaultSidebarWidget(): string
    {
        return $this->sidebar_widget ?? '<ul><li><a href="https://{domain}"><strong>https://{domain}</strong></a></li></ul>';
    }
}
