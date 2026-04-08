<?php

namespace App\Vito\Plugins\VitoDeploy\BulkWordPress;

use App\Plugins\AbstractPlugin;
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
        //
    }

    public function uninstall(): void
    {
        //
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
