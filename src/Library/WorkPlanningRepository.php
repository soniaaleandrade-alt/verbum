<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class WorkPlanningRepository
{
    private const TEXT_FIELDS = [
        'central_question', 'main_thesis', 'overview', 'methodology', 'presentation_form', 'approach',
        'general_structure', 'editorial_notes', 'writing_strategy', 'initial_schedule',
    ];

    private const TYPE_LABELS = [
        'part' => 'Parte',
        'chapter' => 'Capítulo',
        'subchapter' => 'Subcapítulo',
        'preface' => 'Prefácio',
        'presentation' => 'Apresentação',
        'introduction' => 'Introdução',
        'dedication' => 'Dedicatória',
        'acknowledgements' => 'Agradecimentos',
        'epigraph' => 'Epígrafe',
        'letter_to_reader' => 'Carta ao leitor',
        'prologue' => 'Prólogo',
        'conclusion' => 'Conclusão',
        'epilogue' => 'Epílogo',
        'afterword' => 'Posfácio',
        'appendix' => 'Apêndice',
        'annex' => 'Anexo',
        'glossary' => 'Glossário',
        'bibliography' => 'Bibliografia ou Referências',
        'other' => 'Outro elemento',
    ];

    private const TYPE_GROUPS = [
        'dedication' => 'initial', 'epigraph' => 'initial', 'acknowledgements' => 'initial',
        'letter_to_reader' => 'initial', 'preface' => 'initial', 'presentation' => 'initial',
        'prologue' => 'initial', 'introduction' => 'initial',
        'part' => 'body', 'chapter' => 'body', 'subchapter' => 'body',
        'conclusion' => 'final', 'epilogue' => 'final', 'afterword' => 'final',
        'appendix' => 'final', 'annex' => 'final', 'glossary' => 'final', 'bibliography' => 'final',
    ];

    private const SAFE_TITLE_TYPES = [
        'prefacio' => 'preface',
        'apresentacao' => 'presentation',
        'introducao' => 'introduction',
        'dedicatoria' => 'dedication',
        'agradecimentos' => 'acknowledgements',
        'epigrafe' => 'epigraph',
        'carta-ao-leitor' => 'letter_to_reader',
        'prologo' => 'prologue',
        'conclusao' => 'conclusion',
        'epilogo' => 'epilogue',
        'posfacio' => 'afterword',
        'apendice' => 'appendix',
        'anexo' => 'annex',
        'glossario' => 'glossary',
        'bibliografia' => 'bibliography',
        'referencias' => 'bibliography',
        'referencias-bibliograficas' => 'bibliography',
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
        $keywords = get_post_meta($bookId, '_verbum_keywords', true);
        if (is_string($keywords)) {
            $keywords = preg_split('/[,;]+/', $keywords) ?: [];
        }
        $values['keywords'] = $this->stringList($keywords);
        $values['limits'] = trim((string) get_post_meta($bookId, '_verbum_work_project_limits', true));

        $stored = get_post_meta($bookId, '_verbum_planning_structure_items', true);
        $stored = is_array($stored) ? $stored : [];
        $items = $this->normalizeItems($stored);
        $migrations = $this->classificationMigrations($stored, $items);
        if ($items !== $stored) {
            update_post_meta($bookId, '_verbum_planning_structure_items', $items);
            if ($migrations !== []) {
                $this->appendLog($bookId, '_verbum_planning_structure_reclassification_log', $migrations);
            }
        }

        $chapters = $this->chapterRecords($bookId);
        $chapterByItem = [];
        foreach ($chapters as $chapter) {
            if ($chapter['itemId'] !== '') $chapterByItem[$chapter['itemId']] = $chapter;
        }
        foreach ($items as &$item) {
            $linked = $chapterByItem[(string) $item['id']] ?? null;
            if ($linked !== null) {
                $item['linkedChapterId'] = (string) $linked['id'];
                $item['linkedChapterTitle'] = (string) $linked['title'];
                $item['linkedChapterHasContent'] = (bool) $linked['hasContent'];
                $item['syncState'] = ((string) $linked['title'] === (string) $item['title']) ? 'synced' : 'title_conflict';
            } else {
                $item['linkedChapterId'] = '';
                $item['linkedChapterTitle'] = '';
                $item['linkedChapterHasContent'] = false;
                $item['syncState'] = $item['type'] === 'chapter' ? 'pending' : 'not_applicable';
            }
        }
        unset($item);
        $values['structureItems'] = $items;

        $counts = $this->counts($items);
        $hasStructure = count(array_filter($items, static fn (array $item): bool => trim((string) $item['title']) !== '')) > 0;
        $hasChapter = $counts['chapters'] > 0;
        $completedCount = ($hasStructure ? 1 : 0) + ($hasChapter ? 1 : 0);
        $ready = $completedCount === 2;
        $synced = count(array_filter($items, static fn (array $item): bool => $item['type'] === 'chapter' && (string) ($item['linkedChapterId'] ?? '') !== ''));
        $hierarchyIssues = $this->hierarchyIssues($items);
        $completedStages = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];

        return [
            'progress' => $completedCount * 50,
            'completedCount' => $completedCount,
            'total' => 2,
            'ready' => $ready,
            'completed' => in_array('planning', $completedStages, true),
            'checklist' => [
                ['key' => 'structure', 'label' => 'Pelo menos um item estrutural válido', 'completed' => $hasStructure],
                ['key' => 'chapter', 'label' => 'Pelo menos um capítulo definido', 'completed' => $hasChapter],
            ],
            'values' => $values,
            'counts' => $counts,
            'typeOptions' => $this->typeOptions(),
            'hierarchyIssues' => $hierarchyIssues,
            'generatedChapterIds' => array_values(array_map(static fn (array $chapter): string => (string) $chapter['id'], $chapters)),
            'chaptersGenerated' => $hasChapter && $synced === $counts['chapters'],
            'syncedChapterCount' => $synced,
            'syncLog' => $this->recentLog($bookId, '_verbum_planning_sync_log'),
            'reclassificationLog' => $this->recentLog($bookId, '_verbum_planning_structure_reclassification_log'),
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
        if (array_key_exists('keywords', $fields)) {
            update_post_meta($bookId, '_verbum_keywords', $this->stringList($fields['keywords']));
        }
        if (array_key_exists('limits', $fields)) {
            update_post_meta($bookId, '_verbum_work_project_limits', sanitize_textarea_field((string) $fields['limits']));
        }
        if (array_key_exists('structure_items', $fields)) {
            $old = get_post_meta($bookId, '_verbum_planning_structure_items', true);
            $old = is_array($old) ? $this->normalizeItems($old) : [];
            $items = is_array($fields['structure_items']) ? $this->normalizeItems($fields['structure_items'], true) : [];
            $this->assertHierarchySafe($items);
            update_post_meta($bookId, '_verbum_planning_structure_items', $items);
            $this->recordStructureChanges($bookId, $old, $items);
        }

        $this->touchBook($bookId);
        $data = $this->data($bookId);
        if (! $data['ready']) {
            $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
            $completed = is_array($completed) ? $completed : [];
            if (in_array('planning', $completed, true)) {
                update_post_meta($bookId, '_verbum_completed_stages', array_values(array_diff($completed, ['planning'])));
                $currentStage = (string) (get_post_meta($bookId, '_verbum_stage', true) ?: 'planning');
                if ($currentStage === 'planning') update_post_meta($bookId, '_verbum_stage', 'planning');
            }
        }
        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function syncPreview(int $bookId): array
    {
        $data = $this->data($bookId);
        $items = $data['values']['structureItems'];
        $chapters = $this->chapterRecords($bookId);
        $linkedByItem = [];
        $unlinkedByTitle = [];
        $unlinked = [];
        foreach ($chapters as $chapter) {
            if ($chapter['itemId'] !== '') {
                $linkedByItem[$chapter['itemId']] = $chapter;
            } else {
                $key = $this->normalizedTitle((string) $chapter['title']);
                $unlinkedByTitle[$key][] = $chapter;
                $unlinked[(string) $chapter['id']] = $chapter;
            }
        }

        $create = []; $link = []; $update = []; $unchanged = []; $conflicts = []; $structural = [];
        foreach ($items as $item) {
            if ($item['type'] !== 'chapter') {
                $structural[] = ['itemId' => $item['id'], 'title' => $item['title'], 'type' => $item['type'], 'typeLabel' => self::TYPE_LABELS[$item['type']] ?? 'Elemento'];
                continue;
            }
            $itemId = (string) $item['id'];
            $linked = $linkedByItem[$itemId] ?? null;
            if ($linked !== null) {
                if ((string) $linked['title'] === (string) $item['title']) {
                    $unchanged[] = $this->syncItem($item, $linked, 'unchanged');
                } else {
                    $row = $this->syncItem($item, $linked, 'title_update');
                    $row['chapterTitle'] = $linked['title'];
                    $update[] = $row;
                }
                continue;
            }
            $matches = $unlinkedByTitle[$this->normalizedTitle((string) $item['title'])] ?? [];
            if (count($matches) === 1) {
                $chapter = $matches[0];
                $link[] = $this->syncItem($item, $chapter, 'link');
                unset($unlinked[(string) $chapter['id']]);
            } elseif (count($matches) > 1) {
                $conflicts[] = ['itemId' => $itemId, 'title' => $item['title'], 'reason' => 'Há mais de um capítulo existente com este título.', 'chapterIds' => array_map(static fn (array $chapter): string => (string) $chapter['id'], $matches)];
            } else {
                $create[] = ['itemId' => $itemId, 'title' => $item['title'], 'order' => $this->chapterOrderForItem($items, $itemId)];
            }
        }

        return [
            'create' => $create,
            'link' => $link,
            'update' => $update,
            'unchanged' => $unchanged,
            'structuralItems' => $structural,
            'unmatchedExisting' => array_values(array_map(static fn (array $chapter): array => ['chapterId' => (string) $chapter['id'], 'title' => $chapter['title'], 'hasContent' => $chapter['hasContent']], $unlinked)),
            'conflicts' => $conflicts,
            'canConfirm' => $conflicts === [],
            'summary' => [
                'create' => count($create), 'link' => count($link), 'update' => count($update),
                'unchanged' => count($unchanged), 'conflicts' => count($conflicts),
            ],
        ];
    }

    /** @param array<string, mixed> $options
     *  @return array<string, mixed>
     */
    public function generateChapters(int $userId, int $bookId, array $options = []): array
    {
        if (! (bool) ($options['confirmed'] ?? false)) {
            throw new ValidationError('Confira a pré-visualização e confirme a sincronização antes de continuar.');
        }
        $preview = $this->syncPreview($bookId);
        if ($preview['conflicts'] !== []) {
            throw new ValidationError('Existem conflitos de correspondência. Revise a Estrutura antes de sincronizar.');
        }
        $titleUpdates = array_values(array_map('strval', is_array($options['title_updates'] ?? null) ? $options['title_updates'] : []));
        $syncOrder = (bool) ($options['sync_order'] ?? false);
        $items = $this->data($bookId)['values']['structureItems'];
        $itemById = [];
        foreach ($items as $index => $item) $itemById[(string) $item['id']] = $index;
        $created = []; $linked = []; $renamed = []; $reordered = [];

        foreach ($preview['link'] as $entry) {
            $chapterId = (int) $entry['chapterId'];
            update_post_meta($chapterId, '_verbum_planning_item_id', (string) $entry['itemId']);
            $index = $itemById[(string) $entry['itemId']] ?? null;
            if ($index !== null) $items[$index]['linkedChapterId'] = (string) $chapterId;
            $linked[] = (string) $chapterId;
        }
        foreach ($preview['update'] as $entry) {
            $chapterId = (int) $entry['chapterId'];
            if (in_array((string) $entry['itemId'], $titleUpdates, true)) {
                $history = get_post_meta($chapterId, '_verbum_planning_title_history', true);
                $history = is_array($history) ? $history : [];
                $history[] = ['title' => (string) $entry['chapterTitle'], 'changedAt' => gmdate('c'), 'source' => 'structure'];
                update_post_meta($chapterId, '_verbum_planning_title_history', array_slice($history, -25));
                wp_update_post(['ID' => $chapterId, 'post_title' => (string) $entry['title']]);
                $renamed[] = (string) $chapterId;
            }
        }
        foreach ($preview['create'] as $entry) {
            $chapterId = wp_insert_post([
                'post_type' => LibraryPostTypes::CHAPTER,
                'post_status' => 'publish',
                'post_title' => (string) $entry['title'],
                'post_content' => '',
                'post_author' => $userId,
            ], true);
            if (is_wp_error($chapterId)) throw new \RuntimeException('Não foi possível criar um dos capítulos da obra.');
            $chapterId = (int) $chapterId;
            update_post_meta($chapterId, '_verbum_book_id', $bookId);
            update_post_meta($chapterId, '_verbum_planning_item_id', (string) $entry['itemId']);
            update_post_meta($chapterId, '_verbum_chapter_order', (int) $entry['order']);
            update_post_meta($chapterId, '_verbum_chapter_stage', 'preparation');
            update_post_meta($chapterId, '_verbum_chapter_word_count', 0);
            $index = $itemById[(string) $entry['itemId']] ?? null;
            if ($index !== null) $items[$index]['linkedChapterId'] = (string) $chapterId;
            $created[] = (string) $chapterId;
        }

        if ($syncOrder) {
            foreach ($items as $item) {
                if ($item['type'] !== 'chapter') continue;
                $chapterId = $this->chapterIdForItem($bookId, (string) $item['id']);
                if ($chapterId <= 0) continue;
                $order = $this->chapterOrderForItem($items, (string) $item['id']);
                if ((int) get_post_meta($chapterId, '_verbum_chapter_order', true) !== $order) {
                    update_post_meta($chapterId, '_verbum_chapter_order', $order);
                    $reordered[] = (string) $chapterId;
                }
            }
        }
        update_post_meta($bookId, '_verbum_planning_structure_items', $this->normalizeItems($items));
        $this->appendLog($bookId, '_verbum_planning_sync_log', [[
            'action' => 'chapter_sync', 'at' => gmdate('c'), 'created' => $created, 'linked' => $linked,
            'renamed' => $renamed, 'reordered' => $reordered,
        ]]);
        $this->touchBook($bookId);
        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function complete(int $bookId): array
    {
        $data = $this->data($bookId);
        if (! $data['ready']) {
            $pending = array_map(static fn (array $item): string => (string) $item['label'], array_values(array_filter($data['checklist'], static fn (array $item): bool => ! $item['completed'])));
            throw new ValidationError('Complete os elementos essenciais da Estrutura da Obra: ' . implode(', ', $pending) . '.');
        }
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('project', $completed, true)) throw new ValidationError('Conclua a Fundação da Obra antes da Estrutura.');
        if (! in_array('planning', $completed, true)) $completed[] = 'planning';
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
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $title = trim(sanitize_text_field((string) ($item['title'] ?? '')));
            if ($title === '') continue;
            $type = sanitize_key((string) ($item['type'] ?? 'chapter'));
            if (! array_key_exists($type, self::TYPE_LABELS)) $type = 'chapter';
            $safe = self::SAFE_TITLE_TYPES[$this->normalizedTitle($title)] ?? null;
            $legacyType = sanitize_key((string) ($item['legacyType'] ?? ''));
            if ($safe !== null && $type !== $safe) {
                if ($legacyType === '') $legacyType = $type;
                $type = $safe;
            }
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '' || ($regenerateIds && strpos($id, 'new-') === 0)) {
                $id = 'outline-' . substr(md5($type . '|' . $title . '|' . $index . '|' . microtime(true)), 0, 12);
            }
            $group = sanitize_key((string) ($item['group'] ?? ''));
            if ($type !== 'other') $group = self::TYPE_GROUPS[$type] ?? 'body';
            elseif (! in_array($group, ['initial', 'body', 'final'], true)) $group = 'body';
            $clean[] = [
                'id' => $id,
                'type' => $type,
                'legacyType' => $legacyType,
                'title' => $title,
                'parentId' => sanitize_key((string) ($item['parentId'] ?? '')),
                'group' => $group,
                'linkedChapterId' => preg_replace('/\D+/', '', (string) ($item['linkedChapterId'] ?? '')) ?: '',
                'syncState' => sanitize_key((string) ($item['syncState'] ?? '')),
                'order' => $index + 1,
            ];
        }
        return $clean;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function assertHierarchySafe(array $items): void
    {
        $byId = [];
        foreach ($items as $item) $byId[(string) $item['id']] = $item;
        foreach ($items as $item) {
            $id = (string) $item['id']; $parentId = (string) ($item['parentId'] ?? ''); $type = (string) $item['type'];
            if ($parentId === '') continue;
            if ($parentId === $id) throw new ValidationError('Um item da Estrutura não pode ser pai de si mesmo.');
            if (! isset($byId[$parentId])) throw new ValidationError('O elemento pai selecionado não existe mais na Estrutura.');
            $parentType = (string) $byId[$parentId]['type'];
            if ($type === 'chapter' && $parentType !== 'part') throw new ValidationError('Um Capítulo só pode pertencer a uma Parte.');
            if ($type === 'subchapter' && $parentType !== 'chapter') throw new ValidationError('Um Subcapítulo só pode pertencer a um Capítulo.');
            if (! in_array($type, ['chapter', 'subchapter'], true)) throw new ValidationError('Este tipo editorial não pode possuir elemento pai.');
            $seen = [$id]; $cursor = $parentId;
            while ($cursor !== '') {
                if (in_array($cursor, $seen, true)) throw new ValidationError('A hierarquia não pode formar ciclos.');
                $seen[] = $cursor;
                $cursor = isset($byId[$cursor]) ? (string) ($byId[$cursor]['parentId'] ?? '') : '';
            }
        }
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, string>> */
    private function hierarchyIssues(array $items): array
    {
        $byId = []; $issues = [];
        foreach ($items as $item) $byId[(string) $item['id']] = $item;
        foreach ($items as $item) {
            $type = (string) $item['type']; $parentId = (string) ($item['parentId'] ?? '');
            if ($type === 'subchapter' && ($parentId === '' || ! isset($byId[$parentId]) || $byId[$parentId]['type'] !== 'chapter')) {
                $issues[] = ['itemId' => (string) $item['id'], 'message' => 'Subcapítulo sem Capítulo pai definido.'];
            }
            if ($type === 'chapter' && $parentId !== '' && (! isset($byId[$parentId]) || $byId[$parentId]['type'] !== 'part')) {
                $issues[] = ['itemId' => (string) $item['id'], 'message' => 'Capítulo vinculado a um elemento que não é Parte.'];
            }
        }
        return $issues;
    }

    /** @param array<int, array<string, mixed>> $items
     *  @return array<string, int> */
    private function counts(array $items): array
    {
        $counts = ['initial' => 0, 'parts' => 0, 'chapters' => 0, 'subchapters' => 0, 'final' => 0, 'complementary' => 0];
        foreach ($items as $item) {
            $type = (string) $item['type']; $group = (string) ($item['group'] ?? 'body');
            if ($group === 'initial') $counts['initial']++;
            if ($group === 'final') $counts['final']++;
            if ($type === 'part') $counts['parts']++;
            elseif ($type === 'chapter') $counts['chapters']++;
            elseif ($type === 'subchapter') $counts['subchapters']++;
            if (! in_array($type, ['part', 'chapter', 'subchapter'], true)) $counts['complementary']++;
        }
        return $counts;
    }

    /** @return array<int, array<string, mixed>> */
    private function chapterRecords(int $bookId): array
    {
        $query = new \WP_Query([
            'post_type' => LibraryPostTypes::CHAPTER, 'post_status' => 'publish', 'posts_per_page' => -1,
            'meta_key' => '_verbum_book_id', 'meta_value' => $bookId, 'orderby' => 'meta_value_num',
            'meta_type' => 'NUMERIC', 'no_found_rows' => true,
        ]);
        $records = [];
        foreach (is_array($query->posts) ? $query->posts : [] as $post) {
            $chapterId = $post instanceof \WP_Post ? (int) $post->ID : (int) $post;
            if ($chapterId <= 0) continue;
            $records[] = [
                'id' => $chapterId,
                'itemId' => (string) get_post_meta($chapterId, '_verbum_planning_item_id', true),
                'title' => get_the_title($chapterId),
                'order' => max(1, (int) get_post_meta($chapterId, '_verbum_chapter_order', true)),
                'hasContent' => $this->chapterHasContent($chapterId),
            ];
        }
        usort($records, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
        return $records;
    }

    private function chapterHasContent(int $chapterId): bool
    {
        if ((int) get_post_meta($chapterId, '_verbum_chapter_word_count', true) > 0) return true;
        $completed = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        if (is_array($completed) && $completed !== []) return true;
        foreach (['_verbum_preparation_objective','_verbum_preparation_structure_items','_verbum_research_ideas','_verbum_writing_introduction','_verbum_writing_sections','_verbum_writing_conclusion','_verbum_revision_issues'] as $key) {
            $value = get_post_meta($chapterId, $key, true);
            if ((is_array($value) && $value !== []) || (! is_array($value) && trim((string) $value) !== '')) return true;
        }
        return false;
    }

    /** @param array<string, mixed> $item @param array<string, mixed> $chapter @return array<string, mixed> */
    private function syncItem(array $item, array $chapter, string $action): array
    {
        return ['action' => $action, 'itemId' => (string) $item['id'], 'title' => (string) $item['title'], 'chapterId' => (string) $chapter['id'], 'chapterTitle' => (string) $chapter['title'], 'hasContent' => (bool) $chapter['hasContent']];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function chapterOrderForItem(array $items, string $itemId): int
    {
        $order = 0;
        foreach ($items as $item) {
            if ($item['type'] !== 'chapter') continue;
            $order++;
            if ((string) $item['id'] === $itemId) return $order;
        }
        return max(1, $order + 1);
    }

    private function chapterIdForItem(int $bookId, string $itemId): int
    {
        foreach ($this->chapterRecords($bookId) as $chapter) if ($chapter['itemId'] === $itemId) return (int) $chapter['id'];
        return 0;
    }

    /** @return array<int, array<string, string>> */
    private function typeOptions(): array
    {
        $options = [];
        foreach (self::TYPE_LABELS as $key => $label) $options[] = ['key' => $key, 'label' => $label, 'group' => self::TYPE_GROUPS[$key] ?? 'body'];
        return $options;
    }

    /** @param array<int, mixed> $before @param array<int, array<string, mixed>> $after
     * @return array<int, array<string, mixed>> */
    private function classificationMigrations(array $before, array $after): array
    {
        $oldById = [];
        foreach ($before as $item) if (is_array($item) && isset($item['id'])) $oldById[(string) $item['id']] = $item;
        $log = [];
        foreach ($after as $item) {
            $old = $oldById[(string) $item['id']] ?? null;
            if (! is_array($old)) continue;
            $oldType = sanitize_key((string) ($old['type'] ?? ''));
            if ($oldType !== '' && $oldType !== $item['type'] && (string) ($item['legacyType'] ?? '') === $oldType) {
                $log[] = ['action' => 'safe_reclassification', 'itemId' => $item['id'], 'title' => $item['title'], 'from' => $oldType, 'to' => $item['type'], 'at' => gmdate('c')];
            }
        }
        return $log;
    }

    /** @param array<int, array<string, mixed>> $old @param array<int, array<string, mixed>> $new */
    private function recordStructureChanges(int $bookId, array $old, array $new): void
    {
        $oldById = []; $newById = [];
        foreach ($old as $item) $oldById[(string) $item['id']] = $item;
        foreach ($new as $item) $newById[(string) $item['id']] = $item;
        $events = [];
        foreach ($newById as $id => $item) {
            if (! isset($oldById[$id])) { $events[] = ['action' => 'created', 'itemId' => $id, 'title' => $item['title'], 'at' => gmdate('c')]; continue; }
            $previous = $oldById[$id];
            foreach (['title','type','parentId','order'] as $field) {
                if (($previous[$field] ?? null) !== ($item[$field] ?? null)) $events[] = ['action' => 'changed_' . $field, 'itemId' => $id, 'from' => $previous[$field] ?? '', 'to' => $item[$field] ?? '', 'at' => gmdate('c')];
            }
        }
        foreach ($oldById as $id => $item) if (! isset($newById[$id])) $events[] = ['action' => 'removed_from_structure', 'itemId' => $id, 'title' => $item['title'], 'at' => gmdate('c')];
        if ($events !== []) $this->appendLog($bookId, '_verbum_planning_structure_log', $events);
    }

    /** @param array<int, array<string, mixed>> $events */
    private function appendLog(int $bookId, string $key, array $events): void
    {
        $log = get_post_meta($bookId, $key, true);
        $log = is_array($log) ? $log : [];
        foreach ($events as $event) $log[] = $event;
        update_post_meta($bookId, $key, array_slice($log, -100));
    }

    /** @return array<int, mixed> */
    private function recentLog(int $bookId, string $key): array
    {
        $log = get_post_meta($bookId, $key, true);
        return is_array($log) ? array_slice($log, -20) : [];
    }

    /** @return string[] */
    private function stringList($value): array
    {
        if (! is_array($value)) return [];
        return array_values(array_unique(array_filter(array_map(static fn ($item): string => trim(sanitize_text_field((string) $item)), $value))));
    }

    private function normalizedTitle(string $title): string
    {
        return sanitize_title(remove_accents(trim($title)));
    }

    private function touchBook(int $bookId): void
    {
        $post = get_post($bookId);
        if ($post instanceof \WP_Post) wp_update_post(['ID' => $bookId, 'post_content' => $post->post_content]);
    }

    private function camelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
