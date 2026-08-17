<?php
/**
 * Plugin Name: Verbum Studio
 * Description: Core foundation for the Verbum Studio writing operating system.
 * Version: 2.6.4
 * Author: Verbum Studio
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (! defined('ABSPATH')) { exit; }

if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', static function (): void { echo '<div class="notice notice-error"><p>' . esc_html__('Verbum Studio requer PHP 7.4 ou superior.', 'verbum-studio') . '</p></div>'; });
    return;
}

define('VERBUM_STUDIO_VERSION', '2.6.4');
define('VERBUM_STUDIO_FILE', __FILE__);
define('VERBUM_STUDIO_PATH', plugin_dir_path(__FILE__));
define('VERBUM_STUDIO_URL', plugin_dir_url(__FILE__));
define('VERBUM_STUDIO_BASENAME', plugin_basename(__FILE__));

$autoload = VERBUM_STUDIO_PATH . 'vendor/autoload.php';
if (file_exists($autoload)) { require_once $autoload; }
else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'VerbumStudio\\'; if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $relative = str_replace('\\', '/', substr($class, strlen($prefix))); $file = VERBUM_STUDIO_PATH . 'src/' . $relative . '.php'; if (is_readable($file)) require_once $file;
    });
}

register_activation_hook(__FILE__, ['VerbumStudio\\Core\\Bootstrap', 'activate']);
register_deactivation_hook(__FILE__, ['VerbumStudio\\Core\\Bootstrap', 'deactivate']);
add_action('plugins_loaded', static function (): void { VerbumStudio\Core\Bootstrap::boot(); });
add_action('init', static function (): void {
    $writer = get_role(VerbumStudio\Auth\Capabilities::WRITER_ROLE);
    if ($writer && empty($writer->capabilities['upload_files'])) {
        $writer->add_cap('upload_files');
    }
});

// HOM-012: revision data must never be served from a stale REST/object cache.
add_filter('rest_request_after_callbacks', static function ($response, $handler, $request) {
    if (! is_object($request) || ! method_exists($request, 'get_route')) return $response;
    $route = (string) $request->get_route();
    if (! preg_match('#^/verbum/v1/books/\d+/chapters/(\d+)/revision(?:/.*)?$#', $route, $matches)) return $response;

    $method = method_exists($request, 'get_method') ? strtoupper((string) $request->get_method()) : 'GET';
    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
        $chapterId = (int) ($matches[1] ?? 0);
        if ($chapterId > 0) {
            if (function_exists('clean_post_cache')) clean_post_cache($chapterId);
            if (function_exists('wp_cache_delete')) wp_cache_delete($chapterId, 'post_meta');
        }
    }

    if (is_object($response) && method_exists($response, 'header')) {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
    }
    return $response;
}, 20, 3);

// HOM-013: persist and expose the detailed checklist used by the guided Revision flow.
add_filter('rest_request_after_callbacks', static function ($response, $handler, $request) {
    if (! is_object($request) || ! method_exists($request, 'get_route')) return $response;
    $route = (string) $request->get_route();
    if (! preg_match('#^/verbum/v1/books/\d+/chapters/(\d+)/revision(?:/.*)?$#', $route, $matches)) return $response;

    $chapterId = (int) ($matches[1] ?? 0);
    if ($chapterId <= 0) return $response;

    $allowed = [
        'argumentation_coherent', 'gaps_checked', 'content_aligned',
        'introduction_adequate', 'sequence_logical', 'transitions_checked', 'balance_checked', 'conclusion_adequate',
        'repetitions_checked', 'redundancies_corrected', 'vocabulary_adequate', 'tone_style_coherent', 'fluency_reviewed',
        'orthography_reviewed', 'grammar_reviewed', 'agreement_reviewed', 'punctuation_reviewed', 'typing_reviewed',
    ];

    $method = method_exists($request, 'get_method') ? strtoupper((string) $request->get_method()) : 'GET';
    if ($method === 'PATCH' && preg_match('#/revision/?$#', $route) && method_exists($request, 'get_json_params')) {
        $params = $request->get_json_params();
        if (is_array($params) && array_key_exists('hom013_flags', $params) && is_array($params['hom013_flags'])) {
            $incoming = $params['hom013_flags'];
            $clean = [];
            foreach ($allowed as $key) $clean[$key] = (bool) ($incoming[$key] ?? false);
            update_post_meta($chapterId, '_verbum_revision_hom013_flags', $clean);
            if (function_exists('clean_post_cache')) clean_post_cache($chapterId);
            if (function_exists('wp_cache_delete')) wp_cache_delete($chapterId, 'post_meta');
        }
    }

    $stored = get_post_meta($chapterId, '_verbum_revision_hom013_flags', true);
    $stored = is_array($stored) ? $stored : [];
    $flags = [];
    foreach ($allowed as $key) $flags[$key] = (bool) ($stored[$key] ?? false);

    if (is_object($response) && method_exists($response, 'get_data') && method_exists($response, 'set_data')) {
        $data = $response->get_data();
        if (is_array($data) && ! empty($data['success']) && isset($data['data']) && is_array($data['data'])) {
            if (isset($data['data']['revision']) && is_array($data['data']['revision'])) {
                $data['data']['revision']['hom013Flags'] = $flags;
            } else {
                $data['data']['hom013Flags'] = $flags;
            }
            $response->set_data($data);
        }
    }

    return $response;
}, 30, 3);

// HOM-025A: keep the new Fio Condutor separate from the legacy Estrutura Geral.
add_filter('rest_request_after_callbacks', static function ($response, $handler, $request) {
    if (! is_object($request) || ! method_exists($request, 'get_route')) return $response;
    $route = (string) $request->get_route();
    if (! preg_match('#^/verbum/v1/books/(\d+)/planning-stage/?$#', $route, $matches)) return $response;

    $bookId = (int) ($matches[1] ?? 0);
    if ($bookId <= 0) return $response;

    $method = method_exists($request, 'get_method') ? strtoupper((string) $request->get_method()) : 'GET';
    if ($method === 'PATCH' && method_exists($request, 'get_json_params')) {
        $params = $request->get_json_params();
        if (is_array($params) && array_key_exists('structural_thread', $params)) {
            update_post_meta($bookId, '_verbum_structure_thread', sanitize_textarea_field((string) $params['structural_thread']));
            if (function_exists('clean_post_cache')) clean_post_cache($bookId);
            if (function_exists('wp_cache_delete')) wp_cache_delete($bookId, 'post_meta');
        }
    }

    $thread = trim((string) get_post_meta($bookId, '_verbum_structure_thread', true));
    if (is_object($response) && method_exists($response, 'get_data') && method_exists($response, 'set_data')) {
        $data = $response->get_data();
        if (is_array($data) && ! empty($data['success']) && isset($data['data']) && is_array($data['data'])) {
            if (isset($data['data']['planningStage']['values']) && is_array($data['data']['planningStage']['values'])) {
                $data['data']['planningStage']['values']['structuralThread'] = $thread;
            } elseif (isset($data['data']['values']) && is_array($data['data']['values'])) {
                $data['data']['values']['structuralThread'] = $thread;
            }
            $response->set_data($data);
        }
    }

    if (is_object($response) && method_exists($response, 'header')) {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
    return $response;
}, 35, 3);
