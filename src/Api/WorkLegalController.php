<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\LibraryRepository;
use VerbumStudio\Library\WorkLegalRepository;

final class WorkLegalController
{
    private Config $config; private ResponseFactory $responses; private Capabilities $capabilities; private LibraryRepository $library; private WorkLegalRepository $legal;
    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library, WorkLegalRepository $legal)
    { $this->config = $config; $this->responses = $responses; $this->capabilities = $capabilities; $this->library = $library; $this->legal = $legal; }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $ns = $this->config->get('api_namespace'); $permission = [$this, 'canAccess'];
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/documents', ['methods' => 'POST', 'callback' => [$this, 'createDocument'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/documents/(?P<document_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateDocument'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteDocument'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/third-party', ['methods' => 'POST', 'callback' => [$this, 'createThirdParty'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/third-party/(?P<item_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateThirdParty'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteThirdParty'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/issues', ['methods' => 'POST', 'callback' => [$this, 'createIssue'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/issues/(?P<issue_id>[A-Za-z0-9_-]+)', [
                ['methods' => 'PATCH', 'callback' => [$this, 'updateIssue'], 'permission_callback' => $permission],
                ['methods' => 'DELETE', 'callback' => [$this, 'deleteIssue'], 'permission_callback' => $permission],
            ]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/proofs', ['methods' => 'POST', 'callback' => [$this, 'proof'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/assist', ['methods' => 'POST', 'callback' => [$this, 'assist'], 'permission_callback' => $permission]);
            register_rest_route($ns, '/books/(?P<id>\\d+)/legal-stage/complete', ['methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission]);
        });
    }

    public function canAccess(): bool { return $this->capabilities->currentUserCanAccess(); }
    public function show(\WP_REST_Request $request): \WP_REST_Response { return $this->run($request, fn (int $id) => $this->legal->data(get_current_user_id(), $id)); }
    public function save(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->saveState(get_current_user_id(), $id, $this->payload($request))); }
    public function createDocument(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->createDocument(get_current_user_id(), $id, $this->payload($request))); }
    public function updateDocument(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->updateDocument(get_current_user_id(), $id, sanitize_key((string) $request['document_id']), $this->payload($request))); }
    public function deleteDocument(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->deleteDocument(get_current_user_id(), $id, sanitize_key((string) $request['document_id']))); }
    public function createThirdParty(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->createThirdParty(get_current_user_id(), $id, $this->payload($request))); }
    public function updateThirdParty(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->updateThirdParty(get_current_user_id(), $id, sanitize_key((string) $request['item_id']), $this->payload($request))); }
    public function deleteThirdParty(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->deleteThirdParty(get_current_user_id(), $id, sanitize_key((string) $request['item_id']))); }
    public function createIssue(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->createIssue(get_current_user_id(), $id, $this->payload($request))); }
    public function updateIssue(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->updateIssue(get_current_user_id(), $id, sanitize_key((string) $request['issue_id']), $this->payload($request))); }
    public function deleteIssue(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->deleteIssue(get_current_user_id(), $id, sanitize_key((string) $request['issue_id']))); }
    public function proof(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->registerProof(get_current_user_id(), $id, $this->payload($request))); }
    public function complete(\WP_REST_Request $request): \WP_REST_Response { return $this->mutation($request, fn (int $id) => $this->legal->complete(get_current_user_id(), $id)); }

    public function assist(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id']; $this->assertOwned($bookId); $key = trim((string) $this->config->get('openai_api_key', ''));
            if ($key === '') throw new ValidationError('O Assistente Legal-Editorial requer a configuração segura de VERBUM_OPENAI_API_KEY no servidor.');
            $json = $request->get_json_params(); $json = is_array($json) ? $json : []; $action = sanitize_key((string) ($json['action'] ?? 'next_steps'));
            $actions = [
                'checklist' => 'Organize o checklist legal-editorial e destaque campos ainda vazios ou processos não resolvidos, sem declarar obrigação jurídica.',
                'documents' => 'Organize os documentos cadastrados por finalidade, status e próximos passos administrativos.',
                'credits' => 'Revise a consistência dos créditos editoriais cadastrados e aponte campos para conferência.',
                'consistency' => 'Compare identificação da edição, ISBN, ficha, créditos, arquivos e dados técnicos e aponte inconsistências para conferência.',
                'third_party' => 'Organize os conteúdos de terceiros e autorizações registradas, destacando somente pendências presentes nos dados.',
                'next_steps' => 'Sugira próximos passos organizacionais para concluir os Trâmites Legais com base apenas nos dados cadastrados.',
            ]; if (! isset($actions[$action])) $action = 'next_steps';
            $context = $this->legal->assistantContext(get_current_user_id(), $bookId);
            $input = "TAREFA\n" . $actions[$action] . "\n\nCONTEXTO\n" . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); if (strlen($input) > 45000) $input = substr($input, 0, 45000);
            $response = wp_remote_post('https://api.openai.com/v1/responses', ['timeout' => 60, 'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'], 'body' => wp_json_encode([
                'model' => 'gpt-5.6-luna',
                'instructions' => 'Você é o Assistente Legal-Editorial do Verbum Studio. Ofereça apenas apoio organizacional e editorial. Não forneça aconselhamento jurídico, não invente requisitos legais, prazos oficiais, registros, números, autorizações ou documentos. Quando um requisito depender de país, modalidade, contrato, órgão, instituição ou legislação vigente, diga para confirmar com o órgão ou profissional responsável. Trabalhe apenas com o contexto fornecido e responda em português de forma objetiva.',
                'input' => $input, 'max_output_tokens' => 1300,
            ])]);
            if (is_wp_error($response)) throw new ValidationError('Não foi possível acessar o Assistente Legal-Editorial neste momento.');
            $status = (int) wp_remote_retrieve_response_code($response); $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || ! is_array($body)) throw new ValidationError('O Assistente Legal-Editorial não conseguiu gerar uma análise.');
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
    { try { $bookId = (int) $request['id']; $this->assertOwned($bookId); $legal = $callback($bookId); return $this->responses->success(['legalStage' => $legal, 'workspace' => $this->library->workspaceForBook(get_current_user_id(), $bookId)]); } catch (\Throwable $exception) { return $this->responses->error($exception); } }
    private function assertOwned(int $bookId): void { $this->library->workspaceForBook(get_current_user_id(), $bookId); }
    /** @return array<string,mixed> */ private function payload(\WP_REST_Request $request): array { $json = $request->get_json_params(); return is_array($json) ? $json : []; }
}
