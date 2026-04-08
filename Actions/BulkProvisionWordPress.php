<?php

namespace App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Actions;

use App\Models\User;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Jobs\ProvisionSingleWordPressSite;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Models\BulkWpConfig;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Models\BulkWpServerCapacity;
use App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Models\BulkWpSite;
use Illuminate\Support\Str;

class BulkProvisionWordPress
{
    private const ADJECTIVES = [
        'quick', 'bright', 'calm', 'bold', 'cool', 'fast', 'keen', 'sharp',
        'smart', 'swift', 'warm', 'wild', 'pure', 'clear', 'fresh', 'light',
    ];

    private const NOUNS = [
        'fox', 'wolf', 'bear', 'hawk', 'lion', 'deer', 'owl', 'eagle',
        'tiger', 'panda', 'falcon', 'cobra', 'whale', 'raven', 'lynx', 'crane',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function provision(User $user, array $rows): string
    {
        $batchId = Str::uuid()->toString();
        $config = BulkWpConfig::where('user_id', $user->id)->first();

        foreach ($rows as $row) {
            $domain = $row['domain'];
            $username = $row['admin_username'] ?: $this->generateUsername();
            $email = $row['admin_email'] ?: $this->generateEmail($username, $domain);
            $password = $row['admin_password'] ?: $this->generatePassword();
            $theme = $row['theme'] ?: $this->pickRandomTheme($config);
            $plugins = $row['plugins'] ?: ($config?->plugins ?? '');

            $site = BulkWpSite::create([
                'user_id' => $user->id,
                'config_id' => $config?->id,
                'batch_id' => $batchId,
                'domain' => $domain,
                'server_id' => $row['server_id'],
                'title' => $row['title'] ?: ($config?->defaults['title'] ?? $domain),
                'tagline' => $row['tagline'] ?: ($config?->defaults['tagline'] ?? ''),
                'timezone' => $row['timezone'] ?: ($config?->defaults['timezone'] ?? 'UTC'),
                'admin_username' => $username,
                'admin_email' => $email,
                'admin_password' => $password,
                'plugins' => $plugins,
                'theme' => $theme,
                'status' => 'pending',
            ]);

            dispatch(new ProvisionSingleWordPressSite($site))
                ->onQueue('ssh');
        }

        return $batchId;
    }

    private function generateUsername(): string
    {
        $adjective = self::ADJECTIVES[array_rand(self::ADJECTIVES)];
        $noun = self::NOUNS[array_rand(self::NOUNS)];
        $digits = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

        return $adjective.$noun.$digits;
    }

    private function generateEmail(string $username, string $domain): string
    {
        return $username.'@'.$domain;
    }

    private function generatePassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < 16; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    private function pickRandomTheme(?BulkWpConfig $config): string
    {
        if (! $config) {
            return 'twentytwentyfour';
        }

        $themes = $config->getThemesArray();

        if (empty($themes)) {
            return 'twentytwentyfour';
        }

        return $themes[array_rand($themes)];
    }
}
