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
        'verbum-studio-technical' => 'frontend/app/src/styles/technical.css',
    ];

    private const SCRIPT_FILES = [
        'build/verbum-app.js',
        'frontend/app/src/static-runtime.js',
        'frontend/app/src/workspace-mobile-runtime.js',
        'frontend/app/src/identification-runtime.js',
        'frontend/app/src/project-stage-runtime.js',
        'frontend/app/src/technical-runtime.js',
    ];

    public function register(): void
    {
        add_shortcode('verbum_app', [$this, 'shortcode']);
    }

    public function enqueue(): void
    {
        $dependencies = [];
        foreach (self::STYLE_FILES as $handle => $relativePath) {
            wp_enqueue_style(
                $handle,
                VERBUM_STUDIO_URL . $relativePath,
                $dependencies,
                $this->assetVersion($relativePath)
            );
            $dependencies = [$handle];
        }

        wp_enqueue_script(
            'verbum-studio-app',
            VERBUM_STUDIO_URL . 'build/verbum-app.js',
            [],
            $this->latestAssetVersion(self::SCRIPT_FILES),
            true
        );

        wp_localize_script('verbum-studio-app', 'VerbumStudioConfig', [
            'apiRoot' => esc_url_raw(rest_url('verbum/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => VERBUM_STUDIO_VERSION,
        ]);
    }

    public function shortcode(): string
    {
        if ($this->shouldEnqueueForCurrentRequest()) {
            $this->enqueue();
        }

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
            if (! is_file($path)) {
                continue;
            }
            $modified = filemtime($path);
            if ($modified !== false) {
                $latest = max($latest, $modified);
            }
        }

        return $latest > 0 ? (string) $latest : VERBUM_STUDIO_VERSION;
    }

    private function shouldEnqueueForCurrentRequest(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return false;
        }

        return true;
    }
}
