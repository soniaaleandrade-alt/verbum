<?php

declare(strict_types=1);

namespace VerbumStudio\Integrations\Supabase;

final class SupabaseClient
{
    private SupabaseConfig $config;

    public function __construct(SupabaseConfig $config)
    {
        $this->config = $config;
    }

    /** @return array{status: string} */
    public function testConnection(): array
    {
        if (! $this->config->isConfigured()) {
            return ['status' => 'CONNECTION ERROR'];
        }

        $response = wp_remote_get(rtrim($this->config->url(), '/') . '/rest/v1/', [
            'headers' => [
                'apikey' => $this->config->serviceKey(),
                'Authorization' => 'Bearer ' . $this->config->serviceKey(),
            ],
            'timeout' => 8,
        ]);

        if (is_wp_error($response)) {
            return ['status' => 'CONNECTION ERROR'];
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);

        return ['status' => $statusCode >= 200 && $statusCode < 500 ? 'CONNECTED' : 'CONNECTION ERROR'];
    }
}
