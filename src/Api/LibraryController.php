<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
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
    private function sanitizeBookPayload(array $payload): array
    {
        $textFields = [
            'title', 'subtitle', 'series', 'category', 'genre', 'audience', 'age_range', 'language', 'country',
            'author_name', 'coauthor_name', 'keyword', 'target_date', 'workflow_status', 'collection', 'priority',
            'cover_url', 'color', 'icon',
        ];
        $longFields = ['main_objective', 'reader_problem', 'reader_transformation', 'proposal_summary', 'notes'];
        $numericFields = ['project_id', 'planned_chapters', 'word_goal'];

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
        if (array_key_exists('tags', $payload)) {
            $tags = is_array($payload['tags']) ? $payload['tags'] : explode(',', (string) $payload['tags']);
            $clean['tags'] = array_values(array_filter(array_map('sanitize_text_field', $tags)));
        }

        return $clean;
    }
}
