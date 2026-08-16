<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkProjectRepository;

final class WorkProjectController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkProjectRepository $projects;

    public function __construct(
        Config $config,
        ResponseFactory $responses,
        Capabilities $capabilities,
        LibraryRepository $library,
        WorkProjectRepository $projects
    ) {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->projects = $projects;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];

            register_rest_route($namespace, '/books/(?P<id>\d+)/project-stage', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);

            register_rest_route($namespace, '/books/(?P<id>\d+)/project-stage/complete', [
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
            return $this->responses->success($this->projects->data($bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $stage = $this->projects->save($bookId, $this->sanitizePayload($request));

            return $this->responses->success([
                'projectStage' => $stage,
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
            $stage = $this->projects->complete($bookId);

            return $this->responses->success([
                'projectStage' => $stage,
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
            'theme', 'general_objective', 'purpose', 'audience', 'secondary_audience', 'reader_need',
            'benefits', 'transformation', 'central_message', 'differentials', 'value_proposition',
            'limits', 'motivation', 'verse', 'guiding_phrase', 'central_question', 'main_thesis',
            'overview', 'methodology', 'presentation_form', 'approach', 'audience_main',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = sanitize_textarea_field((string) $payload[$field]);
            }
        }

        if (array_key_exists('keywords', $payload)) {
            $keywords = is_array($payload['keywords']) ? $payload['keywords'] : [];
            $clean['keywords'] = array_values(array_filter(array_map(
                static fn ($item): string => sanitize_text_field((string) $item),
                $keywords
            ), static fn (string $item): bool => trim($item) !== ''));
        }

        foreach (['benefits_consolidated', 'value_proposition_consolidated'] as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = (bool) $payload[$field];
            }
        }

        if (array_key_exists('specific_objectives', $payload)) {
            $objectives = is_array($payload['specific_objectives']) ? $payload['specific_objectives'] : [];
            $clean['specific_objectives'] = [];
            foreach ($objectives as $index => $objective) {
                if (! is_array($objective)) {
                    continue;
                }
                $clean['specific_objectives'][] = [
                    'id' => sanitize_key((string) ($objective['id'] ?? '')),
                    'text' => sanitize_textarea_field((string) ($objective['text'] ?? '')),
                    'order' => max(1, (int) ($objective['order'] ?? ($index + 1))),
                ];
            }
        }

        return $clean;
    }
}
