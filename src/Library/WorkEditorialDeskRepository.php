<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkEditorialDeskRepository
{
    private const MANUAL_FLAGS = [
        'version_validated' => 'Versão auditada validada',
        'title_approved' => 'Título aprovado',
        'subtitle_approved' => 'Subtítulo aprovado',
        'positioning_confirmed' => 'Público e posicionamento confirmados',
        'synopsis_approved' => 'Sinopse aprovada',
        'back_cover_defined' => 'Quarta capa definida',
        'author_profile_checked' => 'Perfil editorial do autor conferido',
        'elements_defined' => 'Elementos da obra definidos',
        'order_defined' => 'Ordem editorial definida',
        'cover_brief_complete' => 'Briefing de capa concluído',
        'layout_brief_complete' => 'Briefing de diagramação concluído',
        'opinion_complete' => 'Parecer Editorial concluído',
        'adjustments_resolved' => 'Pendências editoriais resolvidas',
        'final_decision_recorded' => 'Decisão final registrada',
    ];

    private const ADJUSTMENT_TYPES = [
        'editorial' => 'Editorial não estrutural',
        'content' => 'Conteúdo da obra — requer nova Auditoria',
    ];

    private const PRIORITIES = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'critical' => 'Crítica'];
    private const ADJUSTMENT_STATUSES = ['open' => 'Aberto', 'in_progress' => 'Em andamento', 'resolved' => 'Resolvido', 'dismissed' => 'Dispensado com justificativa'];
    private const ASSESSMENT_CRITERIA = [
        'audience_fit' => 'Adequação ao público',
        'proposal_clarity' => 'Clareza da proposta',
        'structure' => 'Estrutura',
        'length' => 'Extensão',
        'progression' => 'Progressão da obra',
        'title' => 'Título',
        'subtitle' => 'Subtítulo',
        'differential' => 'Diferencial',
        'editorial_consistency' => 'Consistência editorial',
        'publication_readiness' => 'Preparação para publicação',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId): array
    {
        $this->assertAvailable($userId, $bookId);
        $version = $this->approvedAuditVersion($bookId);
        $rounds = $this->rounds($bookId);
        $round = $this->currentRound($rounds, (string) $version['id']);
        if ($round === null) {
            $round = $this->newRound($userId, $bookId, $version, count($rounds) + 1);
            $rounds[] = $round;
            $this->storeRounds($bookId, $rounds);
        }

        $fields = is_array($round['fields'] ?? null) ? $round['fields'] : $this->initialFields($userId, $bookId, $version);
        $flags = $this->normalizeFlags(is_array($round['flags'] ?? null) ? $round['flags'] : []);
        $assessments = $this->normalizeAssessments(is_array($round['assessments'] ?? null) ? $round['assessments'] : []);
        $adjustments = is_array($round['adjustments'] ?? null) ? array_values(array_filter($round['adjustments'], 'is_array')) : [];
        $openBlocking = count(array_filter($adjustments, static function (array $item): bool {
            return in_array((string) ($item['status'] ?? 'open'), ['open', 'in_progress'], true)
                && in_array((string) ($item['priority'] ?? 'medium'), ['high', 'critical'], true);
        }));
        $requiresNewAudit = count(array_filter($adjustments, static fn (array $item): bool => (string) ($item['type'] ?? 'editorial') === 'content')) > 0;
        $completed = (string) ($round['status'] ?? '') === 'approved_for_layout';
        $auditStillValid = (string) get_post_meta($bookId, '_verbum_audit_approved_version_id', true) === (string) $version['id']
            && (string) get_post_meta($bookId, '_verbum_audit_approved_hash', true) === (string) $version['hash'];
        $finalConfirmation = (bool) ($round['finalConfirmation'] ?? false);

        $checklist = [];
        foreach (self::MANUAL_FLAGS as $key => $label) {
            $checked = (bool) ($flags[$key] ?? false);
            if ($key === 'adjustments_resolved') $checked = $checked && $openBlocking === 0 && ! $requiresNewAudit;
            if ($key === 'opinion_complete') $checked = $checked && trim((string) ($fields['opinion']['conclusion'] ?? '')) !== '';
            $checklist[] = ['key' => $key, 'label' => $label, 'completed' => $checked, 'automatic' => false];
        }
        $checklist[] = ['key' => 'audit_baseline_valid', 'label' => 'Baseline auditada continua válida', 'completed' => $auditStillValid, 'automatic' => true];
        $checklist[] = ['key' => 'no_blockers', 'label' => 'Nenhuma pendência editorial bloqueante', 'completed' => $openBlocking === 0 && ! $requiresNewAudit, 'automatic' => true];
        $checklist[] = ['key' => 'completed', 'label' => 'Mesa Editorial concluída', 'completed' => $completed, 'automatic' => true];
        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $manualReady = count(array_filter(array_keys(self::MANUAL_FLAGS), static fn (string $key): bool => (bool) ($flags[$key] ?? false))) === count(self::MANUAL_FLAGS);
        $ready = ! $completed && $auditStillValid && ! $requiresNewAudit && $openBlocking === 0 && $manualReady && $finalConfirmation && trim((string) ($fields['opinion']['conclusion'] ?? '')) !== '';

        return [
            'bookId' => (string) $bookId,
            'title' => (string) ($fields['identity']['titleFinal'] ?? get_the_title($bookId)),
            'version' => $this->versionSummary($version),
            'round' => $this->roundSummary($round),
            'rounds' => array_map(fn (array $item): array => $this->roundSummary($item), array_reverse($rounds)),
            'fields' => $fields,
            'assessments' => $assessments,
            'assessmentCriteria' => $this->options(self::ASSESSMENT_CRITERIA),
            'adjustments' => $adjustments,
            'adjustmentTypes' => $this->options(self::ADJUSTMENT_TYPES),
            'priorities' => $this->options(self::PRIORITIES),
            'adjustmentStatuses' => $this->options(self::ADJUSTMENT_STATUSES),
            'openBlockingCount' => $openBlocking,
            'requiresNewAudit' => $requiresNewAudit,
            'auditStillValid' => $auditStillValid,
            'flags' => $flags,
            'finalConfirmation' => $finalConfirmation,
            'checklist' => $checklist,
            'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            'completedCount' => $completedCount,
            'total' => count($checklist),
            'ready' => $ready,
            'completed' => $completed,
            'status' => (string) ($round['status'] ?? 'in_review'),
            'statusLabel' => $this->statusLabel((string) ($round['status'] ?? 'in_review'), $requiresNewAudit, $openBlocking),
        ];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function saveState(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId);
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertRoundMutable($round);
            if (array_key_exists('fields', $payload)) $round['fields'] = $this->normalizeFields(is_array($payload['fields']) ? $payload['fields'] : []);
            if (array_key_exists('flags', $payload)) $round['flags'] = $this->normalizeFlags(is_array($payload['flags']) ? $payload['flags'] : []);
            if (array_key_exists('assessments', $payload)) $round['assessments'] = $this->normalizeAssessments(is_array($payload['assessments']) ? $payload['assessments'] : []);
            if (array_key_exists('final_confirmation', $payload)) $round['finalConfirmation'] = (bool) $payload['final_confirmation'];
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function createAdjustment(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId);
        $description = trim(sanitize_textarea_field((string) ($payload['description'] ?? '')));
        if ($description === '') throw new ValidationError('Descreva o ajuste solicitado pela Mesa Editorial.');
        $type = sanitize_key((string) ($payload['type'] ?? 'editorial'));
        if (! isset(self::ADJUSTMENT_TYPES[$type])) $type = 'editorial';
        $priority = sanitize_key((string) ($payload['priority'] ?? 'medium'));
        if (! isset(self::PRIORITIES[$priority])) $priority = 'medium';
        $chapterId = (string) (int) ($payload['chapter_id'] ?? 0);
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertRoundMutable($round);
            $items = is_array($round['adjustments'] ?? null) ? $round['adjustments'] : [];
            $items[] = [
                'id' => 'editorial-adjustment-' . substr(md5($description . '|' . microtime(true)), 0, 14),
                'type' => $type,
                'typeLabel' => self::ADJUSTMENT_TYPES[$type],
                'priority' => $priority,
                'priorityLabel' => self::PRIORITIES[$priority],
                'description' => $description,
                'chapterId' => $chapterId === '0' ? '' : $chapterId,
                'chapterTitle' => $this->chapterTitle((array) ($round['snapshot']['chapters'] ?? []), $chapterId),
                'responsible' => trim(sanitize_text_field((string) ($payload['responsible'] ?? ''))),
                'status' => 'open',
                'statusLabel' => self::ADJUSTMENT_STATUSES['open'],
                'justification' => '',
                'createdAt' => gmdate('c'),
                'updatedAt' => gmdate('c'),
            ];
            $round['adjustments'] = $items;
            $round['status'] = 'adjustments_requested';
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function updateAdjustment(int $userId, int $bookId, string $adjustmentId, array $payload): array
    {
        $data = $this->data($userId, $bookId);
        $rounds = $this->rounds($bookId);
        $found = false;
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertRoundMutable($round);
            $items = is_array($round['adjustments'] ?? null) ? $round['adjustments'] : [];
            foreach ($items as &$item) {
                if ((string) ($item['id'] ?? '') !== $adjustmentId) continue;
                $found = true;
                $status = sanitize_key((string) ($payload['status'] ?? $item['status'] ?? 'open'));
                if (! isset(self::ADJUSTMENT_STATUSES[$status])) $status = 'open';
                $justification = trim(sanitize_textarea_field((string) ($payload['justification'] ?? $item['justification'] ?? '')));
                if ($status === 'dismissed' && $justification === '') throw new ValidationError('Informe a justificativa para dispensar o ajuste editorial.');
                $item['status'] = $status;
                $item['statusLabel'] = self::ADJUSTMENT_STATUSES[$status];
                $item['justification'] = $justification;
                $item['updatedAt'] = gmdate('c');
                break;
            }
            unset($item);
            $round['adjustments'] = $items;
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        if (! $found) throw new NotFoundError('Ajuste editorial não encontrado.');
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function deleteAdjustment(int $userId, int $bookId, string $adjustmentId): array
    {
        $data = $this->data($userId, $bookId);
        $rounds = $this->rounds($bookId);
        $deleted = false;
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertRoundMutable($round);
            $items = is_array($round['adjustments'] ?? null) ? $round['adjustments'] : [];
            $next = array_values(array_filter($items, static fn (array $item): bool => (string) ($item['id'] ?? '') !== $adjustmentId));
            $deleted = count($next) !== count($items);
            $round['adjustments'] = $next;
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        if (! $deleted) throw new NotFoundError('Ajuste editorial não encontrado.');
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function assistantContext(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        return [
            'version' => $data['version'],
            'identity' => $fields['identity'] ?? [],
            'positioning' => $fields['positioning'] ?? [],
            'backCover' => $fields['backCover'] ?? [],
            'coverBrief' => $fields['coverBrief'] ?? [],
            'layoutBrief' => $fields['layoutBrief'] ?? [],
            'opinion' => $fields['opinion'] ?? [],
            'assessments' => $data['assessments'],
            'adjustments' => $data['adjustments'],
        ];
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        if (! $data['ready']) throw new ValidationError('Conclua as decisões editoriais, resolva pendências bloqueantes e confirme a versão antes de aprovar para Diagramação.');
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $round['status'] = 'approved_for_layout';
            $round['completedAt'] = gmdate('c');
            $round['approvedVersionId'] = (string) $data['version']['id'];
            $round['approvedHash'] = (string) $data['version']['hash'];
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);

        $identity = is_array($data['fields']['identity'] ?? null) ? $data['fields']['identity'] : [];
        $book = get_post($bookId);
        $title = trim(sanitize_text_field((string) ($identity['titleFinal'] ?? '')));
        if ($book instanceof \WP_Post && $title !== '') wp_update_post(['ID' => $bookId, 'post_title' => $title, 'post_content' => $book->post_content]);
        $metaMap = ['subtitle' => 'subtitleFinal', 'author_name' => 'authorDisplay', 'genre' => 'genre', 'category' => 'category', 'language' => 'language', 'audience' => 'audience', 'synopsis' => 'synopsisFull'];
        foreach ($metaMap as $meta => $field) if (array_key_exists($field, $identity)) update_post_meta($bookId, '_verbum_' . $meta, sanitize_textarea_field((string) $identity[$field]));

        update_post_meta($bookId, '_verbum_editorial_approved_version_id', (string) $data['version']['id']);
        update_post_meta($bookId, '_verbum_editorial_approved_hash', (string) $data['version']['hash']);
        update_post_meta($bookId, '_verbum_editorial_completed_at', gmdate('c'));
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('editorial_desk', $completed, true)) $completed[] = 'editorial_desk';
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        update_post_meta($bookId, '_verbum_stage', 'layout');
        $this->markApprovedVersion($bookId, (string) $data['version']['id']);
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    private function assertAvailable(int $userId, int $bookId): void
    {
        $book = get_post($bookId);
        if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) throw new NotFoundError('Obra não encontrada.');
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('audit', $completed, true)) throw new ValidationError('Conclua a Auditoria da Obra antes de iniciar a Mesa Editorial.');
        if ((string) get_post_meta($bookId, '_verbum_audit_approved_version_id', true) === '') throw new ValidationError('A Auditoria ainda não possui uma versão aprovada para a Mesa Editorial.');
    }

    /** @return array<string, mixed> */
    private function approvedAuditVersion(int $bookId): array
    {
        $id = (string) get_post_meta($bookId, '_verbum_audit_approved_version_id', true);
        $hash = (string) get_post_meta($bookId, '_verbum_audit_approved_hash', true);
        $versions = get_post_meta($bookId, '_verbum_work_versions', true);
        $versions = is_array($versions) ? $versions : [];
        foreach ($versions as $version) {
            if (! is_array($version) || (string) ($version['id'] ?? '') !== $id) continue;
            if ($hash === '' || (string) ($version['hash'] ?? '') !== $hash) throw new ValidationError('O marco aprovado pela Auditoria perdeu a integridade esperada. Volte à Auditoria antes de continuar.');
            return $version;
        }
        throw new ValidationError('A versão aprovada pela Auditoria não foi encontrada no histórico da obra.');
    }

    /** @param array<string, mixed> $version
     *  @return array<string, mixed>
     */
    private function newRound(int $userId, int $bookId, array $version, int $number): array
    {
        return [
            'id' => 'editorial-round-' . substr(md5((string) $version['id'] . '|' . microtime(true)), 0, 14),
            'number' => $number,
            'versionId' => (string) $version['id'],
            'versionNumber' => (string) $version['number'],
            'versionName' => (string) $version['name'],
            'versionHash' => (string) $version['hash'],
            'snapshot' => is_array($version['snapshot'] ?? null) ? $version['snapshot'] : [],
            'fields' => $this->initialFields($userId, $bookId, $version),
            'assessments' => $this->normalizeAssessments([]),
            'flags' => $this->normalizeFlags([]),
            'adjustments' => [],
            'finalConfirmation' => false,
            'status' => 'in_review',
            'startedAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
            'completedAt' => '',
        ];
    }

    /** @param array<string, mixed> $version
     *  @return array<string, mixed>
     */
    private function initialFields(int $userId, int $bookId, array $version): array
    {
        $snapshot = is_array($version['snapshot'] ?? null) ? $version['snapshot'] : [];
        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $project = static function (int $id, string $key): string { return trim((string) get_post_meta($id, '_verbum_work_project_' . $key, true)); };
        $front = is_array($snapshot['frontMatter'] ?? null) ? $snapshot['frontMatter'] : [];
        $synopsis = trim((string) get_post_meta($bookId, '_verbum_synopsis', true));
        $user = get_userdata($userId);
        $elements = [
            ['key' => 'dedication', 'label' => 'Dedicatória', 'include' => false],
            ['key' => 'epigraph', 'label' => 'Epígrafe', 'include' => false],
            ['key' => 'acknowledgements', 'label' => 'Agradecimentos', 'include' => false],
            ['key' => 'preface', 'label' => 'Prefácio', 'include' => trim(wp_strip_all_tags((string) ($front['preface'] ?? ''))) !== ''],
            ['key' => 'presentation', 'label' => 'Apresentação', 'include' => trim(wp_strip_all_tags((string) ($front['presentation'] ?? ''))) !== ''],
            ['key' => 'author_note', 'label' => 'Nota do Autor', 'include' => trim(wp_strip_all_tags((string) ($front['authorNote'] ?? ''))) !== ''],
            ['key' => 'introduction', 'label' => 'Introdução', 'include' => trim(wp_strip_all_tags((string) ($front['introduction'] ?? ''))) !== ''],
            ['key' => 'chapters', 'label' => 'Capítulos', 'include' => true],
            ['key' => 'conclusion', 'label' => 'Conclusão', 'include' => trim(wp_strip_all_tags((string) ($front['conclusion'] ?? ''))) !== ''],
            ['key' => 'bibliography', 'label' => 'Bibliografia / Referências', 'include' => true],
            ['key' => 'glossary', 'label' => 'Glossário', 'include' => false],
            ['key' => 'appendices', 'label' => 'Anexos / Apêndices', 'include' => false],
            ['key' => 'about_author', 'label' => 'Sobre o Autor', 'include' => true],
        ];
        $order = array_values(array_map(static fn (array $item): string => (string) $item['key'], array_filter($elements, static fn (array $item): bool => (bool) $item['include'])));
        $authorName = trim((string) get_post_meta($bookId, '_verbum_author_name', true));
        $fullName = is_object($user) && isset($user->display_name) ? (string) $user->display_name : '';
        return [
            'identity' => [
                'titleFinal' => (string) (($metadata['title'] ?? '') ?: get_the_title($bookId)),
                'subtitleFinal' => (string) (($metadata['subtitle'] ?? '') ?: get_post_meta($bookId, '_verbum_subtitle', true)),
                'titleOptions' => [],
                'authorDisplay' => $authorName,
                'genre' => trim((string) get_post_meta($bookId, '_verbum_genre', true)),
                'subgenre' => '',
                'category' => trim((string) get_post_meta($bookId, '_verbum_category', true)),
                'language' => trim((string) get_post_meta($bookId, '_verbum_language', true)),
                'audience' => $project($bookId, 'audience') ?: trim((string) get_post_meta($bookId, '_verbum_audience', true)),
                'shortDescription' => $project($bookId, 'value_proposition'),
                'synopsisShort' => $synopsis,
                'synopsisFull' => $synopsis,
            ],
            'positioning' => [
                'need' => $project($bookId, 'purpose'),
                'proposal' => $project($bookId, 'value_proposition'),
                'audience' => $project($bookId, 'audience'),
                'differential' => $project($bookId, 'differentials'),
                'perception' => $project($bookId, 'transformation'),
                'references' => '',
            ],
            'backCover' => ['headline' => '', 'text' => '', 'highlight' => $project($bookId, 'guiding_phrase'), 'authorShort' => ''],
            'authorProfile' => ['displayName' => $authorName, 'fullName' => $fullName, 'shortBio' => '', 'longBio' => '', 'site' => '', 'social' => ''],
            'elements' => $elements,
            'elementOrder' => $order,
            'edition' => ['formats' => [], 'edition' => '1ª edição', 'year' => gmdate('Y'), 'place' => '', 'publisherType' => 'independent', 'publisherName' => '', 'trimSize' => '14 × 21 cm'],
            'layoutBrief' => ['style' => '', 'dropCaps' => false, 'chapterOpening' => '', 'quoteHighlights' => true, 'footnotes' => false, 'images' => false, 'boxes' => false, 'specialElements' => '', 'notes' => ''],
            'coverBrief' => ['concept' => '', 'feeling' => '', 'includeElements' => '', 'avoidElements' => '', 'palette' => '', 'visualReferences' => '', 'coverPhrase' => ''],
            'opinion' => ['summary' => '', 'strengths' => '', 'attention' => '', 'recommendations' => '', 'risks' => '', 'conclusion' => ''],
            'religious' => ['nature' => '', 'bible' => false, 'catechism' => false, 'magisterium' => false, 'specializedReview' => false],
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    private function normalizeFields(array $fields): array
    {
        $clean = [];
        foreach ($fields as $section => $value) {
            $sectionKey = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $section);
            if (! is_string($sectionKey) || $sectionKey === '') continue;
            $clean[$sectionKey] = is_array($value) ? $this->sanitizeArray($value) : sanitize_textarea_field((string) $value);
        }
        return $clean;
    }

    /** @param array<mixed> $value
     *  @return array<mixed>
     */
    private function sanitizeArray(array $value): array
    {
        $clean = [];
        foreach ($value as $key => $item) {
            $targetKey = is_int($key) ? $key : preg_replace('/[^A-Za-z0-9_-]/', '', (string) $key);
            if (! is_int($targetKey) && (! is_string($targetKey) || $targetKey === '')) continue;
            if (is_array($item)) $clean[$targetKey] = $this->sanitizeArray($item);
            elseif (is_bool($item)) $clean[$targetKey] = $item;
            elseif (is_int($item) || is_float($item)) $clean[$targetKey] = $item;
            else $clean[$targetKey] = sanitize_textarea_field((string) $item);
        }
        return $clean;
    }

    /** @param array<string, mixed> $flags
     *  @return array<string, bool>
     */
    private function normalizeFlags(array $flags): array
    {
        $clean = [];
        foreach (array_keys(self::MANUAL_FLAGS) as $key) $clean[$key] = (bool) ($flags[$key] ?? false);
        return $clean;
    }

    /** @param array<string, mixed> $items
     *  @return array<string, array<string, string>>
     */
    private function normalizeAssessments(array $items): array
    {
        $clean = [];
        foreach (self::ASSESSMENT_CRITERIA as $key => $label) {
            $item = is_array($items[$key] ?? null) ? $items[$key] : [];
            $status = sanitize_key((string) ($item['status'] ?? 'pending'));
            if (! in_array($status, ['pending', 'approved', 'adjust'], true)) $status = 'pending';
            $clean[$key] = ['key' => $key, 'label' => $label, 'status' => $status, 'note' => trim(sanitize_textarea_field((string) ($item['note'] ?? '')))];
        }
        return $clean;
    }

    /** @return array<int, array<string, mixed>> */
    private function rounds(int $bookId): array
    {
        $rounds = get_post_meta($bookId, '_verbum_editorial_desk_rounds', true);
        return is_array($rounds) ? array_values(array_filter($rounds, 'is_array')) : [];
    }

    /** @param array<int, array<string, mixed>> $rounds */
    private function storeRounds(int $bookId, array $rounds): void
    {
        update_post_meta($bookId, '_verbum_editorial_desk_rounds', array_values($rounds));
    }

    /** @param array<int, array<string, mixed>> $rounds
     *  @return array<string, mixed>|null
     */
    private function currentRound(array $rounds, string $versionId)
    {
        foreach (array_reverse($rounds) as $round) if (is_array($round) && (string) ($round['versionId'] ?? '') === $versionId) return $round;
        return null;
    }

    /** @param array<string, mixed> $round
     *  @return array<string, mixed>
     */
    private function roundSummary(array $round): array
    {
        return [
            'id' => (string) ($round['id'] ?? ''),
            'number' => (int) ($round['number'] ?? 0),
            'versionId' => (string) ($round['versionId'] ?? ''),
            'versionNumber' => (string) ($round['versionNumber'] ?? ''),
            'versionName' => (string) ($round['versionName'] ?? ''),
            'versionHash' => (string) ($round['versionHash'] ?? ''),
            'status' => (string) ($round['status'] ?? 'in_review'),
            'startedAt' => (string) ($round['startedAt'] ?? ''),
            'updatedAt' => (string) ($round['updatedAt'] ?? ''),
            'completedAt' => (string) ($round['completedAt'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $version
     *  @return array<string, mixed>
     */
    private function versionSummary(array $version): array
    {
        return [
            'id' => (string) ($version['id'] ?? ''),
            'number' => (string) ($version['number'] ?? ''),
            'name' => (string) ($version['name'] ?? ''),
            'hash' => (string) ($version['hash'] ?? ''),
            'chapterCount' => (int) ($version['chapterCount'] ?? 0),
            'wordCount' => (int) ($version['wordCount'] ?? 0),
            'createdAt' => (string) ($version['createdAt'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $round */
    private function assertRoundMutable(array $round): void
    {
        if ((string) ($round['status'] ?? '') === 'approved_for_layout') throw new ValidationError('A rodada editorial aprovada é imutável. Inicie uma nova rodada a partir de uma nova versão auditada.');
    }

    /** @param array<int, mixed> $chapters */
    private function chapterTitle(array $chapters, string $chapterId): string
    {
        if ($chapterId === '' || $chapterId === '0') return '';
        foreach ($chapters as $chapter) if (is_array($chapter) && (string) ($chapter['id'] ?? '') === $chapterId) return (string) ($chapter['title'] ?? '');
        return '';
    }

    /** @param array<string, string> $options
     *  @return array<int, array<string, string>>
     */
    private function options(array $options): array
    {
        $result = [];
        foreach ($options as $key => $label) $result[] = ['key' => $key, 'label' => $label];
        return $result;
    }

    private function statusLabel(string $status, bool $requiresNewAudit, int $openBlocking): string
    {
        if ($status === 'approved_for_layout') return 'Aprovada para Diagramação';
        if ($requiresNewAudit || $openBlocking > 0 || $status === 'adjustments_requested') return 'Ajustes solicitados';
        if ($status === 'ready_for_decision') return 'Pronta para decisão';
        return 'Em avaliação';
    }

    private function markApprovedVersion(int $bookId, string $versionId): void
    {
        $versions = get_post_meta($bookId, '_verbum_work_versions', true);
        $versions = is_array($versions) ? $versions : [];
        foreach ($versions as &$version) {
            if (! is_array($version) || (string) ($version['id'] ?? '') !== $versionId) continue;
            $version['protected'] = true;
            $version['editorialDeskApproved'] = true;
            $version['editorialDeskApprovedAt'] = gmdate('c');
            break;
        }
        unset($version);
        update_post_meta($bookId, '_verbum_work_versions', $versions);
    }

    private function touchBook(int $bookId): void
    {
        $book = get_post($bookId);
        if ($book instanceof \WP_Post) wp_update_post(['ID' => $bookId, 'post_content' => $book->post_content]);
    }
}
