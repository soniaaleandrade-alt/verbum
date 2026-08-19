<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkPublicationRepository;

final class WorkPublicationController
{
    private Config $config; private ResponseFactory $responses; private Capabilities $capabilities; private LibraryRepository $library; private WorkPublicationRepository $publication;
    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library, WorkPublicationRepository $publication)
    { $this->config = $config; $this->responses = $responses; $this->capabilities = $capabilities; $this->library = $library; $this->publication = $publication; }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $ns = $this->config->get('api_namespace'); $permission = [$this, 'canAccess'];
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage/journey', [
                ['methods' => 'GET', 'callback' => [$this, 'showJourney'], 'permission_callback' => $permission],
                ['methods' => 'POST', 'callback' => [$this, 'journeyAction'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage/channels', ['methods' => 'POST', 'callback' => [$this, 'createChannel'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage/channels/(?P<channel_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateChannel'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteChannel'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage/tasks', ['methods' => 'POST', 'callback' => [$this, 'createTask'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage/tasks/(?P<task_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateTask'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteTask'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage/updates', ['methods' => 'POST', 'callback' => [$this, 'registerUpdate'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage/assist', ['methods' => 'POST', 'callback' => [$this, 'assist'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/publication-stage/complete', ['methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission]);
        });
    }

    public function canAccess(): bool { return $this->capabilities->currentUserCanAccess(); }
    public function showJourney(\WP_REST_Request $request): \WP_REST_Response { return $this->run($request, fn (int $id) => $this->publication->journeyData(get_current_user_id(), $id)); }
    public function journeyAction(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->journeyAction(get_current_user_id(), $id, $this->payload($request))); }
    public function show(\WP_REST_Request $request): \WP_REST_Response { return $this->run($request, fn (int $id) => $this->publication->data(get_current_user_id(), $id)); }
    public function save(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->saveState(get_current_user_id(), $id, $this->payload($request))); }
    public function createChannel(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->createChannel(get_current_user_id(), $id, $this->payload($request))); }
    public function updateChannel(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->updateChannel(get_current_user_id(), $id, sanitize_key((string) $request['channel_id']), $this->payload($request))); }
    public function deleteChannel(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->deleteChannel(get_current_user_id(), $id, sanitize_key((string) $request['channel_id']))); }
    public function createTask(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->createTask(get_current_user_id(), $id, $this->payload($request))); }
    public function updateTask(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->updateTask(get_current_user_id(), $id, sanitize_key((string) $request['task_id']), $this->payload($request))); }
    public function deleteTask(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->deleteTask(get_current_user_id(), $id, sanitize_key((string) $request['task_id']))); }
    public function registerUpdate(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->registerUpdate(get_current_user_id(), $id, $this->payload($request))); }
    public function complete(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->publication->complete(get_current_user_id(), $id)); }

    public function assist(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id']; $this->assertOwned($bookId); $key = trim((string) $this->config->get('openai_api_key', ''));
            if ($key === '') throw new ValidationError('O Assistente de Publicação requer a configuração segura de VERBUM_OPENAI_API_KEY no servidor.');
            $json = $request->get_json_params(); $json = is_array($json) ? $json : []; $action = sanitize_key((string) ($json['action'] ?? 'consistency'));
            $actions = [
                'description' => 'Aprimore a descrição comercial sem inventar conteúdo, avaliações, prêmios ou promessas de resultado.',
                'keywords' => 'Sugira palavras-chave coerentes com os metadados e a proposta da obra, sem alegar desempenho em mecanismos de busca.',
                'categories' => 'Sugira categorias editoriais/comerciais genéricas para conferência. Não afirme que uma categoria existe ou é aceita em uma plataforma específica.',
                'bio' => 'Aprimore a apresentação do autor usando apenas os dados fornecidos.',
                'release' => 'Prepare uma sugestão de release editorial com base somente nos dados cadastrados.',
                'launch' => 'Organize um checklist simples de pré-lançamento, lançamento e pós-lançamento a partir dos dados existentes.',
                'consistency' => 'Analise a consistência entre pacote, metadados, preços, canais e baseline legal e aponte itens para conferência.',
            ]; if (! isset($actions[$action])) $action = 'consistency';
            $context = $this->publication->assistantContext(get_current_user_id(), $bookId);
            $input = "TAREFA\n" . $actions[$action] . "\n\nCONTEXTO\n" . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); if (strlen($input) > 45000) $input = substr($input, 0, 45000);
            $response = wp_remote_post('https://api.openai.com/v1/responses', ['timeout' => 60, 'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'], 'body' => wp_json_encode([
                'model' => 'gpt-5.6-luna',
                'instructions' => 'Você é o Assistente de Publicação do Verbum Studio. Trabalhe somente com o contexto fornecido. Não invente canais, categorias oficiais, taxas, disponibilidade, avaliações, rankings, prêmios ou desempenho comercial. Não altere dados automaticamente. A IA sugere e o autor decide. Responda em português, de forma objetiva e acionável.',
                'input' => $input, 'max_output_tokens' => 1400,
            ])]);
            if (is_wp_error($response)) throw new ValidationError('Não foi possível acessar o Assistente de Publicação neste momento.');
            $status = (int) wp_remote_retrieve_response_code($response); $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || ! is_array($body)) throw new ValidationError('O Assistente de Publicação não conseguiu gerar uma análise.');
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
    { try { $bookId = (int) $request['id']; $this->assertOwned($bookId); $publication = $callback($bookId); return $this->responses->success(['publicationStage' => $publication, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]); } catch (\Throwable $exception) { return $this->responses->error($exception); } }
    private function assertOwned(int $bookId): void { $this->library->workspaceForBook(get_current_user_id(), $bookId); }
    /** @return array<string,mixed> */ private function payload(\WP_REST_Request $request): array { $json = $request->get_json_params(); return is_array($json) ? $json : []; }
}
