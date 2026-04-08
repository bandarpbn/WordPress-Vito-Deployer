<?php

namespace App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer;

use App\Plugins\AbstractPlugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class Plugin extends AbstractPlugin
{
    protected string $name = 'BulkWordPress';

    protected string $description = 'Bulk WordPress Provisioning & Management';

    public function boot(): void
    {
        $this->registerRoutes();
    }

    public function install(): void
    {
        // Copy migration
        $migrationSource = __DIR__.'/database/migrations';
        $migrationDest = database_path('migrations');

        if (File::isDirectory($migrationSource)) {
            foreach (File::files($migrationSource) as $file) {
                $dest = $migrationDest.DIRECTORY_SEPARATOR.$file->getFilename();
                if (! File::exists($dest)) {
                    File::copy($file->getPathname(), $dest);
                }
            }
        }

        // Copy frontend pages
        $pagesSource = __DIR__.'/resources/js/pages/bulk-wordpress';
        $pagesDest = resource_path('js/pages/bulk-wordpress');

        if (File::isDirectory($pagesSource)) {
            File::ensureDirectoryExists($pagesDest);
            File::copyDirectory($pagesSource, $pagesDest);
        }

        // Run migrations
        Artisan::call('migrate', ['--force' => true]);
    }

    public function uninstall(): void
    {
        // Remove frontend pages
        $pagesDest = resource_path('js/pages/bulk-wordpress');
        if (File::isDirectory($pagesDest)) {
            File::deleteDirectory($pagesDest);
        }
    }

    private function registerRoutes(): void
    {
        $controllerPath = __DIR__.'/Http/Controllers';

        $directories = config('route-attributes.directories', []);
        $directories[$controllerPath] = [
            'prefix' => '',
            'middleware' => 'web',
            'patterns' => ['*Controller.php'],
            'not_patterns' => [],
        ];
        config(['route-attributes.directories' => $directories]);
    }
}
