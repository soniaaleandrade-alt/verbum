<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkAuditRepository
{
    private const CATEGORIES = [
        'integrity' => 'Integridade da Obra',
        'structure' => 'Estrutura Editorial',
        'content' => 'Conteúdo',
        'sources' => 'Fontes e Referências',
        'consistency' => 'Consistência',
        'elements' => 'Elementos Pré e Pós-textuais',
        'editorial' => 'Preparação Editorial',
        'doctrine' => 'Conferência Doutrinal',
    ];

    private const SEVERITIES = [
        'info' => 'Informativo',
        'warning' => 'Aviso',
        'pending' => 'Pendência',
        'critical' => 'Crítico',
    ];

    private const STATUSES = [
        'open' => 'Aberto',
        'reviewing' => 'Em análise',
        'resolved' => 'Resolvido',
        'ignored' => 'Ignorado com justificativa',
    ];

    private const MANUAL_FLAGS = [
        'structure_checked' => 'Estrutura editorial conferida',
        'chapters_checked' => 'Capítulos conferidos',
        'markers_checked' => 'Marcadores pendentes analisados',
        'sources_checked' => 'Fontes conferidas',
        'citations_checked' => 'Citações conferidas',
        'terminology_checked' => 'Terminologia analisada',
        'elements_checked' => 'Elementos editoriais conferidos',
        'findings_checked' => 'Pendências resolvidas/justificadas',
        'version_validated' => 'Versão auditada validada',
        'report_checked' => 'Relatório conferido',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId): array
    {
        $this->assertAvailable($userId, $bookId);
        $version = $this->auditVersion($bookId);
        $rounds = $this->rounds($bookId);
        $round = $this->currentRound($rounds, (string) $version['id']);
        if ($round === null) {
            $round = $this->newRound($userId, $bookId, $version, count($rounds) + 1);
            $rounds[] = $round;
            $this->storeRounds($bookId, $rounds);
        }

        $automatic = $this->automaticFindings($round);
        $manual = is_array($round['manualFindings'] ?? null) ? $round['manualFindings'] : [];
        $decisions = is_array($round['decisions'] ?? null) ? $round['decisions'] : [];
        $findings = [];
        foreach (array_merge($automatic, $manual) as $finding) {
            if (! is_array($finding)) continue;
            $id = (string) ($finding['id'] ?? '');
            if ($id !== '' && isset($decisions[$id]) && is_array($decisions[$id])) {
                $decision = $decisions[$id];
                $finding['status'] = $decision['status'] ?? $finding['status'];
                $finding['justification'] = $decision['justification'] ?? '';
                $finding['resolvedAt'] = $decision['resolvedAt'] ?? '';
            }
            $findings[] = $finding;
        }

        $summary = $this->summary($findings);
        $integrityValid = $this->snapshotHash((array) $version['snapshot']) === (string) $version['hash'];
        $flags = $this->normalizeFlags(is_array($round['flags'] ?? null) ? $round['flags'] : []);
        $report = is_array($round['report'] ?? null) ? $round['report'] : [];
        $reportGenerated = $report !== [];
        $finalConfirmation = (bool) ($round['finalConfirmation'] ?? false);
        $completed = ($round['status'] ?? '') === 'approved';
        $blocking = $summary['openCritical'] + $summary['openPending'];

        $checklist = [
            ['key' => 'integrity', 'label' => 'Integridade do snapshot conferida', 'completed' => $integrityValid, 'automatic' => true],
        ];
        foreach (self::MANUAL_FLAGS as $key => $label) {
            $complete = (bool) ($flags[$key] ?? false);
            if ($key === 'report_checked') $complete = $complete && $reportGenerated;
            $checklist[] = ['key' => $key, 'label' => $label, 'completed' => $complete, 'automatic' => false];
        }
        $checklist[] = ['key' => 'no_blockers', 'label' => 'Nenhuma pendência obrigatória aberta', 'completed' => $blocking === 0, 'automatic' => true];
        $checklist[] = ['key' => 'completed', 'label' => 'Auditoria concluída', 'completed' => $completed, 'automatic' => true];
        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $manualReady = count(array_filter(array_keys(self::MANUAL_FLAGS), static fn (string $key): bool => (bool) ($flags[$key] ?? false))) === count(self::MANUAL_FLAGS);
        $ready = $integrityValid && $blocking === 0 && $manualReady && $reportGenerated && $finalConfirmation && ! $completed;

        $currentHash = $this->currentWorkHash($userId, $bookId);
        $workChanged = $currentHash !== (string) $version['hash'];

        return [
            'bookId' => (string) $bookId,
            'title' => (string) (($version['snapshot']['metadata']['title'] ?? '') ?: get_the_title($bookId)),
            'round' => $this->roundSummary($round),
            'rounds' => array_map(fn (array $item): array => $this->roundSummary($item), array_reverse($rounds)),
            'version' => $this->versionSummary($version),
            'workChangedAfterBaseline' => $workChanged,
            'currentWorkHash' => $currentHash,
            'categories' => $this->options(self::CATEGORIES),
            'severities' => $this->options(self::SEVERITIES),
            'statuses' => $this->options(self::STATUSES),
            'findings' => $findings,
            'summary' => $summary,
            'flags' => $flags,
            'finalConfirmation' => $finalConfirmation,
            'reportGenerated' => $reportGenerated,
            'report' => $report,
            'checklist' => $checklist,
            'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            'completedCount' => $completedCount,
            'total' => count($checklist),
            'ready' => $ready,
            'completed' => $completed,
            'result' => $completed ? 'approved' : ($blocking > 0 ? 'requires_corrections' : 'in_progress'),
            'resultLabel' => $completed ? 'Aprovada' : ($blocking > 0 ? 'Requer correções' : 'Em andamento'),
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function saveState(int $userId, int $bookId, array $fields): array
    {
        $data = $this->data($userId, $bookId);
        $roundId = (string) $data['round']['id'];
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== $roundId) continue;
            if (($round['status'] ?? '') === 'approved') throw new ValidationError('Esta rodada de Auditoria está aprovada e não pode mais ser alterada.');
            if (array_key_exists('flags', $fields)) $round['flags'] = $this->normalizeFlags(is_array($fields['flags']) ? $fields['flags'] : []);
            if (array_key_exists('final_confirmation', $fields)) $round['finalConfirmation'] = (bool) $fields['final_confirmation'];
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function createFinding(int $userId, int $bookId, array $fields): array
    {
        $data = $this->data($userId, $bookId);
        $roundId = (string) $data['round']['id'];
        $description = trim(sanitize_textarea_field((string) ($fields['description'] ?? '')));
        if ($description === '') throw new ValidationError('Descreva o achado da Auditoria.');
        $category = sanitize_key((string) ($fields['category'] ?? 'editorial'));
        if (! isset(self::CATEGORIES[$category])) $category = 'editorial';
        $severity = sanitize_key((string) ($fields['severity'] ?? 'warning'));
        if (! isset(self::SEVERITIES[$severity])) $severity = 'warning';
        $chapterId = (string) (int) ($fields['chapter_id'] ?? 0);
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== $roundId) continue;
            if (($round['status'] ?? '') === 'approved') throw new ValidationError('Esta rodada de Auditoria está aprovada e não pode mais ser alterada.');
            $items = is_array($round['manualFindings'] ?? null) ? $round['manualFindings'] : [];
            $items[] = $this->finding(
                'audit-manual-' . substr(md5($description . '|' . microtime(true)), 0, 14),
                $category,
                $severity,
                $description,
                trim(sanitize_textarea_field((string) ($fields['recommendation'] ?? ''))),
                $chapterId === '0' ? '' : $chapterId,
                trim(sanitize_text_field((string) ($fields['chapter_title'] ?? ''))),
                'manual'
            );
            $round['manualFindings'] = $items;
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function updateFinding(int $userId, int $bookId, string $findingId, array $fields): array
    {
        $data = $this->data($userId, $bookId);
        $roundId = (string) $data['round']['id'];
        $status = sanitize_key((string) ($fields['status'] ?? 'reviewing'));
        if (! isset(self::STATUSES[$status])) $status = 'reviewing';
        $justification = trim(sanitize_textarea_field((string) ($fields['justification'] ?? '')));
        if ($status === 'ignored' && $justification === '') throw new ValidationError('Informe a justificativa para ignorar este achado.');
        $exists = false;
        foreach ((array) $data['findings'] as $finding) if ((string) ($finding['id'] ?? '') === $findingId) { $exists = true; break; }
        if (! $exists) throw new NotFoundError('Achado da Auditoria não encontrado.');
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== $roundId) continue;
            if (($round['status'] ?? '') === 'approved') throw new ValidationError('Esta rodada de Auditoria está aprovada e não pode mais ser alterada.');
            $decisions = is_array($round['decisions'] ?? null) ? $round['decisions'] : [];
            $decisions[$findingId] = [
                'status' => $status,
                'justification' => $justification,
                'resolvedAt' => in_array($status, ['resolved', 'ignored'], true) ? gmdate('c') : '',
            ];
            $round['decisions'] = $decisions;
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function deleteFinding(int $userId, int $bookId, string $findingId): array
    {
        $data = $this->data($userId, $bookId);
        $roundId = (string) $data['round']['id'];
        $rounds = $this->rounds($bookId);
        $deleted = false;
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== $roundId) continue;
            if (($round['status'] ?? '') === 'approved') throw new ValidationError('Esta rodada de Auditoria está aprovada e não pode mais ser alterada.');
            $items = is_array($round['manualFindings'] ?? null) ? $round['manualFindings'] : [];
            $next = array_values(array_filter($items, static fn (array $item): bool => (string) ($item['id'] ?? '') !== $findingId));
            $deleted = count($next) !== count($items);
            $round['manualFindings'] = $next;
            if (isset($round['decisions'][$findingId])) unset($round['decisions'][$findingId]);
            break;
        }
        unset($round);
        if (! $deleted) throw new ValidationError('Somente achados manuais podem ser excluídos.');
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function generateReport(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        $roundId = (string) $data['round']['id'];
        $report = [
            'generatedAt' => gmdate('c'),
            'workTitle' => (string) $data['title'],
            'versionNumber' => (string) $data['version']['number'],
            'versionName' => (string) $data['version']['name'],
            'versionHash' => (string) $data['version']['hash'],
            'roundNumber' => (int) $data['round']['number'],
            'summary' => $data['summary'],
            'result' => (string) $data['result'],
            'resultLabel' => (string) $data['resultLabel'],
            'findings' => array_map(static function (array $finding): array {
                return [
                    'category' => $finding['categoryLabel'],
                    'severity' => $finding['severityLabel'],
                    'description' => $finding['description'],
                    'status' => $finding['statusLabel'],
                    'chapterTitle' => $finding['chapterTitle'],
                    'justification' => $finding['justification'] ?? '',
                ];
            }, (array) $data['findings']),
        ];
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== $roundId) continue;
            if (($round['status'] ?? '') === 'approved') throw new ValidationError('Esta rodada de Auditoria está aprovada e não pode mais ser alterada.');
            $round['report'] = $report;
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function assistantContext(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        $version = $this->auditVersion($bookId);
        $snapshot = (array) $version['snapshot'];
        $chapters = [];
        foreach ((array) ($snapshot['chapters'] ?? []) as $chapter) {
            if (! is_array($chapter)) continue;
            $chapters[] = [
                'number' => (int) ($chapter['number'] ?? 0),
                'title' => (string) ($chapter['title'] ?? ''),
                'wordCount' => (int) ($chapter['wordCount'] ?? 0),
                'excerpt' => $this->excerpt((string) ($chapter['content'] ?? ''), 1200),
            ];
        }
        return [
            'version' => $data['version'],
            'summary' => $data['summary'],
            'chapters' => $chapters,
            'terminology' => $data['round']['terminology'] ?? [],
            'existingFindings' => array_slice((array) $data['findings'], 0, 80),
        ];
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        if (! $data['ready']) {
            throw new ValidationError('Resolva as pendências obrigatórias, gere e confira o relatório e confirme a versão auditada antes de aprovar a Auditoria.');
        }
        $roundId = (string) $data['round']['id'];
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== $roundId) continue;
            $round['status'] = 'approved';
            $round['result'] = 'approved';
            $round['completedAt'] = gmdate('c');
            $round['approvedVersionId'] = (string) $data['version']['id'];
            $round['approvedHash'] = (string) $data['version']['hash'];
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);
        update_post_meta($bookId, '_verbum_audit_approved_version_id', (string) $data['version']['id']);
        update_post_meta($bookId, '_verbum_audit_approved_hash', (string) $data['version']['hash']);
        update_post_meta($bookId, '_verbum_audit_completed_at', gmdate('c'));
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('audit', $completed, true)) $completed[] = 'audit';
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        update_post_meta($bookId, '_verbum_stage', 'editorial_desk');
        $this->protectApprovedVersion($bookId, (string) $data['version']['id']);
        $this->touchBook($bookId);
        return $this->data($userId, $bookId);
    }

    private function assertAvailable(int $userId, int $bookId): void
    {
        $book = get_post($bookId);
        if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) throw new NotFoundError('Obra não encontrada.');
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('versions', $completed, true)) throw new ValidationError('Conclua o Controle de Versões antes de iniciar a Auditoria da Obra.');
        if ((string) get_post_meta($bookId, '_verbum_versions_audit_baseline_id', true) === '') throw new ValidationError('Selecione uma Versão para Auditoria no Controle de Versões.');
    }

    /** @return array<string, mixed> */
    private function auditVersion(int $bookId): array
    {
        $baselineId = (string) get_post_meta($bookId, '_verbum_versions_audit_baseline_id', true);
        foreach ($this->versions($bookId) as $version) if ((string) ($version['id'] ?? '') === $baselineId) return $version;
        throw new ValidationError('A Versão para Auditoria não foi encontrada. Volte ao Controle de Versões e selecione uma nova baseline.');
    }

    /** @param array<string, mixed> $version
     *  @return array<string, mixed>
     */
    private function newRound(int $userId, int $bookId, array $version, int $number): array
    {
        return [
            'id' => 'audit-round-' . substr(md5((string) $version['id'] . '|' . microtime(true)), 0, 14),
            'number' => $number,
            'versionId' => (string) $version['id'],
            'versionNumber' => (string) $version['number'],
            'versionName' => (string) $version['name'],
            'versionHash' => (string) $version['hash'],
            'startedAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
            'completedAt' => '',
            'status' => 'in_progress',
            'result' => 'in_progress',
            'flags' => $this->normalizeFlags([]),
            'finalConfirmation' => false,
            'manualFindings' => [],
            'decisions' => [],
            'report' => [],
            'sources' => $this->sourceSnapshot($userId, $bookId, (array) ($version['snapshot']['chapters'] ?? [])),
            'terminology' => $this->terminologySnapshot($bookId),
            'elementChoices' => [],
        ];
    }

    /** @param array<string, mixed> $round
     *  @return array<int, array<string, mixed>>
     */
    private function automaticFindings(array $round): array
    {
        $version = null;
        foreach ($this->versions((int) get_post_meta((int) 0, '', true)) as $unused) { $version = $unused; }
        unset($version);
        $bookId = 0;
        $versionId = (string) ($round['versionId'] ?? '');
        // Resolve the owning work from the active baseline stored in the round through the snapshot copy saved with the version.
        // The caller always invokes this after auditVersion(), so find the version in the current work via the cached round snapshot below.
        $snapshot = is_array($round['snapshot'] ?? null) ? $round['snapshot'] : [];
        if ($snapshot === []) {
            // Older/new rounds do not duplicate the complete snapshot. It is injected by hydrateRoundSnapshot().
            $snapshot = is_array($round['_snapshot'] ?? null) ? $round['_snapshot'] : [];
        }
        $findings = [];
        if ($snapshot === []) return $findings;
        $hashValid = $this->snapshotHash($snapshot) === (string) ($round['versionHash'] ?? '');
        if (! $hashValid) $findings[] = $this->finding('audit-integrity-hash', 'integrity', 'critical', 'O hash da versão auditada não corresponde ao snapshot preservado.', 'Volte ao Controle de Versões e selecione uma versão íntegra.', '', '', 'automatic');

        $chapters = is_array($snapshot['chapters'] ?? null) ? $snapshot['chapters'] : [];
        if ($chapters === []) $findings[] = $this->finding('audit-integrity-no-chapters', 'integrity', 'critical', 'A versão auditada não possui capítulos.', 'Selecione uma versão completa antes de continuar.', '', '', 'automatic');
        $numbers = [];
        $titles = [];
        foreach ($chapters as $index => $chapter) {
            if (! is_array($chapter)) continue;
            $chapterId = (string) ($chapter['id'] ?? '');
            $number = max(1, (int) ($chapter['number'] ?? ($index + 1)));
            $title = trim((string) ($chapter['title'] ?? ''));
            $content = (string) ($chapter['content'] ?? '');
            $numbers[] = $number;
            $titles[] = mb_strtolower($title);
            if (trim(wp_strip_all_tags($content)) === '') $findings[] = $this->finding('audit-empty-' . $chapterId, 'content', 'critical', 'O capítulo ' . $number . ' está sem conteúdo na versão auditada.', 'Preencha o capítulo e crie uma nova versão para Auditoria.', $chapterId, $title, 'automatic');
            if ($title === '') $findings[] = $this->finding('audit-title-' . $chapterId, 'structure', 'pending', 'O capítulo ' . $number . ' está sem título.', 'Defina um título e gere nova versão.', $chapterId, $title, 'automatic');
            $markerPattern = '/(?:\bTODO\b|\?\?\?|\[\s*(?:completar|inserir[^\]]*|revisar)\s*\]|X{5,})/iu';
            if (preg_match_all($markerPattern, wp_strip_all_tags($content), $matches) && ! empty($matches[0])) {
                $findings[] = $this->finding('audit-marker-' . $chapterId, 'content', 'pending', 'Possível marcador editorial pendente no capítulo ' . $number . ': ' . implode(', ', array_slice(array_unique($matches[0]), 0, 4)), 'Confirme se o marcador deve ser removido ou registre uma justificativa.', $chapterId, $title, 'automatic');
            }
        }
        $expected = range(1, count($chapters));
        $sorted = $numbers; sort($sorted);
        if ($sorted !== $expected) $findings[] = $this->finding('audit-sequence', 'structure', 'pending', 'A numeração dos capítulos não forma uma sequência contínua.', 'Revise a ordem e a numeração no Planejamento antes de criar nova versão.', '', '', 'automatic');
        if (count(array_unique($numbers)) !== count($numbers)) $findings[] = $this->finding('audit-duplicate-number', 'structure', 'pending', 'Existem capítulos com numeração duplicada.', 'Revise a numeração dos capítulos.', '', '', 'automatic');
        $nonEmptyTitles = array_values(array_filter($titles));
        if (count(array_unique($nonEmptyTitles)) !== count($nonEmptyTitles)) $findings[] = $this->finding('audit-duplicate-title', 'structure', 'warning', 'Existem títulos de capítulos duplicados ou idênticos.', 'Confirme se a repetição é intencional.', '', '', 'automatic');

        $front = is_array($snapshot['frontMatter'] ?? null) ? $snapshot['frontMatter'] : [];
        if (trim(wp_strip_all_tags((string) ($front['introduction'] ?? ''))) === '') $findings[] = $this->finding('audit-element-introduction', 'elements', 'pending', 'A Introdução Geral da obra está vazia.', 'Escreva a Introdução Geral ou justifique editorialmente sua ausência.', '', '', 'automatic');
        if (trim(wp_strip_all_tags((string) ($front['conclusion'] ?? ''))) === '') $findings[] = $this->finding('audit-element-conclusion', 'elements', 'pending', 'A Conclusão Geral da obra está vazia.', 'Escreva a Conclusão Geral ou justifique editorialmente sua ausência.', '', '', 'automatic');

        foreach ((array) ($round['sources'] ?? []) as $source) {
            if (! is_array($source) || ! ($source['used'] ?? false)) continue;
            $id = (string) ($source['id'] ?? '');
            $reference = trim((string) ($source['reference'] ?? ''));
            $title = trim((string) ($source['title'] ?? ''));
            $chapterId = (string) ($source['chapterId'] ?? '');
            $chapterTitle = (string) ($source['chapterTitle'] ?? '');
            if ($reference === '' && $title === '') $findings[] = $this->finding('audit-source-missing-' . $id, 'sources', 'pending', 'Uma fonte utilizada não possui referência suficiente.', 'Complete a referência na Pesquisa do capítulo e crie nova versão para Auditoria.', $chapterId, $chapterTitle, 'automatic');
            elseif (! ($source['verified'] ?? false)) $findings[] = $this->finding('audit-source-unverified-' . $id, 'sources', 'warning', 'Fonte utilizada ainda não consta como verificada: ' . ($reference !== '' ? $reference : $title) . '.', 'Confira a origem e os dados bibliográficos.', $chapterId, $chapterTitle, 'automatic');
        }

        return $findings;
    }

    /** @param array<string, mixed> $round
     *  @return array<string, mixed>
     */
    private function hydrateRoundSnapshot(array $round, array $version): array
    {
        $round['_snapshot'] = is_array($version['snapshot'] ?? null) ? $version['snapshot'] : [];
        return $round;
    }

    /** @param array<int, array<string, mixed>> $findings
     *  @return array<string, int>
     */
    private function summary(array $findings): array
    {
        $summary = ['total' => count($findings), 'conforming' => 0, 'info' => 0, 'warnings' => 0, 'pending' => 0, 'critical' => 0, 'openCritical' => 0, 'openPending' => 0, 'ignored' => 0, 'resolved' => 0];
        foreach ($findings as $finding) {
            $severity = (string) ($finding['severity'] ?? 'info');
            $status = (string) ($finding['status'] ?? 'open');
            if ($severity === 'info') $summary['info']++;
            if ($severity === 'warning') $summary['warnings']++;
            if ($severity === 'pending') $summary['pending']++;
            if ($severity === 'critical') $summary['critical']++;
            if ($status === 'resolved') $summary['resolved']++;
            if ($status === 'ignored') $summary['ignored']++;
            if ($status === 'open' || $status === 'reviewing') {
                if ($severity === 'critical') $summary['openCritical']++;
                if ($severity === 'pending') $summary['openPending']++;
            } else $summary['conforming']++;
        }
        return $summary;
    }

    /** @return array<string, mixed> */
    private function finding(string $id, string $category, string $severity, string $description, string $recommendation, string $chapterId, string $chapterTitle, string $origin): array
    {
        return [
            'id' => sanitize_key($id),
            'category' => $category,
            'categoryLabel' => self::CATEGORIES[$category] ?? self::CATEGORIES['editorial'],
            'severity' => $severity,
            'severityLabel' => self::SEVERITIES[$severity] ?? self::SEVERITIES['warning'],
            'description' => $description,
            'recommendation' => $recommendation,
            'chapterId' => $chapterId,
            'chapterTitle' => $chapterTitle,
            'status' => 'open',
            'statusLabel' => self::STATUSES['open'],
            'justification' => '',
            'origin' => $origin,
            'createdAt' => gmdate('c'),
            'resolvedAt' => '',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceSnapshot(int $userId, int $bookId, array $chapters): array
    {
        $chapterTitles = [];
        foreach ($chapters as $chapter) if (is_array($chapter)) $chapterTitles[(string) ($chapter['id'] ?? '')] = (string) ($chapter['title'] ?? '');
        $query = new \WP_Query([
            'post_type' => LibraryPostTypes::RESEARCH,
            'post_status' => 'publish',
            'author' => $userId,
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_verbum_book_id', 'value' => $bookId, 'compare' => '=', 'type' => 'NUMERIC']],
            'no_found_rows' => true,
        ]);
        $result = [];
        foreach ($query->posts as $post) {
            if (! $post instanceof \WP_Post) continue;
            $chapterId = (string) (int) get_post_meta($post->ID, '_verbum_chapter_id', true);
            if (! isset($chapterTitles[$chapterId])) continue;
            $verifiedIds = get_post_meta((int) $chapterId, '_verbum_revision_verified_source_ids', true);
            $verifiedIds = is_array($verifiedIds) ? array_map('strval', $verifiedIds) : [];
            $result[] = [
                'id' => (string) $post->ID,
                'chapterId' => $chapterId,
                'chapterTitle' => $chapterTitles[$chapterId],
                'category' => (string) get_post_meta($post->ID, '_verbum_research_category', true),
                'title' => (string) get_post_meta($post->ID, '_verbum_research_title', true),
                'author' => (string) get_post_meta($post->ID, '_verbum_research_author', true),
                'reference' => (string) get_post_meta($post->ID, '_verbum_research_reference', true),
                'used' => (bool) get_post_meta($post->ID, '_verbum_research_used', true),
                'verified' => in_array((string) $post->ID, $verifiedIds, true),
            ];
        }
        return $result;
    }

    /** @return array<int, array<string, string>> */
    private function terminologySnapshot(int $bookId): array
    {
        $raw = get_post_meta($bookId, '_verbum_general_review_terms', true);
        $raw = is_array($raw) ? $raw : [];
        $result = [];
        foreach ($raw as $item) {
            if (! is_array($item)) continue;
            $term = trim(sanitize_text_field((string) ($item['term'] ?? '')));
            if ($term !== '') $result[] = ['term' => $term, 'note' => trim(sanitize_textarea_field((string) ($item['note'] ?? '')))];
        }
        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function rounds(int $bookId): array
    {
        $rounds = get_post_meta($bookId, '_verbum_audit_rounds', true);
        return is_array($rounds) ? array_values(array_filter($rounds, 'is_array')) : [];
    }

    /** @param array<int, array<string, mixed>> $rounds */
    private function storeRounds(int $bookId, array $rounds): void
    {
        update_post_meta($bookId, '_verbum_audit_rounds', array_values($rounds));
    }

    /** @param array<int, array<string, mixed>> $rounds
     *  @return array<string, mixed>|null
     */
    private function currentRound(array $rounds, string $versionId)
    {
        foreach (array_reverse($rounds) as $round) if (is_array($round) && (string) ($round['versionId'] ?? '') === $versionId) return $round;
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function versions(int $bookId): array
    {
        $versions = get_post_meta($bookId, '_verbum_work_versions', true);
        return is_array($versions) ? array_values(array_filter($versions, 'is_array')) : [];
    }

    /** @param array<string, mixed> $round
     *  @return array<string, mixed>
     */
    private function roundSummary(array $round): array
    {
        return [
            'id' => (string) ($round['id'] ?? ''),
            'number' => (int) ($round['number'] ?? 0),
            'versionId' => (string) ($round['versionId'] ?? ''),
            'versionNumber' => (string) ($round['versionNumber'] ?? ''),
            'versionName' => (string) ($round['versionName'] ?? ''),
            'versionHash' => (string) ($round['versionHash'] ?? ''),
            'startedAt' => (string) ($round['startedAt'] ?? ''),
            'completedAt' => (string) ($round['completedAt'] ?? ''),
            'status' => (string) ($round['status'] ?? 'in_progress'),
            'result' => (string) ($round['result'] ?? 'in_progress'),
            'terminology' => is_array($round['terminology'] ?? null) ? $round['terminology'] : [],
        ];
    }

    /** @param array<string, mixed> $version
     *  @return array<string, mixed>
     */
    private function versionSummary(array $version): array
    {
        return [
            'id' => (string) ($version['id'] ?? ''),
            'number' => (string) ($version['number'] ?? ''),
            'name' => (string) ($version['name'] ?? ''),
            'hash' => (string) ($version['hash'] ?? ''),
            'createdAt' => (string) ($version['createdAt'] ?? ''),
            'chapterCount' => (int) ($version['chapterCount'] ?? 0),
            'wordCount' => (int) ($version['wordCount'] ?? 0),
            'protected' => (bool) ($version['protected'] ?? false),
        ];
    }

    /** @param array<string, bool> $flags
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

    /** @param array<string, mixed> $snapshot */
    private function snapshotHash(array $snapshot): string
    {
        return hash('sha256', (string) wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function currentWorkHash(int $userId, int $bookId): string
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
        $frontRaw = get_post_meta($bookId, '_verbum_general_review_front_matter', true);
        $frontRaw = is_array($frontRaw) ? $frontRaw : [];
        $front = [];
        foreach (['preface', 'presentation', 'authorNote', 'introduction', 'conclusion'] as $key) $front[$key] = wp_kses_post((string) ($frontRaw[$key] ?? ''));
        $structure = get_post_meta($bookId, '_verbum_planning_structure_items', true);
        $snapshot = [
            'metadata' => ['title' => $book instanceof \WP_Post ? get_the_title($book) : '', 'subtitle' => trim((string) get_post_meta($bookId, '_verbum_subtitle', true))],
            'structure' => is_array($structure) ? $structure : [],
            'frontMatter' => $front,
            'chapters' => $chapters,
        ];
        return $this->snapshotHash($snapshot);
    }

    private function protectApprovedVersion(int $bookId, string $versionId): void
    {
        $versions = $this->versions($bookId);
        foreach ($versions as &$version) {
            if ((string) ($version['id'] ?? '') !== $versionId) continue;
            $version['protected'] = true;
            $version['auditApprovedAt'] = gmdate('c');
            break;
        }
        unset($version);
        update_post_meta($bookId, '_verbum_work_versions', $versions);
    }

    private function excerpt(string $html, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return strlen($text) <= $limit ? $text : substr($text, 0, $limit) . '…';
    }

    private function touchBook(int $bookId): void
    {
        $book = get_post($bookId);
        if ($book instanceof \WP_Post) wp_update_post(['ID' => $bookId, 'post_content' => $book->post_content]);
    }
}
