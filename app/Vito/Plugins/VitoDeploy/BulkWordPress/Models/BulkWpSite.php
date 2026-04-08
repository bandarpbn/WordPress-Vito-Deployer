<?php

namespace App\Vito\Plugins\VitoDeploy\BulkWordPress\Models;

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $config_id
 * @property int|null $site_id
 * @property string $batch_id
 * @property string $domain
 * @property int $server_id
 * @property string|null $title
 * @property string|null $tagline
 * @property string $timezone
 * @property string|null $admin_username
 * @property string|null $admin_email
 * @property string|null $admin_password
 * @property string|null $plugins
 * @property string|null $theme
 * @property string $status
 * @property string|null $current_step
 * @property string|null $error
 * @property string|null $app_password
 * @property int $retry_count
 */
class BulkWpSite extends Model
{
    protected $table = 'bulk_wp_sites';

    protected $fillable = [
        'user_id',
        'config_id',
        'site_id',
        'batch_id',
        'domain',
        'server_id',
        'title',
        'tagline',
        'timezone',
        'admin_username',
        'admin_email',
        'admin_password',
        'plugins',
        'theme',
        'status',
        'current_step',
        'error',
        'app_password',
        'retry_count',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'user_id' => 'integer',
        'config_id' => 'integer',
        'site_id' => 'integer',
        'retry_count' => 'integer',
    ];

    protected $hidden = [
        'admin_password',
        'app_password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(BulkWpConfig::class, 'config_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function setAdminPasswordAttribute(?string $value): void
    {
        $this->attributes['admin_password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getDecryptedAdminPassword(): ?string
    {
        return $this->attributes['admin_password'] ? Crypt::decryptString($this->attributes['admin_password']) : null;
    }

    public function setAppPasswordAttribute(?string $value): void
    {
        $this->attributes['app_password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getDecryptedAppPassword(): ?string
    {
        return $this->attributes['app_password'] ? Crypt::decryptString($this->attributes['app_password']) : null;
    }

    public function getPluginsArray(): array
    {
        if (empty($this->plugins)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $this->plugins)));
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
