<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class FoundationReaderResultRepository
{
    private const NEEDS_META = '_verbum_foundation_reader_needs';
    private const TRANSFORMATION_META = '_verbum_work_project_transformation';
    private const DIFFERENTIAL_META = '_verbum_work_project_differentials';
    private const INCLUDED_META = '_verbum_foundation_scope_included';
    private const EXCLUDED_META = '_verbum_foundation_scope_excluded';
    private const REVISION_META = '_verbum_foundation_reader_result_revision';
    private const UPDATED_META = '_verbum_foundation_reader_result_updated_at';
    private const COMPLETED_AT_META = '_verbum_foundation_reader_result_completed_at';
    private const SUBSTEPS_META = '_verbum_foundation_substeps';

    /** @return array<string, mixed> */
    public function data(int $bookId): array
    {
        $completed = get_post_meta($bookId, self::SUBSTEPS_META, true);
        $completed = is_array($completed) ? $completed : [];
        $audience = trim((string) get_post_meta($bookId, '_verbum_audience', true));
        $needs = trim((string) get_post_meta($bookId, self::NEEDS_META, true));
        $transformation = trim((string) get_post_meta($bookId, self::TRANSFORMATION_META, true));
        $differential = trim((string) get_post_meta($bookId, self::DIFFERENTIAL_META, true));

        return [
            'substep' => 'reader-result', 'order' => 3, 'total' => 4,
            'audience' => $audience,
            'needs' => $needs,
            'transformation' => $transformation,
            'differential' => $differential,
            'scopeIncluded' => trim((string) get_post_meta($bookId, self::INCLUDED_META, true)),
            'scopeExcluded' => trim((string) get_post_meta($bookId, self::EXCLUDED_META, true)),
            'revision' => max(0, (int) get_post_meta($bookId, self::REVISION_META, true)),
            'updatedAt' => (string) get_post_meta($bookId, self::UPDATED_META, true),
            'completedAt' => (string) get_post_meta($bookId, self::COMPLETED_AT_META, true),
            'completed' => in_array('reader-result', $completed, true),
            'intentionCompleted' => in_array('intention', $completed, true),
            'ready' => $audience !== '' && $needs !== '' && $transformation !== '' && $differential !== '',
            'legacy' => [
                'readerNeed' => trim((string) get_post_meta($bookId, '_verbum_reader_problem', true)),
                'benefits' => trim((string) get_post_meta($bookId, '_verbum_work_project_benefits', true)),
                'valueProposition' => trim((string) get_post_meta($bookId, '_verbum_work_project_value_proposition', true)),
                'limits' => trim((string) get_post_meta($bookId, '_verbum_work_project_limits', true)),
            ],
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $bookId, array $fields): array
    {
        $currentRevision = max(0, (int) get_post_meta($bookId, self::REVISION_META, true));
        $baseRevision = array_key_exists('base_revision', $fields) ? max(0, (int) $fields['base_revision']) : $currentRevision;
        if ($baseRevision !== $currentRevision) throw new ValidationError('Este rascunho foi atualizado em outra sessão. Recarregue a página antes de salvar novamente.');
        $map = [
            'needs' => self::NEEDS_META, 'transformation' => self::TRANSFORMATION_META,
            'differential' => self::DIFFERENTIAL_META, 'scope_included' => self::INCLUDED_META,
            'scope_excluded' => self::EXCLUDED_META,
        ];
        foreach ($map as $field => $meta) if (array_key_exists($field, $fields)) update_post_meta($bookId, $meta, sanitize_textarea_field((string) $fields[$field]));
        update_post_meta($bookId, self::REVISION_META, $currentRevision + 1);
        update_post_meta($bookId, self::UPDATED_META, gmdate('c'));
        $this->touchBook($bookId);
        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function complete(int $bookId): array
    {
        $data = $this->data($bookId);
        if (! $data['intentionCompleted']) throw new ValidationError('Conclua Fundação 2 — Intenção antes de concluir Leitor e Resultado.');
        $pending = [];
        if ($data['audience'] === '') $pending[] = 'Público principal na Identificação';
        if ($data['needs'] === '') $pending[] = 'Necessidades do leitor';
        if ($data['transformation'] === '') $pending[] = 'Transformação esperada';
        if ($data['differential'] === '') $pending[] = 'Diferencial da obra';
        if ($pending !== []) throw new ValidationError('Complete Fundação 3 — Leitor e Resultado antes de avançar: ' . implode(', ', $pending) . '.');
        $completed = get_post_meta($bookId, self::SUBSTEPS_META, true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('reader-result', $completed, true)) $completed[] = 'reader-result';
        update_post_meta($bookId, self::SUBSTEPS_META, array_values(array_unique($completed)));
        update_post_meta($bookId, self::COMPLETED_AT_META, gmdate('c'));
        update_post_meta($bookId, self::UPDATED_META, gmdate('c'));
        $this->touchBook($bookId);
        return $this->data($bookId);
    }

    private function touchBook(int $bookId): void
    {
        $post = get_post($bookId);
        if ($post instanceof \WP_Post) wp_update_post(['ID' => $bookId, 'post_content' => $post->post_content]);
    }
}
