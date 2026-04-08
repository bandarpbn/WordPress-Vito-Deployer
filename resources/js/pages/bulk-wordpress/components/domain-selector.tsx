import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import axios from 'axios';

interface Domain {
  id: string;
  name: string;
  provider_id: number;
  provider_name: string;
}

interface DNSProviderOption {
  id: number;
  name: string;
  provider: string;
}

interface DomainSelectorProps {
  onDomainsSelected: (domains: string[]) => void;
}

export default function DomainSelector({ onDomainsSelected }: DomainSelectorProps) {
  const [providers, setProviders] = useState<DNSProviderOption[]>([]);
  const [domains, setDomains] = useState<Domain[]>([]);
  const [selectedDomains, setSelectedDomains] = useState<Set<string>>(new Set());
  const [selectedProvider, setSelectedProvider] = useState<string>('all');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);
  const [providersLoaded, setProvidersLoaded] = useState(false);

  const loadProviders = async () => {
    if (providersLoaded) return;
    try {
      const res = await axios.get(route('bulk-wordpress.domains'));
      setProviders(res.data.providers);
      setProvidersLoaded(true);
    } catch {
      //
    }
  };

  const fetchDomains = async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = {};
      if (selectedProvider !== 'all') {
        params.provider_id = selectedProvider;
      }
      const res = await axios.get(route('bulk-wordpress.domains.fetch'), { params });
      setDomains(res.data.domains);
    } catch {
      //
    } finally {
      setLoading(false);
    }
  };

  const filteredDomains = domains.filter((d) => d.name.toLowerCase().includes(search.toLowerCase()));

  const toggleDomain = (name: string) => {
    const next = new Set(selectedDomains);
    if (next.has(name)) {
      next.delete(name);
    } else {
      next.add(name);
    }
    setSelectedDomains(next);
  };

  const selectAll = () => {
    setSelectedDomains(new Set(filteredDomains.map((d) => d.name)));
  };

  const clearAll = () => {
    setSelectedDomains(new Set());
  };

  const addToGrid = () => {
    onDomainsSelected(Array.from(selectedDomains));
    setSelectedDomains(new Set());
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Domain Selector</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex items-center gap-2">
          <Select
            value={selectedProvider}
            onValueChange={setSelectedProvider}
            onOpenChange={() => loadProviders()}
          >
            <SelectTrigger className="w-[200px]">
              <SelectValue placeholder="Select DNS Provider" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Providers</SelectItem>
              {providers.map((p) => (
                <SelectItem key={p.id} value={String(p.id)}>
                  {p.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button onClick={fetchDomains} disabled={loading}>
            {loading ? 'Fetching...' : 'Fetch Domains'}
          </Button>
        </div>

        {domains.length > 0 && (
          <>
            <div className="flex items-center gap-2">
              <Input placeholder="Search domains..." value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
              <Button variant="outline" size="sm" onClick={selectAll}>
                Select All
              </Button>
              <Button variant="outline" size="sm" onClick={clearAll}>
                Clear All
              </Button>
              <span className="text-muted-foreground text-sm">{selectedDomains.size} selected</span>
            </div>

            <ScrollArea className="h-[200px] rounded border p-3">
              <div className="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4">
                {filteredDomains.map((domain) => (
                  <label key={domain.id} className="flex cursor-pointer items-center gap-2 rounded p-1 hover:bg-accent">
                    <Checkbox checked={selectedDomains.has(domain.name)} onCheckedChange={() => toggleDomain(domain.name)} />
                    <span className="text-sm">{domain.name}</span>
                  </label>
                ))}
              </div>
            </ScrollArea>

            <Button onClick={addToGrid} disabled={selectedDomains.size === 0}>
              + Add Selected to Grid ({selectedDomains.size})
            </Button>
          </>
        )}
      </CardContent>
    </Card>
  );
}
