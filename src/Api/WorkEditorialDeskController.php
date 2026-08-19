<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkEditorialDeskRepository;

final class WorkEditorialDeskController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkEditorialDeskRepository $editorial;

    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library, WorkEditorialDeskRepository $editorial)
    {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->editorial = $editorial;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];
            register_rest_route($namespace, '/books/(?P<id>\\d+)/editorial-desk', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/editorial-desk/adjustments', [
                'methods' => 'POST', 'callback' => [$this, 'createAdjustment'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/editorial-desk/adjustments/(?P<adjustment_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateAdjustment'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteAdjustment'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/editorial-desk/assist', [
                'methods' => 'POST', 'callback' => [$this, 'assist'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/editorial-desk/complete', [
                'methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/editorial-desk/preparation', [
                'methods' => 'POST', 'callback' => [$this, 'preparationAction'], 'permission_callback' => $permission,
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
            return $this->responses->success($this->editorial->data(get_current_user_id(), $bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $json = $request->get_json_params();
            $json = is_array($json) ? $json : [];
            $payload = [];
            if (array_key_exists('fields', $json)) $payload['fields'] = is_array($json['fields']) ? $json['fields'] : [];
            if (array_key_exists('flags', $json)) $payload['flags'] = is_array($json['flags']) ? $json['flags'] : [];
            if (array_key_exists('assessments', $json)) $payload['assessments'] = is_array($json['assessments']) ? $json['assessments'] : [];
            if (array_key_exists('final_confirmation', $json)) $payload['final_confirmation'] = (bool) $json['final_confirmation'];
            $stage = $this->editorial->saveState(get_current_user_id(), $bookId, $payload);
            return $this->responses->success(['editorialDesk' => $stage, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function createAdjustment(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $stage = $this->editorial->createAdjustment(get_current_user_id(), $bookId, $this->adjustmentPayload($request));
            return $this->responses->success(['editorialDesk' => $stage, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function updateAdjustment(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $stage = $this->editorial->updateAdjustment(get_current_user_id(), $bookId, sanitize_key((string) $request['adjustment_id']), $this->adjustmentPayload($request));
            return $this->responses->success(['editorialDesk' => $stage, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function deleteAdjustment(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $stage = $this->editorial->deleteAdjustment(get_current_user_id(), $bookId, sanitize_key((string) $request['adjustment_id']));
            return $this->responses->success(['editorialDesk' => $stage, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $stage = $this->editorial->complete(get_current_user_id(), $bookId);
            return $this->responses->success(['editorialDesk' => $stage, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function preparationAction(\WP_REST_Request $request): \WP_REST_Response
    {
        try{$bookId=(int)$request['id'];$this->assertOwned($bookId);$json=$request->get_json_params();$stage=$this->editorial->preparationAction(get_current_user_id(),$bookId,is_array($json)?$json:[]);return$this->responses->success(['editorialDesk'=>$stage,'workspace'=>$this->library->workspaceForBook(get_current_user_id(),$bookId)]);}catch(\Throwable$exception){return$this->responses->error($exception);}
    }

    public function assist(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $key = trim((string) $this->config->get('openai_api_key', ''));
            if ($key === '') throw new ValidationError('O Assistente Editorial requer a configuração segura de VERBUM_OPENAI_API_KEY no servidor.');
            $json = $request->get_json_params();
            $json = is_array($json) ? $json : [];
            $action = sanitize_key((string) ($json['action'] ?? 'positioning'));
            $actions = [
                'positioning' => 'Analise o posicionamento editorial e sugira melhorias objetivas.',
                'title' => 'Avalie título e subtítulo e proponha alternativas coerentes com a obra.',
                'synopsis' => 'Aprimore a sinopse curta e a sinopse editorial sem inventar conteúdo.',
                'back_cover' => 'Sugira uma quarta capa clara, atraente e fiel à obra.',
                'audience' => 'Analise a adequação da proposta ao público-alvo informado.',
                'cover_brief' => 'Sugira um briefing de capa coerente com posicionamento, público e conteúdo.',
                'layout_brief' => 'Sugira um briefing de diagramação coerente com gênero, público e leitura.',
                'opinion' => 'Ajude a estruturar um parecer editorial com pontos fortes, atenção, recomendações, riscos e conclusão.',
            ];
            if (! isset($actions[$action])) $action = 'positioning';
            $context = $this->editorial->assistantContext(get_current_user_id(), $bookId);
            $input = "TAREFA\n" . $actions[$action] . "\n\nCONTEXTO EDITORIAL\n" . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (strlen($input) > 50000) $input = substr($input, 0, 50000);
            $response = wp_remote_post('https://api.openai.com/v1/responses', [
                'timeout' => 60,
                'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'model' => 'gpt-5.6-luna',
                    'instructions' => 'Você é o Assistente Editorial do Verbum Studio. Trabalhe somente com o contexto fornecido. Não altere dados automaticamente, não invente conteúdo da obra, fontes, citações, fatos, credenciais ou aprovações. Gere sugestões editoriais em português para decisão humana. Quando houver incerteza, indique-a.',
                    'input' => $input,
                    'max_output_tokens' => 1800,
                ]),
            ]);
            if (is_wp_error($response)) throw new ValidationError('Não foi possível acessar o Assistente Editorial neste momento.');
            $status = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || ! is_array($body)) throw new ValidationError('O Assistente Editorial não conseguiu gerar uma sugestão. Tente novamente.');
            $text = '';
            foreach ((array) ($body['output'] ?? []) as $item) {
                if (! is_array($item) || ($item['type'] ?? '') !== 'message') continue;
                foreach ((array) ($item['content'] ?? []) as $content) if (is_array($content) && ($content['type'] ?? '') === 'output_text') $text .= (string) ($content['text'] ?? '');
            }
            $text = trim($text);
            if ($text === '') throw new ValidationError('O Assistente Editorial retornou uma sugestão vazia.');
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
    private function adjustmentPayload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        $json = is_array($json) ? $json : [];
        $clean = [];
        foreach (['type', 'priority', 'description', 'responsible', 'status', 'justification'] as $field) if (array_key_exists($field, $json)) $clean[$field] = (string) $json[$field];
        if (array_key_exists('chapter_id', $json)) $clean['chapter_id'] = (string) $json['chapter_id'];
        return $clean;
    }
}
