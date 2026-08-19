<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkGeneralReviewRepository;

final class WorkGeneralReviewController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkGeneralReviewRepository $review;

    public function __construct(
        Config $config,
        ResponseFactory $responses,
        Capabilities $capabilities,
        LibraryRepository $library,
        WorkGeneralReviewRepository $review
    ) {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->review = $review;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];
            register_rest_route($namespace, '/books/(?P<id>\\d+)/general-review', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/general-review/reading', [
                'methods' => 'GET', 'callback' => [$this, 'reading'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/general-review/issues', [
                'methods' => 'POST', 'callback' => [$this, 'createIssue'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/general-review/issues/(?P<issue_id>[a-zA-Z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateIssue'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteIssue'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/general-review/complete', [
                'methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/general-review/assist', [
                'methods' => 'POST', 'callback' => [$this, 'assist'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/general-review/substeps/(?P<substep>[a-z-]+)/complete', ['methods'=>'POST','callback'=>[$this,'completeSubstep'],'permission_callback'=>$permission]);
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
            return $this->responses->success($this->review->data(get_current_user_id(), $bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $review = $this->review->save(get_current_user_id(), $bookId, $this->payload($request));
            return $this->responses->success(['generalReview' => $review, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function reading(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->review->reading(get_current_user_id(), $bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function createIssue(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $review = $this->review->createIssue(get_current_user_id(), $bookId, $this->issuePayload($request));
            return $this->responses->success(['generalReview' => $review, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function updateIssue(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $review = $this->review->updateIssue(get_current_user_id(), $bookId, sanitize_key((string) $request['issue_id']), $this->issuePayload($request));
            return $this->responses->success(['generalReview' => $review, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function deleteIssue(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $review = $this->review->deleteIssue(get_current_user_id(), $bookId, sanitize_key((string) $request['issue_id']));
            return $this->responses->success(['generalReview' => $review, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $review = $this->review->complete(get_current_user_id(), $bookId);
            return $this->responses->success(['generalReview' => $review, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function completeSubstep(\WP_REST_Request$request):\WP_REST_Response{try{$id=(int)$request['id'];$this->assertOwned($id);$review=$this->review->completeSubstep(get_current_user_id(),$id,sanitize_key((string)$request['substep']));return$this->responses->success(['generalReview'=>$review,'workspace'=>$this->library->workspaceForBook(get_current_user_id(),$id)]);}catch(\Throwable$e){return$this->responses->error($e);}}

    public function assist(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $key = trim((string) $this->config->get('openai_api_key', ''));
            if ($key === '') throw new ValidationError('O Assistente de Revisão Geral requer a configuração segura de VERBUM_OPENAI_API_KEY no servidor.');
            $json = $request->get_json_params();
            $json = is_array($json) ? $json : [];
            $action = sanitize_key((string) ($json['action'] ?? 'coherence'));
            $actions = [
                'coherence' => 'Analise a coerência global e a linha lógica entre os capítulos.',
                'repetitions' => 'Identifique possíveis repetições temáticas ou argumentativas entre capítulos.',
                'gaps' => 'Identifique possíveis lacunas de conteúdo em relação ao objetivo, pergunta central e tese.',
                'progression' => 'Avalie a progressão dos capítulos e se a ordem conduz o leitor de forma consistente.',
                'transitions' => 'Avalie as transições entre capítulos e indique onde a continuidade pode ser fortalecida.',
                'objective' => 'Compare o conjunto da obra com o objetivo geral e a transformação pretendida.',
                'thesis' => 'Compare o conjunto da obra com a tese principal e indique pontos de maior ou menor sustentação.',
                'language' => 'Analise a uniformidade de linguagem, tom e terminologia entre os capítulos.',
            ];
            if (! isset($actions[$action])) $action = 'coherence';
            $context = $this->review->assistantContext(get_current_user_id(), $bookId);
            $input = "TAREFA\n" . $actions[$action]
                . "\n\nDIREÇÃO DA OBRA\n" . wp_json_encode($context['direction'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\nRESUMOS E ESTRUTURAS DOS CAPÍTULOS\n" . wp_json_encode($context['chapters'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\nTRANSIÇÕES REGISTRADAS\n" . wp_json_encode($context['transitions'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\nTERMINOLOGIA REGISTRADA\n" . wp_json_encode($context['terms'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (strlen($input) > 50000) $input = substr($input, 0, 50000);
            $response = wp_remote_post('https://api.openai.com/v1/responses', [
                'timeout' => 60,
                'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'model' => 'gpt-5.6-luna',
                    'instructions' => 'Você é o Assistente de Revisão Geral do Verbum Studio. Analise apenas o contexto fornecido. O contexto usa resumos estruturados dos capítulos, não o livro inteiro. Não invente citações, passagens bíblicas, documentos, autores, referências bibliográficas, dados ou fatos. Não declare aprovação doutrinal. Se faltar evidência para uma conclusão, sinalize a limitação. Responda em português com observações objetivas e acionáveis, sem alterar automaticamente o texto do autor.',
                    'input' => $input,
                    'max_output_tokens' => 1800,
                ]),
            ]);
            if (is_wp_error($response)) throw new ValidationError('Não foi possível acessar o Assistente de Revisão Geral neste momento.');
            $status = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || ! is_array($body)) throw new ValidationError('O Assistente de Revisão Geral não conseguiu gerar uma análise. Tente novamente.');
            $text = '';
            foreach ((array) ($body['output'] ?? []) as $item) {
                if (! is_array($item) || ($item['type'] ?? '') !== 'message') continue;
                foreach ((array) ($item['content'] ?? []) as $content) {
                    if (is_array($content) && ($content['type'] ?? '') === 'output_text') $text .= (string) ($content['text'] ?? '');
                }
            }
            $text = trim($text);
            if ($text === '') throw new ValidationError('O Assistente de Revisão Geral retornou uma análise vazia.');
            return $this->responses->success(['suggestion' => $text, 'action' => $action]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    private function assertOwned(int $bookId): void
    {
        $this->library->workspaceForBook(get_current_user_id(), $bookId);
    }

    /** @return array<string, mixed> */
    private function payload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        $json = is_array($json) ? $json : [];
        $clean = [];
        foreach (['flags', 'evaluations', 'transitions', 'terms', 'front_matter'] as $field) {
            if (array_key_exists($field, $json)) $clean[$field] = is_array($json[$field]) ? $json[$field] : [];
        }
        if (array_key_exists('final_confirmation', $json)) $clean['final_confirmation'] = (bool) $json['final_confirmation'];
        if (array_key_exists('save_mode', $json)) $clean['save_mode'] = sanitize_key((string) $json['save_mode']);
        return $clean;
    }

    /** @return array<string, mixed> */
    private function issuePayload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        $json = is_array($json) ? $json : [];
        $clean = [];
        foreach (['type', 'description', 'priority', 'status'] as $field) if (array_key_exists($field, $json)) $clean[$field] = (string) $json[$field];
        if (array_key_exists('chapter_id', $json)) $clean['chapter_id'] = (string) $json['chapter_id'];
        return $clean;
    }
}
