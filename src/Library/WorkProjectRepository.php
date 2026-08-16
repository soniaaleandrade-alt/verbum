<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class WorkProjectRepository
{
    private const ESSENTIAL = [
        'theme' => 'Tema central',
        'purpose' => 'Propósito da obra',
        'central_question' => 'Pergunta essencial',
        'main_thesis' => 'Tese principal',
        'general_objective' => 'Objetivo geral',
        'audience_main' => 'Público-alvo principal',
    ];

    private const RECOMMENDED = [
        'specific_objectives' => 'Objetivos específicos',
        'audience_description' => 'Descrição do público',
        'reader_need' => 'Necessidade do leitor',
        'transformation' => 'Transformação esperada',
        'differentials' => 'Diferencial da obra',
        'methodology' => 'Metodologia',
        'presentation_form' => 'Forma narrativa',
        'approach' => 'Abordagem',
    ];

    private const OPTIONAL = [
        'secondary_audience' => 'Público secundário',
        'overview' => 'Síntese da Fundação',
        'keywords' => 'Palavras-chave',
        'limits' => 'Limites da obra',
        'motivation' => 'Motivação pessoal',
        'verse' => 'Versículo inspirador',
        'guiding_phrase' => 'Frase norteadora',
    ];

    private const PROJECT_TEXT_FIELDS = [
        'theme', 'general_objective', 'purpose', 'audience', 'secondary_audience', 'benefits',
        'transformation', 'central_message', 'differentials', 'value_proposition', 'keyword',
        'limits', 'motivation', 'verse', 'guiding_phrase',
    ];

    private const PLANNING_SHARED_FIELDS = [
        'central_question', 'main_thesis', 'overview', 'methodology', 'presentation_form', 'approach',
    ];

    /** @return array<string, mixed> */
    public function data(int $bookId): array
    {
        $values = [];
        foreach (self::PROJECT_TEXT_FIELDS as $field) {
            $values[$this->camelCase($field)] = trim((string) get_post_meta($bookId, '_verbum_work_project_' . $field, true));
        }
        foreach (self::PLANNING_SHARED_FIELDS as $field) {
            $values[$this->camelCase($field)] = trim((string) get_post_meta($bookId, '_verbum_planning_' . $field, true));
        }

        $values['audienceMain'] = trim((string) get_post_meta($bookId, '_verbum_audience', true));
        $values['readerNeed'] = trim((string) get_post_meta($bookId, '_verbum_reader_problem', true));
        $values['keywords'] = $this->keywords($bookId);
        $values['benefitsConsolidated'] = (bool) get_post_meta($bookId, '_verbum_work_project_benefits_consolidated', true);
        $values['valuePropositionConsolidated'] = (bool) get_post_meta($bookId, '_verbum_work_project_value_proposition_consolidated', true);

        $specificObjectives = get_post_meta($bookId, '_verbum_work_project_specific_objectives', true);
        $specificObjectives = is_array($specificObjectives) ? $specificObjectives : [];
        $specificObjectives = array_values(array_map(function ($item, int $index): array {
            $item = is_array($item) ? $item : [];
            return [
                'id' => sanitize_key((string) ($item['id'] ?? ('objective-' . ($index + 1)))),
                'text' => trim((string) ($item['text'] ?? '')),
                'order' => max(1, (int) ($item['order'] ?? ($index + 1))),
            ];
        }, $specificObjectives, array_keys($specificObjectives)));
        usort($specificObjectives, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
        $values['specificObjectives'] = $specificObjectives;

        $flags = [
            'theme' => $values['theme'] !== '',
            'purpose' => $values['purpose'] !== '',
            'central_question' => $values['centralQuestion'] !== '',
            'main_thesis' => $values['mainThesis'] !== '',
            'general_objective' => $values['generalObjective'] !== '',
            'audience_main' => $values['audienceMain'] !== '',
            'specific_objectives' => count(array_filter($specificObjectives, static fn (array $item): bool => trim((string) $item['text']) !== '')) > 0,
            'audience_description' => $values['audience'] !== '',
            'reader_need' => $values['readerNeed'] !== '',
            'transformation' => $values['transformation'] !== '',
            'differentials' => $values['differentials'] !== '',
            'methodology' => $values['methodology'] !== '',
            'presentation_form' => $values['presentationForm'] !== '',
            'approach' => $values['approach'] !== '',
            'secondary_audience' => $values['secondaryAudience'] !== '',
            'overview' => $values['overview'] !== '',
            'keywords' => count($values['keywords']) > 0,
            'limits' => $values['limits'] !== '',
            'motivation' => $values['motivation'] !== '',
            'verse' => $values['verse'] !== '',
            'guiding_phrase' => $values['guidingPhrase'] !== '',
        ];

        $essential = $this->groupChecklist(self::ESSENTIAL, $flags);
        $recommended = $this->groupChecklist(self::RECOMMENDED, $flags);
        $optional = $this->groupChecklist(self::OPTIONAL, $flags);
        $completedCount = count(array_filter($essential, static fn (array $item): bool => $item['completed']));
        $total = count($essential);

        $completedStages = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];

        return [
            'progress' => (int) round(($completedCount / $total) * 100),
            'completedCount' => $completedCount,
            'total' => $total,
            'ready' => $completedCount === $total,
            'completed' => in_array('project', $completedStages, true),
            'checklist' => $essential,
            'essential' => $essential,
            'recommended' => $recommended,
            'optional' => $optional,
            'recommendedCount' => count(array_filter($recommended, static fn (array $item): bool => $item['completed'])),
            'recommendedTotal' => count($recommended),
            'optionalCount' => count(array_filter($optional, static fn (array $item): bool => $item['completed'])),
            'optionalTotal' => count($optional),
            'values' => $values,
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $bookId, array $fields): array
    {
        foreach (self::PROJECT_TEXT_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                update_post_meta($bookId, '_verbum_work_project_' . $field, sanitize_textarea_field((string) $fields[$field]));
            }
        }

        foreach (self::PLANNING_SHARED_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                update_post_meta($bookId, '_verbum_planning_' . $field, sanitize_textarea_field((string) $fields[$field]));
            }
        }

        if (array_key_exists('audience_main', $fields)) {
            update_post_meta($bookId, '_verbum_audience', sanitize_text_field((string) $fields['audience_main']));
        }
        if (array_key_exists('reader_need', $fields)) {
            update_post_meta($bookId, '_verbum_reader_problem', sanitize_textarea_field((string) $fields['reader_need']));
        }
        if (array_key_exists('keywords', $fields)) {
            $keywords = is_array($fields['keywords']) ? $fields['keywords'] : [];
            $keywords = array_values(array_unique(array_filter(array_map(
                static fn ($item): string => trim(sanitize_text_field((string) $item)),
                $keywords
            ))));
            update_post_meta($bookId, '_verbum_keywords', $keywords);
        }

        foreach (['benefits_consolidated', 'value_proposition_consolidated'] as $field) {
            if (array_key_exists($field, $fields)) {
                update_post_meta($bookId, '_verbum_work_project_' . $field, $fields[$field] ? 1 : 0);
            }
        }

        if (array_key_exists('specific_objectives', $fields)) {
            $objectives = is_array($fields['specific_objectives']) ? $fields['specific_objectives'] : [];
            $clean = [];
            foreach ($objectives as $index => $objective) {
                if (! is_array($objective)) {
                    continue;
                }
                $text = trim(sanitize_textarea_field((string) ($objective['text'] ?? '')));
                if ($text === '') {
                    continue;
                }
                $id = sanitize_key((string) ($objective['id'] ?? ''));
                if ($id === '' || strpos($id, 'new-') === 0) {
                    $id = 'objective-' . substr(md5($text . '|' . $index . '|' . microtime(true)), 0, 12);
                }
                $clean[] = ['id' => $id, 'text' => $text, 'order' => max(1, (int) ($objective['order'] ?? ($index + 1)))];
            }
            usort($clean, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
            foreach ($clean as $index => &$objective) {
                $objective['order'] = $index + 1;
            }
            unset($objective);
            update_post_meta($bookId, '_verbum_work_project_specific_objectives', $clean);
        }

        $this->touchBook($bookId);
        $data = $this->data($bookId);

        if (! $data['ready']) {
            $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
            $completed = is_array($completed) ? $completed : [];
            if (in_array('project', $completed, true)) {
                update_post_meta($bookId, '_verbum_completed_stages', array_values(array_diff($completed, ['project'])));
            }
        }

        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function complete(int $bookId): array
    {
        $data = $this->data($bookId);
        if (! $data['ready']) {
            $pending = array_map(
                static fn (array $item): string => (string) $item['label'],
                array_values(array_filter($data['essential'], static fn (array $item): bool => ! $item['completed']))
            );
            throw new ValidationError('Complete a Fundação da Obra antes de continuar: ' . implode(', ', $pending) . '.');
        }

        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('identification', $completed, true)) {
            throw new ValidationError('Conclua a Identificação da Obra antes da Fundação.');
        }
        if (! in_array('project', $completed, true)) {
            $completed[] = 'project';
        }

        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        $currentStage = (string) (get_post_meta($bookId, '_verbum_stage', true) ?: 'project');
        if (in_array($currentStage, ['identification', 'project'], true)) {
            update_post_meta($bookId, '_verbum_stage', 'planning');
        }
        update_post_meta($bookId, '_verbum_work_project_completed_at', gmdate('c'));
        $this->touchBook($bookId);

        return $this->data($bookId);
    }

    /** @param array<string, string> $labels
     *  @param array<string, bool> $flags
     *  @return array<int, array<string, mixed>>
     */
    private function groupChecklist(array $labels, array $flags): array
    {
        $items = [];
        foreach ($labels as $key => $label) {
            $items[] = ['key' => $key, 'label' => $label, 'completed' => (bool) ($flags[$key] ?? false)];
        }
        return $items;
    }

    /** @return string[] */
    private function keywords(int $bookId): array
    {
        $sources = [
            get_post_meta($bookId, '_verbum_keywords', true),
            get_post_meta($bookId, '_verbum_keyword', true),
            get_post_meta($bookId, '_verbum_work_project_keyword', true),
        ];
        $result = [];
        foreach ($sources as $source) {
            $items = is_array($source) ? $source : preg_split('/[,;]+/', (string) $source);
            foreach (is_array($items) ? $items : [] as $item) {
                $value = trim((string) $item);
                if ($value !== '' && ! in_array($value, $result, true)) {
                    $result[] = $value;
                }
            }
        }
        return $result;
    }

    private function touchBook(int $bookId): void
    {
        $post = get_post($bookId);
        if ($post instanceof \WP_Post) {
            wp_update_post(['ID' => $bookId, 'post_content' => $post->post_content]);
        }
    }

    private function camelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
