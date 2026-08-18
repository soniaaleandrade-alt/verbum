<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\FoundationLetterSoulRepository;
use VerbumStudio\Library\LibraryRepository;

final class FoundationLetterSoulController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private FoundationLetterSoulRepository $foundation;

    public function __construct(
        Config $config,
        ResponseFactory $responses,
        Capabilities $capabilities,
        LibraryRepository $library,
        FoundationLetterSoulRepository $foundation
    ) {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->foundation = $foundation;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];

            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/carta-alma', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/carta-alma/complete', [
                'methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/carta-alma/analyze', [
                'methods' => 'POST', 'callback' => [$this, 'analyze'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/carta-alma/suggest', [
                'methods' => 'POST', 'callback' => [$this, 'suggest'], 'permission_callback' => $permission,
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
            return $this->responses->success($this->foundation->data($bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];
            $clean = [];
            if (array_key_exists('letter_html', $payload)) {
                $clean['letter_html'] = (string) $payload['letter_html'];
            }
            if (array_key_exists('soul', $payload)) {
                $clean['soul'] = (string) $payload['soul'];
            }
            if (array_key_exists('base_revision', $payload)) {
                $clean['base_revision'] = max(0, (int) $payload['base_revision']);
            }
            return $this->responses->success($this->foundation->save($bookId, $clean));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->foundation->complete($bookId));
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function analyze(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];
            $letter = trim(wp_strip_all_tags((string) ($payload['letter_html'] ?? '')));
            if ($letter === '') {
                throw new ValidationError('Escreva a Carta aos Leitores antes de solicitar a análise.');
            }

            $input = "CARTA AOS LEITORES\n" . $letter;
            $json = $this->callOpenAIJson(
                'Você é o Assistente de Fundação do Verbum Studio. Analise somente o texto fornecido pelo autor. Não invente fatos pessoais, testemunhos, intenções, referências, citações ou doutrina. Quando algo não estiver explícito, use linguagem cautelosa. Responda estritamente em JSON válido, sem markdown, com as chaves: origin, intention, recurring_message, audience, expected_fruit, themes, clarity_points, soul_suggestion. themes e clarity_points devem ser arrays de strings. soul_suggestion deve ser uma síntese curta, editável e fiel ao texto.',
                $input,
                1800
            );

            return $this->responses->success([
                'origin' => trim((string) ($json['origin'] ?? '')),
                'intention' => trim((string) ($json['intention'] ?? '')),
                'recurringMessage' => trim((string) ($json['recurring_message'] ?? '')),
                'audience' => trim((string) ($json['audience'] ?? '')),
                'expectedFruit' => trim((string) ($json['expected_fruit'] ?? '')),
                'themes' => $this->stringList($json['themes'] ?? []),
                'clarityPoints' => $this->stringList($json['clarity_points'] ?? []),
                'soulSuggestion' => trim((string) ($json['soul_suggestion'] ?? '')),
            ]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    public function suggest(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];
            $letter = trim(wp_strip_all_tags((string) ($payload['letter_html'] ?? '')));
            if ($letter === '') {
                throw new ValidationError('Escreva a Carta aos Leitores antes de gerar uma sugestão para a Alma da Obra.');
            }

            $json = $this->callOpenAIJson(
                'Você é o Assistente de Fundação do Verbum Studio. Gere somente uma proposta concisa de Alma da Obra com base exclusiva na Carta aos Leitores fornecida. Preserve a voz, a experiência, a motivação e a mudança interior realmente presentes no texto. Não invente acontecimentos, fatos pessoais, testemunhos, intenções, citações ou conteúdo religioso que não esteja no texto. Se houver conteúdo católico, preserve reverência sem acrescentar afirmações doutrinais não sustentadas. Responda estritamente em JSON válido, sem markdown, com a chave suggestion.',
                "CARTA AOS LEITORES\n" . $letter,
                900
            );

            $suggestion = trim((string) ($json['suggestion'] ?? ''));
            if ($suggestion === '') {
                throw new ValidationError('O Assistente não conseguiu gerar uma sugestão neste momento.');
            }
            return $this->responses->success(['suggestion' => $suggestion]);
        } catch (\Throwable $exception) {
            return $this->responses->error($exception);
        }
    }

    private function assertOwned(int $bookId): void
    {
        $this->library->workspaceForBook(get_current_user_id(), $bookId);
    }

    /** @return array<string, mixed> */
    private function callOpenAIJson(string $instructions, string $input, int $maxOutputTokens): array
    {
        $key = trim((string) $this->config->get('openai_api_key', ''));
        if ($key === '') {
            throw new ValidationError('O recurso de inteligência artificial está indisponível porque VERBUM_OPENAI_API_KEY ainda não foi configurada no servidor.');
        }

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-5.6-luna',
                'instructions' => $instructions,
                'input' => $input,
                'max_output_tokens' => $maxOutputTokens,
            ]),
        ]);
        if (is_wp_error($response)) {
            throw new ValidationError('Não foi possível acessar a inteligência artificial neste momento.');
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            throw new ValidationError('A inteligência artificial não conseguiu concluir a solicitação. Tente novamente.');
        }

        $text = '';
        foreach ((array) ($body['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? '') !== 'message') continue;
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (is_array($content) && ($content['type'] ?? '') === 'output_text') {
                    $text .= (string) ($content['text'] ?? '');
                }
            }
        }
        $text = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $matches)) {
            $text = trim((string) ($matches[1] ?? ''));
        }
        $json = json_decode($text, true);
        if (! is_array($json)) {
            throw new ValidationError('A inteligência artificial retornou uma resposta inválida. Tente novamente.');
        }
        return $json;
    }

    /** @return string[] */
    private function stringList($value): array
    {
        $items = is_array($value) ? $value : [];
        $clean = [];
        foreach ($items as $item) {
            $text = trim(sanitize_text_field((string) $item));
            if ($text !== '') $clean[] = $text;
        }
        return array_values(array_unique($clean));
    }
}
