<?php

namespace App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DNSProvider;
use App\Vito\Plugins\Bandarpbn\WordPressVitoDeployer\Actions\FetchDomainsFromDNS;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainFetchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = user();

        $providers = DNSProvider::getByProjectId($user->current_project_id, $user)
            ->get(['id', 'name', 'provider']);

        return response()->json([
            'providers' => $providers,
        ]);
    }

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
