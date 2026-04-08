<?php

namespace App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer;

use App\Plugins\AbstractPlugin;
use App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Http\Controllers\BulkWordPressController;
use App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Http\Controllers\DomainFetchController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

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
        $pagesDest = resource_path('js/pages/bulk-wordpress');
        if (File::isDirectory($pagesDest)) {
            File::deleteDirectory($pagesDest);
        }
    }

    private function registerRoutes(): void
    {
        Route::middleware(['web', 'auth', 'has-project'])
            ->prefix('bulk-wordpress')
            ->group(function () {
                // Main pages
                Route::get('/', [BulkWordPressController::class, 'index'])->name('bulk-wordpress.index');
                Route::post('/provision', [BulkWordPressController::class, 'provision'])->name('bulk-wordpress.provision');
                Route::get('/status/{batchId}', [BulkWordPressController::class, 'status'])->name('bulk-wordpress.status');
                Route::get('/sites', [BulkWordPressController::class, 'sites'])->name('bulk-wordpress.sites');
                Route::post('/retry', [BulkWordPressController::class, 'retry'])->name('bulk-wordpress.retry');
                Route::delete('/sites', [BulkWordPressController::class, 'deleteSites'])->name('bulk-wordpress.sites.delete');

                // Settings
                Route::get('/settings', [BulkWordPressController::class, 'settings'])->name('bulk-wordpress.settings');
                Route::post('/settings', [BulkWordPressController::class, 'saveSettings'])->name('bulk-wordpress.settings.save');

                // Domains
                Route::get('/domains', [DomainFetchController::class, 'index'])->name('bulk-wordpress.domains');
                Route::get('/domains/fetch', [DomainFetchController::class, 'fetch'])->name('bulk-wordpress.domains.fetch');
            });
    }
}
