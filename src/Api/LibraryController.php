<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\LibraryRepository;

final class LibraryController
{
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

            register_rest_route($namespace, '/library', [
                'methods' => 'GET',
                'callback' => [$this, 'library'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/projects', [
                'methods' => 'POST',
                'callback' => [$this, 'createProject'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/projects/(?P<id>\d+)', [
                'methods' => 'PATCH',
                'callback' => [$this, 'updateProject'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/projects/(?P<id>\d+)/archive', [
                'methods' => 'POST',
                'callback' => [$this, 'archiveProject'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books', [
                'methods' => 'POST',
                'callback' => [$this, 'createBook'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)', [
                'methods' => 'PATCH',
                'callback' => [$this, 'updateBook'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/workspace', [
                'methods' => 'GET',
                'callback' => [$this, 'workspace'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/identification', [
                'methods' => 'PATCH',
                'callback' => [$this, 'saveIdentification'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/identification/complete', [
                'methods' => 'POST',
                'callback' => [$this, 'completeIdentification'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/cover', [
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'uploadBookCover'],
                    'permission_callback' => $permission,
                ],
                [
                    'methods' => 'DELETE',
                    'callback' => [$this, 'removeBookCover'],
                    'permission_callback' => $permission,
                ],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/archive', [
                'methods' => 'POST',
                'callback' => [$this, 'archiveBook'],
                'permission_callback' => $permission,
            ]);
        });
    }

    public function canAccess(): bool
    {
        return $this->capabilities->currentUserCanAccess();
    }

    public function library(): \WP_REST_Response
    {
        try {
            return $this->responses->success($this->library->libraryForUser(get_current_user_id()));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function workspace(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            return $this->responses->success(
                $this->library->workspaceForBook(get_current_user_id(), (int) $request['id'])
            );
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function saveIdentification(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $payload = $this->sanitizeIdentificationPayload($this->payload($request));
            return $this->responses->success(
                $this->library->saveIdentification(get_current_user_id(), (int) $request['id'], $payload)
            );
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function completeIdentification(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            return $this->responses->success(
                $this->library->completeIdentification(get_current_user_id(), (int) $request['id'])
            );
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function uploadBookCover(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $userId = get_current_user_id();
            $this->library->workspaceForBook($userId, $bookId);

            $files = $request->get_file_params();
            $file = $files['cover'] ?? null;
            if (! is_array($file) || empty($file['tmp_name']) || empty($file['name'])) {
                throw new ValidationError('Selecione uma imagem para a capa da obra.');
            }

            if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new ValidationError('Não foi possível receber o arquivo da capa.');
            }

            $maximum = 10 * 1024 * 1024;
            if (function_exists('wp_max_upload_size')) {
                $maximum = min($maximum, (int) wp_max_upload_size());
            }
            if ((int) ($file['size'] ?? 0) > $maximum) {
                throw new ValidationError('A capa deve ter no máximo 10 MB e respeitar o limite de upload do WordPress.');
            }

            $allowed = [
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
            ];
            $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                throw new ValidationError('Use uma imagem JPG, JPEG, PNG ou WebP para a capa.');
            }

            if (! function_exists('wp_handle_sideload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if (! function_exists('wp_generate_attachment_metadata')) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }

            $handled = wp_handle_sideload($file, [
                'test_form' => false,
                'mimes' => $allowed,
            ]);
            if (! is_array($handled) || isset($handled['error'])) {
                throw new ValidationError((string) ($handled['error'] ?? 'Não foi possível salvar a capa.'));
            }

            $attachmentId = wp_insert_attachment([
                'post_mime_type' => (string) ($handled['type'] ?? ''),
                'post_title' => sanitize_text_field((string) pathinfo((string) $file['name'], PATHINFO_FILENAME)),
                'post_status' => 'inherit',
                'post_author' => $userId,
            ], (string) $handled['file'], $bookId, true);

            if (is_wp_error($attachmentId)) {
                throw new \RuntimeException('Não foi possível registrar a capa na biblioteca de mídia.');
            }

            $metadata = wp_generate_attachment_metadata((int) $attachmentId, (string) $handled['file']);
            if (is_array($metadata)) {
                wp_update_attachment_metadata((int) $attachmentId, $metadata);
            }

            $url = wp_get_attachment_url((int) $attachmentId);
            if (! is_string($url) || $url === '') {
                $url = (string) ($handled['url'] ?? '');
            }

            return $this->responses->success(
                $this->library->setBookCover($userId, $bookId, (int) $attachmentId, $url)
            );
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function removeBookCover(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            return $this->responses->success(
                $this->library->removeBookCover(get_current_user_id(), (int) $request['id'])
            );
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function createProject(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $payload = $this->payload($request);
            $project = $this->library->createProject(
                get_current_user_id(),
                sanitize_text_field((string) ($payload['name'] ?? '')),
                sanitize_textarea_field((string) ($payload['description'] ?? ''))
            );

            return $this->responses->success($project, 201);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function updateProject(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $payload = $this->payload($request);
            $project = $this->library->updateProject(
                get_current_user_id(),
                (int) $request['id'],
                sanitize_text_field((string) ($payload['name'] ?? '')),
                sanitize_textarea_field((string) ($payload['description'] ?? ''))
            );

            return $this->responses->success($project);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function archiveProject(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            return $this->responses->success(
                $this->library->archiveProject(get_current_user_id(), (int) $request['id'])
            );
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function createBook(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $payload = $this->sanitizeBookPayload($this->payload($request));
            $book = $this->library->createBook(
                get_current_user_id(),
                (int) ($payload['project_id'] ?? 0),
                $payload
            );

            return $this->responses->success($book, 201);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function updateBook(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $book = $this->library->updateBook(
                get_current_user_id(),
                (int) $request['id'],
                $this->sanitizeBookPayload($this->payload($request))
            );

            return $this->responses->success($book);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function archiveBook(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            return $this->responses->success(
                $this->library->archiveBook(get_current_user_id(), (int) $request['id'])
            );
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    /** @return array<string, mixed> */
    private function payload(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        return is_array($payload) ? $payload : [];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function sanitizeIdentificationPayload(array $payload): array
    {
        $clean = [];
        foreach (['title', 'subtitle', 'workflow_status', 'genre', 'language', 'audience'] as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = sanitize_text_field((string) $payload[$field]);
            }
        }
        if (array_key_exists('synopsis', $payload)) {
            $clean['synopsis'] = sanitize_textarea_field((string) $payload['synopsis']);
        }
        if (array_key_exists('keywords', $payload)) {
            $keywords = is_array($payload['keywords']) ? $payload['keywords'] : explode(',', (string) $payload['keywords']);
            $clean['keywords'] = array_values(array_unique(array_filter(array_map('sanitize_text_field', $keywords))));
        }
        if (array_key_exists('color', $payload)) {
            $color = sanitize_text_field((string) $payload['color']);
            $clean['color'] = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '';
        }

        return $clean;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function sanitizeBookPayload(array $payload): array
    {
        $textFields = [
            'title', 'subtitle', 'series', 'category', 'genre', 'audience', 'age_range', 'language', 'country',
            'author_name', 'coauthor_name', 'keyword', 'target_date', 'workflow_status', 'collection', 'priority',
            'cover_url', 'color', 'icon',
        ];
        $longFields = ['main_objective', 'reader_problem', 'reader_transformation', 'proposal_summary', 'synopsis', 'notes'];
        $numericFields = ['project_id', 'planned_chapters', 'word_goal', 'cover_id'];

        $clean = [];
        foreach ($textFields as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = sanitize_text_field((string) $payload[$field]);
            }
        }
        foreach ($longFields as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = sanitize_textarea_field((string) $payload[$field]);
            }
        }
        foreach ($numericFields as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = max(0, (int) $payload[$field]);
            }
        }
        foreach (['tags', 'keywords'] as $arrayField) {
            if (array_key_exists($arrayField, $payload)) {
                $items = is_array($payload[$arrayField]) ? $payload[$arrayField] : explode(',', (string) $payload[$arrayField]);
                $clean[$arrayField] = array_values(array_filter(array_map('sanitize_text_field', $items)));
            }
        }

        return $clean;
    }
}
