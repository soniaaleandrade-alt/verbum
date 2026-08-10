<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkChapterPreparationRepository
{
    private const CHECKLIST = [
        'title' => 'Título definido',
        'objective' => 'Objetivo definido',
        'central_question' => 'Pergunta central definida',
        'purpose' => 'Finalidade definida',
        'thesis' => 'Tese definida',
        'main_message' => 'Mensagem principal definida',
        'keywords' => 'Palavras-chave definidas',
        'structure' => 'Estrutura inicial criada',
        'sources' => 'Fontes selecionadas',
        'completed' => 'Preparação concluída',
    ];

    private const TEXT_FIELDS = [
        'subtitle', 'objective', 'central_question', 'purpose', 'thesis', 'main_message', 'guiding_phrase',
        'spiritual_intention', 'virtue', 'writing_prayer', 'notes',
    ];

    private const SOURCE_CATEGORIES = [
        'scripture' => 'Sagrada Escritura',
        'catechism' => 'Catecismo da Igreja Católica',
        'magisterium' => 'Documentos do Magistério',
        'saints' => 'Santos',
        'church_fathers' => 'Padres da Igreja',
        'books' => 'Livros',
        'articles' => 'Artigos',
        'historical_documents' => 'Documentos históricos',
        'other' => 'Outras fontes',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId, int $chapterId): array
    {
        $chapter = $this->ownedChapter($userId, $bookId, $chapterId);
        $values = [];
        foreach (self::TEXT_FIELDS as $field) {
            $values[$this->camelCase($field)] = trim((string) get_post_meta($chapterId, '_verbum_preparation_' . $field, true));
        }

        $keywords = get_post_meta($chapterId, '_verbum_preparation_keywords', true);
        $keywords = is_array($keywords) ? array_values(array_filter(array_map('strval', $keywords))) : [];
        $structure = get_post_meta($chapterId, '_verbum_preparation_structure', true);
        $structure = is_array($structure) ? $this->normalizeStructure($structure) : [];
        $sources = get_post_meta($chapterId, '_verbum_preparation_sources', true);
        $sources = is_array($sources) ? array_values(array_intersect(array_keys(self::SOURCE_CATEGORIES), array_map('sanitize_key', $sources))) : [];

        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $completed = in_array('preparation', $completedStages, true);

        $raw = [
            'title' => trim((string) get_the_title($chapter)),
            'objective' => $values['objective'],
            'central_question' => $values['centralQuestion'],
            'purpose' => $values['purpose'],
            'thesis' => $values['thesis'],
            'main_message' => $values['mainMessage'],
            'keywords' => count($keywords) > 0,
            'structure' => count($structure) > 0,
            'sources' => count($sources) > 0,
            'completed' => $completed,
        ];

        $checklist = [];
        $completedCount = 0;
        foreach (self::CHECKLIST as $key => $label) {
            $done = is_bool($raw[$key]) ? $raw[$key] : trim((string) $raw[$key]) !== '';
            if ($done) {
                $completedCount++;
            }
            $checklist[] = ['key' => $key, 'label' => $label, 'completed' => $done];
        }

        $ready = trim($values['objective']) !== ''
            && trim($values['centralQuestion']) !== ''
            && trim($values['thesis']) !== ''
            && count($structure) > 0
            && count($sources) > 0;

        $values['keywords'] = $keywords;
        $values['structureItems'] = $structure;
        $values['sourceCategories'] = $sources;

        return [
            'chapterId' => (string) $chapterId,
            'title' => get_the_title($chapter),
            'progress' => (int) round(($completedCount / count(self::CHECKLIST)) * 100),
            'completedCount' => $completedCount,
            'total' => count(self::CHECKLIST),
            'ready' => $ready,
            'completed' => $completed,
            'checklist' => $checklist,
            'values' => $values,
            'sourceOptions' => array_map(static fn (string $label, string $key): array => ['key' => $key, 'label' => $label], self::SOURCE_CATEGORIES, array_keys(self::SOURCE_CATEGORIES)),
            'completedAt' => (string) get_post_meta($chapterId, '_verbum_preparation_completed_at', true),
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $userId, int $bookId, int $chapterId, array $fields): array
    {
        $this->ownedChapter($userId, $bookId, $chapterId);

        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                update_post_meta($chapterId, '_verbum_preparation_' . $field, sanitize_textarea_field((string) $fields[$field]));
            }
        }

        if (array_key_exists('keywords', $fields)) {
            $keywords = is_array($fields['keywords']) ? $fields['keywords'] : [];
            $keywords = array_values(array_filter(array_map(static fn ($value): string => sanitize_text_field((string) $value), $keywords), static fn (string $value): bool => $value !== ''));
            update_post_meta($chapterId, '_verbum_preparation_keywords', $keywords);
        }

        if (array_key_exists('structure_items', $fields)) {
            $structure = is_array($fields['structure_items']) ? $fields['structure_items'] : [];
            update_post_meta($chapterId, '_verbum_preparation_structure', $this->normalizeStructure($structure, true));
        }

        if (array_key_exists('source_categories', $fields)) {
            $sources = is_array($fields['source_categories']) ? $fields['source_categories'] : [];
            $sources = array_values(array_intersect(array_keys(self::SOURCE_CATEGORIES), array_map('sanitize_key', $sources)));
            update_post_meta($chapterId, '_verbum_preparation_sources', $sources);
        }

        $this->touchChapter($chapterId);
        return $this->data($userId, $bookId, $chapterId);
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId, int $chapterId): array
    {
        $data = $this->data($userId, $bookId, $chapterId);
        if (! $data['ready']) {
            throw new ValidationError('Complete os campos obrigatórios da Preparação antes de liberar a Pesquisa.');
        }

        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        if (! in_array('preparation', $completedStages, true)) {
            $completedStages[] = 'preparation';
        }
        update_post_meta($chapterId, '_verbum_chapter_completed_stages', array_values(array_unique($completedStages)));
        update_post_meta($chapterId, '_verbum_chapter_stage', 'research');
        update_post_meta($chapterId, '_verbum_preparation_completed_at', gmdate('c'));
        $this->touchChapter($chapterId);

        return $this->data($userId, $bookId, $chapterId);
    }

    private function ownedChapter(int $userId, int $bookId, int $chapterId): \WP_Post
    {
        $chapter = get_post($chapterId);
        if (! $chapter instanceof \WP_Post
            || $chapter->post_type !== LibraryPostTypes::CHAPTER
            || (int) $chapter->post_author !== $userId
            || (int) get_post_meta($chapterId, '_verbum_book_id', true) !== $bookId) {
            throw new NotFoundError('Capítulo não encontrado.');
        }
        return $chapter;
    }

    /** @param array<int, mixed> $items
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeStructure(array $items, bool $regenerateIds = false): array
    {
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $text = trim(sanitize_text_field((string) ($item['text'] ?? '')));
            if ($text === '') continue;
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '' || ($regenerateIds && strpos($id, 'new-') === 0)) {
                $id = 'prep-' . substr(md5($text . '|' . $index . '|' . microtime(true)), 0, 12);
            }
            $clean[] = ['id' => $id, 'text' => $text, 'order' => $index + 1];
        }
        return $clean;
    }

    private function touchChapter(int $chapterId): void
    {
        $post = get_post($chapterId);
        if ($post instanceof \WP_Post) {
            wp_update_post(['ID' => $chapterId, 'post_content' => $post->post_content]);
        }
    }

    private function camelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
