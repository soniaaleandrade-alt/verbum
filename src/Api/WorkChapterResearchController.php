<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkChapterResearchRepository;
use VerbumStudio\Library\WorkDevelopmentRepository;

final class WorkChapterResearchController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkDevelopmentRepository $development;
    private WorkChapterResearchRepository $research;

    public function __construct(
        Config $config,
        ResponseFactory $responses,
        Capabilities $capabilities,
        LibraryRepository $library,
        WorkDevelopmentRepository $development,
        WorkChapterResearchRepository $research
    ) {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->development = $development;
        $this->research = $research;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];
            $base = '/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/research';

            register_rest_route($namespace, $base, [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'saveState'], 'permission_callback' => $permission],
            ]);

            register_rest_route($namespace, $base . '/sources', [
                'methods' => 'POST',
                'callback' => [$this, 'createSource'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, $base . '/sources/(?P<source_id>\\d+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateSource'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteSource'], 'permission_callback' => $permission],
            ]);

            register_rest_route($namespace, $base . '/complete', [
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
            [$bookId, $chapterId] = $this->ids($request);
            $this->assertOwned($bookId);
            return $this->responses->success($this->research->data(get_current_user_id(), $bookId, $chapterId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function saveState(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            [$bookId, $chapterId] = $this->ids($request);
            $this->assertOwned($bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];
            $clean = [];
            if (array_key_exists('direction_reviewed', $payload)) $clean['direction_reviewed'] = (bool) $payload['direction_reviewed'];
            if (array_key_exists('reviewed_categories', $payload)) {
                $clean['reviewed_categories'] = is_array($payload['reviewed_categories']) ? $payload['reviewed_categories'] : [];
            }
            if (array_key_exists('ideas', $payload)) {
                $clean['ideas'] = $this->sanitizeIdeas(is_array($payload['ideas']) ? $payload['ideas'] : []);
            }
            $this->research->saveState(get_current_user_id(), $bookId, $chapterId, $clean);
            return $this->responses->success($this->mutationData($bookId, $chapterId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function createSource(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            [$bookId, $chapterId] = $this->ids($request);
            $this->assertOwned($bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];
            $source = $this->research->createSource(get_current_user_id(), $bookId, $chapterId, $this->sanitizeSource($payload));
            $data = $this->mutationData($bookId, $chapterId);
            $data['source'] = $source;
            return $this->responses->success($data);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function updateSource(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            [$bookId, $chapterId] = $this->ids($request);
            $sourceId = (int) $request['source_id'];
            $this->assertOwned($bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];
            $source = $this->research->updateSource(get_current_user_id(), $bookId, $chapterId, $sourceId, $this->sanitizeSource($payload, true));
            $data = $this->mutationData($bookId, $chapterId);
            $data['source'] = $source;
            return $this->responses->success($data);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function deleteSource(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            [$bookId, $chapterId] = $this->ids($request);
            $sourceId = (int) $request['source_id'];
            $this->assertOwned($bookId);
            $this->research->deleteSource(get_current_user_id(), $bookId, $chapterId, $sourceId);
            return $this->responses->success($this->mutationData($bookId, $chapterId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            [$bookId, $chapterId] = $this->ids($request);
            $this->assertOwned($bookId);
            $this->research->complete(get_current_user_id(), $bookId, $chapterId);
            return $this->responses->success($this->mutationData($bookId, $chapterId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    /** @return array{0:int,1:int} */
    private function ids(\WP_REST_Request $request): array
    {
        return [(int) $request['id'], (int) $request['chapter_id']];
    }

    private function assertOwned(int $bookId): void
    {
        $this->library->workspaceForBook(get_current_user_id(), $bookId);
    }

    /** @return array<string, mixed> */
    private function mutationData(int $bookId, int $chapterId): array
    {
        return [
            'research' => $this->research->data(get_current_user_id(), $bookId, $chapterId),
            'chapter' => $this->development->chapter(get_current_user_id(), $bookId, $chapterId),
            'developmentStage' => $this->development->data(get_current_user_id(), $bookId),
        ];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function sanitizeSource(array $payload, bool $partial = false): array
    {
        $clean = [];
        foreach (['category', 'title', 'author', 'reference', 'excerpt', 'notes', 'application', 'url', 'structure_item_id'] as $field) {
            if ($partial && ! array_key_exists($field, $payload)) continue;
            if (! array_key_exists($field, $payload) && $partial) continue;
            $value = $payload[$field] ?? '';
            $clean[$field] = in_array($field, ['excerpt', 'notes', 'application'], true)
                ? sanitize_textarea_field((string) $value)
                : ($field === 'url' ? esc_url_raw((string) $value) : sanitize_text_field((string) $value));
        }
        foreach (['highlighted', 'selected_for_writing'] as $field) {
            if (! $partial || array_key_exists($field, $payload)) $clean[$field] = (bool) ($payload[$field] ?? false);
        }
        if (! $partial || array_key_exists('tags', $payload)) {
            $clean['tags'] = is_array($payload['tags'] ?? null) ? $payload['tags'] : [];
        }
        if (! $partial || array_key_exists('details', $payload)) {
            $details = is_array($payload['details'] ?? null) ? $payload['details'] : [];
            $clean['details'] = [];
            foreach ($details as $key => $value) {
                $clean['details'][sanitize_key((string) $key)] = sanitize_text_field((string) $value);
            }
        }
        return $clean;
    }

    /** @param array<int, mixed> $ideas
     *  @return array<int, array<string, mixed>>
     */
    private function sanitizeIdeas(array $ideas): array
    {
        $clean = [];
        foreach ($ideas as $idea) {
            if (! is_array($idea)) continue;
            $tags = is_array($idea['tags'] ?? null) ? $idea['tags'] : [];
            $clean[] = [
                'id' => sanitize_key((string) ($idea['id'] ?? '')),
                'title' => sanitize_text_field((string) ($idea['title'] ?? '')),
                'description' => sanitize_textarea_field((string) ($idea['description'] ?? '')),
                'tags' => array_values(array_filter(array_map(static fn ($tag): string => sanitize_text_field((string) $tag), $tags))),
                'structureItemId' => sanitize_key((string) ($idea['structureItemId'] ?? ($idea['structure_item_id'] ?? ''))),
            ];
        }
        return $clean;
    }
}
