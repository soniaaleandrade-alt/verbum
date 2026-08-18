<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\FoundationIntentionRepository;
use VerbumStudio\Library\FoundationLetterSoulRepository;
use VerbumStudio\Library\LibraryRepository;

final class FoundationIntentionController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private FoundationIntentionRepository $intention;
    private FoundationLetterSoulRepository $letterSoul;

    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library, FoundationIntentionRepository $intention, FoundationLetterSoulRepository $letterSoul)
    {
        $this->config = $config;
        $this->responses = $responses;
        $this->capabilities = $capabilities;
        $this->library = $library;
        $this->intention = $intention;
        $this->letterSoul = $letterSoul;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace');
            $permission = [$this, 'canAccess'];
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/intention', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/intention/complete', [
                'methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission,
            ]);
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/intention/coherence', [
                'methods' => 'POST', 'callback' => [$this, 'coherence'], 'permission_callback' => $permission,
            ]);
        });
    }

    public function canAccess(): bool { return $this->capabilities->currentUserCanAccess(); }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->intention->data($bookId));
        } catch (\Throwable $exception) { return $this->responses->error($exception); }
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->intention->save($bookId, $this->payload($request)));
        } catch (\Throwable $exception) { return $this->responses->error($exception); }
    }

    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            return $this->responses->success($this->intention->complete($bookId));
        } catch (\Throwable $exception) { return $this->responses->error($exception); }
    }

    public function coherence(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bookId = (int) $request['id'];
            $this->assertOwned($bookId);
            $payload = $this->payload($request);
            $letterSoul = $this->letterSoul->data($bookId);
            $problem = trim((string) ($payload['problem'] ?? ''));
            $purpose = trim((string) ($payload['purpose'] ?? ''));
            $general = trim((string) ($payload['general_objective'] ?? ''));
            $objectives = $payload['specific_objectives'] ?? [];
            $letter = trim(wp_strip_all_tags((string) ($letterSoul['letterHtml'] ?? '')));
            $soul = trim((string) ($letterSoul['soul'] ?? ''));
            if ($letter === '' || $soul === '' || $problem === '' || $purpose === '' || $general === '' || ! is_array($objectives) || $objectives === []) {
                throw new ValidationError('Preencha Carta aos Leitores, Alma da Obra e os campos da Intenção antes de verificar a coerência.');
            }

            $objectiveLines = [];
            foreach ($objectives as $objective) {
                if (is_array($objective) && trim((string) ($objective['text'] ?? '')) !== '') $objectiveLines[] = '- ' . trim((string) $objective['text']);
            }
            $input = "CARTA AOS LEITORES\n{$letter}\n\nALMA DA OBRA\n{$soul}\n\nPROBLEMA OU NECESSIDADE\n{$problem}\n\nPROPÓSITO\n{$purpose}\n\nOBJETIVO GERAL\n{$general}\n\nOBJETIVOS ESPECÍFICOS\n" . implode("\n", $objectiveLines);
            $json = $this->callOpenAIJson(
                'Você é o Assistente de Coerência da Fundação do Verbum Studio. Analise somente os textos fornecidos, sem inventar fatos. Avalie alinhamento, repetições, contradições, ausências e precisão. Responda estritamente em JSON válido com as chaves coherent_points, attention_points e suggestions. Cada chave deve conter um array. coherent_points e attention_points: objetos com fields (array de nomes dos campos) e observation. suggestions: objetos com field (problem, purpose, general_objective ou specific_objectives), current_text, suggested_text e reason. Sugira alterações apenas quando necessárias e preserve a voz do autor.',
                $input,
                2200
            );
            return $this->responses->success([
                'coherentPoints' => $this->observations($json['coherent_points'] ?? []),
                'attentionPoints' => $this->observations($json['attention_points'] ?? []),
                'suggestions' => $this->suggestions($json['suggestions'] ?? []),
            ]);
        } catch (\Throwable $exception) { return $this->responses->error($exception); }
    }

    private function assertOwned(int $bookId): void { $this->library->workspaceForBook(get_current_user_id(), $bookId); }

    /** @return array<string, mixed> */
    private function payload(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : [];
        $clean = [];
        foreach (['problem', 'purpose', 'general_objective'] as $field) {
            if (array_key_exists($field, $payload)) $clean[$field] = sanitize_textarea_field((string) $payload[$field]);
        }
        if (array_key_exists('base_revision', $payload)) $clean['base_revision'] = max(0, (int) $payload['base_revision']);
        if (array_key_exists('specific_objectives', $payload)) {
            $clean['specific_objectives'] = [];
            foreach (is_array($payload['specific_objectives']) ? $payload['specific_objectives'] : [] as $index => $objective) {
                if (! is_array($objective)) continue;
                $clean['specific_objectives'][] = [
                    'id' => sanitize_key((string) ($objective['id'] ?? '')),
                    'text' => sanitize_textarea_field((string) ($objective['text'] ?? '')),
                    'order' => max(1, (int) ($objective['order'] ?? ($index + 1))),
                ];
            }
        }
        return $clean;
    }

    /** @return array<string, mixed> */
    private function callOpenAIJson(string $instructions, string $input, int $maxOutputTokens): array
    {
        $key = trim((string) $this->config->get('openai_api_key', ''));
        if ($key === '') throw new ValidationError('A Assistência de coerência está indisponível porque VERBUM_OPENAI_API_KEY ainda não foi configurada no servidor.');
        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => 60,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode(['model' => 'gpt-5.6-luna', 'instructions' => $instructions, 'input' => $input, 'max_output_tokens' => $maxOutputTokens]),
        ]);
        if (is_wp_error($response)) throw new ValidationError('Não foi possível acessar a inteligência artificial neste momento.');
        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || ! is_array($body)) throw new ValidationError('A inteligência artificial não conseguiu concluir a solicitação. Tente novamente.');
        $text = '';
        foreach ((array) ($body['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? '') !== 'message') continue;
            foreach ((array) ($item['content'] ?? []) as $content) if (is_array($content) && ($content['type'] ?? '') === 'output_text') $text .= (string) ($content['text'] ?? '');
        }
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', trim($text), $matches)) $text = trim((string) ($matches[1] ?? ''));
        $json = json_decode(trim($text), true);
        if (! is_array($json)) throw new ValidationError('A inteligência artificial retornou uma resposta inválida. Tente novamente.');
        return $json;
    }

    /** @param mixed $items
     *  @return array<int, array<string, mixed>>
     */
    private function observations($items): array
    {
        $result = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) continue;
            $observation = trim(sanitize_textarea_field((string) ($item['observation'] ?? '')));
            if ($observation === '') continue;
            $fields = array_values(array_filter(array_map(static fn ($field): string => sanitize_text_field((string) $field), is_array($item['fields'] ?? null) ? $item['fields'] : [])));
            $result[] = ['fields' => $fields, 'observation' => $observation];
        }
        return $result;
    }

    /** @param mixed $items
     *  @return array<int, array<string, string>>
     */
    private function suggestions($items): array
    {
        $allowed = ['problem', 'purpose', 'general_objective', 'specific_objectives'];
        $result = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) continue;
            $field = sanitize_key((string) ($item['field'] ?? ''));
            $suggested = trim(sanitize_textarea_field((string) ($item['suggested_text'] ?? '')));
            if (! in_array($field, $allowed, true) || $suggested === '') continue;
            $result[] = ['field' => $field, 'currentText' => trim(sanitize_textarea_field((string) ($item['current_text'] ?? ''))), 'suggestedText' => $suggested, 'reason' => trim(sanitize_textarea_field((string) ($item['reason'] ?? '')))];
        }
        return $result;
    }
}
