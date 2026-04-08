import Layout from '@/layouts/app/layout';
import { Head, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import Container from '@/components/container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeftIcon } from 'lucide-react';
import { useState } from 'react';

interface ServerCapacity {
  id: number;
  name: string;
  max_sites: number;
  current_sites: number;
}

interface BulkWpConfig {
  id: number;
  plugins: string | null;
  themes: string | null;
  defaults: Record<string, string> | null;
  sidebar_widget: string | null;
  max_sites_per_server: number;
  max_concurrent_per_server: number;
  max_concurrent_global: number;
  max_retries: number;
}

interface PageProps {
  config: BulkWpConfig;
  servers: ServerCapacity[];
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

export default function BulkWordPressSettings() {
  const page = usePage<PageProps>();
  const { config, servers } = page.props;

  const [form, setForm] = useState({
    plugins: config.plugins ?? '',
    themes: config.themes ?? '',
    defaults: {
      title: config.defaults?.title ?? '',
      tagline: config.defaults?.tagline ?? '',
      timezone: config.defaults?.timezone ?? 'UTC',
      admin_username: config.defaults?.admin_username ?? '',
      admin_email: config.defaults?.admin_email ?? '',
      admin_password: config.defaults?.admin_password ?? '',
    },
    sidebar_widget: config.sidebar_widget ?? '<ul><li><a href="https://{domain}"><strong>https://{domain}</strong></a></li></ul>',
    max_sites_per_server: config.max_sites_per_server,
    max_concurrent_per_server: config.max_concurrent_per_server,
    max_concurrent_global: config.max_concurrent_global,
    max_retries: config.max_retries,
    server_capacities: servers.map((s) => ({ server_id: s.id, max_sites: s.max_sites })),
  });

  const [saving, setSaving] = useState(false);

  const save = () => {
    setSaving(true);
    router.post('/bulk-wordpress/settings', form, {
      preserveScroll: true,
      onFinish: () => setSaving(false),
    });
  };

  const updateDefault = (key: string, value: string) => {
    setForm((prev) => ({
      ...prev,
      defaults: { ...prev.defaults, [key]: value },
    }));
  };

  const updateServerCapacity = (serverId: number, maxSites: number) => {
    setForm((prev) => ({
      ...prev,
      server_capacities: prev.server_capacities.map((sc) => (sc.server_id === serverId ? { ...sc, max_sites: maxSites } : sc)),
    }));
  };

  return (
    <Layout>
      <Head title="Bulk WordPress Settings" />
      <Container className="max-w-3xl">
        <div className="flex items-start justify-between">
          <Heading title="BulkWordPress Settings" description="Configure default values and provisioning settings" />
          <a href="/bulk-wordpress">
            <Button variant="outline">
              <ArrowLeftIcon className="mr-1 h-4 w-4" />
              Back
            </Button>
          </a>
        </div>

        <div className="space-y-6">
          {/* Server Capacity */}
          <Card>
            <CardHeader>
              <CardTitle>Server Capacity</CardTitle>
              <CardDescription>Set maximum number of sites per server</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label>Default Max Sites Per Server</Label>
                  <Input
                    type="number"
                    min={1}
                    value={form.max_sites_per_server}
                    onChange={(e) => setForm({ ...form, max_sites_per_server: Number(e.target.value) })}
                  />
                </div>
              </div>
              {servers.length > 0 && (
                <div className="space-y-2">
                  <Label>Per-Server Override</Label>
                  {servers.map((s) => (
                    <div key={s.id} className="flex items-center gap-3">
                      <span className="w-40 text-sm">
                        {s.name} ({s.current_sites} sites)
                      </span>
                      <Input
                        type="number"
                        min={1}
                        className="w-24"
                        value={form.server_capacities.find((sc) => sc.server_id === s.id)?.max_sites ?? 50}
                        onChange={(e) => updateServerCapacity(s.id, Number(e.target.value))}
                      />
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Concurrency */}
          <Card>
            <CardHeader>
              <CardTitle>Concurrency</CardTitle>
              <CardDescription>Control parallel provisioning limits</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 sm:grid-cols-3">
                <div>
                  <Label>Max Concurrent Per Server</Label>
                  <Input
                    type="number"
                    min={1}
                    max={20}
                    value={form.max_concurrent_per_server}
                    onChange={(e) => setForm({ ...form, max_concurrent_per_server: Number(e.target.value) })}
                  />
                </div>
                <div>
                  <Label>Max Concurrent Global</Label>
                  <Input
                    type="number"
                    min={1}
                    max={100}
                    value={form.max_concurrent_global}
                    onChange={(e) => setForm({ ...form, max_concurrent_global: Number(e.target.value) })}
                  />
                </div>
                <div>
                  <Label>Max Retries</Label>
                  <Input
                    type="number"
                    min={0}
                    max={10}
                    value={form.max_retries}
                    onChange={(e) => setForm({ ...form, max_retries: Number(e.target.value) })}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Plugins */}
          <Card>
            <CardHeader>
              <CardTitle>Default Plugins</CardTitle>
              <CardDescription>Plugin slugs to install on every site (comma or newline separated)</CardDescription>
            </CardHeader>
            <CardContent>
              <textarea
                className="bg-background min-h-[80px] w-full rounded border px-3 py-2 text-sm"
                value={form.plugins}
                onChange={(e) => setForm({ ...form, plugins: e.target.value })}
                placeholder="wordpress-seo, contact-form-7, wp-super-cache"
              />
            </CardContent>
          </Card>

          {/* Themes */}
          <Card>
            <CardHeader>
              <CardTitle>Theme Pool</CardTitle>
              <CardDescription>Themes to randomly assign (comma or newline separated)</CardDescription>
            </CardHeader>
            <CardContent>
              <textarea
                className="bg-background min-h-[80px] w-full rounded border px-3 py-2 text-sm"
                value={form.themes}
                onChange={(e) => setForm({ ...form, themes: e.target.value })}
                placeholder="astra, neve, hello-elementor, oceanwp"
              />
            </CardContent>
          </Card>

          {/* Default Values */}
          <Card>
            <CardHeader>
              <CardTitle>Default Values</CardTitle>
              <CardDescription>
                Used as grid defaults. Use {'`{domain}`'} and {'`{username}`'} as placeholders.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label>Default Site Title</Label>
                  <Input value={form.defaults.title} onChange={(e) => updateDefault('title', e.target.value)} placeholder="{domain}" />
                </div>
                <div>
                  <Label>Default Tagline</Label>
                  <Input value={form.defaults.tagline} onChange={(e) => updateDefault('tagline', e.target.value)} />
                </div>
                <div>
                  <Label>Default Timezone</Label>
                  <Select value={form.defaults.timezone} onValueChange={(v) => updateDefault('timezone', v)}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {TIMEZONES.map((tz) => (
                        <SelectItem key={tz} value={tz}>
                          {tz}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label>Default Admin Username</Label>
                  <Input
                    value={form.defaults.admin_username}
                    onChange={(e) => updateDefault('admin_username', e.target.value)}
                    placeholder="Blank = random"
                  />
                </div>
                <div>
                  <Label>Default Admin Email</Label>
                  <Input
                    value={form.defaults.admin_email}
                    onChange={(e) => updateDefault('admin_email', e.target.value)}
                    placeholder="{username}@{domain}"
                  />
                </div>
                <div>
                  <Label>Default Admin Password</Label>
                  <Input
                    type="password"
                    value={form.defaults.admin_password}
                    onChange={(e) => updateDefault('admin_password', e.target.value)}
                    placeholder="Blank = random 16 chars"
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Sidebar Widget */}
          <Card>
            <CardHeader>
              <CardTitle>Sidebar Widget</CardTitle>
              <CardDescription>
                HTML content for sidebar widget. Use {'`{domain}`'} placeholder.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <textarea
                className="bg-background font-mono min-h-[80px] w-full rounded border px-3 py-2 text-sm"
                value={form.sidebar_widget}
                onChange={(e) => setForm({ ...form, sidebar_widget: e.target.value })}
              />
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Button onClick={save} disabled={saving}>
              {saving ? 'Saving...' : 'Save Settings'}
            </Button>
          </div>
        </div>
      </Container>
    </Layout>
  );
}
