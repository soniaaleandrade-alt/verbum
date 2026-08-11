<?php
/**
 * Plugin Name: Verbum Studio
 * Description: Core foundation for the Verbum Studio writing operating system.
 * Version: 2.1.0
 * Author: Verbum Studio
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>' . esc_html__('Verbum Studio requer PHP 7.4 ou superior.', 'verbum-studio') . '</p></div>';
    });
    return;
}

define('VERBUM_STUDIO_VERSION', '2.1.0');
define('VERBUM_STUDIO_FILE', __FILE__);
define('VERBUM_STUDIO_PATH', plugin_dir_path(__FILE__));
define('VERBUM_STUDIO_URL', plugin_dir_url(__FILE__));
define('VERBUM_STUDIO_BASENAME', plugin_basename(__FILE__));

$autoload = VERBUM_STUDIO_PATH . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'VerbumStudio\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = VERBUM_STUDIO_PATH . 'src/' . $relative . '.php';
        if (is_readable($file)) require_once $file;
    });
}

register_activation_hook(__FILE__, ['VerbumStudio\\Core\\Bootstrap', 'activate']);
register_deactivation_hook(__FILE__, ['VerbumStudio\\Core\\Bootstrap', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    VerbumStudio\Core\Bootstrap::boot();
});
