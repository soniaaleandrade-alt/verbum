<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkChapterRevisionRepository
{
    private const MANUAL_FLAGS = [
        'objective_checked' => 'Objetivo do capítulo conferido',
        'central_question_answered' => 'Pergunta Central respondida',
        'thesis_developed' => 'Tese desenvolvida',
        'structure_reviewed' => 'Estrutura revisada',
        'clarity_reviewed' => 'Clareza revisada',
        'language_reviewed' => 'Linguagem revisada',
        'citations_checked' => 'Citações conferidas',
        'ready_to_finish' => 'Capítulo pronto para conclusão',
    ];

    private const ISSUE_TYPES = [
        'content' => 'Conteúdo',
        'structure' => 'Estrutura',
        'clarity' => 'Clareza',
        'grammar' => 'Gramática',
        'repetition' => 'Repetição',
        'source' => 'Fonte',
        'citation' => 'Citação',
        'coherence' => 'Coerência',
        'style' => 'Estilo',
        'doctrine' => 'Doutrinal',
        'other' => 'Outro',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId, int $chapterId): array
    {
        $chapter = $this->ownedChapter($userId, $bookId, $chapterId);
        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $writingCompleted = in_array('writing', $completedStages, true);
        $completed = in_array('revision', $completedStages, true) || (bool) get_post_meta($chapterId, '_verbum_chapter_completed', true);

        $introduction = (string) get_post_meta($chapterId, '_verbum_writing_introduction', true);
        $conclusion = (string) get_post_meta($chapterId, '_verbum_writing_conclusion', true);
        $sections = get_post_meta($chapterId, '_verbum_writing_sections', true);
        $sections = is_array($sections) ? $this->normalizeSections($sections) : [];

        $flags = get_post_meta($chapterId, '_verbum_revision_flags', true);
        $flags = is_array($flags) ? $this->normalizeFlags($flags) : [];
        $issuesRaw = get_post_meta($chapterId, '_verbum_revision_issues', true);
        $issues = is_array($issuesRaw) ? $this->normalizeIssues($issuesRaw) : [];
        $verifiedSourceIds = $this->stringList(get_post_meta($chapterId, '_verbum_revision_verified_source_ids', true));
        $dismissedSourceIds = $this->stringList(get_post_meta($chapterId, '_verbum_revision_dismissed_source_ids', true));
        $resolvedNoteIds = $this->stringList(get_post_meta($chapterId, '_verbum_revision_resolved_note_ids', true));
        $resolvedCommentIds = $this->stringList(get_post_meta($chapterId, '_verbum_revision_resolved_comment_ids', true));

        $sources = $this->revisionSources($userId, $bookId, $chapterId, $verifiedSourceIds, $dismissedSourceIds);
        $usedSources = array_values(array_filter($sources, static fn (array $source): bool => (bool) $source['used']));
        $unusedSelectedSources = array_values(array_filter($sources, static fn (array $source): bool => (bool) $source['selectedForWriting'] && ! (bool) $source['used'] && ! (bool) $source['dismissed']));
        $allUsedVerified = count($usedSources) === 0 || count(array_filter($usedSources, static fn (array $source): bool => (bool) $source['verified'])) === count($usedSources);

        $pendingIssues = count(array_filter($issues, static fn (array $issue): bool => $issue['status'] === 'pending'));
        $notes = $this->reviewNotes(get_post_meta($chapterId, '_verbum_writing_notes', true), $resolvedNoteIds, 'note');
        $comments = $this->reviewNotes(get_post_meta($chapterId, '_verbum_writing_comments', true), $resolvedCommentIds, 'comment');

        $checklist = [];
        foreach (self::MANUAL_FLAGS as $key => $label) {
            if ($key === 'citations_checked') continue;
            $checklist[] = ['key' => $key, 'label' => $label, 'completed' => (bool) ($flags[$key] ?? false), 'automatic' => false];
        }
        $checklist[] = ['key' => 'sources_verified', 'label' => 'Fontes conferidas', 'completed' => $allUsedVerified, 'automatic' => true];
        $checklist[] = ['key' => 'citations_checked', 'label' => self::MANUAL_FLAGS['citations_checked'], 'completed' => (bool) ($flags['citations_checked'] ?? false), 'automatic' => false];
        $checklist[] = ['key' => 'issues_resolved', 'label' => 'Pendências resolvidas', 'completed' => $pendingIssues === 0, 'automatic' => true];
        $checklist[] = ['key' => 'completed', 'label' => 'Revisão concluída', 'completed' => $completed, 'automatic' => true];

        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $requiredFlags = ['objective_checked', 'central_question_answered', 'thesis_developed', 'structure_reviewed', 'clarity_reviewed', 'language_reviewed', 'citations_checked', 'ready_to_finish'];
        $flagsReady = count(array_filter($requiredFlags, static fn (string $key): bool => (bool) ($flags[$key] ?? false))) === count($requiredFlags);
        $ready = $writingCompleted && $flagsReady && $allUsedVerified && $pendingIssues === 0;

        $preparationStructure = $this->preparationStructure($chapterId);
        $writtenStructure = array_map(static fn (array $section): array => [
            'id' => (string) $section['id'],
            'title' => (string) $section['title'],
            'order' => (int) $section['order'],
        ], $sections);

        return [
            'chapterId' => (string) $chapterId,
            'title' => get_the_title($chapter),
            'writingCompleted' => $writingCompleted,
            'introduction' => $introduction,
            'sections' => $sections,
            'conclusion' => $conclusion,
            'wordCount' => $this->wordCount($this->combinedHtml($introduction, $sections, $conclusion)),
            'preparation' => [
                'objective' => trim((string) get_post_meta($chapterId, '_verbum_preparation_objective', true)),
                'centralQuestion' => trim((string) get_post_meta($chapterId, '_verbum_preparation_central_question', true)),
                'thesis' => trim((string) get_post_meta($chapterId, '_verbum_preparation_thesis', true)),
                'mainMessage' => trim((string) get_post_meta($chapterId, '_verbum_preparation_main_message', true)),
                'guidingPhrase' => trim((string) get_post_meta($chapterId, '_verbum_preparation_guiding_phrase', true)),
                'structureItems' => $preparationStructure,
            ],
            'writtenStructure' => $writtenStructure,
            'sources' => $sources,
            'usedSources' => $usedSources,
            'unusedSelectedSources' => $unusedSelectedSources,
            'verifiedSourceIds' => $verifiedSourceIds,
            'dismissedSourceIds' => $dismissedSourceIds,
            'notes' => $notes,
            'comments' => $comments,
            'resolvedNoteIds' => $resolvedNoteIds,
            'resolvedCommentIds' => $resolvedCommentIds,
            'issues' => $issues,
            'pendingIssueCount' => $pendingIssues,
            'issueTypes' => array_map(static fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys(self::ISSUE_TYPES), array_values(self::ISSUE_TYPES)),
            'flags' => $flags,
            'checklist' => $checklist,
            'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            'completedCount' => $completedCount,
            'total' => count($checklist),
            'ready' => $ready,
            'completed' => $completed,
            'completedAt' => (string) get_post_meta($chapterId, '_verbum_revision_completed_at', true),
            'lastSavedAt' => (string) get_post_meta($chapterId, '_verbum_revision_last_saved_at', true),
            'alteredAfterCompletion' => (bool) get_post_meta($chapterId, '_verbum_revision_altered_after_completion', true),
            'alteredAfterCompletionAt' => (string) get_post_meta($chapterId, '_verbum_revision_altered_after_completion_at', true),
            'versions' => $this->revisionVersions($chapterId),
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $userId, int $bookId, int $chapterId, array $fields): array
    {
        $this->assertRevisionAvailable($userId, $bookId, $chapterId);
        $wasCompleted = $this->isCompleted($chapterId);

        $introduction = array_key_exists('introduction', $fields)
            ? wp_kses_post((string) $fields['introduction'])
            : (string) get_post_meta($chapterId, '_verbum_writing_introduction', true);
        $conclusion = array_key_exists('conclusion', $fields)
            ? wp_kses_post((string) $fields['conclusion'])
            : (string) get_post_meta($chapterId, '_verbum_writing_conclusion', true);
        $sections = array_key_exists('sections', $fields) && is_array($fields['sections'])
            ? $this->normalizeSections($fields['sections'], true)
            : get_post_meta($chapterId, '_verbum_writing_sections', true);
        $sections = is_array($sections) ? $sections : [];

        update_post_meta($chapterId, '_verbum_writing_introduction', $introduction);
        update_post_meta($chapterId, '_verbum_writing_sections', $sections);
        update_post_meta($chapterId, '_verbum_writing_conclusion', $conclusion);

        if (array_key_exists('flags', $fields)) {
            update_post_meta($chapterId, '_verbum_revision_flags', $this->normalizeFlags(is_array($fields['flags']) ? $fields['flags'] : []));
        }
        if (array_key_exists('verified_source_ids', $fields)) {
            update_post_meta($chapterId, '_verbum_revision_verified_source_ids', $this->ownedSourceIds($userId, $bookId, $chapterId, $this->stringList($fields['verified_source_ids'])));
        }
        if (array_key_exists('dismissed_source_ids', $fields)) {
            update_post_meta($chapterId, '_verbum_revision_dismissed_source_ids', $this->ownedSourceIds($userId, $bookId, $chapterId, $this->stringList($fields['dismissed_source_ids'])));
        }
        if (array_key_exists('resolved_note_ids', $fields)) {
            update_post_meta($chapterId, '_verbum_revision_resolved_note_ids', $this->stringList($fields['resolved_note_ids']));
        }
        if (array_key_exists('resolved_comment_ids', $fields)) {
            update_post_meta($chapterId, '_verbum_revision_resolved_comment_ids', $this->stringList($fields['resolved_comment_ids']));
        }

        $combined = $this->combinedHtml($introduction, $sections, $conclusion);
        update_post_meta($chapterId, '_verbum_chapter_word_count', $this->wordCount($combined));
        update_post_meta($chapterId, '_verbum_revision_last_saved_at', gmdate('c'));
        wp_update_post(['ID' => $chapterId, 'post_content' => $combined]);

        $mode = sanitize_key((string) ($fields['save_mode'] ?? 'autosave'));
        if ($mode === 'manual') $this->snapshot($chapterId, $introduction, $sections, $conclusion, 'revision_manual', false);
        if ($wasCompleted) {
            update_post_meta($chapterId, '_verbum_revision_altered_after_completion', 1);
            update_post_meta($chapterId, '_verbum_revision_altered_after_completion_at', gmdate('c'));
        }
        $this->touchChapter($chapterId);

        return $this->data($userId, $bookId, $chapterId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function createIssue(int $userId, int $bookId, int $chapterId, array $fields): array
    {
        $this->assertRevisionAvailable($userId, $bookId, $chapterId);
        $issues = get_post_meta($chapterId, '_verbum_revision_issues', true);
        $issues = is_array($issues) ? $this->normalizeIssues($issues) : [];
        $type = sanitize_key((string) ($fields['type'] ?? 'other'));
        if (! isset(self::ISSUE_TYPES[$type])) $type = 'other';
        $description = trim(sanitize_textarea_field((string) ($fields['description'] ?? '')));
        if ($description === '') throw new ValidationError('Descreva a pendência da Revisão.');
        $issues[] = [
            'id' => 'issue-' . substr(md5($description . '|' . microtime(true)), 0, 12),
            'type' => $type,
            'typeLabel' => self::ISSUE_TYPES[$type],
            'description' => $description,
            'excerpt' => trim(sanitize_textarea_field((string) ($fields['excerpt'] ?? ''))),
            'status' => 'pending',
            'createdAt' => gmdate('c'),
            'resolvedAt' => '',
        ];
        update_post_meta($chapterId, '_verbum_revision_issues', $issues);
        $this->markAlteredIfCompleted($chapterId);
        $this->touchChapter($chapterId);
        return $this->data($userId, $bookId, $chapterId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function updateIssue(int $userId, int $bookId, int $chapterId, string $issueId, array $fields): array
    {
        $this->assertRevisionAvailable($userId, $bookId, $chapterId);
        $issues = get_post_meta($chapterId, '_verbum_revision_issues', true);
        $issues = is_array($issues) ? $this->normalizeIssues($issues) : [];
        $found = false;
        foreach ($issues as &$issue) {
            if ((string) $issue['id'] !== $issueId) continue;
            $found = true;
            if (array_key_exists('type', $fields)) {
                $type = sanitize_key((string) $fields['type']);
                if (isset(self::ISSUE_TYPES[$type])) { $issue['type'] = $type; $issue['typeLabel'] = self::ISSUE_TYPES[$type]; }
            }
            if (array_key_exists('description', $fields)) {
                $description = trim(sanitize_textarea_field((string) $fields['description']));
                if ($description !== '') $issue['description'] = $description;
            }
            if (array_key_exists('excerpt', $fields)) $issue['excerpt'] = trim(sanitize_textarea_field((string) $fields['excerpt']));
            if (array_key_exists('status', $fields)) {
                $status = sanitize_key((string) $fields['status']);
                if (in_array($status, ['pending', 'resolved'], true)) {
                    $issue['status'] = $status;
                    $issue['resolvedAt'] = $status === 'resolved' ? gmdate('c') : '';
                }
            }
            break;
        }
        unset($issue);
        if (! $found) throw new NotFoundError('Pendência de Revisão não encontrada.');
        update_post_meta($chapterId, '_verbum_revision_issues', $issues);
        $this->markAlteredIfCompleted($chapterId);
        $this->touchChapter($chapterId);
        return $this->data($userId, $bookId, $chapterId);
    }

    /** @return array<string, mixed> */
    public function deleteIssue(int $userId, int $bookId, int $chapterId, string $issueId): array
    {
        $this->assertRevisionAvailable($userId, $bookId, $chapterId);
        $issues = get_post_meta($chapterId, '_verbum_revision_issues', true);
        $issues = is_array($issues) ? $this->normalizeIssues($issues) : [];
        $filtered = array_values(array_filter($issues, static fn (array $issue): bool => (string) $issue['id'] !== $issueId));
        if (count($filtered) === count($issues)) throw new NotFoundError('Pendência de Revisão não encontrada.');
        update_post_meta($chapterId, '_verbum_revision_issues', $filtered);
        $this->markAlteredIfCompleted($chapterId);
        $this->touchChapter($chapterId);
        return $this->data($userId, $bookId, $chapterId);
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId, int $chapterId): array
    {
        $data = $this->data($userId, $bookId, $chapterId);
        if (! $data['ready']) {
            throw new ValidationError('Conclua o checklist, confira as fontes utilizadas e resolva todas as pendências antes de finalizar a Revisão.');
        }

        $this->snapshot($chapterId, (string) $data['introduction'], (array) $data['sections'], (string) $data['conclusion'], 'revision_pre_completion', true);
        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        if (! in_array('revision', $completedStages, true)) $completedStages[] = 'revision';
        update_post_meta($chapterId, '_verbum_chapter_completed_stages', array_values(array_unique($completedStages)));
        update_post_meta($chapterId, '_verbum_chapter_stage', 'revision');
        update_post_meta($chapterId, '_verbum_chapter_completed', 1);
        update_post_meta($chapterId, '_verbum_revision_completed_at', gmdate('c'));
        update_post_meta($chapterId, '_verbum_revision_altered_after_completion', 0);
        delete_post_meta($chapterId, '_verbum_revision_altered_after_completion_at');
        $this->snapshot($chapterId, (string) $data['introduction'], (array) $data['sections'], (string) $data['conclusion'], 'revision_completion', true);
        $this->touchChapter($chapterId);

        return $this->data($userId, $bookId, $chapterId);
    }

    private function assertRevisionAvailable(int $userId, int $bookId, int $chapterId): void
    {
        $this->ownedChapter($userId, $bookId, $chapterId);
        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        if (! in_array('writing', $completedStages, true)) throw new ValidationError('Conclua a Redação do Capítulo antes de iniciar a Revisão.');
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

    /** @param mixed $items
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeSections($items, bool $regenerateIds = false): array
    {
        if (! is_array($items)) return [];
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $title = trim(sanitize_text_field((string) ($item['title'] ?? '')));
            if ($title === '') $title = 'Desenvolvimento ' . ($index + 1);
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '' || ($regenerateIds && strpos($id, 'new-') === 0)) $id = 'writing-' . substr(md5($title . '|' . $index . '|' . microtime(true)), 0, 12);
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

    /** @param array<string, mixed> $flags
     *  @return array<string, bool>
     */
    private function normalizeFlags(array $flags): array
    {
        $clean = [];
        foreach (array_keys(self::MANUAL_FLAGS) as $key) $clean[$key] = (bool) ($flags[$key] ?? false);
        return $clean;
    }

    /** @param array<int, mixed> $items
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeIssues(array $items): array
    {
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $description = trim(sanitize_textarea_field((string) ($item['description'] ?? '')));
            if ($description === '') continue;
            $type = sanitize_key((string) ($item['type'] ?? 'other'));
            if (! isset(self::ISSUE_TYPES[$type])) $type = 'other';
            $status = sanitize_key((string) ($item['status'] ?? 'pending'));
            if (! in_array($status, ['pending', 'resolved'], true)) $status = 'pending';
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '') $id = 'issue-' . substr(md5($description . '|' . $index), 0, 12);
            $clean[] = [
                'id' => $id,
                'type' => $type,
                'typeLabel' => self::ISSUE_TYPES[$type],
                'description' => $description,
                'excerpt' => trim(sanitize_textarea_field((string) ($item['excerpt'] ?? ''))),
                'status' => $status,
                'createdAt' => sanitize_text_field((string) ($item['createdAt'] ?? gmdate('c'))),
                'resolvedAt' => $status === 'resolved' ? sanitize_text_field((string) ($item['resolvedAt'] ?? gmdate('c'))) : '',
            ];
        }
        return $clean;
    }

    /** @param mixed $items
     *  @return array<int, array<string, mixed>>
     */
    private function reviewNotes($items, array $resolvedIds, string $kind): array
    {
        if (! is_array($items)) return [];
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $text = trim(sanitize_textarea_field((string) ($item['text'] ?? '')));
            if ($text === '') continue;
            $id = sanitize_key((string) ($item['id'] ?? ($kind . '-' . $index)));
            $clean[] = [
                'id' => $id,
                'text' => $text,
                'kind' => $kind,
                'createdAt' => sanitize_text_field((string) ($item['createdAt'] ?? '')),
                'resolved' => in_array($id, $resolvedIds, true),
            ];
        }
        return $clean;
    }

    /** @return array<int, array<string, mixed>> */
    private function revisionSources(int $userId, int $bookId, int $chapterId, array $verifiedIds, array $dismissedIds): array
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
            $id = (string) $post->ID;
            $sources[] = [
                'id' => $id,
                'category' => sanitize_key((string) get_post_meta($post->ID, '_verbum_research_category', true)),
                'title' => trim((string) get_post_meta($post->ID, '_verbum_research_title', true)),
                'author' => trim((string) get_post_meta($post->ID, '_verbum_research_author', true)),
                'reference' => trim((string) get_post_meta($post->ID, '_verbum_research_reference', true)),
                'excerpt' => trim((string) get_post_meta($post->ID, '_verbum_research_excerpt', true)),
                'selectedForWriting' => $selected,
                'used' => $used,
                'verified' => in_array($id, $verifiedIds, true),
                'dismissed' => in_array($id, $dismissedIds, true),
            ];
        }
        return $sources;
    }

    /** @param string[] $requested
     *  @return string[]
     */
    private function ownedSourceIds(int $userId, int $bookId, int $chapterId, array $requested): array
    {
        if ($requested === []) return [];
        $query = new \WP_Query([
            'post_type' => LibraryPostTypes::RESEARCH,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => '_verbum_book_id', 'value' => $bookId, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => '_verbum_chapter_id', 'value' => $chapterId, 'compare' => '=', 'type' => 'NUMERIC'],
            ],
            'no_found_rows' => true,
        ]);
        $allowed = array_map('strval', (array) $query->posts);
        return array_values(array_intersect($requested, $allowed));
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
            $clean[] = ['id' => sanitize_key((string) ($item['id'] ?? ('prep-' . ($index + 1)))), 'text' => $text, 'order' => $index + 1];
        }
        return $clean;
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
    private function combinedHtml(string $introduction, array $sections, string $conclusion): string
    {
        $html = '<h2>Introdução</h2>' . $introduction;
        foreach ($sections as $section) $html .= '<h2>' . esc_html((string) ($section['title'] ?? 'Desenvolvimento')) . '</h2>' . (string) ($section['content'] ?? '');
        return $html . '<h2>Conclusão</h2>' . $conclusion;
    }

    private function wordCount(string $html): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') return 0;
        $words = preg_split('/\s+/u', $text);
        return is_array($words) ? count(array_filter($words, static fn (string $word): bool => $word !== '')) : 0;
    }

    /** @param array<int, array<string, mixed>> $sections */
    private function snapshot(int $chapterId, string $introduction, array $sections, string $conclusion, string $kind, bool $force): void
    {
        $versions = get_post_meta($chapterId, '_verbum_revision_versions', true);
        $versions = is_array($versions) ? $versions : [];
        $content = $this->combinedHtml($introduction, $sections, $conclusion);
        $hash = md5($content);
        $last = $versions !== [] ? end($versions) : null;
        if (! $force && is_array($last) && (string) ($last['hash'] ?? '') === $hash) return;
        $versions[] = [
            'id' => 'revision-version-' . substr(md5($hash . '|' . microtime(true)), 0, 12),
            'savedAt' => gmdate('c'),
            'kind' => $kind,
            'wordCount' => $this->wordCount($content),
            'hash' => $hash,
            'introduction' => $introduction,
            'sections' => $sections,
            'conclusion' => $conclusion,
        ];
        if (count($versions) > 20) $versions = array_slice($versions, -20);
        update_post_meta($chapterId, '_verbum_revision_versions', $versions);
    }

    /** @return array<int, array<string, mixed>> */
    private function revisionVersions(int $chapterId): array
    {
        $versions = get_post_meta($chapterId, '_verbum_revision_versions', true);
        if (! is_array($versions)) return [];
        $result = [];
        foreach (array_reverse($versions) as $version) {
            if (! is_array($version)) continue;
            $result[] = [
                'id' => (string) ($version['id'] ?? ''),
                'savedAt' => (string) ($version['savedAt'] ?? ''),
                'kind' => (string) ($version['kind'] ?? 'revision_manual'),
                'wordCount' => (int) ($version['wordCount'] ?? 0),
            ];
        }
        return $result;
    }

    private function isCompleted(int $chapterId): bool
    {
        $stages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        return (is_array($stages) && in_array('revision', $stages, true)) || (bool) get_post_meta($chapterId, '_verbum_chapter_completed', true);
    }

    private function markAlteredIfCompleted(int $chapterId): void
    {
        if (! $this->isCompleted($chapterId)) return;
        update_post_meta($chapterId, '_verbum_revision_altered_after_completion', 1);
        update_post_meta($chapterId, '_verbum_revision_altered_after_completion_at', gmdate('c'));
    }

    private function touchChapter(int $chapterId): void
    {
        $post = get_post($chapterId);
        if ($post instanceof \WP_Post) wp_update_post(['ID' => $chapterId, 'post_content' => $post->post_content]);
    }
}
