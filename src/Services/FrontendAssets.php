<?php

declare(strict_types=1);

namespace VerbumStudio\Services;

final class FrontendAssets
{
    private const BASE_STYLE_FILES = [
        'verbum-studio-app' => 'frontend/app/src/styles/verbum.css',
        'verbum-studio-library' => 'frontend/app/src/styles/library.css',
        'verbum-studio-technical' => 'frontend/app/src/styles/technical.css',
        'verbum-studio-dashboard-official' => 'frontend/app/src/styles/dashboard-official.css',
        'verbum-studio-dashboard-polish' => 'frontend/app/src/styles/dashboard-polish.css',
        'verbum-studio-sidebar-profile' => 'frontend/app/src/styles/sidebar-profile.css',
        'verbum-studio-minhas-obras' => 'frontend/app/src/styles/minhas-obras.css',
        'verbum-studio-minhas-obras-reference' => 'frontend/app/src/styles/minhas-obras-reference.css',
        'verbum-studio-published-work' => 'frontend/app/src/styles/published-work.css',
        'verbum-studio-auth-profile' => 'frontend/app/src/styles/auth-profile.css',
        'verbum-studio-profile-polish' => 'frontend/app/src/styles/profile-polish.css',
        'verbum-studio-research-layout-hotfix' => 'frontend/app/src/styles/chapter-research-layout-hotfix.css',
        'verbum-studio-structure-stage' => 'frontend/app/src/styles/structure-stage.css',
    ];

    private const LAZY_STYLE_FILES = [
        'frontend/app/src/styles/workspace.css', 'frontend/app/src/styles/workspace-manager.css', 'frontend/app/src/styles/identification.css', 'frontend/app/src/styles/identification-hom027-polish.css', 'frontend/app/src/styles/project-stage.css',
        'frontend/app/src/styles/planning-stage.css', 'frontend/app/src/styles/development-stage.css', 'frontend/app/src/styles/chapter-workflow.css',
        'frontend/app/src/styles/chapter-preparation.css', 'frontend/app/src/styles/chapter-research.css', 'frontend/app/src/styles/chapter-writing.css',
        'frontend/app/src/styles/chapter-revision.css', 'frontend/app/src/styles/general-review.css', 'frontend/app/src/styles/work-versions.css',
        'frontend/app/src/styles/work-audit.css', 'frontend/app/src/styles/editorial-desk.css', 'frontend/app/src/styles/layout-stage.css',
        'frontend/app/src/styles/legal-stage.css', 'frontend/app/src/styles/publication-stage.css',
    ];

    private const SCRIPT_FILES = [
        'build/verbum-app.js', 'frontend/app/src/auth-profile-runtime.js', 'frontend/app/src/static-runtime.js',
        'frontend/app/src/workspace-mobile-runtime.js', 'frontend/app/src/workspace-ui-runtime.js', 'frontend/app/src/workspace-manager-runtime.js', 'frontend/app/src/hom023a-journey-runtime.js', 'frontend/app/src/hom027-journey-runtime.js', 'frontend/app/src/workspace-book-dialog-runtime.js',
        'frontend/app/src/identification-initial-prelude.js', 'frontend/app/src/identification-runtime.js', 'frontend/app/src/project-stage-runtime.js', 'frontend/app/src/foundation-intention-runtime.js', 'frontend/app/src/foundation-reader-result-runtime.js', 'frontend/app/src/foundation-truth-central-runtime.js', 'frontend/app/src/foundation-simplification-runtime.js',
        'frontend/app/src/planning-stage-runtime.js', 'frontend/app/src/structure-direction-runtime.js', 'frontend/app/src/structure-architecture-runtime.js', 'frontend/app/src/structure-elements-runtime.js', 'frontend/app/src/structure-index-runtime.js', 'frontend/app/src/structure-refinement-runtime.js', 'frontend/app/src/development-stage-runtime.js', 'frontend/app/src/chapter-workflow-runtime.js',
        'frontend/app/src/chapter-preparation-runtime.js', 'frontend/app/src/chapter-research-runtime.js', 'frontend/app/src/chapter-writing-runtime.js',
        'frontend/app/src/writing-hom010-hotfix.js', 'frontend/app/src/writing-hom011-hotfix.js', 'frontend/app/src/revision-hom012-hotfix.js',
        'frontend/app/src/chapter-revision-runtime.js', 'frontend/app/src/general-review-runtime.js', 'frontend/app/src/work-versions-runtime.js',
        'frontend/app/src/work-audit-runtime.js', 'frontend/app/src/editorial-desk-runtime.js', 'frontend/app/src/layout-stage-runtime.js',
        'frontend/app/src/legal-stage-runtime.js', 'frontend/app/src/publication-stage-runtime.js', 'frontend/app/src/technical-runtime.js',
        'frontend/app/src/dashboard-official-runtime.js', 'frontend/app/src/sidebar-profile-runtime.js', 'frontend/app/src/minhas-obras-runtime.js', 'frontend/app/src/minhas-obras-identificacao-bridge.js', 'frontend/app/src/published-work-runtime.js',
        'frontend/app/src/profile-polish-runtime.js',
    ];

    public function register(): void { add_shortcode('verbum_app', [$this, 'shortcode']); }

    public function enqueue(): void
    {
        $assetVersion = $this->latestAssetVersion(array_merge(array_values(self::BASE_STYLE_FILES), self::LAZY_STYLE_FILES, self::SCRIPT_FILES));
        foreach (self::BASE_STYLE_FILES as $handle => $relativePath) {
            wp_enqueue_style($handle, VERBUM_STUDIO_URL . $relativePath, [], $assetVersion);
        }
        wp_enqueue_script('verbum-studio-app', VERBUM_STUDIO_URL . 'build/verbum-app.js', [], $assetVersion, true);
        wp_enqueue_script('verbum-foundation-simplification', VERBUM_STUDIO_URL . 'frontend/app/src/foundation-simplification-runtime.js', ['verbum-studio-app'], $assetVersion, true);
        wp_enqueue_script('verbum-structure-refinement', VERBUM_STUDIO_URL . 'frontend/app/src/structure-refinement-runtime.js', ['verbum-studio-app'], $assetVersion, true);
        wp_enqueue_script('verbum-minhas-obras-identificacao-bridge', VERBUM_STUDIO_URL . 'frontend/app/src/minhas-obras-identificacao-bridge.js', ['verbum-studio-app'], $assetVersion, true);
        $charset = function_exists('get_bloginfo') ? (string) get_bloginfo('charset') : 'UTF-8';
        $logoutUrl = html_entity_decode(wp_logout_url(home_url('/')), ENT_QUOTES, $charset !== '' ? $charset : 'UTF-8');
        wp_localize_script('verbum-studio-app', 'VerbumStudioConfig', ['apiRoot' => esc_url_raw(rest_url('verbum/v1')), 'nonce' => wp_create_nonce('wp_rest'), 'version' => VERBUM_STUDIO_VERSION, 'logoutUrl' => esc_url_raw($logoutUrl), 'appUrl' => esc_url_raw(home_url('/')), 'authenticated' => is_user_logged_in()]);
    }

    public function shortcode(): string { if ($this->shouldEnqueueForCurrentRequest()) $this->enqueue(); return '<div class="verbum-app" data-verbum-app></div>'; }
    /** @param string[] $relativePaths */ private function latestAssetVersion(array $relativePaths): string { $latest = 0; foreach ($relativePaths as $relativePath) { $path = VERBUM_STUDIO_PATH . $relativePath; if (! is_file($path)) continue; $modified = filemtime($path); if ($modified !== false) $latest = max($latest, $modified); } return $latest > 0 ? (string) $latest : VERBUM_STUDIO_VERSION; }
    private function shouldEnqueueForCurrentRequest(): bool { if (defined('REST_REQUEST') && REST_REQUEST) return false; if (function_exists('wp_is_json_request') && wp_is_json_request()) return false; return true; }
}
