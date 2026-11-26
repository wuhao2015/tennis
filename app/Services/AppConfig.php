<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Runtime configuration delivered by AWS AppConfig via the agent sidecar
 * (http://localhost:2772). The agent polls AppConfig every ~45s; we cache the
 * decoded payload for 30s so a release shows up within ~1-2 minutes without
 * a redeploy.
 */
class AppConfig
{
    private const AGENT_URL = 'http://localhost:2772/applications/tennis/environments/production/configurations/web';

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = Cache::remember('appconfig.web', 30, function () {
            try {
                $response = Http::timeout(2)->get(self::AGENT_URL);

                return $response->successful() ? ($response->json() ?? []) : [];
            } catch (\Throwable) {
                return [];
            }
        });

        return $config[$key] ?? $default;
    }
}
