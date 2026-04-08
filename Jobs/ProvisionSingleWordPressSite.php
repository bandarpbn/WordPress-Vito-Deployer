<?php

namespace App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Jobs;

use App\Actions\Database\CreateDatabase;
use App\Actions\Database\CreateDatabaseUser;
use App\Actions\Site\CreateSite;
use App\Actions\SSL\CreateSSL;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Events\SiteProvisionStatusUpdated;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Models\BulkWpConfig;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Models\BulkWpServerCapacity;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Models\BulkWpSite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionSingleWordPressSite implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public BulkWpSite $bulkWpSite,
    ) {}

    public function handle(): void
    {
        $site = $this->bulkWpSite;
        $server = $site->server;
        $config = $site->config ?? BulkWpConfig::where('user_id', $site->user_id)->first();

        // Concurrency control per server
        $maxConcurrent = $config?->max_concurrent_per_server ?? 3;
        $lockKey = "bulk-wp-server-{$server->id}-running";
        $running = (int) Cache::get($lockKey, 0);

        if ($running >= $maxConcurrent) {
            $this->release(15);

            return;
        }

        Cache::increment($lockKey);

        try {
            $this->doProvision($config);
        } finally {
            Cache::decrement($lockKey);
        }
    }

    private function doProvision(?BulkWpConfig $config): void
    {
        $site = $this->bulkWpSite;
        $server = $site->server;

        // Step 1: Capacity check
        $this->updateStep('Checking server capacity...');
        $capacity = BulkWpServerCapacity::firstOrCreate(
            ['server_id' => $server->id],
            ['max_sites' => $config?->max_sites_per_server ?? 50]
        );

        if ($capacity->isFull()) {
            $this->markFailed('Server has reached maximum site capacity ('.$capacity->max_sites.')');

            return;
        }

        // Step 2: Create database
        $this->updateStep('Creating database...');
        $dbName = 'wp_'.preg_replace('/[^a-zA-Z0-9_]/', '_', $site->domain);
        $dbName = substr($dbName, 0, 64);
        $dbUserName = substr($dbName.'_u', 0, 32);
        $dbPass = bin2hex(random_bytes(12));

        try {
            $database = app(CreateDatabase::class)->create($server, [
                'name' => $dbName,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'username' => $dbUserName,
                'password' => $dbPass,
            ]);

            $dbUser = $server->databaseUsers()->where('username', $dbUserName)->first();
        } catch (Throwable $e) {
            $this->markFailed('Failed to create database: '.$e->getMessage());

            return;
        }

        // Step 3: Create site in VitoDeploy
        $this->updateStep('Creating site in VitoDeploy...');
        $phpVersions = $server->installedPHPVersions();
        if (empty($phpVersions)) {
            $this->markFailed('No PHP version installed on server');

            return;
        }

        try {
            $isolatedUser = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace('.', '', $site->domain)));
            if (strlen($isolatedUser) < 3) {
                $isolatedUser = 'wp'.substr(md5($site->domain), 0, 10);
            }
            $isolatedUser = substr($isolatedUser, 0, 32);
            // Ensure valid format
            if (! preg_match('/^[a-z_][a-z0-9_-]*[a-z0-9]$/', $isolatedUser)) {
                $isolatedUser = 'wp'.substr(md5($site->domain), 0, 10);
            }

            $vitoSite = app(CreateSite::class)->create($server, [
                'type' => 'wordpress',
                'domain' => $site->domain,
                'aliases' => [],
                'user' => $isolatedUser,
                'php_version' => $phpVersions[0],
                'title' => $site->title ?? $site->domain,
                'username' => $site->admin_username,
                'password' => $site->getDecryptedAdminPassword(),
                'email' => $site->admin_email,
                'database' => $database->id,
                'database_user' => $dbUser->id,
            ]);

            $site->update(['site_id' => $vitoSite->id]);

            // Wait for site installation
            $maxWait = 180;
            $waited = 0;
            while ($waited < $maxWait) {
                $vitoSite->refresh();
                if ($vitoSite->isReady()) {
                    break;
                }
                if ($vitoSite->isInstallationFailed()) {
                    $this->markFailed('VitoDeploy site installation failed');

                    return;
                }
                sleep(5);
                $waited += 5;
            }

            if (! $vitoSite->isReady()) {
                $this->markFailed('Site installation timed out after '.$maxWait.'s');

                return;
            }
        } catch (Throwable $e) {
            $this->markFailed('Failed to create site: '.$e->getMessage());

            return;
        }

        $ssh = $server->ssh($vitoSite->user);
        $path = $vitoSite->path;

        // Step 4: Configure WordPress settings
        $this->updateStep('Configuring WordPress settings...');
        try {
            $tagline = addslashes($site->tagline ?? '');
            $timezone = $site->timezone;

            $ssh->exec(
                "wp --path={$path} option update blogdescription \"{$tagline}\" && ".
                "wp --path={$path} option update timezone_string \"{$timezone}\" && ".
                "wp --path={$path} option update default_comment_status \"closed\"",
                'bulk-wp-configure-'.$site->id
            );
        } catch (Throwable $e) {
            Log::warning('BulkWP: Failed to configure settings for '.$site->domain.': '.$e->getMessage());
        }

        // Step 5: Remove default content
        $this->updateStep('Removing default content...');
        try {
            $ssh->exec(
                "wp --path={$path} post delete 1 --force 2>/dev/null || true && ".
                "wp --path={$path} post delete 2 --force 2>/dev/null || true && ".
                "wp --path={$path} comment delete 1 --force 2>/dev/null || true",
                'bulk-wp-cleanup-'.$site->id
            );
        } catch (Throwable $e) {
            Log::warning('BulkWP: Failed to remove default content for '.$site->domain.': '.$e->getMessage());
        }

        // Step 6: Install and activate plugins
        $plugins = $site->getPluginsArray();
        if (! empty($plugins)) {
            $this->updateStep('Installing plugins ('.count($plugins).')...');
            foreach ($plugins as $plugin) {
                try {
                    $ssh->exec(
                        "wp --path={$path} plugin install {$plugin} --activate",
                        'bulk-wp-plugin-'.$site->id.'-'.$plugin
                    );
                } catch (Throwable $e) {
                    Log::warning("BulkWP: Failed to install plugin {$plugin} for {$site->domain}: ".$e->getMessage());
                }
            }
        }

        // Step 7: Install and activate theme
        if ($site->theme) {
            $this->updateStep('Installing theme...');
            try {
                $ssh->exec(
                    "wp --path={$path} theme install {$site->theme} --activate",
                    'bulk-wp-theme-'.$site->id
                );
            } catch (Throwable $e) {
                Log::warning('BulkWP: Failed to install theme for '.$site->domain.': '.$e->getMessage());
            }
        }

        // Step 8: Create application password
        $this->updateStep('Creating application password...');
        try {
            $appPassword = $ssh->exec(
                "wp --path={$path} user application-password create 1 \"BulkWP-{$site->domain}\" --porcelain",
                'bulk-wp-app-password-'.$site->id
            );
            $appPassword = trim($appPassword);
            if (! empty($appPassword)) {
                $site->app_password = $appPassword;
                $site->saveQuietly();
            }
        } catch (Throwable $e) {
            Log::warning('BulkWP: Failed to create app password for '.$site->domain.': '.$e->getMessage());
        }

        // Step 9: Create sidebar widget
        $this->updateStep('Creating sidebar widget...');
        try {
            $widgetContent = $config?->getDefaultSidebarWidget() ?? '<ul><li><a href="https://{domain}"><strong>https://{domain}</strong></a></li></ul>';
            $widgetContent = str_replace('{domain}', $site->domain, $widgetContent);
            $escapedContent = addslashes($widgetContent);

            $widgetScript = <<<SCRIPT
wp --path={$path} eval '
\$wc = "{$escapedContent}";
\$sb = get_option("sidebars_widgets");
\$wid = "text-bulk-1";
if (!isset(\$sb["sidebar-1"])) \$sb["sidebar-1"] = [];
\$sb["sidebar-1"][] = \$wid;
update_option("sidebars_widgets", \$sb);
\$w = get_option("widget_text", []);
\$w["bulk-1"] = ["title"=>"","text"=>\$wc,"filter"=>false,"visual"=>false];
update_option("widget_text", \$w);
'
SCRIPT;

            $ssh->exec($widgetScript, 'bulk-wp-widget-'.$site->id);
        } catch (Throwable $e) {
            Log::warning('BulkWP: Failed to create widget for '.$site->domain.': '.$e->getMessage());
        }

        // Step 10: Enable SSL
        $this->updateStep('Enabling SSL...');
        try {
            $vitoSite->refresh();
            app(CreateSSL::class)->create($vitoSite, [
                'type' => 'letsencrypt',
                'email' => $site->admin_email,
            ]);
        } catch (Throwable $e) {
            Log::warning('BulkWP: Failed to enable SSL for '.$site->domain.': '.$e->getMessage());
        }

        // Step 11: Done
        $site->update([
            'status' => 'done',
            'current_step' => null,
        ]);

        $this->broadcastStatus();
    }

    private function updateStep(string $step): void
    {
        $this->bulkWpSite->update([
            'status' => 'running',
            'current_step' => $step,
        ]);

        $this->broadcastStatus();
    }

    private function markFailed(string $error): void
    {
        $this->bulkWpSite->update([
            'status' => 'failed',
            'error' => $error,
            'current_step' => null,
        ]);

        $this->broadcastStatus();
    }

    private function broadcastStatus(): void
    {
        $batchId = $this->bulkWpSite->batch_id;
        $total = BulkWpSite::where('batch_id', $batchId)->count();
        $done = BulkWpSite::where('batch_id', $batchId)
            ->whereIn('status', ['done', 'failed'])
            ->count();

        event(new SiteProvisionStatusUpdated($this->bulkWpSite, $done, $total));
    }

    public function failed(Throwable $exception): void
    {
        $this->bulkWpSite->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'current_step' => null,
        ]);

        $this->broadcastStatus();

        Log::error('BulkWP: Provision failed for '.$this->bulkWpSite->domain.': '.$exception->getMessage());
    }
}
