<?php

namespace App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Actions\BulkProvisionWordPress;
use App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Http\Requests\BulkProvisionRequest;
use App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Models\BulkWpConfig;
use App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Models\BulkWpServerCapacity;
use App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Models\BulkWpSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BulkWordPressController extends Controller
{
    public function index(): Response
    {
        $user = user();

        $servers = Server::query()
            ->where('project_id', $user->current_project_id)
            ->get()
            ->map(function (Server $server) {
                $capacity = BulkWpServerCapacity::firstOrCreate(
                    ['server_id' => $server->id],
                    ['max_sites' => 50]
                );

                return [
                    'id' => $server->id,
                    'name' => $server->name,
                    'ip' => $server->ip,
                    'current_sites' => $capacity->getCurrentSiteCount(),
                    'max_sites' => $capacity->max_sites,
                    'remaining' => $capacity->getRemainingCapacity(),
                ];
            });

        $config = BulkWpConfig::where('user_id', $user->id)->first();

        $recentBatches = BulkWpSite::where('user_id', $user->id)
            ->selectRaw('batch_id, COUNT(*) as total, SUM(CASE WHEN status = "done" THEN 1 ELSE 0 END) as done_count, SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count, MAX(created_at) as last_updated')
            ->groupBy('batch_id')
            ->orderByDesc('last_updated')
            ->limit(10)
            ->get();

        return Inertia::render('bulk-wordpress/index', [
            'servers' => $servers,
            'config' => $config,
            'recentBatches' => $recentBatches,
        ]);
    }

    public function provision(BulkProvisionRequest $request): RedirectResponse
    {
        $user = user();
        $batchId = app(BulkProvisionWordPress::class)->provision($user, $request->validated('rows'));

        return back()->with('success', 'Provisioning started. Batch ID: '.$batchId)->with('data', ['batch_id' => $batchId]);
    }

    public function status(string $batchId): JsonResponse
    {
        $sites = BulkWpSite::where('batch_id', $batchId)
            ->where('user_id', user()->id)
            ->get(['id', 'domain', 'server_id', 'status', 'current_step', 'error']);

        $total = $sites->count();
        $done = $sites->where('status', 'done')->count();
        $failed = $sites->where('status', 'failed')->count();

        return response()->json([
            'sites' => $sites,
            'progress' => [
                'total' => $total,
                'done' => $done,
                'failed' => $failed,
                'running' => $sites->where('status', 'running')->count(),
                'pending' => $sites->where('status', 'pending')->count(),
            ],
        ]);
    }

    public function sites(): JsonResponse
    {
        $sites = BulkWpSite::where('user_id', user()->id)
            ->where('status', 'done')
            ->with('server:id,name,ip')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($sites);
    }

    public function retry(Request $request): RedirectResponse
    {
        $request->validate([
            'site_ids' => ['required', 'array'],
            'site_ids.*' => ['integer'],
        ]);

        $sites = BulkWpSite::whereIn('id', $request->input('site_ids'))
            ->where('user_id', user()->id)
            ->where('status', 'failed')
            ->get();

        foreach ($sites as $site) {
            $site->update([
                'status' => 'pending',
                'error' => null,
                'current_step' => null,
                'retry_count' => $site->retry_count + 1,
            ]);

            dispatch(new \App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Jobs\ProvisionSingleWordPressSite($site))
                ->onQueue('ssh');
        }

        return back()->with('success', 'Retrying '.$sites->count().' failed sites.');
    }

    public function deleteSites(Request $request): RedirectResponse
    {
        $request->validate([
            'site_ids' => ['required', 'array'],
            'site_ids.*' => ['integer'],
        ]);

        BulkWpSite::whereIn('id', $request->input('site_ids'))
            ->where('user_id', user()->id)
            ->delete();

        return back()->with('success', 'Sites deleted.');
    }

    public function settings(): Response
    {
        $user = user();
        $config = BulkWpConfig::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'default',
                'defaults' => [
                    'title' => '',
                    'tagline' => '',
                    'timezone' => 'UTC',
                    'admin_username' => '',
                    'admin_email' => '',
                    'admin_password' => '',
                ],
                'sidebar_widget' => '<ul><li><a href="https://{domain}"><strong>https://{domain}</strong></a></li></ul>',
            ]
        );

        $servers = Server::query()
            ->where('project_id', $user->current_project_id)
            ->get()
            ->map(function (Server $server) {
                $capacity = BulkWpServerCapacity::firstOrCreate(
                    ['server_id' => $server->id],
                    ['max_sites' => 50]
                );

                return [
                    'id' => $server->id,
                    'name' => $server->name,
                    'max_sites' => $capacity->max_sites,
                    'current_sites' => $capacity->getCurrentSiteCount(),
                ];
            });

        return Inertia::render('bulk-wordpress/settings', [
            'config' => $config,
            'servers' => $servers,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'plugins' => ['nullable', 'string'],
            'themes' => ['nullable', 'string'],
            'defaults' => ['nullable', 'array'],
            'sidebar_widget' => ['nullable', 'string'],
            'max_sites_per_server' => ['nullable', 'integer', 'min:1'],
            'max_concurrent_per_server' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_concurrent_global' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'server_capacities' => ['nullable', 'array'],
            'server_capacities.*.server_id' => ['required', 'integer'],
            'server_capacities.*.max_sites' => ['required', 'integer', 'min:1'],
        ]);

        $user = user();
        $config = BulkWpConfig::firstOrCreate(['user_id' => $user->id], ['name' => 'default']);

        $config->update($request->only([
            'plugins', 'themes', 'defaults', 'sidebar_widget',
            'max_sites_per_server', 'max_concurrent_per_server',
            'max_concurrent_global', 'max_retries',
        ]));

        if ($request->has('server_capacities')) {
            foreach ($request->input('server_capacities') as $sc) {
                BulkWpServerCapacity::updateOrCreate(
                    ['server_id' => $sc['server_id']],
                    ['max_sites' => $sc['max_sites']]
                );
            }
        }

        return back()->with('success', 'Settings saved.');
    }
}
