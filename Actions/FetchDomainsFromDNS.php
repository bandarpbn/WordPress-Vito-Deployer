<?php

namespace App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Actions;

use App\Models\DNSProvider;
use App\Models\User;

class FetchDomainsFromDNS
{
    /**
     * @return array<int, array{id: string, name: string, provider_id: int, provider_name: string}>
     */
    public function fetch(User $user, ?int $providerId = null): array
    {
        $query = DNSProvider::getByProjectId($user->current_project_id, $user);

        if ($providerId) {
            $query->where('id', $providerId);
        }

        $providers = $query->get();
        $domains = [];

        foreach ($providers as $provider) {
            $dnsHandler = $provider->provider();
            $providerDomains = $dnsHandler->getDomains();

            foreach ($providerDomains as $domain) {
                $domains[] = [
                    'id' => $domain['id'] ?? $domain['name'],
                    'name' => $domain['name'],
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->name,
                ];
            }
        }

        return $domains;
    }
}
