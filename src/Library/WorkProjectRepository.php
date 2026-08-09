<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class WorkProjectRepository
{
    private const CHECKLIST = [
        'general_objective' => 'Objetivo geral',
        'specific_objectives' => 'Objetivos específicos',
        'purpose' => 'Finalidade',
        'audience' => 'Público',
        'benefits' => 'Benefícios',
        'transformation' => 'Transformação',
        'central_message' => 'Mensagem central',
        'differentials' => 'Diferenciais',
        'value_proposition' => 'Proposta de valor',
        'motivation' => 'Motivação',
        'verse' => 'Versículo',
        'guiding_phrase' => 'Frase norteadora',
    ];

    private const TEXT_FIELDS = [
        'general_objective',
        'purpose',
        'audience',
        'benefits',
        'transformation',
        'central_message',
        'differentials',
        'value_proposition',
        'keyword',
        'motivation',
        'verse',
        'guiding_phrase',
    ];

    /** @return array<string, mixed> */
    public function data(int $bookId): array
    {
        $values = [];
        foreach (self::TEXT_FIELDS as $field) {
            $values[$this->camelCase($field)] = trim((string) get_post_meta($bookId, '_verbum_work_project_' . $field, true));
        }

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

        $raw = [
            'general_objective' => $values['generalObjective'],
            'specific_objectives' => array_values(array_filter($specificObjectives, static fn (array $item): bool => trim($item['text']) !== '')),
            'purpose' => $values['purpose'],
            'audience' => $values['audience'],
            'benefits' => $values['benefits'],
            'transformation' => $values['transformation'],
            'central_message' => $values['centralMessage'],
            'differentials' => $values['differentials'],
            'value_proposition' => $values['valueProposition'],
            'motivation' => $values['motivation'],
            'verse' => $values['verse'],
            'guiding_phrase' => $values['guidingPhrase'],
        ];

        $checklist = [];
        $completedCount = 0;
        foreach (self::CHECKLIST as $key => $label) {
            $completed = $key === 'specific_objectives'
                ? count($raw[$key]) > 0
                : trim((string) $raw[$key]) !== '';
            if ($completed) {
                $completedCount++;
            }
            $checklist[] = [
                'key' => $key,
                'label' => $label,
                'completed' => $completed,
            ];
        }

        $completedStages = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $total = count(self::CHECKLIST);

        return [
            'progress' => (int) round(($completedCount / $total) * 100),
            'completedCount' => $completedCount,
            'total' => $total,
            'ready' => $completedCount === $total,
            'completed' => in_array('project', $completedStages, true),
            'checklist' => $checklist,
            'values' => $values,
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $bookId, array $fields): array
    {
        foreach (self::TEXT_FIELDS as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }
            update_post_meta($bookId, '_verbum_work_project_' . $field, sanitize_textarea_field((string) $fields[$field]));
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
                if ($id === '' || str_starts_with($id, 'new-')) {
                    $id = 'objective-' . substr(md5($text . '|' . $index . '|' . microtime(true)), 0, 12);
                }
                $clean[] = [
                    'id' => $id,
                    'text' => $text,
                    'order' => max(1, (int) ($objective['order'] ?? ($index + 1))),
                ];
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
                $currentStage = (string) (get_post_meta($bookId, '_verbum_stage', true) ?: 'project');
                $laterStages = ['project', 'planning', 'development', 'general_review', 'versions', 'audit', 'editorial_desk', 'layout', 'legal', 'publication'];
                if (in_array($currentStage, $laterStages, true)) {
                    update_post_meta($bookId, '_verbum_stage', 'project');
                }
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
                array_values(array_filter($data['checklist'], static fn (array $item): bool => ! $item['completed']))
            );
            throw new ValidationError('Complete o Projeto da Obra antes de continuar: ' . implode(', ', $pending) . '.');
        }

        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('identification', $completed, true)) {
            throw new ValidationError('Conclua a Identificação da Obra antes do Projeto da Obra.');
        }
        if (! in_array('project', $completed, true)) {
            $completed[] = 'project';
        }

        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        update_post_meta($bookId, '_verbum_stage', 'planning');
        update_post_meta($bookId, '_verbum_work_project_completed_at', gmdate('c'));
        $this->touchBook($bookId);

        return $this->data($bookId);
    }

    private function touchBook(int $bookId): void
    {
        $post = get_post($bookId);
        if (! $post instanceof \WP_Post) {
            return;
        }
        wp_update_post([
            'ID' => $bookId,
            'post_content' => $post->post_content,
        ]);
    }

    private function camelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
