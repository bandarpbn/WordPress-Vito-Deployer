import Layout from '@/layouts/app/layout';
import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import Container from '@/components/container';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { SettingsIcon, PlayIcon, RotateCcwIcon } from 'lucide-react';
import DomainSelector from '@/pages/bulk-wordpress/components/domain-selector';
import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { HotTable } from '@handsontable/react-wrapper';
import { registerAllModules } from 'handsontable/registry';
import 'handsontable/styles/handsontable.css';
import 'handsontable/styles/ht-theme-main.css';

registerAllModules();

interface ServerOption {
  id: number;
  name: string;
  ip: string;
  current_sites: number;
  max_sites: number;
  remaining: number;
}

interface BulkWpConfig {
  id: number;
  plugins: string | null;
  themes: string | null;
  defaults: Record<string, string> | null;
}

interface SiteStatus {
  id: number;
  domain: string;
  server_id: number;
  status: string;
  current_step: string | null;
  error: string | null;
}

interface PageProps {
  servers: ServerOption[];
  config: BulkWpConfig | null;
  recentBatches: unknown[];
}

const TIMEZONES = [
  'UTC',
  'Asia/Jakarta',
  'Asia/Singapore',
  'Asia/Tokyo',
  'Asia/Kolkata',
  'Asia/Dubai',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'Australia/Sydney',
  'Pacific/Auckland',
];

export default function BulkWordPressIndex() {
  const page = usePage<PageProps>();
  const { servers, config } = page.props;
  const hotRef = useRef<InstanceType<typeof HotTable>>(null);
  const [data, setData] = useState<(string | number | null)[][]>([]);
  const [batchId, setBatchId] = useState<string | null>(null);
  const [provisioning, setProvisioning] = useState(false);
  const [progress, setProgress] = useState({ total: 0, done: 0, failed: 0, running: 0, pending: 0 });
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const defaults = config?.defaults ?? {};

  const serverNames = servers.map((s) => `${s.name} (${s.current_sites}/${s.max_sites})`);
  const serverMap = Object.fromEntries(servers.map((s, i) => [serverNames[i], s.id]));
  const serverIdToName = Object.fromEntries(servers.map((s, i) => [s.id, serverNames[i]]));

  const addDomains = useCallback(
    (domains: string[]) => {
      const defaultServer = servers.length === 1 ? serverNames[0] : '';
      const newRows = domains.map((domain) => [
        domain,
        defaultServer,
        (defaults.title ?? '').replace('{domain}', domain) || domain,
        (defaults.tagline ?? '').replace('{domain}', domain),
        defaults.timezone ?? 'UTC',
        defaults.admin_username ?? '',
        (defaults.admin_email ?? '').replace('{domain}', domain),
        defaults.admin_password ?? '',
        config?.plugins ?? '',
        '',
        'New',
      ]);
      setData((prev) => [...prev, ...newRows]);
    },
    [servers, config, defaults, serverNames],
  );

  const columns = [
    { data: 0, title: 'Domain', width: 160 },
    { data: 1, title: 'Server', type: 'dropdown' as const, source: serverNames, width: 180 },
    { data: 2, title: 'Title', width: 140 },
    { data: 3, title: 'Tagline', width: 140 },
    { data: 4, title: 'Timezone', type: 'dropdown' as const, source: TIMEZONES, width: 140 },
    { data: 5, title: 'Username', width: 120 },
    { data: 6, title: 'Email', width: 160 },
    { data: 7, title: 'Password', width: 120 },
    { data: 8, title: 'Plugins', width: 160 },
    { data: 9, title: 'Theme', width: 100 },
    { data: 10, title: 'Status', readOnly: true, width: 130 },
  ];

  const provisionAll = async () => {
    const validRows = data.filter((r) => r[0] && r[1]);
    if (validRows.length === 0) return;

    setProvisioning(true);
    try {
      const rows = validRows.map((r) => ({
        domain: r[0] as string,
        server_id: serverMap[r[1] as string] ?? null,
        title: r[2] as string,
        tagline: r[3] as string,
        timezone: r[4] as string,
        admin_username: r[5] as string,
        admin_email: r[6] as string,
        admin_password: r[7] as string,
        plugins: r[8] as string,
        theme: r[9] as string,
      }));

      const res = await axios.post('/bulk-wordpress/provision', { rows });

      // Extract batch_id
      const flashData = res.data?.props?.flash?.data;
      if (flashData?.batch_id) {
        setBatchId(flashData.batch_id);
        startPolling(flashData.batch_id);
      }

      // Update status column to Pending
      setData((prev) => prev.map((row) => (row[0] && row[1] ? [...row.slice(0, 10), 'Pending'] : row)));
    } catch {
      setProvisioning(false);
    }
  };

  const startPolling = (id: string) => {
    if (pollRef.current) clearInterval(pollRef.current);

    pollRef.current = setInterval(async () => {
      try {
        const res = await axios.get(`/bulk-wordpress/status/${id}`);
        const sites: SiteStatus[] = res.data.sites;
        setProgress(res.data.progress);

        setData((prev) =>
          prev.map((row) => {
            const siteStatus = sites.find((s) => s.domain === row[0]);
            if (siteStatus) {
              let statusLabel = siteStatus.status.charAt(0).toUpperCase() + siteStatus.status.slice(1);
              if (siteStatus.current_step) {
                statusLabel = siteStatus.current_step;
              }
              if (siteStatus.error) {
                statusLabel = `Failed: ${siteStatus.error.substring(0, 50)}`;
              }
              return [...row.slice(0, 10), statusLabel];
            }
            return row;
          }),
        );

        if (res.data.progress.done + res.data.progress.failed >= res.data.progress.total) {
          if (pollRef.current) clearInterval(pollRef.current);
          setProvisioning(false);
        }
      } catch {
        // continue
      }
    }, 5000);
  };

  const retryFailed = async () => {
    if (!batchId) return;
    try {
      const res = await axios.get(`/bulk-wordpress/status/${batchId}`);
      const failedIds = res.data.sites.filter((s: SiteStatus) => s.status === 'failed').map((s: SiteStatus) => s.id);

      if (failedIds.length > 0) {
        await axios.post('/bulk-wordpress/retry', { site_ids: failedIds });
        setProvisioning(true);
        startPolling(batchId);
      }
    } catch {
      //
    }
  };

  useEffect(() => {
    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
    };
  }, []);

  const progressPercent = progress.total > 0 ? ((progress.done + progress.failed) / progress.total) * 100 : 0;
  const validCount = data.filter((r) => r[0] && r[1]).length;

  return (
    <Layout>
      <Head title="Bulk WordPress" />
      <Container className="max-w-[1400px]">
        <div className="flex items-start justify-between">
          <Heading title="Bulk WordPress Provisioning" description="Provision multiple WordPress sites at once" />
          <a href="/bulk-wordpress/settings">
            <Button variant="outline">
              <SettingsIcon className="mr-1 h-4 w-4" />
              Settings
            </Button>
          </a>
        </div>

        <DomainSelector onDomainsSelected={addDomains} />

        {data.length > 0 && (
          <Card>
            <CardContent className="p-4">
              <HotTable
                ref={hotRef}
                data={data}
                columns={columns}
                rowHeaders={true}
                colHeaders={columns.map((c) => c.title)}
                height="auto"
                maxRows={500}
                contextMenu={provisioning ? false : ['row_above', 'row_below', 'remove_row', 'copy', 'cut']}
                fillHandle={!provisioning}
                readOnly={provisioning}
                manualColumnResize={true}
                stretchH="all"
                autoWrapRow={true}
                autoWrapCol={true}
                licenseKey="non-commercial-and-evaluation"
                className="ht-theme-main"
                cells={(row, col) => {
                  const cellProperties: Record<string, unknown> = {};
                  if (col === 10) {
                    cellProperties.readOnly = true;
                    const value = data[row]?.[10] as string;
                    if (value) {
                      const lower = value.toLowerCase();
                      if (lower === 'done') {
                        cellProperties.className = 'htCenter text-green-600 font-medium';
                      } else if (lower.startsWith('failed')) {
                        cellProperties.className = 'htCenter text-red-600 font-medium';
                      } else if (lower === 'pending' || lower === 'new') {
                        cellProperties.className = 'htCenter text-gray-500';
                      } else {
                        cellProperties.className = 'htCenter text-blue-600';
                      }
                    }
                  }
                  return cellProperties;
                }}
              />
            </CardContent>
          </Card>
        )}

        {provisioning && progress.total > 0 && (
          <div className="space-y-2">
            <Progress value={progressPercent} />
            <p className="text-muted-foreground text-sm">
              {progress.done + progress.failed} of {progress.total} sites provisioned
              {progress.failed > 0 && <span className="text-red-600"> ({progress.failed} failed)</span>}
              {progress.running > 0 && <span className="text-blue-600"> ({progress.running} running)</span>}
            </p>
          </div>
        )}

        {data.length > 0 && (
          <div className="flex items-center gap-2">
            <Button onClick={provisionAll} disabled={provisioning || validCount === 0}>
              <PlayIcon className="mr-1 h-4 w-4" />
              Provision All ({validCount})
            </Button>
            {progress.failed > 0 && (
              <Button variant="outline" onClick={retryFailed}>
                <RotateCcwIcon className="mr-1 h-4 w-4" />
                Retry Failed ({progress.failed})
              </Button>
            )}
            {!provisioning && (
              <Button
                variant="ghost"
                onClick={() => {
                  setData((prev) => [...prev, ['', '', '', '', 'UTC', '', '', '', '', '', 'New']]);
                }}
              >
                + Add Row
              </Button>
            )}
          </div>
        )}
      </Container>
    </Layout>
  );
}
