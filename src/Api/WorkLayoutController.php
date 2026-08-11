<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkLayoutRepository;

final class WorkLayoutController
{
    private Config $config; private ResponseFactory $responses; private Capabilities $capabilities; private LibraryRepository $library; private WorkLayoutRepository $layout;
    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library, WorkLayoutRepository $layout)
    { $this->config = $config; $this->responses = $responses; $this->capabilities = $capabilities; $this->library = $library; $this->layout = $layout; }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $ns = $this->config->get('api_namespace'); $permission = [$this, 'canAccess'];
            register_rest_route($ns, '/books/(?P<id>\\d+)/layout-stage', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/layout-stage/preview', ['methods' => 'GET', 'callback' => [$this, 'preview'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/layout-stage/issues', ['methods' => 'POST', 'callback' => [$this, 'createIssue'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/layout-stage/issues/(?P<issue_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateIssue'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteIssue'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/layout-stage/proofs', ['methods' => 'POST', 'callback' => [$this, 'proof'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/layout-stage/assist', ['methods' => 'POST', 'callback' => [$this, 'assist'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/layout-stage/complete', ['methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission]);
        });
    }
    public function canAccess(): bool { return $this->capabilities->currentUserCanAccess(); }

    public function show(\WP_REST_Request $request): \WP_REST_Response { return $this->run($request, fn (int $id) => $this->layout->data(get_current_user_id(), $id)); }
    public function preview(\WP_REST_Request $request): \WP_REST_Response { return $this->run($request, fn (int $id) => $this->layout->preview(get_current_user_id(), $id)); }
    public function save(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->layout->saveState(get_current_user_id(), $id, $this->payload($request))); }
    public function createIssue(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->layout->createIssue(get_current_user_id(), $id, $this->payload($request))); }
    public function updateIssue(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->layout->updateIssue(get_current_user_id(), $id, sanitize_key((string) $request['issue_id']), $this->payload($request))); }
    public function deleteIssue(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->layout->deleteIssue(get_current_user_id(), $id, sanitize_key((string) $request['issue_id']))); }
    public function proof(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->layout->generateProof(get_current_user_id(), $id, $this->payload($request))); }
    public function complete(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->layout->complete(get_current_user_id(), $id)); }

    public function assist(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id']; $this->assertOwned($bookId); $key = trim((string) $this->config->get('openai_api_key', ''));
            if ($key === '') throw new ValidationError('O Assistente de Diagramação requer a configuração segura de VERBUM_OPENAI_API_KEY no servidor.');
            $json = $request->get_json_params(); $json = is_array($json) ? $json : []; $action = sanitize_key((string) ($json['action'] ?? 'consistency'));
            $actions = [
                'typography' => 'Sugira uma combinação tipográfica coerente com o briefing e o gênero da obra, usando apenas famílias comuns/licenciáveis e sem afirmar disponibilidade local.',
                'hierarchy' => 'Avalie a hierarquia visual planejada para títulos, subtítulos, corpo, citações e notas.',
                'format' => 'Avalie o formato físico e margens pretendidos em relação ao tipo de obra.',
                'chapter_opening' => 'Sugira um tratamento consistente para abertura de capítulos.',
                'quotes' => 'Sugira tratamento visual para citações, versículos e referências.',
                'consistency' => 'Analise a consistência global das configurações de Diagramação e aponte pontos para conferência.',
            ]; if (! isset($actions[$action])) $action = 'consistency';
            $context = $this->layout->assistantContext(get_current_user_id(), $bookId);
            $input = "TAREFA\n" . $actions[$action] . "\n\nCONTEXTO\n" . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); if (strlen($input) > 45000) $input = substr($input, 0, 45000);
            $response = wp_remote_post('https://api.openai.com/v1/responses', ['timeout' => 60, 'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'], 'body' => wp_json_encode([
                'model' => 'gpt-5.6-luna',
                'instructions' => 'Você é o Assistente de Diagramação do Verbum Studio. Trabalhe apenas com o contexto fornecido. Sugira decisões visuais e tipográficas; não altere conteúdo, não invente fontes licenciadas, não declare conformidade PDF/X nem prontidão profissional de gráfica. Responda em português, de forma objetiva e acionável.',
                'input' => $input, 'max_output_tokens' => 1300,
            ])]);
            if (is_wp_error($response)) throw new ValidationError('Não foi possível acessar o Assistente de Diagramação neste momento.');
            $status = (int) wp_remote_retrieve_response_code($response); $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || ! is_array($body)) throw new ValidationError('O Assistente de Diagramação não conseguiu gerar uma análise.');
            $text = ''; foreach ((array) ($body['output'] ?? []) as $item) { if (! is_array($item) || ($item['type'] ?? '') !== 'message') continue; foreach ((array) ($item['content'] ?? []) as $content) if (is_array($content) && ($content['type'] ?? '') === 'output_text') $text .= (string) ($content['text'] ?? ''); }
            $text = trim($text); if ($text === '') throw new ValidationError('O Assistente retornou uma sugestão vazia.');
            return $this->responses->success(['suggestion' => $text, 'action' => $action]);
        } catch (\Throwable $exception) { return $this->responses->error($exception); }
    }

    /** @param callable(int):array<string,mixed> $callback */
    private function run(\WP_REST_Request $request, callable $callback): \WP_REST_Response
    { try { $bookId = (int) $request['id']; $this->assertOwned($bookId); return $this->responses->success($callback($bookId)); } catch (\Throwable $exception) { return $this->responses->error($exception); } }
    /** @param callable(int):array<string,mixed> $callback */
    private function mutation(\WP_REST_Request $request, callable $callback): \WP_REST_Response
    { try { $bookId = (int) $request['id']; $this->assertOwned($bookId); $layout = $callback($bookId); return $this->responses->success(['layoutStage' => $layout, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]); } catch (\Throwable $exception) { return $this->responses->error($exception); } }
    private function assertOwned(int $bookId): void { $this->library->workspaceForBook(get_current_user_id(), $bookId); }
    /** @return array<string,mixed> */ private function payload(\WP_REST_Request $request): array { $json = $request->get_json_params(); return is_array($json) ? $json : []; }
}
