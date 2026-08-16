<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkPlanningRepository;

final class WorkPlanningController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkPlanningRepository $planning;

    public function __construct(
        Config $config,
        ResponseFactory $responses,
        Capabilities $capabilities,
        LibraryRepository $library,
        WorkPlanningRepository $planning
    ) {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->planning = $planning;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];

            register_rest_route($namespace, '/books/(?P<id>\d+)/planning-stage', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/planning-stage/chapter-sync-preview', [
                'methods' => 'GET',
                'callback' => [$this, 'syncPreview'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/planning-stage/generate-chapters', [
                'methods' => 'POST',
                'callback' => [$this, 'generateChapters'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/planning-stage/complete', [
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

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->planning->data($bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $stage = $this->planning->save($bookId, $this->sanitizePayload($request));
            return $this->responses->success([
                'planningStage' => $stage,
                'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId),
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function syncPreview(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->planning->syncPreview($bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function generateChapters(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];
            $titleUpdates = is_array($payload['title_updates'] ?? null) ? $payload['title_updates'] : [];
            $options = [
                'confirmed' => (bool) ($payload['confirmed'] ?? false),
                'sync_order' => (bool) ($payload['sync_order'] ?? false),
                'title_updates' => array_values(array_unique(array_filter(array_map(static fn ($value): string => sanitize_key((string) $value), $titleUpdates)))),
            ];
            $stage = $this->planning->generateChapters(get_current_user_id(), $bookId, $options);
            return $this->responses->success([
                'planningStage' => $stage,
                'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId),
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $stage = $this->planning->complete($bookId);
            return $this->responses->success([
                'planningStage' => $stage,
                'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId),
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    private function assertOwned(int $bookId): void
    {
        $this->library->workspaceForBook(get_current_user_id(), $bookId);
    }

    /** @return array<string, mixed> */
    private function sanitizePayload(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : [];
        $clean = [];

        foreach ([
            'central_question', 'main_thesis', 'overview', 'methodology', 'presentation_form', 'approach',
            'general_structure', 'editorial_notes', 'writing_strategy', 'initial_schedule', 'limits',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = sanitize_textarea_field((string) $payload[$field]);
            }
        }

        foreach (['target_chapters', 'target_words', 'target_pages'] as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = max(0, (int) $payload[$field]);
            }
        }

        if (array_key_exists('keywords', $payload)) {
            $keywords = is_array($payload['keywords']) ? $payload['keywords'] : [];
            $clean['keywords'] = array_values(array_unique(array_filter(array_map(static fn ($value): string => sanitize_text_field((string) $value), $keywords))));
        }

        if (array_key_exists('structure_items', $payload)) {
            $items = is_array($payload['structure_items']) ? $payload['structure_items'] : [];
            $clean['structure_items'] = [];
            foreach ($items as $index => $item) {
                if (! is_array($item)) continue;
                $clean['structure_items'][] = [
                    'id' => sanitize_key((string) ($item['id'] ?? '')),
                    'type' => sanitize_key((string) ($item['type'] ?? 'chapter')),
                    'legacyType' => sanitize_key((string) ($item['legacyType'] ?? '')),
                    'title' => sanitize_text_field((string) ($item['title'] ?? '')),
                    'parentId' => sanitize_key((string) ($item['parentId'] ?? '')),
                    'group' => sanitize_key((string) ($item['group'] ?? '')),
                    'linkedChapterId' => preg_replace('/\D+/', '', (string) ($item['linkedChapterId'] ?? '')) ?: '',
                    'syncState' => sanitize_key((string) ($item['syncState'] ?? '')),
                    'order' => max(1, (int) ($item['order'] ?? ($index + 1))),
                ];
            }
        }

        return $clean;
    }
}
