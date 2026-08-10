<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class WorkPlanningRepository
{
    private const CHECKLIST = [
        'central_question' => 'Pergunta central',
        'main_thesis' => 'Tese principal',
        'overview' => 'Visão geral',
        'methodology' => 'Metodologia',
        'presentation_form' => 'Forma de apresentação',
        'approach' => 'Abordagem',
        'general_structure' => 'Estrutura geral',
        'provisional_index' => 'Índice provisório',
        'work_goal' => 'Meta da obra',
        'chapters_generated' => 'Capítulos gerados',
    ];

    private const TEXT_FIELDS = [
        'central_question', 'main_thesis', 'overview', 'methodology', 'presentation_form', 'approach',
        'general_structure', 'editorial_notes', 'writing_strategy', 'initial_schedule',
    ];

    /** @return array<string, mixed> */
    public function data(int $bookId): array
    {
        $values = [];
        foreach (self::TEXT_FIELDS as $field) {
            $values[$this->camelCase($field)] = trim((string) get_post_meta($bookId, '_verbum_planning_' . $field, true));
        }

        foreach (['target_chapters', 'target_words', 'target_pages'] as $field) {
            $values[$this->camelCase($field)] = max(0, (int) get_post_meta($bookId, '_verbum_planning_' . $field, true));
        }

        $items = get_post_meta($bookId, '_verbum_planning_structure_items', true);
        $items = is_array($items) ? $items : [];
        $items = $this->normalizeItems($items);
        $values['structureItems'] = $items;

        $counts = $this->counts($items);
        $generated = $this->generatedChapterIds($bookId);
        $chapterItemIds = array_values(array_map(static fn (array $item): string => (string) $item['id'], array_filter($items, static fn (array $item): bool => $item['type'] === 'chapter')));
        $generatedItemIds = array_values(array_filter(array_map(static function (int $chapterId): string {
            return (string) get_post_meta($chapterId, '_verbum_planning_item_id', true);
        }, $generated)));
        $chaptersGenerated = count($chapterItemIds) > 0 && count(array_diff($chapterItemIds, $generatedItemIds)) === 0;

        $raw = [
            'central_question' => $values['centralQuestion'],
            'main_thesis' => $values['mainThesis'],
            'overview' => $values['overview'],
            'methodology' => $values['methodology'],
            'presentation_form' => $values['presentationForm'],
            'approach' => $values['approach'],
            'general_structure' => $values['generalStructure'],
            'provisional_index' => count($items) > 0,
            'work_goal' => $values['targetChapters'] > 0 && $values['targetWords'] > 0 && $values['targetPages'] > 0,
            'chapters_generated' => $chaptersGenerated,
        ];

        $checklist = [];
        $completedCount = 0;
        foreach (self::CHECKLIST as $key => $label) {
            $completed = is_bool($raw[$key]) ? $raw[$key] : trim((string) $raw[$key]) !== '';
            if ($completed) {
                $completedCount++;
            }
            $checklist[] = ['key' => $key, 'label' => $label, 'completed' => $completed];
        }

        $completedStages = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $total = count(self::CHECKLIST);

        return [
            'progress' => (int) round(($completedCount / $total) * 100),
            'completedCount' => $completedCount,
            'total' => $total,
            'ready' => $completedCount === $total,
            'completed' => in_array('planning', $completedStages, true),
            'checklist' => $checklist,
            'values' => $values,
            'counts' => $counts,
            'generatedChapterIds' => array_map('strval', $generated),
            'chaptersGenerated' => $chaptersGenerated,
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $bookId, array $fields): array
    {
        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                update_post_meta($bookId, '_verbum_planning_' . $field, sanitize_textarea_field((string) $fields[$field]));
            }
        }

        foreach (['target_chapters', 'target_words', 'target_pages'] as $field) {
            if (array_key_exists($field, $fields)) {
                update_post_meta($bookId, '_verbum_planning_' . $field, max(0, (int) $fields[$field]));
            }
        }

        if (array_key_exists('structure_items', $fields)) {
            $items = is_array($fields['structure_items']) ? $fields['structure_items'] : [];
            update_post_meta($bookId, '_verbum_planning_structure_items', $this->normalizeItems($items, true));
        }

        $this->touchBook($bookId);
        $data = $this->data($bookId);
        if (! $data['ready']) {
            $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
            $completed = is_array($completed) ? $completed : [];
            if (in_array('planning', $completed, true)) {
                update_post_meta($bookId, '_verbum_completed_stages', array_values(array_diff($completed, ['planning'])));
                $currentStage = (string) (get_post_meta($bookId, '_verbum_stage', true) ?: 'planning');
                if (in_array($currentStage, ['planning', 'development', 'general_review', 'versions', 'audit', 'editorial_desk', 'layout', 'legal', 'publication'], true)) {
                    update_post_meta($bookId, '_verbum_stage', 'planning');
                }
            }
        }

        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function generateChapters(int $userId, int $bookId): array
    {
        $data = $this->data($bookId);
        $items = $data['values']['structureItems'];
        $chapterItems = array_values(array_filter($items, static fn (array $item): bool => $item['type'] === 'chapter'));
        if (count($chapterItems) === 0) {
            throw new ValidationError('Adicione pelo menos um item do tipo Capítulo ao índice provisório.');
        }

        $existing = [];
        foreach ($this->generatedChapterIds($bookId) as $chapterId) {
            $itemId = (string) get_post_meta($chapterId, '_verbum_planning_item_id', true);
            if ($itemId !== '') {
                $existing[$itemId] = $chapterId;
            }
        }

        foreach ($chapterItems as $index => $item) {
            $itemId = (string) $item['id'];
            if (isset($existing[$itemId])) {
                wp_update_post(['ID' => $existing[$itemId], 'post_title' => (string) $item['title']]);
                update_post_meta($existing[$itemId], '_verbum_chapter_order', $index + 1);
                continue;
            }

            $chapterId = wp_insert_post([
                'post_type' => LibraryPostTypes::CHAPTER,
                'post_status' => 'publish',
                'post_title' => (string) $item['title'],
                'post_content' => '',
                'post_author' => $userId,
            ], true);
            if (is_wp_error($chapterId)) {
                throw new \RuntimeException('Não foi possível gerar os capítulos da obra.');
            }
            $chapterId = (int) $chapterId;
            update_post_meta($chapterId, '_verbum_book_id', $bookId);
            update_post_meta($chapterId, '_verbum_planning_item_id', $itemId);
            update_post_meta($chapterId, '_verbum_chapter_order', $index + 1);
            update_post_meta($chapterId, '_verbum_chapter_stage', 'preparation');
            update_post_meta($chapterId, '_verbum_chapter_word_count', 0);
        }

        $this->touchBook($bookId);
        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function complete(int $bookId): array
    {
        $data = $this->data($bookId);
        if (! $data['ready']) {
            $pending = array_map(static fn (array $item): string => (string) $item['label'], array_values(array_filter($data['checklist'], static fn (array $item): bool => ! $item['completed'])));
            throw new ValidationError('Complete o Planejamento da Obra antes de continuar: ' . implode(', ', $pending) . '.');
        }

        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('project', $completed, true)) {
            throw new ValidationError('Conclua o Projeto da Obra antes do Planejamento.');
        }
        if (! in_array('planning', $completed, true)) {
            $completed[] = 'planning';
        }

        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        update_post_meta($bookId, '_verbum_stage', 'development');
        update_post_meta($bookId, '_verbum_planning_completed_at', gmdate('c'));
        $this->touchBook($bookId);

        return $this->data($bookId);
    }

    /** @param array<int, mixed> $items
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items, bool $regenerateIds = false): array
    {
        $clean = [];
        $allowed = ['part', 'chapter', 'subchapter'];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = trim(sanitize_text_field((string) ($item['title'] ?? '')));
            if ($title === '') {
                continue;
            }
            $type = sanitize_key((string) ($item['type'] ?? 'chapter'));
            if (! in_array($type, $allowed, true)) {
                $type = 'chapter';
            }
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '' || ($regenerateIds && strpos($id, 'new-') === 0)) {
                $id = 'outline-' . substr(md5($type . '|' . $title . '|' . $index . '|' . microtime(true)), 0, 12);
            }
            $clean[] = [
                'id' => $id,
                'type' => $type,
                'title' => $title,
                'parentId' => sanitize_key((string) ($item['parentId'] ?? '')),
                'order' => $index + 1,
            ];
        }
        return $clean;
    }

    /** @param array<int, array<string, mixed>> $items
     *  @return array<string, int>
     */
    private function counts(array $items): array
    {
        $counts = ['parts' => 0, 'chapters' => 0, 'subchapters' => 0];
        foreach ($items as $item) {
            if ($item['type'] === 'part') $counts['parts']++;
            elseif ($item['type'] === 'chapter') $counts['chapters']++;
            elseif ($item['type'] === 'subchapter') $counts['subchapters']++;
        }
        return $counts;
    }

    /** @return int[] */
    private function generatedChapterIds(int $bookId): array
    {
        $query = new \WP_Query([
            'post_type' => LibraryPostTypes::CHAPTER,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => '_verbum_book_id',
            'meta_value' => $bookId,
            'orderby' => 'meta_value_num',
            'meta_type' => 'NUMERIC',
            'no_found_rows' => true,
        ]);
        return array_values(array_map('intval', is_array($query->posts) ? $query->posts : []));
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
