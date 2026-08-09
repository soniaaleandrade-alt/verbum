<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class LibraryRepository
{
    private const WORKFLOW_STAGES = [
        'identification' => 'Identificação',
        'project' => 'Projeto da Obra',
        'planning' => 'Planejamento',
        'development' => 'Desenvolvimento',
        'general_review' => 'Revisão Geral',
        'versions' => 'Controle de Versões',
        'audit' => 'Auditoria',
        'editorial_desk' => 'Mesa Editorial',
        'layout' => 'Diagramação',
        'legal' => 'Trâmites Legais',
        'publication' => 'Publicação',
    ];

    private const BOOK_META_FIELDS = [
        'subtitle',
        'series',
        'category',
        'genre',
        'audience',
        'age_range',
        'language',
        'country',
        'author_name',
        'coauthor_name',
        'main_objective',
        'reader_problem',
        'reader_transformation',
        'proposal_summary',
        'keyword',
        'planned_chapters',
        'word_goal',
        'target_date',
        'workflow_status',
        'tags',
        'collection',
        'priority',
        'cover_url',
        'color',
        'icon',
        'notes',
    ];

    /** @return array{projects: array<int, array<string, mixed>>, books: array<int, array<string, mixed>>} */
    public function libraryForUser(int $userId): array
    {
        $projects = $this->queryUserPosts(LibraryPostTypes::PROJECT, $userId);
        $books = $this->queryUserPosts(LibraryPostTypes::BOOK, $userId);

        return [
            'projects' => array_map(fn (\WP_Post $post): array => $this->projectData($post), $projects),
            'books' => array_map(fn (\WP_Post $post): array => $this->bookData($post), $books),
        ];
    }

    /** @return array<string, mixed> */
    public function workspaceForBook(int $userId, int $bookId): array
    {
        $book = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        $projectId = (int) get_post_meta($bookId, '_verbum_project_id', true);
        $project = $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);
        $currentStage = (string) (get_post_meta($bookId, '_verbum_stage', true) ?: 'identification');

        if (! array_key_exists($currentStage, self::WORKFLOW_STAGES)) {
            $currentStage = 'identification';
        }

        $stageKeys = array_keys(self::WORKFLOW_STAGES);
        $currentIndex = array_search($currentStage, $stageKeys, true);
        $completedMeta = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completedMeta = is_array($completedMeta) ? $completedMeta : [];
        $workflow = [];
        $completedCount = 0;

        foreach (self::WORKFLOW_STAGES as $key => $label) {
            $index = array_search($key, $stageKeys, true);
            $completed = in_array($key, $completedMeta, true) || ($currentIndex !== false && $index < $currentIndex);
            $status = $completed ? 'completed' : ($key === $currentStage ? 'in_progress' : 'locked');
            if ($completed) {
                $completedCount++;
            }
            $workflow[] = [
                'key' => $key,
                'label' => $label,
                'status' => $status,
                'order' => $index + 1,
            ];
        }

        $progress = (int) round(($completedCount / count(self::WORKFLOW_STAGES)) * 100);

        return [
            'book' => $this->bookData($book),
            'project' => $this->projectData($project),
            'currentStage' => $currentStage,
            'workflow' => $workflow,
            'metrics' => [
                'imo' => null,
                'rme' => null,
                'progress' => $progress,
                'chapters' => 0,
                'words' => (int) get_post_meta($bookId, '_verbum_word_count', true),
                'lastEdited' => mysql_to_rfc3339($book->post_modified_gmt ?: $book->post_modified),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function createProject(int $userId, string $name, string $description = ''): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new ValidationError('Informe o nome do projeto.');
        }

        $id = wp_insert_post([
            'post_type' => LibraryPostTypes::PROJECT,
            'post_status' => 'publish',
            'post_title' => $name,
            'post_content' => $description,
            'post_author' => $userId,
        ], true);

        if (is_wp_error($id)) {
            throw new \RuntimeException('Não foi possível criar o projeto.');
        }

        update_post_meta((int) $id, '_verbum_status', 'active');

        return $this->projectData($this->ownedPost((int) $id, LibraryPostTypes::PROJECT, $userId));
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function createBook(int $userId, int $projectId, array $fields): array
    {
        $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);

        $title = trim((string) ($fields['title'] ?? ''));
        if ($title === '') {
            throw new ValidationError('Informe o título da obra.');
        }

        $id = wp_insert_post([
            'post_type' => LibraryPostTypes::BOOK,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => '',
            'post_author' => $userId,
        ], true);

        if (is_wp_error($id)) {
            throw new \RuntimeException('Não foi possível criar a obra.');
        }

        $bookId = (int) $id;
        update_post_meta($bookId, '_verbum_project_id', $projectId);
        update_post_meta($bookId, '_verbum_status', 'active');
        update_post_meta($bookId, '_verbum_stage', 'identification');
        update_post_meta($bookId, '_verbum_completed_stages', []);
        $this->saveBookMeta($bookId, $fields);

        return $this->bookData($this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId));
    }

    /** @return array<string, mixed> */
    public function updateProject(int $userId, int $projectId, string $name, string $description = ''): array
    {
        $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);
        $name = trim($name);
        if ($name === '') {
            throw new ValidationError('Informe o nome do projeto.');
        }

        $result = wp_update_post([
            'ID' => $projectId,
            'post_title' => $name,
            'post_content' => $description,
        ], true);

        if (is_wp_error($result)) {
            throw new \RuntimeException('Não foi possível atualizar o projeto.');
        }

        return $this->projectData($this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId));
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function updateBook(int $userId, int $bookId, array $fields): array
    {
        $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);

        if (array_key_exists('project_id', $fields)) {
            $projectId = (int) $fields['project_id'];
            $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);
            update_post_meta($bookId, '_verbum_project_id', $projectId);
        }

        if (array_key_exists('title', $fields)) {
            $title = trim((string) $fields['title']);
            if ($title === '') {
                throw new ValidationError('Informe o título da obra.');
            }
            $result = wp_update_post(['ID' => $bookId, 'post_title' => $title], true);
            if (is_wp_error($result)) {
                throw new \RuntimeException('Não foi possível atualizar a obra.');
            }
        }

        $this->saveBookMeta($bookId, $fields);

        return $this->bookData($this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId));
    }

    /** @return array<string, mixed> */
    public function archiveProject(int $userId, int $projectId): array
    {
        $post = $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);
        update_post_meta($projectId, '_verbum_status', 'archived');

        foreach ($this->queryUserPosts(LibraryPostTypes::BOOK, $userId) as $book) {
            if ((int) get_post_meta($book->ID, '_verbum_project_id', true) === $projectId) {
                update_post_meta($book->ID, '_verbum_status', 'archived');
            }
        }

        return $this->projectData($post);
    }

    /** @return array<string, mixed> */
    public function archiveBook(int $userId, int $bookId): array
    {
        $post = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        update_post_meta($bookId, '_verbum_status', 'archived');

        return $this->bookData($post);
    }

    /** @return \WP_Post[] */
    private function queryUserPosts(string $postType, int $userId): array
    {
        $query = new \WP_Query([
            'post_type' => $postType,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        return array_values(array_filter($query->posts, static fn ($post): bool => $post instanceof \WP_Post));
    }

    private function ownedPost(int $postId, string $postType, int $userId): \WP_Post
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== $postType || (int) $post->post_author !== $userId) {
            throw new NotFoundError('Registro não encontrado.');
        }

        return $post;
    }

    /** @param array<string, mixed> $fields */
    private function saveBookMeta(int $bookId, array $fields): void
    {
        foreach (self::BOOK_META_FIELDS as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            $value = $fields[$field];
            if (is_array($value)) {
                $value = array_values(array_filter(array_map('sanitize_text_field', $value)));
            } else {
                $value = sanitize_textarea_field((string) $value);
            }

            update_post_meta($bookId, '_verbum_' . $field, $value);
        }
    }

    /** @return array<string, mixed> */
    private function projectData(\WP_Post $post): array
    {
        return [
            'id' => (string) $post->ID,
            'name' => get_the_title($post),
            'description' => (string) $post->post_content,
            'status' => (string) (get_post_meta($post->ID, '_verbum_status', true) ?: 'active'),
            'createdAt' => mysql_to_rfc3339($post->post_date_gmt ?: $post->post_date),
            'updatedAt' => mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified),
        ];
    }

    /** @return array<string, mixed> */
    private function bookData(\WP_Post $post): array
    {
        $data = [
            'id' => (string) $post->ID,
            'projectId' => (string) get_post_meta($post->ID, '_verbum_project_id', true),
            'title' => get_the_title($post),
            'status' => (string) (get_post_meta($post->ID, '_verbum_status', true) ?: 'active'),
            'stage' => (string) (get_post_meta($post->ID, '_verbum_stage', true) ?: 'identification'),
            'createdAt' => mysql_to_rfc3339($post->post_date_gmt ?: $post->post_date),
            'updatedAt' => mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified),
        ];

        foreach (self::BOOK_META_FIELDS as $field) {
            $data[$this->camelCase($field)] = get_post_meta($post->ID, '_verbum_' . $field, true);
        }

        return $data;
    }

    private function camelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
