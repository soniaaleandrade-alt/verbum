<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkChapterResearchRepository
{
    private const CATEGORIES = [
        'scripture' => 'Sagrada Escritura',
        'catechism' => 'Catecismo da Igreja Católica',
        'magisterium' => 'Documentos do Magistério',
        'saints' => 'Santos',
        'church_fathers' => 'Padres da Igreja',
        'books' => 'Livros',
        'articles' => 'Artigos',
        'historical_documents' => 'Documentos Históricos',
        'other' => 'Outras Fontes',
    ];

    private const DETAIL_FIELDS = [
        'book', 'chapter', 'verses', 'paragraph', 'document_type', 'institution', 'year', 'publisher', 'page',
        'isbn', 'journal', 'volume', 'issue', 'pages', 'doi', 'source', 'location', 'work', 'section', 'context',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId, int $chapterId): array
    {
        $chapter = $this->ownedChapter($userId, $bookId, $chapterId);
        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $preparationCompleted = in_array('preparation', $completedStages, true);
        $completed = in_array('research', $completedStages, true);

        $selectedCategories = get_post_meta($chapterId, '_verbum_preparation_sources', true);
        $selectedCategories = is_array($selectedCategories)
            ? array_values(array_intersect(array_keys(self::CATEGORIES), array_map('sanitize_key', $selectedCategories)))
            : [];

        $reviewedCategories = get_post_meta($chapterId, '_verbum_research_reviewed_categories', true);
        $reviewedCategories = is_array($reviewedCategories)
            ? array_values(array_intersect(array_keys(self::CATEGORIES), array_map('sanitize_key', $reviewedCategories)))
            : [];

        $directionReviewed = (bool) get_post_meta($chapterId, '_verbum_research_direction_reviewed', true);
        $sources = array_map(fn (\WP_Post $post): array => $this->sourceData($post), $this->sourcesForChapter($userId, $bookId, $chapterId));
        $ideas = get_post_meta($chapterId, '_verbum_research_ideas', true);
        $ideas = is_array($ideas) ? $this->normalizeIdeas($ideas) : [];

        $counts = [
            'total' => count($sources),
            'selectedForWriting' => 0,
            'highlighted' => 0,
            'scripture' => 0,
            'catechism' => 0,
            'magisterium' => 0,
            'saints' => 0,
            'church_fathers' => 0,
            'books' => 0,
            'articles' => 0,
            'historical_documents' => 0,
            'other' => 0,
        ];
        foreach ($sources as $source) {
            $category = (string) $source['category'];
            if (array_key_exists($category, $counts)) $counts[$category]++;
            if ((bool) $source['selectedForWriting']) $counts['selectedForWriting']++;
            if ((bool) $source['highlighted']) $counts['highlighted']++;
        }

        $structure = get_post_meta($chapterId, '_verbum_preparation_structure', true);
        $structure = is_array($structure) ? $this->normalizeStructure($structure) : [];
        $linkedCount = count(array_filter($sources, static fn (array $source): bool => (string) $source['structureItemId'] !== ''));

        $checklist = [];
        $checklist[] = ['key' => 'direction', 'label' => 'Direção da pesquisa revisada', 'completed' => $directionReviewed];
        foreach ($selectedCategories as $category) {
            $checklist[] = [
                'key' => 'category_' . $category,
                'label' => self::CATEGORIES[$category] . ' pesquisada',
                'completed' => in_array($category, $reviewedCategories, true),
            ];
        }
        $checklist[] = ['key' => 'references', 'label' => 'Referências organizadas', 'completed' => $counts['total'] > 0];
        $checklist[] = ['key' => 'linked', 'label' => 'Fontes vinculadas à estrutura', 'completed' => $structure === [] || $linkedCount > 0];
        $checklist[] = ['key' => 'highlighted', 'label' => 'Fontes principais destacadas', 'completed' => $counts['highlighted'] > 0];
        $checklist[] = ['key' => 'selected', 'label' => 'Materiais selecionados para Redação', 'completed' => $counts['selectedForWriting'] > 0];
        $checklist[] = ['key' => 'ideas', 'label' => 'Ideias da pesquisa organizadas', 'completed' => count($ideas) > 0];
        $checklist[] = ['key' => 'completed', 'label' => 'Pesquisa concluída', 'completed' => $completed];

        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $requiredCategoriesReviewed = count($selectedCategories) > 0
            && count(array_diff($selectedCategories, $reviewedCategories)) === 0;
        $ready = $preparationCompleted
            && $counts['total'] > 0
            && $requiredCategoriesReviewed
            && $counts['selectedForWriting'] > 0;

        $categoryOptions = [];
        foreach (self::CATEGORIES as $key => $label) {
            $categoryOptions[] = [
                'key' => $key,
                'label' => $label,
                'selectedInPreparation' => in_array($key, $selectedCategories, true),
                'reviewed' => in_array($key, $reviewedCategories, true),
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }
        usort($categoryOptions, static function (array $a, array $b): int {
            if ($a['selectedInPreparation'] === $b['selectedInPreparation']) return 0;
            return $a['selectedInPreparation'] ? -1 : 1;
        });

        $preparation = [
            'objective' => trim((string) get_post_meta($chapterId, '_verbum_preparation_objective', true)),
            'centralQuestion' => trim((string) get_post_meta($chapterId, '_verbum_preparation_central_question', true)),
            'thesis' => trim((string) get_post_meta($chapterId, '_verbum_preparation_thesis', true)),
            'keywords' => $this->stringList(get_post_meta($chapterId, '_verbum_preparation_keywords', true)),
            'sourceCategories' => $selectedCategories,
            'structureItems' => $structure,
        ];

        return [
            'chapterId' => (string) $chapterId,
            'title' => get_the_title($chapter),
            'preparationCompleted' => $preparationCompleted,
            'preparation' => $preparation,
            'sources' => $sources,
            'ideas' => $ideas,
            'categoryOptions' => $categoryOptions,
            'reviewedCategories' => $reviewedCategories,
            'directionReviewed' => $directionReviewed,
            'counts' => $counts,
            'progress' => count($checklist) > 0 ? (int) round(($completedCount / count($checklist)) * 100) : 0,
            'completedCount' => $completedCount,
            'total' => count($checklist),
            'checklist' => $checklist,
            'ready' => $ready,
            'completed' => $completed,
            'completedAt' => (string) get_post_meta($chapterId, '_verbum_research_completed_at', true),
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function saveState(int $userId, int $bookId, int $chapterId, array $fields): array
    {
        $this->ownedChapter($userId, $bookId, $chapterId);
        if (array_key_exists('direction_reviewed', $fields)) {
            update_post_meta($chapterId, '_verbum_research_direction_reviewed', (bool) $fields['direction_reviewed'] ? 1 : 0);
        }
        if (array_key_exists('reviewed_categories', $fields)) {
            $categories = is_array($fields['reviewed_categories']) ? $fields['reviewed_categories'] : [];
            $categories = array_values(array_intersect(array_keys(self::CATEGORIES), array_map('sanitize_key', $categories)));
            update_post_meta($chapterId, '_verbum_research_reviewed_categories', $categories);
        }
        if (array_key_exists('ideas', $fields)) {
            $ideas = is_array($fields['ideas']) ? $fields['ideas'] : [];
            update_post_meta($chapterId, '_verbum_research_ideas', $this->normalizeIdeas($ideas, true));
        }
        $this->touchChapter($chapterId);
        return $this->data($userId, $bookId, $chapterId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function createSource(int $userId, int $bookId, int $chapterId, array $fields): array
    {
        $this->assertResearchAvailable($userId, $bookId, $chapterId);
        $clean = $this->normalizeSourceFields($fields);
        $postId = wp_insert_post([
            'post_type' => LibraryPostTypes::RESEARCH,
            'post_status' => 'publish',
            'post_author' => $userId,
            'post_title' => $this->sourcePostTitle($clean),
            'post_content' => (string) $clean['excerpt'],
        ], true);
        if (is_wp_error($postId)) {
            throw new ValidationError('Não foi possível cadastrar a fonte de pesquisa.');
        }
        $this->persistSource((int) $postId, $bookId, $chapterId, $clean);
        $this->touchChapter($chapterId);
        return $this->sourceData(get_post((int) $postId));
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function updateSource(int $userId, int $bookId, int $chapterId, int $sourceId, array $fields): array
    {
        $post = $this->ownedSource($userId, $bookId, $chapterId, $sourceId);
        $existing = $this->sourceData($post);
        $merged = array_merge([
            'category' => $existing['category'],
            'title' => $existing['title'],
            'author' => $existing['author'],
            'reference' => $existing['reference'],
            'excerpt' => $existing['excerpt'],
            'notes' => $existing['notes'],
            'application' => $existing['application'],
            'tags' => $existing['tags'],
            'url' => $existing['url'],
            'structure_item_id' => $existing['structureItemId'],
            'highlighted' => $existing['highlighted'],
            'selected_for_writing' => $existing['selectedForWriting'],
            'details' => $existing['details'],
        ], $fields);
        $clean = $this->normalizeSourceFields($merged);
        wp_update_post([
            'ID' => $sourceId,
            'post_title' => $this->sourcePostTitle($clean),
            'post_content' => (string) $clean['excerpt'],
        ]);
        $this->persistSource($sourceId, $bookId, $chapterId, $clean);
        $this->touchChapter($chapterId);
        return $this->sourceData(get_post($sourceId));
    }

    public function deleteSource(int $userId, int $bookId, int $chapterId, int $sourceId): void
    {
        $this->ownedSource($userId, $bookId, $chapterId, $sourceId);
        wp_delete_post($sourceId, true);
        $this->touchChapter($chapterId);
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId, int $chapterId): array
    {
        $data = $this->data($userId, $bookId, $chapterId);
        if (! $data['ready']) {
            throw new ValidationError('Cadastre ao menos uma fonte, revise as categorias escolhidas na Preparação e selecione ao menos um material para a Redação.');
        }

        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        if (! in_array('research', $completedStages, true)) $completedStages[] = 'research';
        update_post_meta($chapterId, '_verbum_chapter_completed_stages', array_values(array_unique($completedStages)));
        update_post_meta($chapterId, '_verbum_chapter_stage', 'writing');
        update_post_meta($chapterId, '_verbum_research_completed_at', gmdate('c'));
        $this->touchChapter($chapterId);

        return $this->data($userId, $bookId, $chapterId);
    }

    /** @return \WP_Post[] */
    private function sourcesForChapter(int $userId, int $bookId, int $chapterId): array
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
        return array_values(array_filter($query->posts, static fn ($post): bool => $post instanceof \WP_Post));
    }

    /** @return array<string, mixed> */
    private function sourceData(?\WP_Post $post): array
    {
        if (! $post instanceof \WP_Post) throw new NotFoundError('Fonte de pesquisa não encontrada.');
        $category = sanitize_key((string) get_post_meta($post->ID, '_verbum_research_category', true));
        if (! array_key_exists($category, self::CATEGORIES)) $category = 'other';
        $details = get_post_meta($post->ID, '_verbum_research_details', true);
        $details = is_array($details) ? $this->normalizeDetails($details) : [];
        $selected = (bool) get_post_meta($post->ID, '_verbum_research_selected', true);
        $used = (bool) get_post_meta($post->ID, '_verbum_research_used', true);
        return [
            'id' => (string) $post->ID,
            'category' => $category,
            'categoryLabel' => self::CATEGORIES[$category],
            'title' => trim((string) get_post_meta($post->ID, '_verbum_research_title', true)),
            'author' => trim((string) get_post_meta($post->ID, '_verbum_research_author', true)),
            'reference' => trim((string) get_post_meta($post->ID, '_verbum_research_reference', true)),
            'excerpt' => trim((string) get_post_meta($post->ID, '_verbum_research_excerpt', true)),
            'notes' => trim((string) get_post_meta($post->ID, '_verbum_research_notes', true)),
            'application' => trim((string) get_post_meta($post->ID, '_verbum_research_application', true)),
            'tags' => $this->stringList(get_post_meta($post->ID, '_verbum_research_tags', true)),
            'url' => trim((string) get_post_meta($post->ID, '_verbum_research_url', true)),
            'structureItemId' => sanitize_key((string) get_post_meta($post->ID, '_verbum_research_structure_item_id', true)),
            'highlighted' => (bool) get_post_meta($post->ID, '_verbum_research_highlighted', true),
            'selectedForWriting' => $selected,
            'status' => $used ? 'used' : ($selected ? 'selected' : 'research'),
            'details' => $details,
            'createdAt' => mysql_to_rfc3339($post->post_date_gmt ?: $post->post_date),
            'updatedAt' => mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified),
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    private function normalizeSourceFields(array $fields): array
    {
        $category = sanitize_key((string) ($fields['category'] ?? 'other'));
        if (! array_key_exists($category, self::CATEGORIES)) $category = 'other';
        $tags = is_array($fields['tags'] ?? null) ? $fields['tags'] : [];
        $tags = array_values(array_filter(array_map(static fn ($value): string => sanitize_text_field((string) $value), $tags), static fn (string $value): bool => $value !== ''));
        $details = is_array($fields['details'] ?? null) ? $fields['details'] : [];
        return [
            'category' => $category,
            'title' => sanitize_text_field((string) ($fields['title'] ?? '')),
            'author' => sanitize_text_field((string) ($fields['author'] ?? '')),
            'reference' => sanitize_text_field((string) ($fields['reference'] ?? '')),
            'excerpt' => sanitize_textarea_field((string) ($fields['excerpt'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($fields['notes'] ?? '')),
            'application' => sanitize_textarea_field((string) ($fields['application'] ?? '')),
            'tags' => $tags,
            'url' => esc_url_raw((string) ($fields['url'] ?? '')),
            'structure_item_id' => sanitize_key((string) ($fields['structure_item_id'] ?? '')),
            'highlighted' => (bool) ($fields['highlighted'] ?? false),
            'selected_for_writing' => (bool) ($fields['selected_for_writing'] ?? false),
            'details' => $this->normalizeDetails($details),
        ];
    }

    /** @param array<string, mixed> $fields */
    private function persistSource(int $sourceId, int $bookId, int $chapterId, array $fields): void
    {
        update_post_meta($sourceId, '_verbum_book_id', $bookId);
        update_post_meta($sourceId, '_verbum_chapter_id', $chapterId);
        update_post_meta($sourceId, '_verbum_research_category', $fields['category']);
        foreach (['title', 'author', 'reference', 'excerpt', 'notes', 'application', 'url', 'structure_item_id'] as $field) {
            update_post_meta($sourceId, '_verbum_research_' . $field, $fields[$field]);
        }
        update_post_meta($sourceId, '_verbum_research_tags', $fields['tags']);
        update_post_meta($sourceId, '_verbum_research_highlighted', $fields['highlighted'] ? 1 : 0);
        update_post_meta($sourceId, '_verbum_research_selected', $fields['selected_for_writing'] ? 1 : 0);
        update_post_meta($sourceId, '_verbum_research_details', $fields['details']);
    }

    /** @param array<string, mixed> $fields */
    private function sourcePostTitle(array $fields): string
    {
        foreach (['reference', 'title', 'author'] as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') return $value;
        }
        return self::CATEGORIES[(string) $fields['category']];
    }

    private function assertResearchAvailable(int $userId, int $bookId, int $chapterId): void
    {
        $this->ownedChapter($userId, $bookId, $chapterId);
        $completedStages = get_post_meta($chapterId, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        if (! in_array('preparation', $completedStages, true)) {
            throw new ValidationError('Conclua a Preparação do Capítulo antes de iniciar a Pesquisa.');
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

    private function ownedSource(int $userId, int $bookId, int $chapterId, int $sourceId): \WP_Post
    {
        $source = get_post($sourceId);
        if (! $source instanceof \WP_Post
            || $source->post_type !== LibraryPostTypes::RESEARCH
            || (int) $source->post_author !== $userId
            || (int) get_post_meta($sourceId, '_verbum_book_id', true) !== $bookId
            || (int) get_post_meta($sourceId, '_verbum_chapter_id', true) !== $chapterId) {
            throw new NotFoundError('Fonte de pesquisa não encontrada.');
        }
        return $source;
    }

    /** @param mixed $value
     *  @return string[]
     */
    private function stringList($value): array
    {
        return is_array($value)
            ? array_values(array_filter(array_map(static fn ($item): string => sanitize_text_field((string) $item), $value), static fn (string $item): bool => $item !== ''))
            : [];
    }

    /** @param array<string, mixed> $details
     *  @return array<string, string>
     */
    private function normalizeDetails(array $details): array
    {
        $clean = [];
        foreach (self::DETAIL_FIELDS as $field) {
            if (! array_key_exists($field, $details)) continue;
            $value = sanitize_text_field((string) $details[$field]);
            if ($value !== '') $clean[$this->camelCase($field)] = $value;
        }
        return $clean;
    }

    /** @param array<int, mixed> $items
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeStructure(array $items): array
    {
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $text = trim(sanitize_text_field((string) ($item['text'] ?? '')));
            if ($text === '') continue;
            $clean[] = [
                'id' => sanitize_key((string) ($item['id'] ?? ('structure-' . ($index + 1)))),
                'text' => $text,
                'order' => max(1, (int) ($item['order'] ?? ($index + 1))),
            ];
        }
        return $clean;
    }

    /** @param array<int, mixed> $ideas
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeIdeas(array $ideas, bool $regenerateIds = false): array
    {
        $clean = [];
        foreach ($ideas as $index => $idea) {
            if (! is_array($idea)) continue;
            $title = sanitize_text_field((string) ($idea['title'] ?? ''));
            $description = sanitize_textarea_field((string) ($idea['description'] ?? ''));
            if ($title === '' && $description === '') continue;
            $id = sanitize_key((string) ($idea['id'] ?? ''));
            if ($id === '' || ($regenerateIds && strpos($id, 'new-') === 0)) {
                $id = 'idea-' . substr(md5($title . '|' . $description . '|' . $index . '|' . microtime(true)), 0, 12);
            }
            $clean[] = [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'tags' => $this->stringList($idea['tags'] ?? []),
                'structureItemId' => sanitize_key((string) ($idea['structureItemId'] ?? ($idea['structure_item_id'] ?? ''))),
            ];
        }
        return $clean;
    }

    private function touchChapter(int $chapterId): void
    {
        $post = get_post($chapterId);
        if ($post instanceof \WP_Post) wp_update_post(['ID' => $chapterId, 'post_content' => $post->post_content]);
    }

    private function camelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
