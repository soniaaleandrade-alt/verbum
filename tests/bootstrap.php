<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/wp/');
define('WP_DEBUG', true);

global $verbum_test_actions, $verbum_test_shortcodes, $verbum_test_routes, $verbum_test_post_types, $verbum_test_enqueued, $verbum_test_json_request, $verbum_test_logged_in, $verbum_test_caps, $verbum_test_user;
$verbum_test_actions = [];
$verbum_test_shortcodes = [];
$verbum_test_routes = [];
$verbum_test_post_types = [];
$verbum_test_enqueued = [];
$verbum_test_json_request = false;
$verbum_test_logged_in = false;
$verbum_test_caps = [];
$verbum_test_user = (object) ['ID' => 7, 'display_name' => 'gestor', 'first_name' => 'Sonia', 'last_name' => 'Andrade', 'user_email' => 'autora@example.test'];

final class WP_REST_Response
{
    private array $data;
    private int $status;

    public function __construct(array $data, int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data(): array
    {
        return $this->data;
    }

    public function get_status(): int
    {
        return $this->status;
    }
}

final class WP_REST_Request implements ArrayAccess
{
    private array $params;
    private array $json;

    public function __construct(array $params = [], array $json = [])
    {
        $this->params = $params;
        $this->json = $json;
    }

    public function get_json_params(): array
    {
        return $this->json;
    }

    public function offsetExists($offset): bool { return isset($this->params[$offset]); }
    public function offsetGet($offset) { return $this->params[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->params[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->params[$offset]); }
}

final class Verbum_Test_Role
{
    /** @var string[] */
    public array $caps = [];

    public function add_cap(string $capability): void
    {
        $this->caps[] = $capability;
    }

    public function remove_cap(string $capability): void
    {
        $this->caps = array_values(array_diff($this->caps, [$capability]));
    }
}

function plugin_dir_path($file): string { return dirname((string) $file) . '/'; }
function plugin_dir_url($file): string { return 'https://example.test/wp-content/plugins/verbum-studio/'; }
function plugin_basename($file): string { return basename((string) $file); }
function register_activation_hook($file, $callback): void {}
function register_deactivation_hook($file, $callback): void {}
function flush_rewrite_rules(): void {}
function esc_html__($text, $domain = null): string { return (string) $text; }
function esc_url_raw($url): string { return filter_var((string) $url, FILTER_SANITIZE_URL); }
function sanitize_key($key): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function sanitize_email($email): string { return filter_var((string) $email, FILTER_SANITIZE_EMAIL); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value): string { return trim(strip_tags((string) $value)); }
function wp_json_encode($data): string { return json_encode($data); }
function rest_url($path = ''): string { return 'https://example.test/wp-json/' . ltrim((string) $path, '/'); }
function home_url($path = ''): string { return 'https://example.test/' . ltrim((string) $path, '/'); }
function wp_logout_url($redirect = ''): string { return 'https://example.test/wp-login.php?action=logout&_wpnonce=test' . ($redirect !== '' ? '&redirect_to=' . rawurlencode((string) $redirect) : ''); }
function get_bloginfo($show = ''): string { return $show === 'charset' ? 'UTF-8' : ''; }
function wp_create_nonce($action): string { return 'nonce-' . (string) $action; }
function wp_enqueue_style($handle, $src, $deps = [], $ver = null): void { global $verbum_test_enqueued; $verbum_test_enqueued[] = ['style', $handle, $src]; }
function wp_enqueue_script($handle, $src, $deps = [], $ver = null, $in_footer = false): void { global $verbum_test_enqueued; $verbum_test_enqueued[] = ['script', $handle, $src]; }
function wp_localize_script($handle, $object_name, $l10n): void { global $verbum_test_enqueued; $verbum_test_enqueued[] = ['localize', $handle, $object_name, $l10n]; }
function did_action($hook): int { return 0; }
function wp_is_json_request(): bool { global $verbum_test_json_request; return $verbum_test_json_request; }
function __return_true(): bool { return true; }
function is_wp_error($thing): bool { return false; }
function wp_remote_get($url, $args = []) { return ['response' => ['code' => 200]]; }
function wp_remote_retrieve_response_code($response): int { return (int) ($response['response']['code'] ?? 0); }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void { global $verbum_test_actions; $verbum_test_actions[$hook][] = $callback; }
function add_shortcode($tag, $callback): void { global $verbum_test_shortcodes; $verbum_test_shortcodes[$tag] = $callback; }
function register_rest_route($namespace, $route, $args): void { global $verbum_test_routes; $verbum_test_routes[$namespace . $route] = $args; }
function register_post_type($post_type, $args = []): void { global $verbum_test_post_types; $verbum_test_post_types[$post_type] = $args; }
function is_user_logged_in(): bool { global $verbum_test_logged_in; return $verbum_test_logged_in; }
function current_user_can($capability): bool { global $verbum_test_caps; return in_array($capability, $verbum_test_caps, true); }
function wp_get_current_user() { global $verbum_test_user; return $verbum_test_user; }
function get_current_user_id(): int { global $verbum_test_user; return (int) $verbum_test_user->ID; }
function get_role($name) { static $roles = []; return $roles[$name] ??= new Verbum_Test_Role(); }

require_once __DIR__ . '/../verbum-studio.php';
