<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkChapterWritingRepository
{
    private const MANUAL_FLAGS = [
        'thesis_covered' => 'Tese contemplada',
        'sources_used' => 'Fontes selecionadas utilizadas',
        'citations_verified' => 'Citações verificadas',
        'author_reviewed' => 'Texto revisado pelo autor',
        'goal_analyzed' => 'Meta de conteúdo analisada',
        'ready_for_revision' => 'Texto pronto para revisão',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId, int $chapterId): array
    {
        $chapter = $this->ownedChapter($userId, $bookId, $chapterId);
        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $researchCompleted = in_array('research', $completedStages, true);
        $completed = in_array('writing', $completedStages, true);

        $introduction = (string) get_post_meta($chapterId, '_verbum_writing_introduction', true);
        $conclusion = (string) get_post_meta($chapterId, '_verbum_writing_conclusion', true);
        $sections = get_post_meta($chapterId, '_verbum_writing_sections', true);
        $sections = is_array($sections) ? $this->normalizeSections($sections) : [];
        if ($sections === []) {
            $sections = $this->initialSections($chapterId);
        }

        $wordGoal = max(0, (int) get_post_meta($chapterId, '_verbum_writing_word_goal', true));
        $notes = get_post_meta($chapterId, '_verbum_writing_notes', true);
        $notes = is_array($notes) ? $this->normalizeNotes($notes) : [];
        $comments = get_post_meta($chapterId, '_verbum_writing_comments', true);
        $comments = is_array($comments) ? $this->normalizeNotes($comments) : [];
        $flags = get_post_meta($chapterId, '_verbum_writing_flags', true);
        $flags = is_array($flags) ? $this->normalizeFlags($flags) : [];
        $usedIdeaIds = $this->stringList(get_post_meta($chapterId, '_verbum_writing_used_idea_ids', true));

        $contentHtml = $this->combinedHtml($introduction, $sections, $conclusion);
        $wordCount = $this->wordCount($contentHtml);
        $characterCount = $this->characterCount($contentHtml);
        $timeSeconds = max(0, (int) get_post_meta($chapterId, '_verbum_writing_time_seconds', true));
        $startedAt = (string) get_post_meta($chapterId, '_verbum_writing_started_at', true);

        $sources = $this->writingSources($userId, $bookId, $chapterId);
        $ideasRaw = get_post_meta($chapterId, '_verbum_research_ideas', true);
        $ideasRaw = is_array($ideasRaw) ? $ideasRaw : [];
        $ideas = [];
        foreach ($ideasRaw as $item) {
            if (! is_array($item)) continue;
            $id = sanitize_key((string) ($item['id'] ?? ''));
            $title = trim(sanitize_text_field((string) ($item['title'] ?? '')));
            $description = trim(sanitize_textarea_field((string) ($item['description'] ?? '')));
            if ($id === '' || ($title === '' && $description === '')) continue;
            $ideas[] = [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'tags' => $this->stringList($item['tags'] ?? []),
                'structureItemId' => sanitize_key((string) ($item['structureItemId'] ?? '')),
                'used' => in_array($id, $usedIdeaIds, true),
            ];
        }

        $preparation = [
            'objective' => trim((string) get_post_meta($chapterId, '_verbum_preparation_objective', true)),
            'centralQuestion' => trim((string) get_post_meta($chapterId, '_verbum_preparation_central_question', true)),
            'thesis' => trim((string) get_post_meta($chapterId, '_verbum_preparation_thesis', true)),
            'keywords' => $this->stringList(get_post_meta($chapterId, '_verbum_preparation_keywords', true)),
            'structureItems' => $this->preparationStructure($chapterId),
        ];

        $checklist = [
            ['key' => 'introduction', 'label' => 'Introdução desenvolvida', 'completed' => $this->hasText($introduction)],
            ['key' => 'development', 'label' => 'Estrutura principal desenvolvida', 'completed' => $this->sectionsHaveText($sections)],
            ['key' => 'conclusion', 'label' => 'Conclusão desenvolvida', 'completed' => $this->hasText($conclusion)],
        ];
        foreach (self::MANUAL_FLAGS as $key => $label) {
            $checklist[] = ['key' => $key, 'label' => $label, 'completed' => (bool) ($flags[$key] ?? false)];
        }
        $checklist[] = ['key' => 'completed', 'label' => 'Redação concluída', 'completed' => $completed];
        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));

        $ready = $researchCompleted
            && $this->hasText($introduction)
            && $this->sectionsHaveText($sections)
            && $this->hasText($conclusion)
            && (bool) ($flags['ready_for_revision'] ?? false);

        $versions = get_post_meta($chapterId, '_verbum_writing_versions', true);
        $versions = is_array($versions) ? $versions : [];
        $versionList = [];
        foreach (array_reverse($versions) as $version) {
            if (! is_array($version)) continue;
            $versionList[] = [
                'id' => (string) ($version['id'] ?? ''),
                'savedAt' => (string) ($version['savedAt'] ?? ''),
                'kind' => (string) ($version['kind'] ?? 'autosave'),
                'wordCount' => (int) ($version['wordCount'] ?? 0),
                'characterCount' => (int) ($version['characterCount'] ?? 0),
            ];
        }

        return [
            'chapterId' => (string) $chapterId,
            'title' => get_the_title($chapter),
            'researchCompleted' => $researchCompleted,
            'preparation' => $preparation,
            'introduction' => $introduction,
            'sections' => $sections,
            'conclusion' => $conclusion,
            'wordGoal' => $wordGoal,
            'wordCount' => $wordCount,
            'characterCount' => $characterCount,
            'goalProgress' => $wordGoal > 0 ? min(100, (int) round(($wordCount / $wordGoal) * 100)) : 0,
            'timeSeconds' => $timeSeconds,
            'startedAt' => $startedAt,
            'notes' => $notes,
            'comments' => $comments,
            'flags' => $flags,
            'sources' => $sources,
            'ideas' => $ideas,
            'usedIdeaIds' => $usedIdeaIds,
            'versions' => $versionList,
            'progress' => (int) round(($completedCount / count($checklist)) * 100),
            'completedCount' => $completedCount,
            'total' => count($checklist),
            'checklist' => $checklist,
            'ready' => $ready,
            'completed' => $completed,
            'completedAt' => (string) get_post_meta($chapterId, '_verbum_writing_completed_at', true),
            'lastSavedAt' => (string) get_post_meta($chapterId, '_verbum_writing_last_saved_at', true),
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $userId, int $bookId, int $chapterId, array $fields): array
    {
        $this->assertWritingAvailable($userId, $bookId, $chapterId);

        if ((string) get_post_meta($chapterId, '_verbum_writing_started_at', true) === '') {
            update_post_meta($chapterId, '_verbum_writing_started_at', gmdate('c'));
        }

        $introduction = array_key_exists('introduction', $fields)
            ? wp_kses_post((string) $fields['introduction'])
            : (string) get_post_meta($chapterId, '_verbum_writing_introduction', true);
        $conclusion = array_key_exists('conclusion', $fields)
            ? wp_kses_post((string) $fields['conclusion'])
            : (string) get_post_meta($chapterId, '_verbum_writing_conclusion', true);
        $sections = array_key_exists('sections', $fields) && is_array($fields['sections'])
            ? $this->normalizeSections($fields['sections'], true)
            : get_post_meta($chapterId, '_verbum_writing_sections', true);
        $sections = is_array($sections) && $sections !== [] ? $sections : $this->initialSections($chapterId);

        update_post_meta($chapterId, '_verbum_writing_introduction', $introduction);
        update_post_meta($chapterId, '_verbum_writing_sections', $sections);
        update_post_meta($chapterId, '_verbum_writing_conclusion', $conclusion);

        if (array_key_exists('word_goal', $fields)) {
            update_post_meta($chapterId, '_verbum_writing_word_goal', max(0, (int) $fields['word_goal']));
        }
        if (array_key_exists('notes', $fields)) {
            update_post_meta($chapterId, '_verbum_writing_notes', $this->normalizeNotes(is_array($fields['notes']) ? $fields['notes'] : [], true));
        }
        if (array_key_exists('comments', $fields)) {
            update_post_meta($chapterId, '_verbum_writing_comments', $this->normalizeNotes(is_array($fields['comments']) ? $fields['comments'] : [], true));
        }
        if (array_key_exists('flags', $fields)) {
            update_post_meta($chapterId, '_verbum_writing_flags', $this->normalizeFlags(is_array($fields['flags']) ? $fields['flags'] : []));
        }
        if (array_key_exists('used_idea_ids', $fields)) {
            $ids = $this->stringList($fields['used_idea_ids']);
            update_post_meta($chapterId, '_verbum_writing_used_idea_ids', $ids);
        }
        if (array_key_exists('used_source_ids', $fields)) {
            $this->syncUsedSources($userId, $bookId, $chapterId, $this->stringList($fields['used_source_ids']));
        }
        if (array_key_exists('session_seconds', $fields)) {
            $delta = min(3600, max(0, (int) $fields['session_seconds']));
            if ($delta > 0) {
                $current = max(0, (int) get_post_meta($chapterId, '_verbum_writing_time_seconds', true));
                update_post_meta($chapterId, '_verbum_writing_time_seconds', $current + $delta);
            }
        }

        $combined = $this->combinedHtml($introduction, $sections, $conclusion);
        $wordCount = $this->wordCount($combined);
        update_post_meta($chapterId, '_verbum_chapter_word_count', $wordCount);
        update_post_meta($chapterId, '_verbum_writing_last_saved_at', gmdate('c'));
        wp_update_post(['ID' => $chapterId, 'post_content' => $combined]);

        $mode = sanitize_key((string) ($fields['save_mode'] ?? 'autosave'));
        $this->maybeSnapshot($chapterId, $introduction, $sections, $conclusion, $mode === 'manual' ? 'manual' : 'autosave', $mode === 'manual');

        return $this->data($userId, $bookId, $chapterId);
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId, int $chapterId): array
    {
        $data = $this->data($userId, $bookId, $chapterId);
        if (! $data['ready']) {
            throw new ValidationError('Preencha Introdução, Desenvolvimento e Conclusão e confirme que o texto está pronto para Revisão.');
        }

        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        if (! in_array('writing', $completedStages, true)) $completedStages[] = 'writing';
        update_post_meta($chapterId, '_verbum_chapter_completed_stages', array_values(array_unique($completedStages)));
        update_post_meta($chapterId, '_verbum_chapter_stage', 'revision');
        update_post_meta($chapterId, '_verbum_writing_completed_at', gmdate('c'));
        $this->maybeSnapshot($chapterId, (string) $data['introduction'], (array) $data['sections'], (string) $data['conclusion'], 'completion', true);
        $this->touchChapter($chapterId);

        return $this->data($userId, $bookId, $chapterId);
    }

    private function assertWritingAvailable(int $userId, int $bookId, int $chapterId): void
    {
        $this->ownedChapter($userId, $bookId, $chapterId);
        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        if (! in_array('research', $completedStages, true)) {
            throw new ValidationError('Conclua a Pesquisa do Capítulo antes de iniciar a Redação.');
        }
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

    /** @return array<int, array<string, mixed>> */
    private function initialSections(int $chapterId): array
    {
        $structure = $this->preparationStructure($chapterId);
        if ($structure === []) {
            return [['id' => 'writing-development', 'title' => 'Desenvolvimento', 'content' => '', 'order' => 1, 'sourceStructureItemId' => '']];
        }
        $sections = [];
        foreach ($structure as $index => $item) {
            $sections[] = [
                'id' => 'writing-' . sanitize_key((string) $item['id']),
                'title' => (string) $item['text'],
                'content' => '',
                'order' => $index + 1,
                'sourceStructureItemId' => (string) $item['id'],
            ];
        }
        return $sections;
    }

    /** @return array<int, array<string, mixed>> */
    private function preparationStructure(int $chapterId): array
    {
        $items = get_post_meta($chapterId, '_verbum_preparation_structure', true);
        if (! is_array($items)) return [];
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $text = trim(sanitize_text_field((string) ($item['text'] ?? '')));
            if ($text === '') continue;
            $clean[] = [
                'id' => sanitize_key((string) ($item['id'] ?? ('prep-' . ($index + 1)))),
                'text' => $text,
                'order' => $index + 1,
            ];
        }
        return $clean;
    }

    /** @param array<int, mixed> $items
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeSections(array $items, bool $regenerateIds = false): array
    {
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $title = trim(sanitize_text_field((string) ($item['title'] ?? '')));
            if ($title === '') $title = 'Desenvolvimento ' . ($index + 1);
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '' || ($regenerateIds && strpos($id, 'new-') === 0)) {
                $id = 'writing-' . substr(md5($title . '|' . $index . '|' . microtime(true)), 0, 12);
            }
            $clean[] = [
                'id' => $id,
                'title' => $title,
                'content' => wp_kses_post((string) ($item['content'] ?? '')),
                'order' => $index + 1,
                'sourceStructureItemId' => sanitize_key((string) ($item['sourceStructureItemId'] ?? $item['source_structure_item_id'] ?? '')),
            ];
        }
        return $clean;
    }

    /** @param array<int, mixed> $items
     *  @return array<int, array<string, string>>
     */
    private function normalizeNotes(array $items, bool $regenerateIds = false): array
    {
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $text = trim(sanitize_textarea_field((string) ($item['text'] ?? '')));
            if ($text === '') continue;
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '' || ($regenerateIds && strpos($id, 'new-') === 0)) $id = 'note-' . substr(md5($text . '|' . $index . '|' . microtime(true)), 0, 12);
            $clean[] = ['id' => $id, 'text' => $text, 'createdAt' => sanitize_text_field((string) ($item['createdAt'] ?? gmdate('c')))];
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

    /** @return array<int, array<string, mixed>> */
    private function writingSources(int $userId, int $bookId, int $chapterId): array
    {
        $query = new \WP_Query([
            'post_type' => LibraryPostTypes::RESEARCH,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => '_verbum_book_id', 'value' => $bookId, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => '_verbum_chapter_id', 'value' => $chapterId, 'compare' => '=', 'type' => 'NUMERIC'],
            ],
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);
        $sources = [];
        foreach ($query->posts as $post) {
            if (! $post instanceof \WP_Post) continue;
            $selected = (bool) get_post_meta($post->ID, '_verbum_research_selected', true);
            $used = (bool) get_post_meta($post->ID, '_verbum_research_used', true);
            if (! $selected && ! $used) continue;
            $sources[] = [
                'id' => (string) $post->ID,
                'category' => sanitize_key((string) get_post_meta($post->ID, '_verbum_research_category', true)),
                'title' => trim((string) get_post_meta($post->ID, '_verbum_research_title', true)),
                'author' => trim((string) get_post_meta($post->ID, '_verbum_research_author', true)),
                'reference' => trim((string) get_post_meta($post->ID, '_verbum_research_reference', true)),
                'excerpt' => trim((string) get_post_meta($post->ID, '_verbum_research_excerpt', true)),
                'application' => trim((string) get_post_meta($post->ID, '_verbum_research_application', true)),
                'structureItemId' => sanitize_key((string) get_post_meta($post->ID, '_verbum_research_structure_item_id', true)),
                'highlighted' => (bool) get_post_meta($post->ID, '_verbum_research_highlighted', true),
                'selectedForWriting' => $selected,
                'used' => $used,
            ];
        }
        return $sources;
    }

    /** @param string[] $usedIds */
    private function syncUsedSources(int $userId, int $bookId, int $chapterId, array $usedIds): void
    {
        $query = new \WP_Query([
            'post_type' => LibraryPostTypes::RESEARCH,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => '_verbum_book_id', 'value' => $bookId, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => '_verbum_chapter_id', 'value' => $chapterId, 'compare' => '=', 'type' => 'NUMERIC'],
            ],
            'no_found_rows' => true,
        ]);
        foreach ($query->posts as $post) {
            if (! $post instanceof \WP_Post) continue;
            update_post_meta($post->ID, '_verbum_research_used', in_array((string) $post->ID, $usedIds, true) ? 1 : 0);
        }
    }

    /** @param array<int, array<string, mixed>> $sections */
    private function combinedHtml(string $introduction, array $sections, string $conclusion): string
    {
        $html = '<h2>Introdução</h2>' . $introduction;
        foreach ($sections as $section) $html .= '<h2>' . esc_html((string) ($section['title'] ?? 'Desenvolvimento')) . '</h2>' . (string) ($section['content'] ?? '');
        return $html . '<h2>Conclusão</h2>' . $conclusion;
    }

    private function hasText(string $html): bool
    {
        return trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($html))) !== '';
    }

    /** @param array<int, array<string, mixed>> $sections */
    private function sectionsHaveText(array $sections): bool
    {
        if ($sections === []) return false;
        foreach ($sections as $section) {
            if (! $this->hasText((string) ($section['content'] ?? ''))) return false;
        }
        return true;
    }

    private function wordCount(string $html): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') return 0;
        $words = preg_split('/\s+/u', $text);
        return is_array($words) ? count(array_filter($words, static fn (string $word): bool => $word !== '')) : 0;
    }

    private function characterCount(string $html): int
    {
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    /** @param mixed $value
     *  @return string[]
     */
    private function stringList($value): array
    {
        if (! is_array($value)) return [];
        return array_values(array_unique(array_filter(array_map(static fn ($item): string => sanitize_key((string) $item), $value))));
    }

    /** @param array<int, array<string, mixed>> $sections */
    private function maybeSnapshot(int $chapterId, string $introduction, array $sections, string $conclusion, string $kind, bool $force): void
    {
        $versions = get_post_meta($chapterId, '_verbum_writing_versions', true);
        $versions = is_array($versions) ? $versions : [];
        $content = $this->combinedHtml($introduction, $sections, $conclusion);
        $hash = md5($content);
        $last = $versions !== [] ? end($versions) : null;
        $lastAt = is_array($last) ? strtotime((string) ($last['savedAt'] ?? '')) : false;
        $same = is_array($last) && (string) ($last['hash'] ?? '') === $hash;
        if (! $force && ($same || ($lastAt !== false && (time() - $lastAt) < 300))) return;

        $versions[] = [
            'id' => 'version-' . substr(md5($hash . '|' . microtime(true)), 0, 12),
            'savedAt' => gmdate('c'),
            'kind' => $kind,
            'wordCount' => $this->wordCount($content),
            'characterCount' => $this->characterCount($content),
            'hash' => $hash,
            'introduction' => $introduction,
            'sections' => $sections,
            'conclusion' => $conclusion,
        ];
        if (count($versions) > 30) $versions = array_slice($versions, -30);
        update_post_meta($chapterId, '_verbum_writing_versions', $versions);
    }

    private function touchChapter(int $chapterId): void
    {
        $post = get_post($chapterId);
        if ($post instanceof \WP_Post) wp_update_post(['ID' => $chapterId, 'post_content' => $post->post_content]);
    }
}
