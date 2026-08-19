<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkGeneralReviewRepository
{
    private const SUBSTEPS = ['structure','argument','doctrine','unity','closing'];
    private const MANUAL_FLAGS = [
        'objective_checked' => 'Objetivo geral conferido',
        'central_question_answered' => 'Pergunta Central respondida',
        'thesis_developed' => 'Tese Principal desenvolvida',
        'structure_reviewed' => 'Estrutura geral revisada',
        'continuity_reviewed' => 'Continuidade entre capítulos revisada',
        'repetitions_reviewed' => 'Repetições analisadas',
        'gaps_reviewed' => 'Lacunas analisadas',
        'language_reviewed' => 'Linguagem global revisada',
        'references_checked' => 'Referências globais conferidas',
        'front_back_matter_reviewed' => 'Introdução e conclusão gerais revisadas',
    ];

    private const ISSUE_TYPES = [
        'coherence' => 'Coerência',
        'repetition' => 'Repetição',
        'gap' => 'Lacuna',
        'continuity' => 'Continuidade',
        'structure' => 'Estrutura',
        'language' => 'Linguagem',
        'reference' => 'Referência',
        'introduction' => 'Introdução',
        'conclusion' => 'Conclusão',
        'editorial' => 'Editorial',
        'other' => 'Outro',
    ];

    private const PRIORITIES = [
        'low' => 'Baixa',
        'medium' => 'Média',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId): array
    {
        $book = $this->ownedBook($userId, $bookId);
        $chapters = $this->chaptersForBook($userId, $bookId);
        $startedAt = (string) get_post_meta($bookId, '_verbum_general_review_started_at', true);
        $chapterData = array_map(fn (\WP_Post $chapter): array => $this->chapterSummary($chapter, $startedAt), $chapters);
        $totalWords = array_sum(array_map(static fn (array $chapter): int => (int) $chapter['wordCount'], $chapterData));
        $completedChapters = count(array_filter($chapterData, static fn (array $chapter): bool => (bool) $chapter['completed']));
        $allChaptersCompleted = count($chapterData) > 0 && $completedChapters === count($chapterData);

        $flagsRaw = get_post_meta($bookId, '_verbum_general_review_flags', true);
        $flags = $this->normalizeFlags(is_array($flagsRaw) ? $flagsRaw : []);
        $evaluationsRaw = get_post_meta($bookId, '_verbum_general_review_evaluations', true);
        $evaluations = $this->normalizeEvaluations(is_array($evaluationsRaw) ? $evaluationsRaw : []);
        $transitionsRaw = get_post_meta($bookId, '_verbum_general_review_transitions', true);
        $transitions = $this->normalizeTransitions(is_array($transitionsRaw) ? $transitionsRaw : [], $chapterData);
        $termsRaw = get_post_meta($bookId, '_verbum_general_review_terms', true);
        $terms = $this->normalizeTerms(is_array($termsRaw) ? $termsRaw : []);
        $frontMatterRaw = get_post_meta($bookId, '_verbum_general_review_front_matter', true);
        $frontMatter = $this->normalizeFrontMatter(is_array($frontMatterRaw) ? $frontMatterRaw : []);
        $issuesRaw = get_post_meta($bookId, '_verbum_general_review_issues', true);
        $issues = $this->normalizeIssues(is_array($issuesRaw) ? $issuesRaw : [], $chapterData);
        $pendingIssues = array_values(array_filter($issues, static fn (array $issue): bool => $issue['status'] === 'pending'));
        $pendingCritical = count(array_filter($pendingIssues, static fn (array $issue): bool => $issue['priority'] === 'critical'));

        $completedStages = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $completed = in_array('general_review', $completedStages, true);

        $checklist = [[
            'key' => 'chapters_completed',
            'label' => 'Todos os capítulos concluídos',
            'completed' => $allChaptersCompleted,
            'automatic' => true,
        ]];
        foreach (self::MANUAL_FLAGS as $key => $label) {
            $checklist[] = ['key' => $key, 'label' => $label, 'completed' => (bool) ($flags[$key] ?? false), 'automatic' => false];
        }
        $checklist[] = ['key' => 'completed', 'label' => 'Revisão Geral concluída', 'completed' => $completed, 'automatic' => true];
        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $flagsReady = count(array_filter(array_keys(self::MANUAL_FLAGS), static fn (string $key): bool => (bool) ($flags[$key] ?? false))) === count(self::MANUAL_FLAGS);
        $finalConfirmation = (bool) get_post_meta($bookId, '_verbum_general_review_final_confirmation', true);
        $ready = $allChaptersCompleted && $flagsReady && $pendingCritical === 0 && $finalConfirmation;
        $substepsRaw=get_post_meta($bookId,'_verbum_general_review_substeps',true);$substeps=is_array($substepsRaw)?array_values(array_intersect(self::SUBSTEPS,$substepsRaw)):[];
        $references=$this->references($userId,$bookId);$structure=$this->structureSummary($bookId,$chapterData);$elements=$this->closingElements($bookId);
        $substepProgress=[];foreach(self::SUBSTEPS as $key)$substepProgress[$key]=in_array($key,$substeps,true)?100:$this->substepProgress($key,$flags,$issues,$transitions,$evaluations,$terms,$elements,$finalConfirmation);

        return [
            'bookId' => (string) $bookId,
            'title' => get_the_title($book),
            'summary' => [
                'chapters' => count($chapterData),
                'completedChapters' => $completedChapters,
                'words' => $totalWords,
                'pendingIssues' => count($pendingIssues),
                'pendingCriticalIssues' => $pendingCritical,
                'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            ],
            'chapters' => $chapterData,
            'outline' => $this->outline($bookId, $chapterData),
            'direction' => $this->direction($bookId),
            'evaluations' => $evaluations,
            'transitions' => $transitions,
            'terms' => $terms,
            'frontMatter' => $frontMatter,
            'issues' => $issues,
            'issueTypes' => $this->options(self::ISSUE_TYPES),
            'priorities' => $this->options(self::PRIORITIES),
            'flags' => $flags,
            'checklist' => $checklist,
            'completedCount' => $completedCount,
            'total' => count($checklist),
            'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            'finalConfirmation' => $finalConfirmation,
            'ready' => $ready,
            'completed' => $completed,
            'startedAt' => $startedAt,
            'completedAt' => (string) get_post_meta($bookId, '_verbum_general_review_completed_at', true),
            'lastSavedAt' => (string) get_post_meta($bookId, '_verbum_general_review_last_saved_at', true),
            'alteredAfterCompletion' => (bool) get_post_meta($bookId, '_verbum_general_review_altered_after_completion', true),
            'snapshots' => $this->snapshotList($bookId),
            'substeps'=>$substeps,'activeSubstep'=>$this->activeSubstep($substeps),'substepProgress'=>$substepProgress,
            'structureSummary'=>$structure,'references'=>$references,'referenceSummary'=>$this->referenceSummary($references),'closingElements'=>$elements,
            'validationVersions'=>$this->validationVersionList($bookId),
        ];
    }

    /** @return array<string,mixed> */
    public function completeSubstep(int $userId,int $bookId,string $substep):array
    {
        $this->assertAvailable($userId,$bookId);if(!in_array($substep,self::SUBSTEPS,true))throw new ValidationError('Subetapa da Revisão Geral inválida.');$data=$this->data($userId,$bookId);$position=array_search($substep,self::SUBSTEPS,true);$completed=(array)$data['substeps'];if($position>0&&!in_array(self::SUBSTEPS[$position-1],$completed,true))throw new ValidationError('Conclua a subetapa anterior antes de avançar.');if((int)$data['substepProgress'][$substep]<100)throw new ValidationError('Conclua os critérios essenciais desta subetapa antes de avançar.');if(!in_array($substep,$completed,true))$completed[]=$substep;update_post_meta($bookId,'_verbum_general_review_substeps',array_values(array_unique($completed)));update_post_meta($bookId,'_verbum_general_review_'.$substep.'_completed_at',gmdate('c'));$this->touchBook($bookId);return$this->data($userId,$bookId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $userId, int $bookId, array $fields): array
    {
        $this->assertAvailable($userId, $bookId);
        $wasCompleted = $this->isCompleted($bookId);
        if ((string) get_post_meta($bookId, '_verbum_general_review_started_at', true) === '') {
            update_post_meta($bookId, '_verbum_general_review_started_at', gmdate('c'));
        }
        if (array_key_exists('flags', $fields)) {
            update_post_meta($bookId, '_verbum_general_review_flags', $this->normalizeFlags(is_array($fields['flags']) ? $fields['flags'] : []));
        }
        if (array_key_exists('evaluations', $fields)) {
            update_post_meta($bookId, '_verbum_general_review_evaluations', $this->normalizeEvaluations(is_array($fields['evaluations']) ? $fields['evaluations'] : []));
        }
        if (array_key_exists('transitions', $fields)) {
            $chapters = array_map(fn (\WP_Post $chapter): array => $this->chapterSummary($chapter, (string) get_post_meta($bookId, '_verbum_general_review_started_at', true)), $this->chaptersForBook($userId, $bookId));
            update_post_meta($bookId, '_verbum_general_review_transitions', $this->normalizeTransitions(is_array($fields['transitions']) ? $fields['transitions'] : [], $chapters));
        }
        if (array_key_exists('terms', $fields)) {
            update_post_meta($bookId, '_verbum_general_review_terms', $this->normalizeTerms(is_array($fields['terms']) ? $fields['terms'] : [], true));
        }
        if (array_key_exists('front_matter', $fields)) {
            update_post_meta($bookId, '_verbum_general_review_front_matter', $this->normalizeFrontMatter(is_array($fields['front_matter']) ? $fields['front_matter'] : []));
        }
        if (array_key_exists('final_confirmation', $fields)) {
            update_post_meta($bookId, '_verbum_general_review_final_confirmation', (bool) $fields['final_confirmation'] ? 1 : 0);
        }
        update_post_meta($bookId, '_verbum_general_review_last_saved_at', gmdate('c'));
        if ($wasCompleted) update_post_meta($bookId, '_verbum_general_review_altered_after_completion', 1);
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function createIssue(int $userId, int $bookId, array $fields): array
    {
        $this->assertAvailable($userId, $bookId);
        $issuesRaw = get_post_meta($bookId, '_verbum_general_review_issues', true);
        $chapters = array_map(fn (\WP_Post $chapter): array => $this->chapterSummary($chapter, ''), $this->chaptersForBook($userId, $bookId));
        $issues = $this->normalizeIssues(is_array($issuesRaw) ? $issuesRaw : [], $chapters);
        $description = trim(sanitize_textarea_field((string) ($fields['description'] ?? '')));
        if ($description === '') throw new ValidationError('Descreva a pendência da Revisão Geral.');
        $type = sanitize_key((string) ($fields['type'] ?? 'other'));
        if (! isset(self::ISSUE_TYPES[$type])) $type = 'other';
        $priority = sanitize_key((string) ($fields['priority'] ?? 'medium'));
        if (! isset(self::PRIORITIES[$priority])) $priority = 'medium';
        $chapterId = $this->allowedChapterId($fields['chapter_id'] ?? '', $chapters);
        $issues[] = [
            'id' => 'general-issue-' . substr(md5($description . '|' . microtime(true)), 0, 12),
            'type' => $type,
            'typeLabel' => self::ISSUE_TYPES[$type],
            'description' => $description,
            'chapterId' => $chapterId,
            'priority' => $priority,
            'priorityLabel' => self::PRIORITIES[$priority],
            'status' => 'pending',
            'createdAt' => gmdate('c'),
            'resolvedAt' => '',
        ];
        update_post_meta($bookId, '_verbum_general_review_issues', $issues);
        $this->markAlteredIfCompleted($bookId);
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function updateIssue(int $userId, int $bookId, string $issueId, array $fields): array
    {
        $this->assertAvailable($userId, $bookId);
        $chapters = array_map(fn (\WP_Post $chapter): array => $this->chapterSummary($chapter, ''), $this->chaptersForBook($userId, $bookId));
        $raw = get_post_meta($bookId, '_verbum_general_review_issues', true);
        $issues = $this->normalizeIssues(is_array($raw) ? $raw : [], $chapters);
        $found = false;
        foreach ($issues as &$issue) {
            if ((string) $issue['id'] !== $issueId) continue;
            $found = true;
            if (array_key_exists('description', $fields)) {
                $description = trim(sanitize_textarea_field((string) $fields['description']));
                if ($description !== '') $issue['description'] = $description;
            }
            if (array_key_exists('type', $fields)) {
                $type = sanitize_key((string) $fields['type']);
                if (isset(self::ISSUE_TYPES[$type])) { $issue['type'] = $type; $issue['typeLabel'] = self::ISSUE_TYPES[$type]; }
            }
            if (array_key_exists('priority', $fields)) {
                $priority = sanitize_key((string) $fields['priority']);
                if (isset(self::PRIORITIES[$priority])) { $issue['priority'] = $priority; $issue['priorityLabel'] = self::PRIORITIES[$priority]; }
            }
            if (array_key_exists('chapter_id', $fields)) $issue['chapterId'] = $this->allowedChapterId($fields['chapter_id'], $chapters);
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
        if (! $found) throw new NotFoundError('Pendência da Revisão Geral não encontrada.');
        update_post_meta($bookId, '_verbum_general_review_issues', $issues);
        $this->markAlteredIfCompleted($bookId);
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function deleteIssue(int $userId, int $bookId, string $issueId): array
    {
        $this->assertAvailable($userId, $bookId);
        $chapters = array_map(fn (\WP_Post $chapter): array => $this->chapterSummary($chapter, ''), $this->chaptersForBook($userId, $bookId));
        $raw = get_post_meta($bookId, '_verbum_general_review_issues', true);
        $issues = $this->normalizeIssues(is_array($raw) ? $raw : [], $chapters);
        $filtered = array_values(array_filter($issues, static fn (array $issue): bool => (string) $issue['id'] !== $issueId));
        if (count($filtered) === count($issues)) throw new NotFoundError('Pendência da Revisão Geral não encontrada.');
        update_post_meta($bookId, '_verbum_general_review_issues', $filtered);
        $this->markAlteredIfCompleted($bookId);
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function reading(int $userId, int $bookId): array
    {
        $this->ownedBook($userId, $bookId);
        $chapters = $this->chaptersForBook($userId, $bookId);
        $chapterData = [];
        foreach ($chapters as $chapter) {
            $chapterData[] = [
                'id' => (string) $chapter->ID,
                'number' => max(1, (int) get_post_meta($chapter->ID, '_verbum_chapter_order', true)),
                'title' => get_the_title($chapter),
                'content' => wp_kses_post((string) $chapter->post_content),
                'wordCount' => max(0, (int) get_post_meta($chapter->ID, '_verbum_chapter_word_count', true)),
                'planningItemId' => (string) get_post_meta($chapter->ID, '_verbum_planning_item_id', true),
            ];
        }
        return ['chapters' => $chapterData, 'outline' => $this->outline($bookId, $chapterData)];
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        if (! $data['ready']) {
            throw new ValidationError('Complete o checklist, confirme a versão da obra e resolva todas as pendências críticas antes de concluir a Revisão Geral.');
        }
        $substeps=(array)$data['substeps'];if(array_diff(self::SUBSTEPS,$substeps)!==[])throw new ValidationError('Conclua as cinco subetapas da Revisão Geral antes de enviar a obra para Validação.');
        $this->snapshot($userId, $bookId, 'general_review_pre_completion');
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('general_review', $completed, true)) $completed[] = 'general_review';
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        $this->validationVersion($userId,$bookId,$data);
        update_post_meta($bookId, '_verbum_stage', 'versions');
        update_post_meta($bookId, '_verbum_general_review_completed_at', gmdate('c'));
        update_post_meta($bookId, '_verbum_general_review_altered_after_completion', 0);
        $this->snapshot($userId, $bookId, 'general_review_completion');
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function assistantContext(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        $chapters = $this->chaptersForBook($userId, $bookId);
        $summaries = [];
        foreach ($chapters as $chapter) {
            $sections = get_post_meta($chapter->ID, '_verbum_writing_sections', true);
            $sections = is_array($sections) ? $sections : [];
            $titles = [];
            foreach ($sections as $section) {
                if (! is_array($section)) continue;
                $title = trim(sanitize_text_field((string) ($section['title'] ?? '')));
                if ($title !== '') $titles[] = $title;
            }
            $summaries[] = [
                'number' => max(1, (int) get_post_meta($chapter->ID, '_verbum_chapter_order', true)),
                'title' => get_the_title($chapter),
                'wordCount' => max(0, (int) get_post_meta($chapter->ID, '_verbum_chapter_word_count', true)),
                'introduction' => $this->excerpt((string) get_post_meta($chapter->ID, '_verbum_writing_introduction', true), 900),
                'sections' => array_slice($titles, 0, 20),
                'conclusion' => $this->excerpt((string) get_post_meta($chapter->ID, '_verbum_writing_conclusion', true), 900),
            ];
        }
        return ['direction' => $data['direction'], 'chapters' => $summaries, 'transitions' => $data['transitions'], 'terms' => $data['terms']];
    }

    private function assertAvailable(int $userId, int $bookId): void
    {
        $this->ownedBook($userId, $bookId);
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('development', $completed, true)) throw new ValidationError('Conclua o Desenvolvimento da Obra antes de iniciar a Revisão Geral.');
    }

    private function ownedBook(int $userId, int $bookId): \WP_Post
    {
        $book = get_post($bookId);
        if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) {
            throw new NotFoundError('Obra não encontrada.');
        }
        return $book;
    }

    /** @return \WP_Post[] */
    private function chaptersForBook(int $userId, int $bookId): array
    {
        $query = new \WP_Query([
            'post_type' => LibraryPostTypes::CHAPTER,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_verbum_book_id', 'value' => $bookId, 'compare' => '=', 'type' => 'NUMERIC']],
            'meta_key' => '_verbum_chapter_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);
        return array_values(array_filter($query->posts, static fn ($post): bool => $post instanceof \WP_Post));
    }

    /** @return array<string, mixed> */
    private function chapterSummary(\WP_Post $chapter, string $startedAt): array
    {
        $completedStages = get_post_meta($chapter->ID, '_verbum_chapter_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $completed = in_array('revision', $completedStages, true) || (bool) get_post_meta($chapter->ID, '_verbum_chapter_completed', true);
        $modified = mysql_to_rfc3339($chapter->post_modified_gmt ?: $chapter->post_modified);
        $modifiedTs = strtotime($chapter->post_modified_gmt ?: $chapter->post_modified);
        $startedTs = $startedAt !== '' ? strtotime($startedAt) : false;
        return [
            'id' => (string) $chapter->ID,
            'planningItemId' => (string) get_post_meta($chapter->ID, '_verbum_planning_item_id', true),
            'number' => max(1, (int) get_post_meta($chapter->ID, '_verbum_chapter_order', true)),
            'title' => get_the_title($chapter),
            'wordCount' => max(0, (int) get_post_meta($chapter->ID, '_verbum_chapter_word_count', true)),
            'completed' => $completed,
            'lastEdited' => $modified,
            'alteredAfterRevision' => (bool) get_post_meta($chapter->ID, '_verbum_revision_altered_after_completion', true),
            'changedDuringGeneralReview' => $startedTs !== false && $modifiedTs !== false && $modifiedTs > $startedTs,
        ];
    }

    /** @return array<string, string> */
    private function direction(int $bookId): array
    {
        return [
            'generalObjective' => trim((string) get_post_meta($bookId, '_verbum_work_project_general_objective', true)),
            'purpose' => trim((string) get_post_meta($bookId, '_verbum_work_project_purpose', true)),
            'audience' => trim((string) get_post_meta($bookId, '_verbum_work_project_audience', true)),
            'centralMessage' => trim((string) get_post_meta($bookId, '_verbum_work_project_central_message', true)),
            'transformation' => trim((string) get_post_meta($bookId, '_verbum_work_project_transformation', true)),
            'differentials' => trim((string) get_post_meta($bookId, '_verbum_work_project_differentials', true)),
            'centralQuestion' => trim((string) get_post_meta($bookId, '_verbum_planning_central_question', true)),
            'mainThesis' => trim((string) get_post_meta($bookId, '_verbum_planning_main_thesis', true)),
        ];
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

    /** @param array<string, mixed> $values
     *  @return array<string, string>
     */
    private function normalizeEvaluations(array $values): array
    {
        $clean = [];
        foreach (['objective', 'central_message', 'audience', 'transformation', 'central_question', 'main_thesis'] as $key) {
            $value = sanitize_key((string) ($values[$key] ?? ''));
            $clean[$key] = in_array($value, ['yes', 'partial', 'adjust'], true) ? $value : '';
        }
        return $clean;
    }

    /** @param array<int, mixed> $items
     *  @param array<int, array<string, mixed>> $chapters
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeTransitions(array $items, array $chapters): array
    {
        $saved = [];
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $key = sanitize_key((string) ($item['key'] ?? ''));
            if ($key !== '') $saved[$key] = $item;
        }
        $clean = [];
        for ($index = 0; $index < count($chapters) - 1; $index++) {
            $from = $chapters[$index];
            $to = $chapters[$index + 1];
            $key = 'chapter-' . $from['id'] . '-to-' . $to['id'];
            $item = $saved[$key] ?? [];
            $status = sanitize_key((string) ($item['status'] ?? 'unreviewed'));
            if (! in_array($status, ['unreviewed', 'good', 'needs_work', 'missing'], true)) $status = 'unreviewed';
            $clean[] = [
                'key' => $key,
                'fromChapterId' => (string) $from['id'],
                'fromTitle' => (string) $from['title'],
                'toChapterId' => (string) $to['id'],
                'toTitle' => (string) $to['title'],
                'status' => $status,
                'note' => trim(sanitize_textarea_field((string) ($item['note'] ?? ''))),
            ];
        }
        return $clean;
    }

    /** @param array<int, mixed> $items
     *  @return array<int, array<string, string>>
     */
    private function normalizeTerms(array $items, bool $regenerateIds = false): array
    {
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $term = trim(sanitize_text_field((string) ($item['term'] ?? '')));
            if ($term === '') continue;
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '' || ($regenerateIds && strpos($id, 'new-') === 0)) $id = 'term-' . substr(md5($term . '|' . $index . '|' . microtime(true)), 0, 12);
            $clean[] = ['id' => $id, 'term' => $term, 'note' => trim(sanitize_textarea_field((string) ($item['note'] ?? '')))];
        }
        return $clean;
    }

    /** @param array<string, mixed> $values
     *  @return array<string, string>
     */
    private function normalizeFrontMatter(array $values): array
    {
        $clean = [];
        foreach (['preface', 'presentation', 'authorNote', 'introduction', 'conclusion'] as $key) {
            $snake = $key === 'authorNote' ? 'author_note' : $key;
            $value = array_key_exists($key, $values) ? $values[$key] : ($values[$snake] ?? '');
            $clean[$key] = wp_kses_post((string) $value);
        }
        return $clean;
    }

    /** @param array<int, mixed> $items
     *  @param array<int, array<string, mixed>> $chapters
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeIssues(array $items, array $chapters): array
    {
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) continue;
            $description = trim(sanitize_textarea_field((string) ($item['description'] ?? '')));
            if ($description === '') continue;
            $type = sanitize_key((string) ($item['type'] ?? 'other'));
            if (! isset(self::ISSUE_TYPES[$type])) $type = 'other';
            $priority = sanitize_key((string) ($item['priority'] ?? 'medium'));
            if (! isset(self::PRIORITIES[$priority])) $priority = 'medium';
            $status = sanitize_key((string) ($item['status'] ?? 'pending'));
            if (! in_array($status, ['pending', 'resolved'], true)) $status = 'pending';
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '') $id = 'general-issue-' . substr(md5($description . '|' . $index), 0, 12);
            $clean[] = [
                'id' => $id,
                'type' => $type,
                'typeLabel' => self::ISSUE_TYPES[$type],
                'description' => $description,
                'chapterId' => $this->allowedChapterId($item['chapterId'] ?? $item['chapter_id'] ?? '', $chapters),
                'priority' => $priority,
                'priorityLabel' => self::PRIORITIES[$priority],
                'status' => $status,
                'createdAt' => sanitize_text_field((string) ($item['createdAt'] ?? gmdate('c'))),
                'resolvedAt' => $status === 'resolved' ? sanitize_text_field((string) ($item['resolvedAt'] ?? gmdate('c'))) : '',
            ];
        }
        return $clean;
    }

    /** @param array<int, array<string, mixed>> $chapters */
    private function allowedChapterId($value, array $chapters): string
    {
        $id = (string) (int) $value;
        if ($id === '0') return '';
        $allowed = array_map(static fn (array $chapter): string => (string) $chapter['id'], $chapters);
        return in_array($id, $allowed, true) ? $id : '';
    }

    /** @param array<string, string> $options
     *  @return array<int, array<string, string>>
     */
    private function options(array $options): array
    {
        $result = [];
        foreach ($options as $key => $label) $result[] = ['key' => $key, 'label' => $label];
        return $result;
    }

    /** @param array<int, array<string, mixed>> $chapters
     *  @return array<int, array<string, mixed>>
     */
    private function outline(int $bookId, array $chapters): array
    {
        $items = get_post_meta($bookId, '_verbum_structure_index_items', true);
        if (! is_array($items) || $items === []) $items = get_post_meta($bookId, '_verbum_planning_structure_items', true);
        $items = is_array($items) ? $items : [];
        $byPlanning = [];
        foreach ($chapters as $chapter) {
            if (isset($chapter['planningItemId'])) $byPlanning[(string) $chapter['planningItemId']] = $chapter;
        }
        $result = [];
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $type = sanitize_key((string) ($item['type'] ?? 'chapter'));
            $title = trim(sanitize_text_field((string) ($item['title'] ?? '')));
            $id = (string) ($item['id'] ?? '');
            if ($title === '') continue;
            $realChapterId = (string) ($item['realChapterId'] ?? $item['linkedChapterId'] ?? '');
            if ($type === 'chapter' && $realChapterId !== '') {
                $match = array_values(array_filter($chapters, static fn (array $chapter): bool => (string) ($chapter['id'] ?? '') === $realChapterId));
                if ($match !== []) $result[] = ['type' => 'chapter', 'chapter' => $match[0]];
            } elseif ($type === 'chapter' && isset($byPlanning[$id])) $result[] = ['type' => 'chapter', 'chapter' => $byPlanning[$id]];
            elseif ($type === 'part') $result[] = ['type' => 'part', 'title' => $title];
            elseif ($type === 'subchapter') $result[] = ['type' => 'subchapter', 'title' => $title];
        }
        if ($result === []) foreach ($chapters as $chapter) $result[] = ['type' => 'chapter', 'chapter' => $chapter];
        return $result;
    }

    private function isCompleted(int $bookId): bool
    {
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        return is_array($completed) && in_array('general_review', $completed, true);
    }

    private function markAlteredIfCompleted(int $bookId): void
    {
        if ($this->isCompleted($bookId)) update_post_meta($bookId, '_verbum_general_review_altered_after_completion', 1);
    }

    private function snapshot(int $userId, int $bookId, string $kind): void
    {
        $reading = $this->reading($userId, $bookId);
        $chapters = (array) $reading['chapters'];
        $frontMatterRaw = get_post_meta($bookId, '_verbum_general_review_front_matter', true);
        $frontMatter = $this->normalizeFrontMatter(is_array($frontMatterRaw) ? $frontMatterRaw : []);
        $snapshots = get_post_meta($bookId, '_verbum_general_review_snapshots', true);
        $snapshots = is_array($snapshots) ? $snapshots : [];
        $totalWords = array_sum(array_map(static fn (array $chapter): int => (int) ($chapter['wordCount'] ?? 0), $chapters));
        $hash = md5(wp_json_encode([$frontMatter, $chapters, $reading['outline']]));
        $snapshots[] = [
            'id' => 'work-snapshot-' . substr(md5($hash . '|' . microtime(true)), 0, 12),
            'savedAt' => gmdate('c'),
            'kind' => $kind,
            'wordCount' => $totalWords,
            'chapterCount' => count($chapters),
            'hash' => $hash,
            'frontMatter' => $frontMatter,
            'chapters' => $chapters,
            'outline' => $reading['outline'],
        ];
        if (count($snapshots) > 10) $snapshots = array_slice($snapshots, -10);
        update_post_meta($bookId, '_verbum_general_review_snapshots', $snapshots);
    }

    /** @return array<int,array<string,mixed>> */ private function references(int$userId,int$bookId):array{$chapterIds=array_map(static fn(\WP_Post$p):int=>(int)$p->ID,$this->chaptersForBook($userId,$bookId));if($chapterIds===[])return[];$q=new \WP_Query(['post_type'=>LibraryPostTypes::RESEARCH,'post_status'=>'publish','author'=>$userId,'posts_per_page'=>-1,'meta_query'=>[['key'=>'_verbum_chapter_id','value'=>$chapterIds,'compare'=>'IN','type'=>'NUMERIC']],'no_found_rows'=>true]);$out=[];foreach((array)$q->posts as$p){if(!$p instanceof \WP_Post)continue;$category=sanitize_key((string)get_post_meta($p->ID,'_verbum_research_category',true));$out[]=['id'=>(string)$p->ID,'chapterId'=>(string)get_post_meta($p->ID,'_verbum_chapter_id',true),'source'=>trim((string)get_post_meta($p->ID,'_verbum_research_reference',true))?:trim((string)get_post_meta($p->ID,'_verbum_research_title',true)),'author'=>trim((string)get_post_meta($p->ID,'_verbum_research_author',true)),'category'=>$category,'status'=>(bool)get_post_meta($p->ID,'_verbum_research_used',true)?'confirmed':'review'];}return$out;}
    /** @param array<int,array<string,mixed>>$r @return array<string,int> */ private function referenceSummary(array$r):array{$s=['total'=>count($r),'scripture'=>0,'catechism'=>0,'magisterium'=>0,'saints'=>0,'complementary'=>0];foreach($r as$x){$c=(string)$x['category'];if($c==='scripture')$s['scripture']++;elseif($c==='catechism')$s['catechism']++;elseif(in_array($c,['magisterium','tradition'],true))$s['magisterium']++;elseif($c==='saints')$s['saints']++;else$s['complementary']++;}return$s;}
    /** @param array<int,array<string,mixed>>$chapters @return array<string,mixed> */ private function structureSummary(int$id,array$chapters):array{$items=get_post_meta($id,'_verbum_structure_index_items',true);$items=is_array($items)?$items:[];return['parts'=>count(array_filter($items,static fn($x):bool=>is_array($x)&&($x['type']??'')==='part')),'chapters'=>count($chapters),'completedChapters'=>count(array_filter($chapters,static fn($x):bool=>!empty($x['completed']))),'elements'=>count(array_filter($items,static fn($x):bool=>is_array($x)&&in_array(($x['type']??''),['element_initial','element_final'],true)))];}
    /** @return array<int,array<string,mixed>> */ private function closingElements(int$id):array{$items=get_post_meta($id,'_verbum_structure_elements_items',true);$items=is_array($items)?$items:[];$out=[];foreach($items as$x)if(is_array($x)&&!empty($x['active']))$out[]=['id'=>(string)($x['id']??''),'type'=>(string)($x['type']??''),'title'=>(string)($x['title']??($x['type']??'Elemento')),'group'=>(string)($x['group']??'initial'),'status'=>(string)($x['status']??'pending'),'updatedAt'=>(string)($x['updatedAt']??'')];return$out;}
    /** @param array<string,bool>$f @param array<int,array<string,mixed>>$i @param array<int,array<string,mixed>>$t @param array<string,string>$e @param array<int,array<string,string>>$terms @param array<int,array<string,mixed>>$elements */ private function substepProgress(string$k,array$f,array$i,array$t,array$e,array$terms,array$elements,bool$confirm):int{$checks=[];if($k==='structure')$checks=[!empty($f['structure_reviewed']),!empty($f['continuity_reviewed']),count(array_filter($t,static fn($x):bool=>($x['status']??'unreviewed')!=='unreviewed'))===count($t)];elseif($k==='argument')$checks=[!empty($f['objective_checked']),!empty($f['central_question_answered']),!empty($f['thesis_developed']),!empty($f['repetitions_reviewed']),!empty($f['gaps_reviewed'])];elseif($k==='doctrine')$checks=[!empty($f['references_checked']),count(array_filter($i,static fn($x):bool=>($x['priority']??'')==='critical'&&($x['status']??'')==='pending'))===0];elseif($k==='unity')$checks=[!empty($f['language_reviewed']),$terms!==[]];else$checks=[!empty($f['front_back_matter_reviewed']),$elements!==[],$confirm];return(int)round(count(array_filter($checks))*100/max(1,count($checks)));}
    /** @param array<int,string>$done */ private function activeSubstep(array$done):string{foreach(self::SUBSTEPS as$k)if(!in_array($k,$done,true))return$k;return'closing';}
    /** @param array<string,mixed>$data */ private function validationVersion(int$userId,int$id,array$data):void{$reading=$this->reading($userId,$id);$versions=get_post_meta($id,'_verbum_validation_versions',true);$versions=is_array($versions)?$versions:[];$number=count($versions)+1;$versions[]=['id'=>'validation-v'.$number.'-'.substr(md5((string)microtime(true)),0,8),'number'=>$number,'createdAt'=>gmdate('c'),'userId'=>$userId,'bookId'=>$id,'title'=>get_the_title($id),'subtitle'=>(string)get_post_meta($id,'_verbum_book_subtitle',true),'chapters'=>$reading['chapters'],'outline'=>$reading['outline'],'frontMatter'=>$data['frontMatter'],'references'=>$data['references'],'issues'=>array_values(array_filter($data['issues'],static fn($x):bool=>($x['status']??'')==='pending')),'hash'=>hash('sha256',wp_json_encode([$reading,$data['frontMatter'],$data['references']]))];update_post_meta($id,'_verbum_validation_versions',$versions);update_post_meta($id,'_verbum_active_validation_version',(string)$versions[count($versions)-1]['id']);}
    /** @return array<int,array<string,mixed>> */ private function validationVersionList(int$id):array{$v=get_post_meta($id,'_verbum_validation_versions',true);if(!is_array($v))return[];return array_map(static fn($x):array=>['id'=>(string)($x['id']??''),'number'=>(int)($x['number']??0),'createdAt'=>(string)($x['createdAt']??''),'hash'=>(string)($x['hash']??'')],array_values(array_filter($v,'is_array')));}

    /** @return array<int, array<string, mixed>> */
    private function snapshotList(int $bookId): array
    {
        $snapshots = get_post_meta($bookId, '_verbum_general_review_snapshots', true);
        if (! is_array($snapshots)) return [];
        $result = [];
        foreach (array_reverse($snapshots) as $snapshot) {
            if (! is_array($snapshot)) continue;
            $result[] = [
                'id' => (string) ($snapshot['id'] ?? ''),
                'savedAt' => (string) ($snapshot['savedAt'] ?? ''),
                'kind' => (string) ($snapshot['kind'] ?? ''),
                'wordCount' => (int) ($snapshot['wordCount'] ?? 0),
                'chapterCount' => (int) ($snapshot['chapterCount'] ?? 0),
            ];
        }
        return $result;
    }

    private function excerpt(string $html, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (strlen($text) <= $limit) return $text;
        return substr($text, 0, $limit) . '…';
    }

    private function touchBook(int $bookId): void
    {
        $book = get_post($bookId);
        if ($book instanceof \WP_Post) wp_update_post(['ID' => $bookId, 'post_content' => $book->post_content]);
    }
}
