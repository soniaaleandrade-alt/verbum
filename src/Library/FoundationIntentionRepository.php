<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class FoundationIntentionRepository
{
    private const PROBLEM_META = '_verbum_reader_problem';
    private const PURPOSE_META = '_verbum_work_project_purpose';
    private const GENERAL_OBJECTIVE_META = '_verbum_work_project_general_objective';
    private const OBJECTIVES_META = '_verbum_work_project_specific_objectives';
    private const REVISION_META = '_verbum_foundation_intention_revision';
    private const UPDATED_META = '_verbum_foundation_intention_updated_at';
    private const COMPLETED_AT_META = '_verbum_foundation_intention_completed_at';
    private const SUBSTEPS_META = '_verbum_foundation_substeps';

    /** @return array<string, mixed> */
    public function data(int $bookId): array
    {
        $objectives = get_post_meta($bookId, self::OBJECTIVES_META, true);
        $objectives = is_array($objectives) ? $objectives : [];
        $clean = [];
        foreach ($objectives as $index => $objective) {
            if (! is_array($objective)) {
                continue;
            }
            $text = trim((string) ($objective['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $id = sanitize_key((string) ($objective['id'] ?? ''));
            if ($id === '') {
                $id = 'objective-' . ($index + 1);
            }
            $clean[] = ['id' => $id, 'text' => $text, 'order' => max(1, (int) ($objective['order'] ?? ($index + 1)))];
        }
        usort($clean, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $completed = get_post_meta($bookId, self::SUBSTEPS_META, true);
        $completed = is_array($completed) ? $completed : [];
        $problem = trim((string) get_post_meta($bookId, self::PROBLEM_META, true));
        $purpose = trim((string) get_post_meta($bookId, self::PURPOSE_META, true));
        $general = trim((string) get_post_meta($bookId, self::GENERAL_OBJECTIVE_META, true));

        return [
            'substep' => 'intention',
            'order' => 2,
            'total' => 4,
            'problem' => $problem,
            'purpose' => $purpose,
            'generalObjective' => $general,
            'specificObjectives' => $clean,
            'revision' => max(0, (int) get_post_meta($bookId, self::REVISION_META, true)),
            'updatedAt' => (string) get_post_meta($bookId, self::UPDATED_META, true),
            'completedAt' => (string) get_post_meta($bookId, self::COMPLETED_AT_META, true),
            'completed' => in_array('intention', $completed, true),
            'letterSoulCompleted' => in_array('letter-soul', $completed, true),
            'ready' => $problem !== '' && $purpose !== '' && $general !== '' && count($clean) > 0,
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $bookId, array $fields): array
    {
        $currentRevision = max(0, (int) get_post_meta($bookId, self::REVISION_META, true));
        $baseRevision = array_key_exists('base_revision', $fields) ? max(0, (int) $fields['base_revision']) : $currentRevision;
        if ($baseRevision !== $currentRevision) {
            throw new ValidationError('Este rascunho foi atualizado em outra sessão. Recarregue a página antes de salvar novamente.');
        }

        if (array_key_exists('problem', $fields)) {
            update_post_meta($bookId, self::PROBLEM_META, sanitize_textarea_field((string) $fields['problem']));
        }
        if (array_key_exists('purpose', $fields)) {
            update_post_meta($bookId, self::PURPOSE_META, sanitize_textarea_field((string) $fields['purpose']));
        }
        if (array_key_exists('general_objective', $fields)) {
            update_post_meta($bookId, self::GENERAL_OBJECTIVE_META, sanitize_textarea_field((string) $fields['general_objective']));
        }
        if (array_key_exists('specific_objectives', $fields)) {
            update_post_meta($bookId, self::OBJECTIVES_META, $this->sanitizeObjectives($fields['specific_objectives']));
        }

        update_post_meta($bookId, self::REVISION_META, $currentRevision + 1);
        update_post_meta($bookId, self::UPDATED_META, gmdate('c'));
        $this->touchBook($bookId);
        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function complete(int $bookId): array
    {
        $data = $this->data($bookId);
        if (! $data['letterSoulCompleted']) {
            throw new ValidationError('Conclua Fundação 1 — Carta e Alma antes de concluir a Intenção.');
        }
        $pending = [];
        if ($data['problem'] === '') $pending[] = 'Problema ou necessidade';
        if ($data['purpose'] === '') $pending[] = 'Propósito da obra';
        if ($data['generalObjective'] === '') $pending[] = 'Objetivo geral';
        if (count($data['specificObjectives']) === 0) $pending[] = 'ao menos um objetivo específico';
        if ($pending !== []) {
            throw new ValidationError('Complete Fundação 2 — Intenção antes de avançar: ' . implode(', ', $pending) . '.');
        }

        $completed = get_post_meta($bookId, self::SUBSTEPS_META, true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('intention', $completed, true)) $completed[] = 'intention';
        update_post_meta($bookId, self::SUBSTEPS_META, array_values(array_unique($completed)));
        update_post_meta($bookId, self::COMPLETED_AT_META, gmdate('c'));
        update_post_meta($bookId, self::UPDATED_META, gmdate('c'));
        $this->touchBook($bookId);
        return $this->data($bookId);
    }

    /** @param mixed $items
     *  @return array<int, array<string, mixed>>
     */
    private function sanitizeObjectives($items): array
    {
        $items = is_array($items) ? $items : [];
        $clean = [];
        $seen = [];
        foreach ($items as $index => $objective) {
            if (! is_array($objective)) continue;
            $text = trim(preg_replace('/\s+/u', ' ', sanitize_textarea_field((string) ($objective['text'] ?? ''))) ?? '');
            if ($text === '' || ! preg_match('/[\p{L}\p{N}]/u', $text)) continue;
            $key = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $id = sanitize_key((string) ($objective['id'] ?? ''));
            if ($id === '' || strpos($id, 'new-') === 0) {
                $id = 'objective-' . substr(md5($text . '|' . $index . '|' . microtime(true)), 0, 12);
            }
            $clean[] = ['id' => $id, 'text' => $text, 'order' => count($clean) + 1];
        }
        return $clean;
    }

    private function touchBook(int $bookId): void
    {
        $post = get_post($bookId);
        if ($post instanceof \WP_Post) {
            wp_update_post(['ID' => $bookId, 'post_content' => $post->post_content]);
        }
    }
}
