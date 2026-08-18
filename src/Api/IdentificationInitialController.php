<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\LibraryRepository;

final class IdentificationInitialController
{
    private const REQUIRED = [
        'title' => 'Título provisório da obra',
        'author_name' => 'Nome da autoria',
        'theme' => 'Tema central',
        'genre' => 'Gênero da obra',
        'approach' => 'Abordagem',
        'audience' => 'Público principal',
        'language_tone' => 'Linguagem e tom',
        'intended_format' => 'Formato pretendido',
        'estimated_extent' => 'Extensão estimada',
        'workflow_status' => 'Status da obra',
    ];

    private const META_KEYS = [
        'subtitle' => '_verbum_subtitle',
        'author_name' => '_verbum_author_name',
        'theme' => '_verbum_work_project_theme',
        'genre' => '_verbum_genre',
        'approach' => '_verbum_planning_approach',
        'audience' => '_verbum_audience',
        'language_tone' => '_verbum_language_tone',
        'keywords' => '_verbum_keywords',
        'intended_format' => '_verbum_intended_format',
        'estimated_extent' => '_verbum_estimated_extent',
        'workflow_status' => '_verbum_workflow_status',
        'cover_position_x' => '_verbum_cover_position_x',
        'cover_position_y' => '_verbum_cover_position_y',
    ];

    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;

    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library)
    {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];

            register_rest_route($namespace, '/books/(?P<id>\d+)/identification-initial', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'read'],
                    'permission_callback' => $permission,
                ],
                [
                    'methods' => 'PATCH',
                    'callback' => [$this, 'saveDraft'],
                    'permission_callback' => $permission,
                ],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/identification-initial/complete', [
                'methods' => 'POST',
                'callback' => [$this, 'complete'],
                'permission_callback' => $permission,
            ]);
        });
    }

    public function canAccess(): bool
    {
        return $this->capabilities->currentUserCanAccess();
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            return $this->responses->success($this->data(get_current_user_id(), (int) $request['id']));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function saveDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $userId = get_current_user_id();
            $bookId = (int) $request['id'];
            $workspace = $this->library->workspaceForBook($userId, $bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];

            if (array_key_exists('title', $payload)) {
                $result = wp_update_post([
                    'ID' => $bookId,
                    'post_title' => sanitize_text_field((string) $payload['title']),
                ], true);
                if (is_wp_error($result)) {
                    throw new \RuntimeException('Não foi possível salvar o título provisório da obra.');
                }
            }

            foreach (self::META_KEYS as $field => $metaKey) {
                if (! array_key_exists($field, $payload)) {
                    continue;
                }

                if ($field === 'keywords') {
                    $raw = is_array($payload[$field]) ? $payload[$field] : explode(',', (string) $payload[$field]);
                    $seen = [];
                    $value = [];
                    foreach ($raw as $item) {
                        $clean = trim(sanitize_text_field((string) $item));
                        if ($clean === '') continue;
                        $index = strtolower(remove_accents($clean));
                        if (isset($seen[$index])) continue;
                        $seen[$index] = true;
                        $value[] = $clean;
                    }
                    update_post_meta($bookId, $metaKey, $value);
                    continue;
                }

                if (in_array($field, ['cover_position_x', 'cover_position_y'], true)) {
                    update_post_meta($bookId, $metaKey, max(0, min(100, (int) $payload[$field])));
                    continue;
                }

                update_post_meta($bookId, $metaKey, sanitize_textarea_field((string) $payload[$field]));
            }

            if (trim((string) get_post_meta($bookId, '_verbum_language', true)) === '') {
                update_post_meta($bookId, '_verbum_language', 'Português (BR)');
            }

            if (function_exists('clean_post_cache')) clean_post_cache($bookId);
            if (function_exists('wp_cache_delete')) wp_cache_delete($bookId, 'post_meta');

            return $this->responses->success($this->dataFromWorkspace($bookId, $workspace));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $userId = get_current_user_id();
            $bookId = (int) $request['id'];
            $data = $this->data($userId, $bookId);
            $pending = [];
            foreach (self::REQUIRED as $field => $label) {
                if (trim((string) ($data['book'][$this->camelCase($field)] ?? '')) === '') {
                    $pending[] = $label;
                }
            }
            if ($pending !== []) {
                throw new ValidationError('Complete a Identificação antes de continuar: ' . implode(', ', $pending) . '.');
            }

            if (trim((string) get_post_meta($bookId, '_verbum_language', true)) === '') {
                update_post_meta($bookId, '_verbum_language', 'Português (BR)');
            }

            return $this->responses->success($this->enrichWorkspace($bookId, $this->library->completeIdentification($userId, $bookId)));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    /** @return array<string, mixed> */
    private function data(int $userId, int $bookId): array
    {
        $manualStatus = trim((string) get_post_meta($bookId, '_verbum_workflow_status', true));
        $workspace = $this->library->workspaceForBook($userId, $bookId);
        if ($manualStatus !== '') {
            update_post_meta($bookId, '_verbum_workflow_status', $manualStatus);
        }
        return $this->enrichWorkspace($bookId, $workspace, $manualStatus);
    }

    /** @param array<string, mixed> $workspace
     *  @return array<string, mixed>
     */
    private function dataFromWorkspace(int $bookId, array $workspace): array
    {
        $post = get_post($bookId);
        if ($post instanceof \WP_Post) {
            $workspace['book']['title'] = get_the_title($post);
            $workspace['book']['updatedAt'] = mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified);
        }
        return $this->enrichWorkspace($bookId, $workspace);
    }

    /** @param array<string, mixed> $workspace
     *  @return array<string, mixed>
     */
    private function enrichWorkspace(int $bookId, array $workspace, string $manualStatus = ''): array
    {
        foreach (self::META_KEYS as $field => $metaKey) {
            $value = get_post_meta($bookId, $metaKey, true);
            $workspace['book'][$this->camelCase($field)] = $value;
        }
        if ($manualStatus !== '') {
            $workspace['book']['workflowStatus'] = $manualStatus;
        } elseif (isset($workspace['book']['workflow_status'])) {
            $workspace['book']['workflowStatus'] = $workspace['book']['workflow_status'];
        }
        return $workspace;
    }

    private function camelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
