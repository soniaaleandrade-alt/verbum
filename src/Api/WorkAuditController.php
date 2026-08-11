<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkAuditRepository;

final class WorkAuditController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private WorkAuditRepository $audit;

    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library, WorkAuditRepository $audit)
    {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->audit = $audit;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];
            register_rest_route($namespace, '/books/(?P<id>\\d+)/audit-stage', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/audit-stage/findings', [
                'methods' => 'POST', 'callback' => [$this, 'createFinding'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/audit-stage/findings/(?P<finding_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateFinding'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteFinding'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/audit-stage/report', [
                'methods' => 'POST', 'callback' => [$this, 'report'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/audit-stage/assist', [
                'methods' => 'POST', 'callback' => [$this, 'assist'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\\d+)/audit-stage/complete', [
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
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->audit->data(get_current_user_id(), $bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $audit = $this->audit->saveState(get_current_user_id(), $bookId, $this->statePayload($request));
            return $this->responses->success(['auditStage' => $audit, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function createFinding(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $audit = $this->audit->createFinding(get_current_user_id(), $bookId, $this->findingPayload($request));
            return $this->responses->success(['auditStage' => $audit, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function updateFinding(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $audit = $this->audit->updateFinding(get_current_user_id(), $bookId, sanitize_key((string) $request['finding_id']), $this->findingPayload($request));
            return $this->responses->success(['auditStage' => $audit, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function deleteFinding(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $audit = $this->audit->deleteFinding(get_current_user_id(), $bookId, sanitize_key((string) $request['finding_id']));
            return $this->responses->success(['auditStage' => $audit, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function report(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $audit = $this->audit->generateReport(get_current_user_id(), $bookId);
            return $this->responses->success(['auditStage' => $audit, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $audit = $this->audit->complete(get_current_user_id(), $bookId);
            return $this->responses->success(['auditStage' => $audit, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function assist(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $key = trim((string) $this->config->get('openai_api_key', ''));
            if ($key === '') throw new ValidationError('O Assistente de Auditoria requer a configuração segura de VERBUM_OPENAI_API_KEY no servidor.');
            $json = $request->get_json_params();
            $json = is_array($json) ? $json : [];
            $action = sanitize_key((string) ($json['action'] ?? 'gaps'));
            $actions = [
                'gaps' => 'Procure possíveis lacunas editoriais ou trechos que merecem conferência.',
                'consistency' => 'Analise possíveis inconsistências de terminologia, estrutura e apresentação.',
                'terms' => 'Analise a consistência dos termos editoriais registrados.',
                'repetitions' => 'Identifique repetições suspeitas entre os resumos dos capítulos.',
                'markers' => 'Procure indícios de marcadores, rascunhos ou conteúdo ainda incompleto.',
                'structure' => 'Analise a integridade e progressão da estrutura da obra.',
                'checks' => 'Sugira itens objetivos que o autor deve conferir antes da aprovação editorial.',
                'doctrine' => 'Aponte apenas trechos que possam merecer conferência com fontes doutrinais já registradas; não declare aprovação doutrinal.',
            ];
            if (! isset($actions[$action])) $action = 'checks';
            $context = $this->audit->assistantContext(get_current_user_id(), $bookId);
            $input = "TAREFA\n" . $actions[$action]
                . "\n\nVERSÃO AUDITADA\n" . wp_json_encode($context['version'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\nCAPÍTULOS RESUMIDOS\n" . wp_json_encode($context['chapters'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\nTERMINOLOGIA\n" . wp_json_encode($context['terminology'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\nACHADOS JÁ REGISTRADOS\n" . wp_json_encode($context['existingFindings'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (strlen($input) > 50000) $input = substr($input, 0, 50000);
            $response = wp_remote_post('https://api.openai.com/v1/responses', [
                'timeout' => 60,
                'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'model' => 'gpt-5.6-luna',
                    'instructions' => 'Você é o Assistente de Auditoria do Verbum Studio. Trabalhe somente com o contexto fornecido. Gere achados objetivos para conferência humana. Não altere a obra, não invente fontes, citações, passagens bíblicas, documentos, autores, referências bibliográficas ou fatos. Não declare a obra aprovada e não declare aprovação doutrinal. Se houver incerteza, sinalize-a. Responda em português com observações curtas e acionáveis.',
                    'input' => $input,
                    'max_output_tokens' => 1600,
                ]),
            ]);
            if (is_wp_error($response)) throw new ValidationError('Não foi possível acessar o Assistente de Auditoria neste momento.');
            $status = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || ! is_array($body)) throw new ValidationError('O Assistente de Auditoria não conseguiu gerar uma análise. Tente novamente.');
            $text = '';
            foreach ((array) ($body['output'] ?? []) as $item) {
                if (! is_array($item) || ($item['type'] ?? '') !== 'message') continue;
                foreach ((array) ($item['content'] ?? []) as $content) if (is_array($content) && ($content['type'] ?? '') === 'output_text') $text .= (string) ($content['text'] ?? '');
            }
            $text = trim($text);
            if ($text === '') throw new ValidationError('O Assistente de Auditoria retornou uma análise vazia.');
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
    private function statePayload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        $json = is_array($json) ? $json : [];
        $clean = [];
        if (array_key_exists('flags', $json)) $clean['flags'] = is_array($json['flags']) ? $json['flags'] : [];
        if (array_key_exists('final_confirmation', $json)) $clean['final_confirmation'] = (bool) $json['final_confirmation'];
        return $clean;
    }

    /** @return array<string, mixed> */
    private function findingPayload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        $json = is_array($json) ? $json : [];
        $clean = [];
        foreach (['category', 'severity', 'description', 'recommendation', 'status', 'justification'] as $field) if (array_key_exists($field, $json)) $clean[$field] = (string) $json[$field];
        if (array_key_exists('chapter_id', $json)) $clean['chapter_id'] = (string) $json['chapter_id'];
        return $clean;
    }
}
