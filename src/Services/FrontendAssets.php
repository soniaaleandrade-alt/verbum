<?php

declare(strict_types=1);

namespace VerbumStudio\Services;

final class FrontendAssets
{
    public function register(): void
    {
        add_shortcode('verbum_app', [$this, 'shortcode']);
    }

    public function enqueue(): void
    {
        wp_enqueue_style(
            'verbum-studio-app',
            VERBUM_STUDIO_URL . 'build/verbum-app.css',
            [],
            VERBUM_STUDIO_VERSION
        );

        wp_enqueue_script(
            'verbum-studio-app',
            VERBUM_STUDIO_URL . 'build/verbum-app.js',
            [],
            VERBUM_STUDIO_VERSION,
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
        $this->enqueue();

        return '<div class="verbum-app" data-verbum-app></div>';
    }
}
