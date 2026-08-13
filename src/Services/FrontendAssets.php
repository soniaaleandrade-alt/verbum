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
        'verbum-studio-auth-profile' => 'frontend/app/src/styles/auth-profile.css',
        'verbum-studio-profile-polish' => 'frontend/app/src/styles/profile-polish.css',
        'verbum-studio-research-layout-hotfix' => 'frontend/app/src/styles/chapter-research-layout-hotfix.css',
    ];

    private const LAZY_STYLE_FILES = [
        'frontend/app/src/styles/workspace.css',
        'frontend/app/src/styles/identification.css',
        'frontend/app/src/styles/project-stage.css',
        'frontend/app/src/styles/planning-stage.css',
        'frontend/app/src/styles/development-stage.css',
        'frontend/app/src/styles/chapter-workflow.css',
        'frontend/app/src/styles/chapter-preparation.css',
        'frontend/app/src/styles/chapter-research.css',
        'frontend/app/src/styles/chapter-writing.css',
        'frontend/app/src/styles/chapter-revision.css',
        'frontend/app/src/styles/general-review.css',
        'frontend/app/src/styles/work-versions.css',
        'frontend/app/src/styles/work-audit.css',
        'frontend/app/src/styles/editorial-desk.css',
        'frontend/app/src/styles/layout-stage.css',
        'frontend/app/src/styles/legal-stage.css',
        'frontend/app/src/styles/publication-stage.css',
    ];

    private const SCRIPT_FILES = [
        'build/verbum-app.js', 'frontend/app/src/auth-profile-runtime.js', 'frontend/app/src/static-runtime.js',
        'frontend/app/src/workspace-mobile-runtime.js', 'frontend/app/src/identification-runtime.js', 'frontend/app/src/project-stage-runtime.js',
        'frontend/app/src/planning-stage-runtime.js', 'frontend/app/src/development-stage-runtime.js', 'frontend/app/src/chapter-workflow-runtime.js',
        'frontend/app/src/chapter-preparation-runtime.js', 'frontend/app/src/chapter-research-runtime.js', 'frontend/app/src/chapter-writing-runtime.js',
        'frontend/app/src/writing-hom010-hotfix.js', 'frontend/app/src/writing-hom011-hotfix.js',
        'frontend/app/src/revision-hom012-hotfix.js', 'frontend/app/src/chapter-revision-runtime.js', 'frontend/app/src/general-review-runtime.js', 'frontend/app/src/work-versions-runtime.js',
        'frontend/app/src/work-audit-runtime.js', 'frontend/app/src/editorial-desk-runtime.js', 'frontend/app/src/layout-stage-runtime.js',
        'frontend/app/src/legal-stage-runtime.js', 'frontend/app/src/publication-stage-runtime.js', 'frontend/app/src/technical-runtime.js',
        'frontend/app/src/dashboard-official-runtime.js', 'frontend/app/src/sidebar-profile-runtime.js', 'frontend/app/src/minhas-obras-runtime.js',
        'frontend/app/src/profile-polish-runtime.js',
    ];

    public function register(): void { add_shortcode('verbum_app', [$this, 'shortcode']); }

    public function enqueue(): void
    {
        $assetVersion = $this->latestAssetVersion(array_merge(array_values(self::BASE_STYLE_FILES), self::LAZY_STYLE_FILES, self::SCRIPT_FILES));
        foreach (self::BASE_STYLE_FILES as $handle => $relativePath) {
            wp_enqueue_style($handle, VERBUM_STUDIO_URL . $relativePath, [], $assetVersion);
        }
        wp_add_inline_style('verbum-studio-minhas-obras', '.verbum-minhas-more{position:absolute;top:10px;right:10px;z-index:4;width:34px;height:34px;border:1px solid #ddd;border-radius:9px;background:#fff;cursor:pointer}.verbum-minhas-card-menu{position:absolute;top:49px;right:10px;z-index:5;display:grid;min-width:150px;padding:5px;background:#fff;border:1px solid #ddd;border-radius:10px;box-shadow:0 12px 28px rgba(20,30,48,.18)}.verbum-minhas-card-menu button{border:0;padding:9px 10px;background:transparent;text-align:left;cursor:pointer}.verbum-minhas-modal-backdrop{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:24px;background:rgba(17,25,43,.52)}.verbum-minhas-modal{width:min(620px,90vw);max-height:90vh;overflow:auto;padding:24px;background:#fff;border-radius:16px}.verbum-minhas-modal-heading{display:flex;justify-content:space-between;gap:18px}.verbum-minhas-cover-preview{position:relative;height:265px;overflow:hidden;border-radius:13px;background:#eef1f5;cursor:grab;touch-action:none}.verbum-minhas-cover-preview img{width:100%;height:100%;object-fit:cover}.verbum-minhas-cover-preview>span{position:absolute;left:50%;bottom:12px;transform:translateX(-50%);padding:6px 10px;border-radius:999px;background:rgba(18,27,45,.72);color:#fff;font-size:10px}.verbum-minhas-cover-controls{display:grid;gap:12px;margin-top:18px}.verbum-minhas-cover-controls label{display:grid;grid-template-columns:82px 1fr 48px;gap:10px;align-items:center}.verbum-minhas-cover-controls input{width:100%}.verbum-minhas-modal-actions{display:flex;gap:10px;align-items:center;margin-top:22px}.verbum-minhas-modal-actions span{flex:1}.verbum-minhas-modal-actions button{min-height:40px;padding:0 14px;border:1px solid #dce0e7;border-radius:9px;background:#fff;cursor:pointer}.verbum-minhas-modal-actions .is-primary{background:#6542ec;color:#fff}.verbum-minhas-modal-actions .is-danger{background:#b52c49;color:#fff}.verbum-minhas-warning,.verbum-minhas-modal-error{margin-top:14px;padding:10px 12px;border-radius:9px;background:#fff1f4;color:#a32642}.verbum-minhas-notice{position:fixed;right:26px;bottom:26px;z-index:100000;padding:11px 14px;border:1px solid #ddd;border-radius:10px;background:#fff;box-shadow:0 12px 30px rgba(20,30,48,.15)}');
        wp_enqueue_script('verbum-studio-app', VERBUM_STUDIO_URL . 'build/verbum-app.js', [], $assetVersion, true);
        $charset = function_exists('get_bloginfo') ? (string) get_bloginfo('charset') : 'UTF-8';
        $logoutUrl = html_entity_decode(wp_logout_url(home_url('/')), ENT_QUOTES, $charset !== '' ? $charset : 'UTF-8');
        wp_localize_script('verbum-studio-app', 'VerbumStudioConfig', ['apiRoot' => esc_url_raw(rest_url('verbum/v1')), 'nonce' => wp_create_nonce('wp_rest'), 'version' => VERBUM_STUDIO_VERSION, 'logoutUrl' => esc_url_raw($logoutUrl), 'appUrl' => esc_url_raw(home_url('/')), 'authenticated' => is_user_logged_in()]);
    }

    public function shortcode(): string { if ($this->shouldEnqueueForCurrentRequest()) $this->enqueue(); return '<div class="verbum-app" data-verbum-app></div>'; }
    /** @param string[] $relativePaths */ private function latestAssetVersion(array $relativePaths): string { $latest = 0; foreach ($relativePaths as $relativePath) { $path = VERBUM_STUDIO_PATH . $relativePath; if (! is_file($path)) continue; $modified = filemtime($path); if ($modified !== false) $latest = max($latest, $modified); } return $latest > 0 ? (string) $latest : VERBUM_STUDIO_VERSION; }
    private function shouldEnqueueForCurrentRequest(): bool { if (defined('REST_REQUEST') && REST_REQUEST) return false; if (function_exists('wp_is_json_request') && wp_is_json_request()) return false; return true; }
}
