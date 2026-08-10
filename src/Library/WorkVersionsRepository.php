<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkVersionsRepository
{
    private const TYPES = [
        'milestone' => 'Marco Editorial',
        'manual_backup' => 'Backup Manual',
        'before_change' => 'Antes de Alteração',
        'after_change' => 'Depois de Alteração',
        'review' => 'Versão para Revisão',
        'audit' => 'Versão para Auditoria',
        'layout' => 'Versão para Diagramação',
        'publication' => 'Versão final para Publicação',
        'before_restore' => 'Backup antes da restauração',
        'duplicate' => 'Duplicada de versão histórica',
        'other' => 'Outro',
    ];

    private const MANUAL_FLAGS = [
        'history_checked' => 'Histórico conferido',
        'changes_evaluated' => 'Alterações pendentes avaliadas',
        'strategy_defined' => 'Estratégia de versionamento definida',
        'backup_checked' => 'Backup editorial conferido',
        'current_validated' => 'Versão atual validada',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId): array
    {
        $this->assertAvailable($userId, $bookId);
        $this->ensureInitialVersion($userId, $bookId);
        $versions = $this->versions($bookId);
        $current = $this->currentSnapshot($userId, $bookId);
        $currentHash = $this->hashSnapshot($current);
        $latest = $versions !== [] ? end($versions) : null;
        $latestHash = is_array($latest) ? (string) ($latest['hash'] ?? '') : '';
        $integrityErrors = $this->integrityErrors($versions);
        $baselineId = (string) get_post_meta($bookId, '_verbum_versions_audit_baseline_id', true);
        $baseline = $this->findVersion($versions, $baselineId, false);
        $flagsRaw = get_post_meta($bookId, '_verbum_versions_flags', true);
        $flags = $this->normalizeFlags(is_array($flagsRaw) ? $flagsRaw : []);
        $completedStages = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];
        $completed = in_array('versions', $completedStages, true);
        $initialExists = count(array_filter($versions, static fn (array $version): bool => ($version['origin'] ?? '') === 'general_review')) > 0;
        $protectedExists = count(array_filter($versions, static fn (array $version): bool => (bool) ($version['protected'] ?? false))) > 0;
        $comparisonDone = (string) get_post_meta($bookId, '_verbum_versions_last_comparison_at', true) !== '';
        $auditReady = is_array($baseline) && ($baseline['type'] ?? '') === 'audit' && ($baseline['hash'] ?? '') === $currentHash;

        $checklist = [
            ['key' => 'initial_registered', 'label' => 'Versão inicial registrada', 'completed' => $initialExists, 'automatic' => true],
            ['key' => 'history_checked', 'label' => self::MANUAL_FLAGS['history_checked'], 'completed' => (bool) $flags['history_checked'], 'automatic' => false],
            ['key' => 'important_protected', 'label' => 'Versões importantes protegidas', 'completed' => $protectedExists, 'automatic' => true],
            ['key' => 'changes_evaluated', 'label' => self::MANUAL_FLAGS['changes_evaluated'], 'completed' => (bool) $flags['changes_evaluated'], 'automatic' => false],
            ['key' => 'comparison_done', 'label' => 'Comparação realizada', 'completed' => $comparisonDone, 'automatic' => true],
            ['key' => 'strategy_defined', 'label' => self::MANUAL_FLAGS['strategy_defined'], 'completed' => (bool) $flags['strategy_defined'], 'automatic' => false],
            ['key' => 'backup_checked', 'label' => self::MANUAL_FLAGS['backup_checked'], 'completed' => (bool) $flags['backup_checked'], 'automatic' => false],
            ['key' => 'current_validated', 'label' => self::MANUAL_FLAGS['current_validated'], 'completed' => (bool) $flags['current_validated'], 'automatic' => false],
            ['key' => 'audit_version_created', 'label' => 'Versão para Auditoria criada', 'completed' => $auditReady, 'automatic' => true],
            ['key' => 'completed', 'label' => 'Controle de Versões concluído', 'completed' => $completed, 'automatic' => true],
        ];
        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $manualReady = count(array_filter(array_keys(self::MANUAL_FLAGS), static fn (string $key): bool => (bool) $flags[$key])) === count(self::MANUAL_FLAGS);
        $ready = $initialExists && $integrityErrors === [] && $manualReady && $protectedExists && $comparisonDone && $auditReady;
        $changes = $this->changesFromVersion(is_array($latest) ? $latest : null, $current);

        return [
            'bookId' => (string) $bookId,
            'title' => (string) $current['metadata']['title'],
            'types' => $this->options(self::TYPES),
            'versions' => array_map(fn (array $version): array => $this->versionSummary($version, $baselineId), array_reverse($versions)),
            'currentHash' => $currentHash,
            'latestVersionId' => is_array($latest) ? (string) $latest['id'] : '',
            'latestVersionNumber' => is_array($latest) ? (string) $latest['number'] : '',
            'auditBaselineId' => $baselineId,
            'unversioned' => $changes,
            'integrityErrors' => $integrityErrors,
            'flags' => $flags,
            'checklist' => $checklist,
            'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            'completedCount' => $completedCount,
            'total' => count($checklist),
            'ready' => $ready,
            'completed' => $completed,
            'completedAt' => (string) get_post_meta($bookId, '_verbum_versions_completed_at', true),
            'lastComparisonAt' => (string) get_post_meta($bookId, '_verbum_versions_last_comparison_at', true),
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function saveState(int $userId, int $bookId, array $fields): array
    {
        $this->assertAvailable($userId, $bookId);
        if (array_key_exists('flags', $fields)) {
            update_post_meta($bookId, '_verbum_versions_flags', $this->normalizeFlags(is_array($fields['flags']) ? $fields['flags'] : []));
        }
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function create(int $userId, int $bookId, array $fields): array
    {
        $this->assertAvailable($userId, $bookId);
        $this->ensureInitialVersion($userId, $bookId);
        $versions = $this->versions($bookId);
        $snapshot = $this->currentSnapshot($userId, $bookId);
        $hash = $this->hashSnapshot($snapshot);
        $latest = $versions !== [] ? end($versions) : null;
        $force = (bool) ($fields['force'] ?? false);
        if (! $force && is_array($latest) && (string) ($latest['hash'] ?? '') === $hash) {
            throw new ValidationError('Não foram encontradas alterações desde a última versão. Marque a criação forçada se quiser registrar um novo marco mesmo assim.');
        }

        $type = sanitize_key((string) ($fields['type'] ?? 'milestone'));
        if (! isset(self::TYPES[$type])) $type = 'other';
        $major = (bool) ($fields['major'] ?? false);
        $number = $this->nextNumber($versions, $major);
        $name = trim(sanitize_text_field((string) ($fields['name'] ?? '')));
        if ($name === '') $name = self::TYPES[$type];
        $version = $this->buildVersion(
            $number,
            $name,
            $type,
            trim(sanitize_textarea_field((string) ($fields['notes'] ?? ''))),
            (bool) ($fields['protected'] ?? false),
            'manual',
            $snapshot,
            $userId
        );
        $versions[] = $version;
        $this->storeVersions($bookId, $versions);
        if ($type === 'audit' || (bool) ($fields['audit_baseline'] ?? false)) {
            update_post_meta($bookId, '_verbum_versions_audit_baseline_id', $version['id']);
        }
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function version(int $userId, int $bookId, string $versionId): array
    {
        $this->assertAvailable($userId, $bookId);
        $version = $this->findVersion($this->versions($bookId), $versionId, true);
        return [
            'version' => $this->versionSummary($version, (string) get_post_meta($bookId, '_verbum_versions_audit_baseline_id', true)),
            'snapshot' => $version['snapshot'],
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function updateVersion(int $userId, int $bookId, string $versionId, array $fields): array
    {
        $this->assertAvailable($userId, $bookId);
        $versions = $this->versions($bookId);
        $found = false;
        foreach ($versions as &$version) {
            if ((string) ($version['id'] ?? '') !== $versionId) continue;
            $found = true;
            if (array_key_exists('name', $fields)) {
                $name = trim(sanitize_text_field((string) $fields['name']));
                if ($name !== '') $version['name'] = $name;
            }
            if (array_key_exists('notes', $fields)) $version['notes'] = trim(sanitize_textarea_field((string) $fields['notes']));
            if (array_key_exists('protected', $fields)) $version['protected'] = (bool) $fields['protected'];
            break;
        }
        unset($version);
        if (! $found) throw new NotFoundError('Versão da obra não encontrada.');
        $this->storeVersions($bookId, $versions);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function deleteVersion(int $userId, int $bookId, string $versionId): array
    {
        $this->assertAvailable($userId, $bookId);
        $versions = $this->versions($bookId);
        $version = $this->findVersion($versions, $versionId, true);
        if ((bool) ($version['protected'] ?? false)) throw new ValidationError('Remova a proteção desta versão antes de excluí-la.');
        if ((string) get_post_meta($bookId, '_verbum_versions_audit_baseline_id', true) === $versionId) {
            throw new ValidationError('A versão selecionada para Auditoria não pode ser excluída. Selecione outra versão primeiro.');
        }
        $versions = array_values(array_filter($versions, static fn (array $item): bool => (string) ($item['id'] ?? '') !== $versionId));
        $this->storeVersions($bookId, $versions);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function duplicate(int $userId, int $bookId, string $versionId, string $name = '', string $notes = ''): array
    {
        $this->assertAvailable($userId, $bookId);
        $versions = $this->versions($bookId);
        $source = $this->findVersion($versions, $versionId, true);
        $number = $this->nextNumber($versions, false);
        $version = $this->buildVersion(
            $number,
            $name !== '' ? sanitize_text_field($name) : 'Nova versão a partir de ' . (string) $source['number'],
            'duplicate',
            sanitize_textarea_field($notes),
            false,
            'duplicate:' . $versionId,
            (array) $source['snapshot'],
            $userId
        );
        $versions[] = $version;
        $this->storeVersions($bookId, $versions);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function selectAudit(int $userId, int $bookId, string $versionId): array
    {
        $this->assertAvailable($userId, $bookId);
        $versions = $this->versions($bookId);
        $found = false;
        foreach ($versions as &$version) {
            if ((string) ($version['id'] ?? '') !== $versionId) continue;
            $found = true;
            $version['type'] = 'audit';
            $version['typeLabel'] = self::TYPES['audit'];
            $version['protected'] = true;
            break;
        }
        unset($version);
        if (! $found) throw new NotFoundError('Versão da obra não encontrada.');
        $this->storeVersions($bookId, $versions);
        update_post_meta($bookId, '_verbum_versions_audit_baseline_id', $versionId);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function compare(int $userId, int $bookId, string $fromId, string $toId): array
    {
        $this->assertAvailable($userId, $bookId);
        $versions = $this->versions($bookId);
        $from = $this->findVersion($versions, $fromId, true);
        $to = $this->findVersion($versions, $toId, true);
        $result = $this->compareSnapshots((array) $from['snapshot'], (array) $to['snapshot']);
        update_post_meta($bookId, '_verbum_versions_last_comparison_at', gmdate('c'));
        update_post_meta($bookId, '_verbum_versions_last_comparison', ['from' => $fromId, 'to' => $toId]);
        return [
            'from' => $this->versionSummary($from, (string) get_post_meta($bookId, '_verbum_versions_audit_baseline_id', true)),
            'to' => $this->versionSummary($to, (string) get_post_meta($bookId, '_verbum_versions_audit_baseline_id', true)),
            'comparison' => $result,
        ];
    }

    /** @return array<string, mixed> */
    public function restore(int $userId, int $bookId, string $versionId): array
    {
        $this->assertAvailable($userId, $bookId);
        $versions = $this->versions($bookId);
        $target = $this->findVersion($versions, $versionId, true);
        $current = $this->currentSnapshot($userId, $bookId);
        $backup = $this->buildVersion(
            $this->nextNumber($versions, false),
            'Backup antes da restauração de ' . (string) $target['number'],
            'before_restore',
            'Criado automaticamente antes da restauração da obra.',
            true,
            'automatic_restore_backup',
            $current,
            $userId
        );
        $versions[] = $backup;
        $this->storeVersions($bookId, $versions);
        $this->applySnapshot($userId, $bookId, (array) $target['snapshot']);
        update_post_meta($bookId, '_verbum_versions_audit_baseline_id', '');
        $flagsRaw = get_post_meta($bookId, '_verbum_versions_flags', true);
        $flags = $this->normalizeFlags(is_array($flagsRaw) ? $flagsRaw : []);
        $flags['current_validated'] = false;
        $flags['changes_evaluated'] = false;
        update_post_meta($bookId, '_verbum_versions_flags', $flags);
        $this->invalidateFromVersions($bookId);
        update_post_meta($bookId, '_verbum_versions_last_restore_at', gmdate('c'));
        update_post_meta($bookId, '_verbum_versions_last_restore_source', $versionId);
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        if (! $data['ready']) {
            throw new ValidationError('Conclua o checklist, valide a integridade dos snapshots e selecione uma versão atual para Auditoria antes de finalizar o Controle de Versões.');
        }
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('versions', $completed, true)) $completed[] = 'versions';
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        update_post_meta($bookId, '_verbum_stage', 'audit');
        update_post_meta($bookId, '_verbum_versions_completed_at', gmdate('c'));
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    private function assertAvailable(int $userId, int $bookId): void
    {
        $book = get_post($bookId);
        if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) {
            throw new NotFoundError('Obra não encontrada.');
        }
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('general_review', $completed, true)) throw new ValidationError('Conclua a Revisão Geral antes de iniciar o Controle de Versões.');
    }

    private function ensureInitialVersion(int $userId, int $bookId): void
    {
        $versions = $this->versions($bookId);
        if (count(array_filter($versions, static fn (array $version): bool => ($version['origin'] ?? '') === 'general_review')) > 0) return;
        $snapshot = $this->snapshotFromGeneralReview($userId, $bookId);
        $versions[] = $this->buildVersion('v1.0', 'Final da Revisão Geral', 'milestone', 'Versão inicial criada a partir do marco final da Revisão Geral.', true, 'general_review', $snapshot, $userId);
        $this->storeVersions($bookId, $versions);
    }

    /** @return array<string, mixed> */
    private function snapshotFromGeneralReview(int $userId, int $bookId): array
    {
        $snapshots = get_post_meta($bookId, '_verbum_general_review_snapshots', true);
        $snapshots = is_array($snapshots) ? $snapshots : [];
        $selected = null;
        foreach (array_reverse($snapshots) as $snapshot) {
            if (is_array($snapshot) && ($snapshot['kind'] ?? '') === 'general_review_completion') { $selected = $snapshot; break; }
        }
        if (! is_array($selected)) return $this->currentSnapshot($userId, $bookId);
        $book = get_post($bookId);
        $chapters = is_array($selected['chapters'] ?? null) ? $selected['chapters'] : [];
        return [
            'metadata' => [
                'title' => $book instanceof \WP_Post ? get_the_title($book) : '',
                'subtitle' => trim((string) get_post_meta($bookId, '_verbum_subtitle', true)),
            ],
            'structure' => $this->planningStructure($bookId),
            'frontMatter' => is_array($selected['frontMatter'] ?? null) ? $selected['frontMatter'] : $this->frontMatter($bookId),
            'chapters' => $this->normalizeSnapshotChapters($chapters),
        ];
    }

    /** @return array<string, mixed> */
    private function currentSnapshot(int $userId, int $bookId): array
    {
        $book = get_post($bookId);
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
        $chapters = [];
        foreach ($query->posts as $chapter) {
            if (! $chapter instanceof \WP_Post) continue;
            $chapters[] = [
                'id' => (string) $chapter->ID,
                'planningItemId' => (string) get_post_meta($chapter->ID, '_verbum_planning_item_id', true),
                'number' => max(1, (int) get_post_meta($chapter->ID, '_verbum_chapter_order', true)),
                'title' => get_the_title($chapter),
                'content' => wp_kses_post((string) $chapter->post_content),
                'wordCount' => max(0, (int) get_post_meta($chapter->ID, '_verbum_chapter_word_count', true)),
            ];
        }
        return [
            'metadata' => ['title' => $book instanceof \WP_Post ? get_the_title($book) : '', 'subtitle' => trim((string) get_post_meta($bookId, '_verbum_subtitle', true))],
            'structure' => $this->planningStructure($bookId),
            'frontMatter' => $this->frontMatter($bookId),
            'chapters' => $chapters,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function planningStructure(int $bookId): array
    {
        $items = get_post_meta($bookId, '_verbum_planning_structure_items', true);
        return is_array($items) ? $items : [];
    }

    /** @return array<string, string> */
    private function frontMatter(int $bookId): array
    {
        $raw = get_post_meta($bookId, '_verbum_general_review_front_matter', true);
        $raw = is_array($raw) ? $raw : [];
        $result = [];
        foreach (['preface', 'presentation', 'authorNote', 'introduction', 'conclusion'] as $key) $result[$key] = wp_kses_post((string) ($raw[$key] ?? ''));
        return $result;
    }

    /** @param array<int, mixed> $chapters
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeSnapshotChapters(array $chapters): array
    {
        $clean = [];
        foreach ($chapters as $index => $chapter) {
            if (! is_array($chapter)) continue;
            $clean[] = [
                'id' => (string) (int) ($chapter['id'] ?? 0),
                'planningItemId' => sanitize_key((string) ($chapter['planningItemId'] ?? '')),
                'number' => max(1, (int) ($chapter['number'] ?? ($index + 1))),
                'title' => sanitize_text_field((string) ($chapter['title'] ?? ('Capítulo ' . ($index + 1)))),
                'content' => wp_kses_post((string) ($chapter['content'] ?? '')),
                'wordCount' => max(0, (int) ($chapter['wordCount'] ?? 0)),
            ];
        }
        return $clean;
    }

    /** @param array<string, mixed> $snapshot
     *  @return array<string, mixed>
     */
    private function buildVersion(string $number, string $name, string $type, string $notes, bool $protected, string $origin, array $snapshot, int $userId): array
    {
        $snapshot = [
            'metadata' => is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [],
            'structure' => is_array($snapshot['structure'] ?? null) ? $snapshot['structure'] : [],
            'frontMatter' => is_array($snapshot['frontMatter'] ?? null) ? $snapshot['frontMatter'] : [],
            'chapters' => $this->normalizeSnapshotChapters(is_array($snapshot['chapters'] ?? null) ? $snapshot['chapters'] : []),
        ];
        $hash = $this->hashSnapshot($snapshot);
        $wordCount = array_sum(array_map(static fn (array $chapter): int => (int) ($chapter['wordCount'] ?? 0), $snapshot['chapters']));
        return [
            'id' => 'work-version-' . substr(md5($hash . '|' . microtime(true) . '|' . $number), 0, 16),
            'number' => $number,
            'name' => $name,
            'type' => $type,
            'typeLabel' => self::TYPES[$type] ?? self::TYPES['other'],
            'notes' => $notes,
            'protected' => $protected,
            'origin' => $origin,
            'createdAt' => gmdate('c'),
            'createdBy' => (string) $userId,
            'chapterCount' => count($snapshot['chapters']),
            'wordCount' => $wordCount,
            'characterCount' => strlen(wp_strip_all_tags(wp_json_encode($snapshot['chapters']))),
            'hash' => $hash,
            'snapshot' => $snapshot,
        ];
    }

    /** @param array<int, array<string, mixed>> $versions */
    private function nextNumber(array $versions, bool $major): string
    {
        if ($versions === []) return 'v1.0';
        $last = end($versions);
        $number = (string) ($last['number'] ?? 'v1.0');
        if (! preg_match('/^v(\d+)\.(\d+)$/', $number, $matches)) return 'v1.1';
        $majorNumber = (int) $matches[1];
        $minorNumber = (int) $matches[2];
        return $major ? 'v' . ($majorNumber + 1) . '.0' : 'v' . $majorNumber . '.' . ($minorNumber + 1);
    }

    /** @return array<int, array<string, mixed>> */
    private function versions(int $bookId): array
    {
        $versions = get_post_meta($bookId, '_verbum_work_versions', true);
        return is_array($versions) ? array_values(array_filter($versions, 'is_array')) : [];
    }

    /** @param array<int, array<string, mixed>> $versions */
    private function storeVersions(int $bookId, array $versions): void
    {
        update_post_meta($bookId, '_verbum_work_versions', array_values($versions));
    }

    /** @param array<int, array<string, mixed>> $versions
     *  @return array<string, mixed>|null
     */
    private function findVersion(array $versions, string $versionId, bool $required)
    {
        foreach ($versions as $version) if ((string) ($version['id'] ?? '') === $versionId) return $version;
        if ($required) throw new NotFoundError('Versão da obra não encontrada.');
        return null;
    }

    /** @param array<string, mixed> $version
     *  @return array<string, mixed>
     */
    private function versionSummary(array $version, string $baselineId): array
    {
        return [
            'id' => (string) ($version['id'] ?? ''),
            'number' => (string) ($version['number'] ?? ''),
            'name' => (string) ($version['name'] ?? ''),
            'type' => (string) ($version['type'] ?? 'other'),
            'typeLabel' => (string) ($version['typeLabel'] ?? self::TYPES['other']),
            'notes' => (string) ($version['notes'] ?? ''),
            'protected' => (bool) ($version['protected'] ?? false),
            'origin' => (string) ($version['origin'] ?? ''),
            'createdAt' => (string) ($version['createdAt'] ?? ''),
            'createdBy' => (string) ($version['createdBy'] ?? ''),
            'chapterCount' => (int) ($version['chapterCount'] ?? 0),
            'wordCount' => (int) ($version['wordCount'] ?? 0),
            'characterCount' => (int) ($version['characterCount'] ?? 0),
            'hash' => (string) ($version['hash'] ?? ''),
            'auditBaseline' => $baselineId !== '' && $baselineId === (string) ($version['id'] ?? ''),
        ];
    }

    /** @param array<int, array<string, mixed>> $versions
     *  @return string[]
     */
    private function integrityErrors(array $versions): array
    {
        $errors = [];
        foreach ($versions as $version) {
            $snapshot = is_array($version['snapshot'] ?? null) ? $version['snapshot'] : [];
            if ($snapshot === [] || (string) ($version['hash'] ?? '') !== $this->hashSnapshot($snapshot)) $errors[] = (string) ($version['number'] ?? 'Versão sem número');
        }
        return $errors;
    }

    /** @param array<string, mixed> $snapshot */
    private function hashSnapshot(array $snapshot): string
    {
        return hash('sha256', (string) wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed>|null $version
     *  @param array<string, mixed> $current
     *  @return array<string, mixed>
     */
    private function changesFromVersion($version, array $current): array
    {
        if (! is_array($version)) return ['hasChanges' => true, 'wordDelta' => 0, 'changedChapters' => count($current['chapters'] ?? []), 'lastVersionHash' => ''];
        $snapshot = is_array($version['snapshot'] ?? null) ? $version['snapshot'] : [];
        $comparison = $this->compareSnapshots($snapshot, $current);
        return [
            'hasChanges' => (string) ($version['hash'] ?? '') !== $this->hashSnapshot($current),
            'wordDelta' => (int) $comparison['summary']['wordDelta'],
            'changedChapters' => (int) $comparison['summary']['changedChapters'] + (int) $comparison['summary']['addedChapters'] + (int) $comparison['summary']['removedChapters'],
            'lastVersionHash' => (string) ($version['hash'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $from
     *  @param array<string, mixed> $to
     *  @return array<string, mixed>
     */
    private function compareSnapshots(array $from, array $to): array
    {
        $fromChapters = [];
        foreach ((array) ($from['chapters'] ?? []) as $chapter) if (is_array($chapter)) $fromChapters[(string) ($chapter['id'] ?? '')] = $chapter;
        $toChapters = [];
        foreach ((array) ($to['chapters'] ?? []) as $chapter) if (is_array($chapter)) $toChapters[(string) ($chapter['id'] ?? '')] = $chapter;
        $chapterIds = array_values(array_unique(array_merge(array_keys($fromChapters), array_keys($toChapters))));
        $items = [];
        $changed = $added = $removed = $renamed = $moved = 0;
        foreach ($chapterIds as $id) {
            $before = $fromChapters[$id] ?? null;
            $after = $toChapters[$id] ?? null;
            if (! is_array($before)) { $added++; $items[] = ['id' => $id, 'status' => 'added', 'title' => (string) ($after['title'] ?? ''), 'paragraphs' => ['added' => $this->paragraphs((string) ($after['content'] ?? '')), 'removed' => []]]; continue; }
            if (! is_array($after)) { $removed++; $items[] = ['id' => $id, 'status' => 'removed', 'title' => (string) ($before['title'] ?? ''), 'paragraphs' => ['added' => [], 'removed' => $this->paragraphs((string) ($before['content'] ?? ''))]]; continue; }
            $titleChanged = (string) ($before['title'] ?? '') !== (string) ($after['title'] ?? '');
            $orderChanged = (int) ($before['number'] ?? 0) !== (int) ($after['number'] ?? 0);
            $contentChanged = md5((string) ($before['content'] ?? '')) !== md5((string) ($after['content'] ?? ''));
            if ($titleChanged) $renamed++;
            if ($orderChanged) $moved++;
            if ($titleChanged || $orderChanged || $contentChanged) {
                $changed++;
                $beforeParagraphs = $this->paragraphs((string) ($before['content'] ?? ''));
                $afterParagraphs = $this->paragraphs((string) ($after['content'] ?? ''));
                $items[] = [
                    'id' => $id,
                    'status' => 'changed',
                    'title' => (string) ($after['title'] ?? ''),
                    'previousTitle' => (string) ($before['title'] ?? ''),
                    'renamed' => $titleChanged,
                    'moved' => $orderChanged,
                    'paragraphs' => [
                        'added' => array_values(array_slice(array_diff($afterParagraphs, $beforeParagraphs), 0, 60)),
                        'removed' => array_values(array_slice(array_diff($beforeParagraphs, $afterParagraphs), 0, 60)),
                    ],
                ];
            } else {
                $items[] = ['id' => $id, 'status' => 'unchanged', 'title' => (string) ($after['title'] ?? ''), 'paragraphs' => ['added' => [], 'removed' => []]];
            }
        }
        $fromWords = array_sum(array_map(static fn (array $chapter): int => (int) ($chapter['wordCount'] ?? 0), array_values($fromChapters)));
        $toWords = array_sum(array_map(static fn (array $chapter): int => (int) ($chapter['wordCount'] ?? 0), array_values($toChapters)));
        $structureChanged = md5((string) wp_json_encode($from['structure'] ?? [])) !== md5((string) wp_json_encode($to['structure'] ?? []));
        $frontMatterChanged = md5((string) wp_json_encode($from['frontMatter'] ?? [])) !== md5((string) wp_json_encode($to['frontMatter'] ?? []));
        return [
            'summary' => [
                'changedChapters' => $changed,
                'addedChapters' => $added,
                'removedChapters' => $removed,
                'renamedChapters' => $renamed,
                'movedChapters' => $moved,
                'wordDelta' => $toWords - $fromWords,
                'structureChanged' => $structureChanged,
                'frontMatterChanged' => $frontMatterChanged,
            ],
            'chapters' => $items,
        ];
    }

    /** @return string[] */
    private function paragraphs(string $html): array
    {
        $text = str_replace(['</p>', '</div>', '</li>', '<br>', '<br/>', '<br />'], "\n", $html);
        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = preg_split('/\n+/u', $text);
        if (! is_array($parts)) return [];
        return array_values(array_filter(array_map(static fn (string $part): string => trim(preg_replace('/\s+/u', ' ', $part)), $parts), static fn (string $part): bool => $part !== ''));
    }

    /** @param array<string, mixed> $snapshot */
    private function applySnapshot(int $userId, int $bookId, array $snapshot): void
    {
        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $title = trim(sanitize_text_field((string) ($metadata['title'] ?? '')));
        $book = get_post($bookId);
        if ($book instanceof \WP_Post && $title !== '') wp_update_post(['ID' => $bookId, 'post_title' => $title, 'post_content' => $book->post_content]);
        if (array_key_exists('subtitle', $metadata)) update_post_meta($bookId, '_verbum_subtitle', sanitize_text_field((string) $metadata['subtitle']));
        update_post_meta($bookId, '_verbum_planning_structure_items', is_array($snapshot['structure'] ?? null) ? $snapshot['structure'] : []);
        update_post_meta($bookId, '_verbum_general_review_front_matter', is_array($snapshot['frontMatter'] ?? null) ? $snapshot['frontMatter'] : []);

        $snapshotChapters = $this->normalizeSnapshotChapters(is_array($snapshot['chapters'] ?? null) ? $snapshot['chapters'] : []);
        $targetIds = [];
        foreach ($snapshotChapters as $index => $chapter) {
            $chapterId = (int) $chapter['id'];
            $post = $chapterId > 0 ? get_post($chapterId) : null;
            if (! $post instanceof \WP_Post || $post->post_type !== LibraryPostTypes::CHAPTER || (int) $post->post_author !== $userId) {
                $created = wp_insert_post([
                    'post_type' => LibraryPostTypes::CHAPTER,
                    'post_status' => 'publish',
                    'post_title' => (string) $chapter['title'],
                    'post_content' => (string) $chapter['content'],
                    'post_author' => $userId,
                ], true);
                if (is_wp_error($created)) throw new ValidationError('Não foi possível restaurar um dos capítulos da versão histórica.');
                $chapterId = (int) $created;
            } else {
                wp_update_post(['ID' => $chapterId, 'post_status' => 'publish', 'post_title' => (string) $chapter['title'], 'post_content' => (string) $chapter['content']]);
            }
            $targetIds[] = $chapterId;
            update_post_meta($chapterId, '_verbum_book_id', $bookId);
            update_post_meta($chapterId, '_verbum_planning_item_id', (string) $chapter['planningItemId']);
            update_post_meta($chapterId, '_verbum_chapter_order', max(1, (int) $chapter['number']));
            update_post_meta($chapterId, '_verbum_chapter_word_count', max(0, (int) $chapter['wordCount']));
        }
        $query = new \WP_Query([
            'post_type' => LibraryPostTypes::CHAPTER,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_verbum_book_id', 'value' => $bookId, 'compare' => '=', 'type' => 'NUMERIC']],
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        foreach ((array) $query->posts as $id) {
            $id = (int) $id;
            if (! in_array($id, $targetIds, true)) wp_trash_post($id);
        }
    }

    private function invalidateFromVersions(int $bookId): void
    {
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        $remove = ['versions', 'audit', 'editorial_desk', 'layout', 'legal', 'publication'];
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_diff($completed, $remove)));
        update_post_meta($bookId, '_verbum_stage', 'versions');
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

    /** @param array<string, string> $options
     *  @return array<int, array<string, string>>
     */
    private function options(array $options): array
    {
        $result = [];
        foreach ($options as $key => $label) $result[] = ['key' => $key, 'label' => $label];
        return $result;
    }

    private function touchBook(int $bookId): void
    {
        $book = get_post($bookId);
        if ($book instanceof \WP_Post) wp_update_post(['ID' => $bookId, 'post_content' => $book->post_content]);
    }
}
