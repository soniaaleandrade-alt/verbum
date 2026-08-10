<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkChapterPreparationRepository;
use VerbumStudio\Library\WorkDevelopmentRepository;

final class WorkChapterPreparationController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkDevelopmentRepository $development;
    private WorkChapterPreparationRepository $preparation;

    public function __construct(
        Config $config,
        ResponseFactory $responses,
        Capabilities $capabilities,
        LibraryRepository $library,
        WorkDevelopmentRepository $development,
        WorkChapterPreparationRepository $preparation
    ) {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->development = $development;
        $this->preparation = $preparation;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];

            register_rest_route($namespace, '/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/preparation', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/preparation/complete', [
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
            $chapterId = (int) $request['chapter_id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->preparation->data(get_current_user_id(), $bookId, $chapterId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $chapterId = (int) $request['chapter_id'];
            $this->assertOwned($bookId);
            $preparation = $this->preparation->save(get_current_user_id(), $bookId, $chapterId, $this->sanitizePayload($request));
            return $this->responses->success([
                'preparation' => $preparation,
                'chapter' => $this->development->chapter(get_current_user_id(), $bookId, $chapterId),
                'developmentStage' => $this->development->data(get_current_user_id(), $bookId),
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $chapterId = (int) $request['chapter_id'];
            $this->assertOwned($bookId);
            $preparation = $this->preparation->complete(get_current_user_id(), $bookId, $chapterId);
            return $this->responses->success([
                'preparation' => $preparation,
                'chapter' => $this->development->chapter(get_current_user_id(), $bookId, $chapterId),
                'developmentStage' => $this->development->data(get_current_user_id(), $bookId),
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
            'subtitle', 'objective', 'central_question', 'purpose', 'thesis', 'main_message', 'guiding_phrase',
            'spiritual_intention', 'virtue', 'writing_prayer', 'notes',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = sanitize_textarea_field((string) $payload[$field]);
            }
        }

        if (array_key_exists('keywords', $payload)) {
            $clean['keywords'] = is_array($payload['keywords']) ? $payload['keywords'] : [];
        }
        if (array_key_exists('source_categories', $payload)) {
            $clean['source_categories'] = is_array($payload['source_categories']) ? $payload['source_categories'] : [];
        }
        if (array_key_exists('structure_items', $payload)) {
            $clean['structure_items'] = [];
            foreach (is_array($payload['structure_items']) ? $payload['structure_items'] : [] as $index => $item) {
                if (! is_array($item)) continue;
                $clean['structure_items'][] = [
                    'id' => sanitize_key((string) ($item['id'] ?? '')),
                    'text' => sanitize_text_field((string) ($item['text'] ?? '')),
                    'order' => max(1, (int) ($item['order'] ?? ($index + 1))),
                ];
            }
        }

        return $clean;
    }
}
