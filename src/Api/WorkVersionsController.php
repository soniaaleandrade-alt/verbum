<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkVersionsRepository;

final class WorkVersionsController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkVersionsRepository $versions;

    public function __construct(
        Config $config,
        ResponseFactory $responses,
        Capabilities $capabilities,
        LibraryRepository $library,
        WorkVersionsRepository $versions
    ) {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->versions = $versions;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];
            register_rest_route($namespace, '/books/(?P<id>\\d+)/versions-stage', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'saveState'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/versions-stage/versions', [
                'methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/versions-stage/versions/(?P<version_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'GET', 'callback' => [$this, 'version'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'updateVersion'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteVersion'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/versions-stage/versions/(?P<version_id>[A-Za-z0-9_-]+)/duplicate', [
                'methods' => 'POST', 'callback' => [$this, 'duplicate'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/versions-stage/versions/(?P<version_id>[A-Za-z0-9_-]+)/restore', [
                'methods' => 'POST', 'callback' => [$this, 'restore'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/versions-stage/versions/(?P<version_id>[A-Za-z0-9_-]+)/audit-baseline', [
                'methods' => 'POST', 'callback' => [$this, 'selectAudit'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/versions-stage/compare', [
                'methods' => 'POST', 'callback' => [$this, 'compare'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/versions-stage/complete', [
                'methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission,
            ]);
        });
    }

    public function canAccess(): bool
    {
        return $this->capabilities->currentUserCanAccess();
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->respond(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->versions->data(get_current_user_id(), $bookId);
        });
    }

    public function saveState(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->mutation(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $json = $this->json($request);
            return $this->versions->saveState(get_current_user_id(), $bookId, ['flags' => is_array($json['flags'] ?? null) ? $json['flags'] : []]);
        }, (int) $request['id']);
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->mutation(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $json = $this->json($request);
            return $this->versions->create(get_current_user_id(), $bookId, [
                'name' => (string) ($json['name'] ?? ''),
                'type' => (string) ($json['type'] ?? 'milestone'),
                'notes' => (string) ($json['notes'] ?? ''),
                'protected' => (bool) ($json['protected'] ?? false),
                'major' => (bool) ($json['major'] ?? false),
                'force' => (bool) ($json['force'] ?? false),
                'audit_baseline' => (bool) ($json['audit_baseline'] ?? false),
            ]);
        }, (int) $request['id']);
    }

    public function version(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->respond(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->versions->version(get_current_user_id(), $bookId, sanitize_key((string) $request['version_id']));
        });
    }

    public function updateVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->mutation(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $json = $this->json($request);
            $fields = [];
            if (array_key_exists('name', $json)) $fields['name'] = (string) $json['name'];
            if (array_key_exists('notes', $json)) $fields['notes'] = (string) $json['notes'];
            if (array_key_exists('protected', $json)) $fields['protected'] = (bool) $json['protected'];
            return $this->versions->updateVersion(get_current_user_id(), $bookId, sanitize_key((string) $request['version_id']), $fields);
        }, (int) $request['id']);
    }

    public function deleteVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->mutation(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->versions->deleteVersion(get_current_user_id(), $bookId, sanitize_key((string) $request['version_id']));
        }, (int) $request['id']);
    }

    public function duplicate(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->mutation(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $json = $this->json($request);
            return $this->versions->duplicate(get_current_user_id(), $bookId, sanitize_key((string) $request['version_id']), (string) ($json['name'] ?? ''), (string) ($json['notes'] ?? ''));
        }, (int) $request['id']);
    }

    public function restore(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->mutation(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->versions->restore(get_current_user_id(), $bookId, sanitize_key((string) $request['version_id']));
        }, (int) $request['id']);
    }

    public function selectAudit(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->mutation(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->versions->selectAudit(get_current_user_id(), $bookId, sanitize_key((string) $request['version_id']));
        }, (int) $request['id']);
    }

    public function compare(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->respond(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $json = $this->json($request);
            return $this->versions->compare(get_current_user_id(), $bookId, sanitize_key((string) ($json['from_id'] ?? '')), sanitize_key((string) ($json['to_id'] ?? '')));
        });
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->mutation(function () use ($request): array {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->versions->complete(get_current_user_id(), $bookId);
        }, (int) $request['id']);
    }

    private function assertOwned(int $bookId): void
    {
        $this->library->workspaceForBook(get_current_user_id(), $bookId);
    }

    /** @return array<string, mixed> */
    private function json(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        return is_array($json) ? $json : [];
    }

    private function respond(callable $callback): \WP_REST_Response
    {
        try {
            return $this->responses->success($callback());
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    private function mutation(callable $callback, int $bookId): \WP_REST_Response
    {
        try {
            $stage = $callback();
            return $this->responses->success(['versionsStage' => $stage, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }
}
