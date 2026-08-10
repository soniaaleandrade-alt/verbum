<?php
declare(strict_types=1);

namespace VerbumStudio\Core;

final class Config
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(array $overrides = [])
    {
        $environment = $this->env('VERBUM_ENV', defined('WP_ENVIRONMENT_TYPE') ? (string) WP_ENVIRONMENT_TYPE : 'production');
        if (! in_array($environment, ['development', 'staging', 'production'], true)) {
            $environment = 'production';
        }
        $this->values = array_merge([
            'environment' => $environment,
            'debug' => $environment !== 'production' && defined('WP_DEBUG') && WP_DEBUG,
            'version' => defined('VERBUM_STUDIO_VERSION') ? VERBUM_STUDIO_VERSION : '1.0.4',
            'api_namespace' => 'verbum/v1',
            'supabase_url' => $this->env('VERBUM_SUPABASE_URL', ''),
            'supabase_anon_key' => $this->env('VERBUM_SUPABASE_ANON_KEY', ''),
            'supabase_service_key' => $this->env('VERBUM_SUPABASE_SERVICE_KEY', ''),
            'openai_api_key' => $this->env('VERBUM_OPENAI_API_KEY', ''),
        ], $overrides);
    }

    public function get(string $key, $default = null)
    {
        return $this->values[$key] ?? $default;
    }

    public function isProduction(): bool
    {
        return $this->get('environment') === 'production';
    }

    private function env(string $key, string $default): string
    {
        $constant = defined($key) ? constant($key) : null;
        $value = $constant ?? getenv($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
