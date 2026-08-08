<?php

declare(strict_types=1);

namespace VerbumStudio\Integrations\Supabase;

use VerbumStudio\Core\Config;

final class SupabaseConfig
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function url(): string
    {
        return (string) $this->config->get('supabase_url', '');
    }

    public function anonKey(): string
    {
        return (string) $this->config->get('supabase_anon_key', '');
    }

    public function serviceKey(): string
    {
        return (string) $this->config->get('supabase_service_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->url() !== '' && $this->serviceKey() !== '';
    }

    /** @return array{configured: bool} */
    public function publicSettings(): array
    {
        return ['configured' => $this->url() !== '' && $this->anonKey() !== ''];
    }
}
