<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkDevelopmentRepository;

final class WorkDevelopmentController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkDevelopmentRepository $development;

    public function __construct(
        Config $config,
        ResponseFactory $responses,
        Capabilities $capabilities,
        LibraryRepository $library,
        WorkDevelopmentRepository $development
    ) {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->development = $development;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];

            register_rest_route($namespace, '/books/(?P<id>\\d+)/development-stage', [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\\d+)/development-stage/complete', [
                'methods' => 'POST',
                'callback' => [$this, 'complete'],
                'permission_callback' => $permission,
            ]);

            register_rest_route($namespace, '/books/(?P<id>\\d+)/development-stage/structure-preview', ['methods'=>'GET','callback'=>[$this,'preview'],'permission_callback'=>$permission]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/development-stage/structure-sync', ['methods'=>'POST','callback'=>[$this,'synchronize'],'permission_callback'=>$permission]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/development-stage/order', ['methods'=>'PATCH','callback'=>[$this,'saveOrder'],'permission_callback'=>$permission]);

            register_rest_route($namespace, '/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'chapter'],
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
            return $this->responses->success($this->development->data(get_current_user_id(), $bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function chapter(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $chapterId = (int) $request['chapter_id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->development->chapter(get_current_user_id(), $bookId, $chapterId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $stage = $this->development->complete(get_current_user_id(), $bookId);
            return $this->responses->success([
                'developmentStage' => $stage,
                'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId),
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function preview(\WP_REST_Request $request):\WP_REST_Response{try{$id=(int)$request['id'];$this->assertOwned($id);return$this->responses->success($this->development->syncPreview(get_current_user_id(),$id));}catch(\Throwable $e){return$this->responses->error($e);}}
    public function synchronize(\WP_REST_Request $request):\WP_REST_Response{try{$id=(int)$request['id'];$this->assertOwned($id);$payload=$request->get_json_params();return$this->responses->success($this->development->synchronize(get_current_user_id(),$id,is_array($payload)?$payload:[]));}catch(\Throwable $e){return$this->responses->error($e);}}
    public function saveOrder(\WP_REST_Request $request):\WP_REST_Response{try{$id=(int)$request['id'];$this->assertOwned($id);$payload=$request->get_json_params();return$this->responses->success($this->development->saveOrder(get_current_user_id(),$id,is_array($payload['chapter_ids']??null)?$payload['chapter_ids']:[]));}catch(\Throwable $e){return$this->responses->error($e);}}

    private function assertOwned(int $bookId): void
    {
        $this->library->workspaceForBook(get_current_user_id(), $bookId);
    }
}
