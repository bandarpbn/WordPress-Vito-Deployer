<?php

namespace App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DNSProvider;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Actions\FetchDomainsFromDNS;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('bulk-wordpress/domains')]
#[Middleware(['auth', 'has-project'])]
class DomainFetchController extends Controller
{
    #[Get('/', name: 'bulk-wordpress.domains')]
    public function index(Request $request): JsonResponse
    {
        $user = user();

        $providers = DNSProvider::getByProjectId($user->current_project_id, $user)
            ->get(['id', 'name', 'provider']);

        return response()->json([
            'providers' => $providers,
        ]);
    }

    #[Get('/fetch', name: 'bulk-wordpress.domains.fetch')]
    public function fetch(Request $request): JsonResponse
    {
        $request->validate([
            'provider_id' => ['nullable', 'integer'],
        ]);

        $user = user();

        try {
            $domains = app(FetchDomainsFromDNS::class)->fetch(
                $user,
                $request->input('provider_id')
            );

            return response()->json([
                'domains' => $domains,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch domains: '.$e->getMessage(),
            ], 500);
        }
    }
}
