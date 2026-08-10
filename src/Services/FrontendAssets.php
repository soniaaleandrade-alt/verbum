<?php

declare(strict_types=1);

namespace VerbumStudio\Services;

final class FrontendAssets
{
    private const STYLE_FILES = [
        'verbum-studio-app' => 'frontend/app/src/styles/verbum.css',
        'verbum-studio-library' => 'frontend/app/src/styles/library.css',
        'verbum-studio-workspace' => 'frontend/app/src/styles/workspace.css',
        'verbum-studio-identification' => 'frontend/app/src/styles/identification.css',
        'verbum-studio-project-stage' => 'frontend/app/src/styles/project-stage.css',
        'verbum-studio-planning-stage' => 'frontend/app/src/styles/planning-stage.css',
        'verbum-studio-development-stage' => 'frontend/app/src/styles/development-stage.css',
        'verbum-studio-chapter-workflow' => 'frontend/app/src/styles/chapter-workflow.css',
        'verbum-studio-chapter-preparation' => 'frontend/app/src/styles/chapter-preparation.css',
        'verbum-studio-chapter-research' => 'frontend/app/src/styles/chapter-research.css',
        'verbum-studio-chapter-writing' => 'frontend/app/src/styles/chapter-writing.css',
        'verbum-studio-technical' => 'frontend/app/src/styles/technical.css',
        'verbum-studio-dashboard-official' => 'frontend/app/src/styles/dashboard-official.css',
        'verbum-studio-dashboard-polish' => 'frontend/app/src/styles/dashboard-polish.css',
        'verbum-studio-sidebar-profile' => 'frontend/app/src/styles/sidebar-profile.css',
        'verbum-studio-minhas-obras' => 'frontend/app/src/styles/minhas-obras.css',
        'verbum-studio-auth-profile' => 'frontend/app/src/styles/auth-profile.css',
        'verbum-studio-profile-polish' => 'frontend/app/src/styles/profile-polish.css',
    ];

    private const SCRIPT_FILES = [
        'build/verbum-app.js',
        'frontend/app/src/auth-profile-runtime.js',
        'frontend/app/src/static-runtime.js',
        'frontend/app/src/workspace-mobile-runtime.js',
        'frontend/app/src/identification-runtime.js',
        'frontend/app/src/project-stage-runtime.js',
        'frontend/app/src/planning-stage-runtime.js',
        'frontend/app/src/development-stage-runtime.js',
        'frontend/app/src/chapter-workflow-runtime.js',
        'frontend/app/src/chapter-preparation-runtime.js',
        'frontend/app/src/chapter-research-runtime.js',
        'frontend/app/src/chapter-writing-runtime.js',
        'frontend/app/src/technical-runtime.js',
        'frontend/app/src/dashboard-official-runtime.js',
        'frontend/app/src/sidebar-profile-runtime.js',
        'frontend/app/src/minhas-obras-runtime.js',
        'frontend/app/src/profile-polish-runtime.js',
    ];

    public function register(): void
    {
        add_shortcode('verbum_app', [$this, 'shortcode']);
    }

    public function enqueue(): void
    {
        $dependencies = [];
        foreach (self::STYLE_FILES as $handle => $relativePath) {
            wp_enqueue_style($handle, VERBUM_STUDIO_URL . $relativePath, $dependencies, $this->assetVersion($relativePath));
            $dependencies = [$handle];
        }

        wp_enqueue_script('verbum-studio-app', VERBUM_STUDIO_URL . 'build/verbum-app.js', [], $this->latestAssetVersion(self::SCRIPT_FILES), true);

        $charset = function_exists('get_bloginfo') ? (string) get_bloginfo('charset') : 'UTF-8';
        $logoutUrl = html_entity_decode(wp_logout_url(home_url('/')), ENT_QUOTES, $charset !== '' ? $charset : 'UTF-8');
        wp_localize_script('verbum-studio-app', 'VerbumStudioConfig', [
            'apiRoot' => esc_url_raw(rest_url('verbum/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => VERBUM_STUDIO_VERSION,
            'logoutUrl' => esc_url_raw($logoutUrl),
            'appUrl' => esc_url_raw(home_url('/')),
            'authenticated' => is_user_logged_in(),
        ]);
    }

    public function shortcode(): string
    {
        if ($this->shouldEnqueueForCurrentRequest()) $this->enqueue();
        return '<div class="verbum-app" data-verbum-app></div>';
    }

    private function assetVersion(string $relativePath): string
    {
        $path = VERBUM_STUDIO_PATH . $relativePath;
        $modified = is_file($path) ? filemtime($path) : false;
        return $modified === false ? VERBUM_STUDIO_VERSION : (string) $modified;
    }

    /** @param string[] $relativePaths */
    private function latestAssetVersion(array $relativePaths): string
    {
        $latest = 0;
        foreach ($relativePaths as $relativePath) {
            $path = VERBUM_STUDIO_PATH . $relativePath;
            if (! is_file($path)) continue;
            $modified = filemtime($path);
            if ($modified !== false) $latest = max($latest, $modified);
        }
        return $latest > 0 ? (string) $latest : VERBUM_STUDIO_VERSION;
    }

    private function shouldEnqueueForCurrentRequest(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) return false;
        if (function_exists('wp_is_json_request') && wp_is_json_request()) return false;
        return true;
    }
}
