<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkLegalRepository
{
    private const PROCESS_STATUSES = [
        'not_started' => 'Não iniciado',
        'preparing' => 'Em preparação',
        'requested' => 'Solicitado',
        'waiting' => 'Aguardando retorno',
        'received' => 'Recebido',
        'validated' => 'Validado',
        'not_applicable' => 'Não aplicável',
        'pending' => 'Pendência',
    ];

    private const DOCUMENT_CATEGORIES = [
        'isbn' => 'ISBN', 'cataloging' => 'Ficha Catalográfica', 'copyright' => 'Registro Autoral',
        'authorization' => 'Autorização', 'license' => 'Licença', 'contract' => 'Contrato',
        'opinion' => 'Parecer', 'ecclesial' => 'Documento Eclesial', 'receipt' => 'Comprovante', 'other' => 'Outro',
    ];

    private const ISSUE_TYPES = [
        'isbn' => 'ISBN', 'cataloging' => 'Ficha', 'copyright' => 'Direitos Autorais', 'authorization' => 'Autorização',
        'credit' => 'Crédito', 'document' => 'Documento', 'registration' => 'Registro', 'final_file' => 'Arquivo Final', 'other' => 'Outro',
    ];

    private const PRIORITIES = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'blocking' => 'Bloqueante'];
    private const ISSUE_STATUSES = ['open' => 'Aberta', 'in_progress' => 'Em andamento', 'resolved' => 'Resolvida'];
    private const THIRD_PARTY_STATUSES = [
        'not_started' => 'Não iniciado', 'pending' => 'Pendente', 'authorized' => 'Autorização recebida',
        'not_required' => 'Autorização não necessária', 'public_domain' => 'Domínio público declarado', 'own_material' => 'Material próprio',
    ];

    private const MANUAL_FLAGS = [
        'identification_checked' => 'Identificação da edição conferida',
        'copyright_registered' => 'Direitos autorais registrados',
        'third_party_checked' => 'Conteúdos de terceiros conferidos',
        'credits_checked' => 'Créditos editoriais conferidos',
        'legal_elements_inserted' => 'Elementos legais inseridos na obra',
        'final_file_checked' => 'Arquivo final conferido',
        'documents_organized' => 'Documentos organizados',
        'legal_version_validated' => 'Versão legal final validada',
    ];

    /** @return array<string,mixed> */
    public function data(int $userId, int $bookId): array
    {
        $this->assertAvailable($userId, $bookId);
        $version = $this->approvedLayoutVersion($bookId);
        $rounds = $this->rounds($bookId);
        $round = $this->currentRound($rounds, (string) $version['id'], (string) $version['hash']);
        if ($round === null) {
            $round = $this->newRound($userId, $bookId, $version, count($rounds) + 1);
            $rounds[] = $round;
            $this->storeRounds($bookId, $rounds);
        }

        $state = is_array($round['state'] ?? null) ? $round['state'] : $this->initialState($userId, $bookId, $version);
        $flags = $this->normalizeFlags(is_array($round['flags'] ?? null) ? $round['flags'] : []);
        $documents = is_array($round['documents'] ?? null) ? array_values(array_filter($round['documents'], 'is_array')) : [];
        $issues = is_array($round['issues'] ?? null) ? array_values(array_filter($round['issues'], 'is_array')) : [];
        $thirdParty = is_array($round['thirdParty'] ?? null) ? array_values(array_filter($round['thirdParty'], 'is_array')) : [];
        $proofs = is_array($round['proofs'] ?? null) ? array_values(array_filter($round['proofs'], 'is_array')) : [];
        $history = is_array($round['history'] ?? null) ? array_values(array_filter($round['history'], 'is_array')) : [];
        $finalConfirmation = (bool) ($round['finalConfirmation'] ?? false);
        $completed = (string) ($round['status'] ?? '') === 'completed';
        $baselineValid = $this->baselineValid($bookId, $version);

        $authorizationBlockers = count(array_filter($thirdParty, static function (array $item): bool {
            if (! (bool) ($item['authorizationRequired'] ?? false)) return false;
            return ! in_array((string) ($item['status'] ?? 'not_started'), ['authorized', 'not_required', 'public_domain', 'own_material'], true);
        }));
        $blockingIssues = count(array_filter($issues, static fn (array $item): bool => (string) ($item['priority'] ?? '') === 'blocking' && (string) ($item['status'] ?? 'open') !== 'resolved'));
        $openIssues = count(array_filter($issues, static fn (array $item): bool => (string) ($item['status'] ?? 'open') !== 'resolved'));
        $isbnReady = $this->isbnReady($state);
        $catalogingReady = in_array((string) ($state['cataloging']['status'] ?? 'not_started'), ['validated', 'not_applicable'], true);
        $finalFileReady = trim((string) ($state['finalFiles']['selectedFileUrl'] ?? '')) !== '';

        $checklist = [];
        foreach (self::MANUAL_FLAGS as $key => $label) $checklist[] = ['key' => $key, 'label' => $label, 'completed' => (bool) ($flags[$key] ?? false), 'automatic' => false];
        $checklist[] = ['key' => 'isbn_handled', 'label' => 'ISBN definido ou marcado como não aplicável', 'completed' => $isbnReady, 'automatic' => true];
        $checklist[] = ['key' => 'cataloging_handled', 'label' => 'Ficha catalográfica definida ou não aplicável', 'completed' => $catalogingReady, 'automatic' => true];
        $checklist[] = ['key' => 'authorizations_resolved', 'label' => 'Autorizações necessárias recebidas', 'completed' => $authorizationBlockers === 0, 'automatic' => true];
        $checklist[] = ['key' => 'blocking_issues_resolved', 'label' => 'Pendências bloqueantes resolvidas', 'completed' => $blockingIssues === 0, 'automatic' => true];
        $checklist[] = ['key' => 'final_file_selected', 'label' => 'Arquivo final selecionado', 'completed' => $finalFileReady, 'automatic' => true];
        $checklist[] = ['key' => 'baseline_valid', 'label' => 'Baseline de Diagramação válida', 'completed' => $baselineValid, 'automatic' => true];
        $checklist[] = ['key' => 'publication_authorized', 'label' => 'Publicação autorizada pelo autor', 'completed' => $finalConfirmation, 'automatic' => true];
        $checklist[] = ['key' => 'completed', 'label' => 'Trâmites Legais concluídos', 'completed' => $completed, 'automatic' => true];
        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $manualReady = count(array_filter(array_keys(self::MANUAL_FLAGS), static fn (string $key): bool => (bool) ($flags[$key] ?? false))) === count(self::MANUAL_FLAGS);
        $ready = ! $completed && $baselineValid && $manualReady && $isbnReady && $catalogingReady && $authorizationBlockers === 0 && $blockingIssues === 0 && $finalFileReady && $finalConfirmation;

        return [
            'bookId' => (string) $bookId,
            'title' => (string) ($state['identity']['title'] ?? get_the_title($bookId)),
            'version' => $this->versionSummary($version),
            'layout' => $this->layoutSummary($bookId, $version),
            'round' => $this->roundSummary($round),
            'rounds' => array_map(fn (array $item): array => $this->roundSummary($item), array_reverse($rounds)),
            'state' => $state,
            'documents' => $documents,
            'thirdParty' => $thirdParty,
            'issues' => $issues,
            'proofs' => array_reverse($proofs),
            'history' => array_reverse($history),
            'flags' => $flags,
            'finalConfirmation' => $finalConfirmation,
            'processStatuses' => $this->options(self::PROCESS_STATUSES),
            'documentCategories' => $this->options(self::DOCUMENT_CATEGORIES),
            'issueTypes' => $this->options(self::ISSUE_TYPES),
            'priorities' => $this->options(self::PRIORITIES),
            'issueStatuses' => $this->options(self::ISSUE_STATUSES),
            'thirdPartyStatuses' => $this->options(self::THIRD_PARTY_STATUSES),
            'authorizationBlockers' => $authorizationBlockers,
            'blockingIssueCount' => $blockingIssues,
            'openIssueCount' => $openIssues,
            'baselineValid' => $baselineValid,
            'checklist' => $checklist,
            'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            'completedCount' => $completedCount,
            'total' => count($checklist),
            'ready' => $ready,
            'completed' => $completed,
            'alerts' => $this->alerts($state, $issues),
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function saveState(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertMutable($round);
            if (array_key_exists('state', $payload)) $round['state'] = $this->normalizeState(is_array($payload['state']) ? $payload['state'] : []);
            if (array_key_exists('flags', $payload)) $round['flags'] = $this->normalizeFlags(is_array($payload['flags']) ? $payload['flags'] : []);
            if (array_key_exists('final_confirmation', $payload)) $round['finalConfirmation'] = (bool) $payload['final_confirmation'];
            $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, 'Estado legal atualizado', 'Dados da edição e checklist foram atualizados.'); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createDocument(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId); $name = trim(sanitize_text_field((string) ($payload['name'] ?? '')));
        if ($name === '') throw new ValidationError('Informe o nome do documento.');
        $category = sanitize_key((string) ($payload['category'] ?? 'other')); if (! isset(self::DOCUMENT_CATEGORIES[$category])) $category = 'other';
        $status = sanitize_key((string) ($payload['status'] ?? 'not_started')); if (! isset(self::PROCESS_STATUSES[$status])) $status = 'not_started';
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round);
            $items = is_array($round['documents'] ?? null) ? $round['documents'] : [];
            $item = ['id' => 'legal-doc-' . substr(md5($name . '|' . microtime(true)), 0, 14), 'name' => $name, 'category' => $category, 'categoryLabel' => self::DOCUMENT_CATEGORIES[$category], 'status' => $status, 'statusLabel' => self::PROCESS_STATUSES[$status], 'number' => trim(sanitize_text_field((string) ($payload['number'] ?? ''))), 'date' => trim(sanitize_text_field((string) ($payload['date'] ?? ''))), 'expiresAt' => trim(sanitize_text_field((string) ($payload['expires_at'] ?? ''))), 'notes' => trim(sanitize_textarea_field((string) ($payload['notes'] ?? ''))), 'fileUrl' => esc_url_raw((string) ($payload['file_url'] ?? '')), 'createdAt' => gmdate('c'), 'updatedAt' => gmdate('c')];
            $items[] = $item; $round['documents'] = $items; $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, 'Documento adicionado', $name); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function updateDocument(int $userId, int $bookId, string $documentId, array $payload): array
    {
        return $this->updateCollectionItem($userId, $bookId, 'documents', $documentId, function (array $item) use ($payload): array {
            if (array_key_exists('status', $payload)) { $status = sanitize_key((string) $payload['status']); if (isset(self::PROCESS_STATUSES[$status])) { $item['status'] = $status; $item['statusLabel'] = self::PROCESS_STATUSES[$status]; } }
            foreach (['name','number','date','expires_at','notes','file_url'] as $key) if (array_key_exists($key, $payload)) {
                $target = $key === 'expires_at' ? 'expiresAt' : ($key === 'file_url' ? 'fileUrl' : $key);
                $item[$target] = $key === 'file_url' ? esc_url_raw((string) $payload[$key]) : ($key === 'notes' ? trim(sanitize_textarea_field((string) $payload[$key])) : trim(sanitize_text_field((string) $payload[$key])));
            }
            $item['updatedAt'] = gmdate('c'); return $item;
        }, 'Documento atualizado');
    }

    /** @return array<string,mixed> */
    public function deleteDocument(int $userId, int $bookId, string $documentId): array { return $this->deleteCollectionItem($userId, $bookId, 'documents', $documentId, 'Documento removido'); }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createThirdParty(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId); $description = trim(sanitize_text_field((string) ($payload['description'] ?? '')));
        if ($description === '') throw new ValidationError('Descreva o conteúdo de terceiro.');
        $status = sanitize_key((string) ($payload['status'] ?? 'not_started')); if (! isset(self::THIRD_PARTY_STATUSES[$status])) $status = 'not_started';
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round);
            $items = is_array($round['thirdParty'] ?? null) ? $round['thirdParty'] : [];
            $items[] = ['id' => 'third-party-' . substr(md5($description . '|' . microtime(true)), 0, 14), 'description' => $description, 'origin' => trim(sanitize_text_field((string) ($payload['origin'] ?? ''))), 'holder' => trim(sanitize_text_field((string) ($payload['holder'] ?? ''))), 'location' => trim(sanitize_text_field((string) ($payload['location'] ?? ''))), 'useType' => trim(sanitize_text_field((string) ($payload['use_type'] ?? ''))), 'authorizationRequired' => (bool) ($payload['authorization_required'] ?? false), 'status' => $status, 'statusLabel' => self::THIRD_PARTY_STATUSES[$status], 'licenseType' => trim(sanitize_text_field((string) ($payload['license_type'] ?? ''))), 'fileUrl' => esc_url_raw((string) ($payload['file_url'] ?? '')), 'notes' => trim(sanitize_textarea_field((string) ($payload['notes'] ?? ''))), 'createdAt' => gmdate('c'), 'updatedAt' => gmdate('c')];
            $round['thirdParty'] = $items; $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, 'Conteúdo de terceiro adicionado', $description); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function updateThirdParty(int $userId, int $bookId, string $itemId, array $payload): array
    {
        return $this->updateCollectionItem($userId, $bookId, 'thirdParty', $itemId, function (array $item) use ($payload): array {
            if (array_key_exists('status', $payload)) { $status = sanitize_key((string) $payload['status']); if (isset(self::THIRD_PARTY_STATUSES[$status])) { $item['status'] = $status; $item['statusLabel'] = self::THIRD_PARTY_STATUSES[$status]; } }
            if (array_key_exists('authorization_required', $payload)) $item['authorizationRequired'] = (bool) $payload['authorization_required'];
            foreach (['description','origin','holder','location','use_type','license_type','file_url','notes'] as $key) if (array_key_exists($key, $payload)) {
                $map = ['use_type' => 'useType', 'license_type' => 'licenseType', 'file_url' => 'fileUrl']; $target = $map[$key] ?? $key;
                $item[$target] = $key === 'file_url' ? esc_url_raw((string) $payload[$key]) : ($key === 'notes' ? trim(sanitize_textarea_field((string) $payload[$key])) : trim(sanitize_text_field((string) $payload[$key])));
            }
            $item['updatedAt'] = gmdate('c'); return $item;
        }, 'Conteúdo de terceiro atualizado');
    }

    /** @return array<string,mixed> */
    public function deleteThirdParty(int $userId, int $bookId, string $itemId): array { return $this->deleteCollectionItem($userId, $bookId, 'thirdParty', $itemId, 'Conteúdo de terceiro removido'); }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createIssue(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId); $description = trim(sanitize_textarea_field((string) ($payload['description'] ?? '')));
        if ($description === '') throw new ValidationError('Descreva a pendência legal.');
        $type = sanitize_key((string) ($payload['type'] ?? 'other')); if (! isset(self::ISSUE_TYPES[$type])) $type = 'other';
        $priority = sanitize_key((string) ($payload['priority'] ?? 'medium')); if (! isset(self::PRIORITIES[$priority])) $priority = 'medium';
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round);
            $items = is_array($round['issues'] ?? null) ? $round['issues'] : [];
            $items[] = ['id' => 'legal-issue-' . substr(md5($description . '|' . microtime(true)), 0, 14), 'type' => $type, 'typeLabel' => self::ISSUE_TYPES[$type], 'priority' => $priority, 'priorityLabel' => self::PRIORITIES[$priority], 'description' => $description, 'responsible' => trim(sanitize_text_field((string) ($payload['responsible'] ?? ''))), 'dueAt' => trim(sanitize_text_field((string) ($payload['due_at'] ?? ''))), 'status' => 'open', 'statusLabel' => self::ISSUE_STATUSES['open'], 'createdAt' => gmdate('c'), 'updatedAt' => gmdate('c')];
            $round['issues'] = $items; $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, 'Pendência legal registrada', $description); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function updateIssue(int $userId, int $bookId, string $issueId, array $payload): array
    {
        return $this->updateCollectionItem($userId, $bookId, 'issues', $issueId, function (array $item) use ($payload): array {
            if (array_key_exists('status', $payload)) { $status = sanitize_key((string) $payload['status']); if (isset(self::ISSUE_STATUSES[$status])) { $item['status'] = $status; $item['statusLabel'] = self::ISSUE_STATUSES[$status]; } }
            if (array_key_exists('priority', $payload)) { $priority = sanitize_key((string) $payload['priority']); if (isset(self::PRIORITIES[$priority])) { $item['priority'] = $priority; $item['priorityLabel'] = self::PRIORITIES[$priority]; } }
            foreach (['description','responsible','due_at'] as $key) if (array_key_exists($key, $payload)) { $target = $key === 'due_at' ? 'dueAt' : $key; $item[$target] = $key === 'description' ? trim(sanitize_textarea_field((string) $payload[$key])) : trim(sanitize_text_field((string) $payload[$key])); }
            $item['updatedAt'] = gmdate('c'); return $item;
        }, 'Pendência legal atualizada');
    }

    /** @return array<string,mixed> */
    public function deleteIssue(int $userId, int $bookId, string $issueId): array { return $this->deleteCollectionItem($userId, $bookId, 'issues', $issueId, 'Pendência legal removida'); }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function registerProof(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId); $url = esc_url_raw((string) ($payload['file_url'] ?? ''));
        if ($url === '') throw new ValidationError('Informe a URL protegida ou referência do arquivo da Prova Legal Final.');
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round);
            $proofs = is_array($round['proofs'] ?? null) ? $round['proofs'] : []; $number = count($proofs) + 1;
            $proofs[] = ['id' => 'legal-proof-' . substr(md5((string) $number . '|' . microtime(true)), 0, 14), 'number' => $number, 'label' => 'Prova Legal ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'fileUrl' => $url, 'notes' => trim(sanitize_textarea_field((string) ($payload['notes'] ?? ''))), 'createdAt' => gmdate('c')];
            $round['proofs'] = $proofs; $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, 'Prova legal registrada', 'Prova Legal ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT)); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @return array<string,mixed> */
    public function assistantContext(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        return ['version' => $data['version'], 'layout' => $data['layout'], 'state' => $data['state'], 'documents' => $data['documents'], 'thirdParty' => $data['thirdParty'], 'issues' => $data['issues'], 'alerts' => $data['alerts'], 'checklist' => $data['checklist']];
    }

    /** @return array<string,mixed> */
    public function complete(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        if (! $data['ready']) throw new ValidationError('Conclua o checklist, resolva pendências bloqueantes e autorizações, selecione o arquivo final e confirme a edição antes de concluir os Trâmites Legais.');
        $rounds = $this->rounds($bookId); $snapshotHash = '';
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $round['status'] = 'completed'; $round['completedAt'] = gmdate('c');
            $round['legalSnapshot'] = ['state' => $data['state'], 'documents' => $data['documents'], 'thirdParty' => $data['thirdParty'], 'issues' => $data['issues'], 'proofs' => $data['proofs'], 'flags' => $data['flags'], 'version' => $data['version'], 'layout' => $data['layout']];
            $snapshotHash = hash('sha256', wp_json_encode($round['legalSnapshot'], JSON_UNESCAPED_UNICODE)); $round['legalSnapshotHash'] = $snapshotHash;
            $this->appendHistory($round, 'Trâmites Legais concluídos', 'Edição legal congelada para Publicação.'); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds);
        update_post_meta($bookId, '_verbum_legal_approved_version_id', (string) $data['version']['id']);
        update_post_meta($bookId, '_verbum_legal_approved_hash', (string) $data['version']['hash']);
        update_post_meta($bookId, '_verbum_legal_snapshot_hash', $snapshotHash);
        update_post_meta($bookId, '_verbum_legal_final_file', (string) ($data['state']['finalFiles']['selectedFileUrl'] ?? ''));
        update_post_meta($bookId, '_verbum_legal_completed_at', gmdate('c'));
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true); $completed = is_array($completed) ? $completed : [];
        if (! in_array('legal', $completed, true)) $completed[] = 'legal';
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        update_post_meta($bookId, '_verbum_stage', 'publication');
        return $this->data($userId, $bookId);
    }

    private function assertAvailable(int $userId, int $bookId): void
    {
        $book = get_post($bookId);
        if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) throw new NotFoundError('Obra não encontrada.');
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true); $completed = is_array($completed) ? $completed : [];
        if (! in_array('layout', $completed, true)) throw new ValidationError('Conclua a Diagramação da Obra antes de iniciar os Trâmites Legais.');
        if ((string) get_post_meta($bookId, '_verbum_layout_approved_version_id', true) === '') throw new ValidationError('A Diagramação ainda não possui uma baseline aprovada.');
    }

    /** @return array<string,mixed> */
    private function approvedLayoutVersion(int $bookId): array
    {
        $id = (string) get_post_meta($bookId, '_verbum_layout_approved_version_id', true); $hash = (string) get_post_meta($bookId, '_verbum_layout_approved_hash', true);
        $versions = get_post_meta($bookId, '_verbum_work_versions', true); $versions = is_array($versions) ? $versions : [];
        foreach ($versions as $version) {
            if (! is_array($version) || (string) ($version['id'] ?? '') !== $id) continue;
            if ($hash === '' || (string) ($version['hash'] ?? '') !== $hash) throw new ValidationError('A baseline aprovada na Diagramação perdeu a integridade esperada.');
            return $version;
        }
        throw new ValidationError('A versão aprovada na Diagramação não foi encontrada no histórico da obra.');
    }

    /** @param array<string,mixed> $version */
    private function baselineValid(int $bookId, array $version): bool
    {
        return (string) get_post_meta($bookId, '_verbum_layout_approved_version_id', true) === (string) ($version['id'] ?? '')
            && (string) get_post_meta($bookId, '_verbum_layout_approved_hash', true) === (string) ($version['hash'] ?? '');
    }

    /** @param array<string,mixed> $version @return array<string,mixed> */
    private function newRound(int $userId, int $bookId, array $version, int $number): array
    {
        return ['id' => 'legal-round-' . substr(md5((string) $version['id'] . '|' . microtime(true)), 0, 14), 'number' => $number, 'versionId' => (string) $version['id'], 'versionHash' => (string) $version['hash'], 'state' => $this->initialState($userId, $bookId, $version), 'flags' => $this->normalizeFlags([]), 'documents' => [], 'thirdParty' => [], 'issues' => [], 'proofs' => [], 'history' => [['id' => 'history-' . substr(md5((string) microtime(true)), 0, 12), 'event' => 'Trâmites Legais iniciados', 'detail' => 'Baseline da Diagramação vinculada à edição.', 'createdAt' => gmdate('c')]], 'finalConfirmation' => false, 'status' => 'in_progress', 'startedAt' => gmdate('c'), 'updatedAt' => gmdate('c'), 'completedAt' => ''];
    }

    /** @param array<string,mixed> $version @return array<string,mixed> */
    private function initialState(int $userId, int $bookId, array $version): array
    {
        $editorial = $this->editorialFields($bookId, (string) $version['id']); $identity = is_array($editorial['identity'] ?? null) ? $editorial['identity'] : []; $edition = is_array($editorial['edition'] ?? null) ? $editorial['edition'] : [];
        $formats = is_array($edition['formats'] ?? null) ? array_values(array_filter(array_map('strval', $edition['formats']))) : []; if ($formats === []) $formats = ['printed'];
        $author = (string) (($identity['authorDisplay'] ?? '') ?: get_post_meta($bookId, '_verbum_author_name', true));
        $layoutRound = $this->approvedLayoutRound($bookId, (string) $version['id']); $layoutConfig = is_array($layoutRound['config'] ?? null) ? $layoutRound['config'] : [];
        $proof = $this->finalLayoutProof($layoutRound); $pageCount = (int) get_post_meta($bookId, '_verbum_layout_final_page_count', true);
        $isbn = [];
        foreach ($formats as $format) $isbn[$format] = ['format' => $format, 'label' => $format === 'digital' ? 'Livro digital' : 'Livro impresso', 'status' => 'not_started', 'number' => '', 'requestedAt' => '', 'issuedAt' => '', 'issuer' => '', 'notes' => '', 'structureValid' => false];
        $user = get_userdata($userId); $fullName = is_object($user) && isset($user->display_name) ? (string) $user->display_name : $author;
        return [
            'identity' => ['title' => (string) (($identity['titleFinal'] ?? '') ?: get_the_title($bookId)), 'subtitle' => (string) ($identity['subtitleFinal'] ?? ''), 'author' => $author, 'authorFullName' => $fullName, 'edition' => (string) ($edition['edition'] ?? '1ª edição'), 'year' => (string) ($edition['year'] ?? gmdate('Y')), 'place' => (string) ($edition['place'] ?? ''), 'publisherType' => (string) ($edition['publisherType'] ?? 'independent'), 'publisherName' => (string) ($edition['publisherName'] ?? ''), 'language' => (string) ($identity['language'] ?? ''), 'format' => (string) ($layoutConfig['format']['name'] ?? ($edition['trimSize'] ?? '')), 'pageCount' => $pageCount, 'publicationFormats' => $formats],
            'isbn' => $isbn,
            'cataloging' => ['status' => 'not_started', 'professional' => '', 'professionalRegistration' => '', 'requestedAt' => '', 'receivedAt' => '', 'fileUrl' => '', 'notes' => '', 'insertedInLayout' => false],
            'copyright' => ['rightsHolder' => $author, 'author' => $author, 'year' => (string) ($edition['year'] ?? gmdate('Y')), 'notice' => '© ' . (string) ($edition['year'] ?? gmdate('Y')) . ' ' . $author, 'reservation' => 'Todos os direitos reservados', 'licenseChoice' => ''],
            'authorRegistration' => ['status' => 'not_started', 'institution' => '', 'number' => '', 'date' => '', 'protocol' => '', 'fileUrl' => '', 'notes' => ''],
            'credits' => ['author' => $author, 'revision' => '', 'editorialPreparation' => '', 'cover' => '', 'illustration' => '', 'layout' => '', 'cataloging' => '', 'publisher' => (string) ($edition['publisherName'] ?? ''), 'printing' => '', 'other' => ''],
            'ecclesial' => ['applicable' => false, 'responsible' => '', 'opinion' => '', 'date' => '', 'fileUrl' => '', 'status' => 'not_started', 'nihilObstat' => '', 'imprimatur' => ''],
            'academic' => ['applicable' => false, 'institution' => '', 'program' => '', 'advisor' => '', 'institutionalApproval' => '', 'repositoryLicense' => '', 'doi' => ''],
            'finalFiles' => ['printInteriorUrl' => (string) ($proof['url'] ?? ''), 'coverUrl' => '', 'finalProofUrl' => (string) ($proof['url'] ?? ''), 'digitalFileUrl' => '', 'digitalCoverUrl' => '', 'selectedFileUrl' => (string) ($proof['url'] ?? '')],
            'technical' => ['format' => (string) ($layoutConfig['format']['name'] ?? ''), 'pages' => $pageCount, 'interiorPaper' => '', 'coverPaper' => '', 'finish' => '', 'spine' => '', 'bleedMm' => (float) ($layoutConfig['format']['bleedMm'] ?? 0), 'interiorColor' => '', 'binding' => '', 'printer' => '', 'quantity' => ''],
        ];
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    private function normalizeState(array $state): array
    {
        $clean = $this->sanitizeArray($state);
        if (isset($clean['isbn']) && is_array($clean['isbn'])) foreach ($clean['isbn'] as &$record) if (is_array($record)) $record['structureValid'] = $this->isbnFormatValid((string) ($record['number'] ?? ''));
        unset($record); return $clean;
    }

    /** @param array<string,mixed> $state */
    private function isbnReady(array $state): bool
    {
        $records = is_array($state['isbn'] ?? null) ? $state['isbn'] : []; if ($records === []) return false;
        foreach ($records as $record) {
            if (! is_array($record)) return false; $status = (string) ($record['status'] ?? 'not_started');
            if ($status === 'not_applicable') continue;
            if ($status !== 'validated' || ! $this->isbnFormatValid((string) ($record['number'] ?? ''))) return false;
        }
        return true;
    }

    private function isbnFormatValid(string $number): bool
    {
        $raw = strtoupper(preg_replace('/[^0-9X]/', '', $number) ?? '');
        if (strlen($raw) === 13 && ctype_digit($raw)) { $sum = 0; for ($i = 0; $i < 12; $i++) $sum += ((int) $raw[$i]) * ($i % 2 === 0 ? 1 : 3); return (10 - ($sum % 10)) % 10 === (int) $raw[12]; }
        if (strlen($raw) === 10 && preg_match('/^[0-9]{9}[0-9X]$/', $raw)) { $sum = 0; for ($i = 0; $i < 10; $i++) $sum += ($raw[$i] === 'X' ? 10 : (int) $raw[$i]) * (10 - $i); return $sum % 11 === 0; }
        return false;
    }

    /** @param array<string,mixed> $state @param array<int,array<string,mixed>> $issues @return array<int,string> */
    private function alerts(array $state, array $issues): array
    {
        $alerts = []; $now = time();
        $checkWaiting = static function (array $item, string $label) use (&$alerts, $now): void { if ((string) ($item['status'] ?? '') !== 'waiting') return; $date = (string) ($item['requestedAt'] ?? ''); $ts = $date !== '' ? strtotime($date) : false; if ($ts === false) return; $days = max(0, (int) floor(($now - $ts) / 86400)); $alerts[] = $label . ' aguardando retorno há ' . $days . ' dia' . ($days === 1 ? '' : 's') . '.'; };
        foreach ((array) ($state['isbn'] ?? []) as $record) if (is_array($record)) $checkWaiting($record, 'ISBN de ' . (string) ($record['label'] ?? 'formato'));
        if (is_array($state['cataloging'] ?? null)) $checkWaiting($state['cataloging'], 'Ficha catalográfica');
        foreach ($issues as $issue) { $due = (string) ($issue['dueAt'] ?? ''); if ($due !== '' && (string) ($issue['status'] ?? '') !== 'resolved' && strtotime($due) !== false && strtotime($due) < $now) $alerts[] = 'Pendência vencida: ' . (string) ($issue['description'] ?? 'Item legal'); }
        return $alerts;
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $mutator @return array<string,mixed> */
    private function updateCollectionItem(int $userId, int $bookId, string $collection, string $id, callable $mutator, string $event): array
    {
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId); $found = false;
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round); $items = is_array($round[$collection] ?? null) ? $round[$collection] : [];
            foreach ($items as &$item) { if (! is_array($item) || (string) ($item['id'] ?? '') !== $id) continue; $item = $mutator($item); $found = true; break; }
            unset($item); $round[$collection] = $items; if ($found) { $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, $event, $id); } break;
        }
        unset($round); if (! $found) throw new NotFoundError('Registro legal não encontrado.'); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @return array<string,mixed> */
    private function deleteCollectionItem(int $userId, int $bookId, string $collection, string $id, string $event): array
    {
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId); $deleted = false;
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round); $items = is_array($round[$collection] ?? null) ? $round[$collection] : [];
            $next = array_values(array_filter($items, static fn ($item): bool => ! is_array($item) || (string) ($item['id'] ?? '') !== $id)); $deleted = count($next) !== count($items); $round[$collection] = $next; if ($deleted) { $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, $event, $id); } break;
        }
        unset($round); if (! $deleted) throw new NotFoundError('Registro legal não encontrado.'); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $round */
    private function appendHistory(array &$round, string $event, string $detail): void
    {
        $history = is_array($round['history'] ?? null) ? $round['history'] : [];
        $history[] = ['id' => 'history-' . substr(md5($event . '|' . microtime(true)), 0, 12), 'event' => $event, 'detail' => $detail, 'createdAt' => gmdate('c')];
        $round['history'] = array_slice($history, -100);
    }

    /** @param array<string,mixed> $round */
    private function assertMutable(array $round): void { if ((string) ($round['status'] ?? '') === 'completed') throw new ValidationError('Os Trâmites Legais desta edição já foram concluídos e estão congelados.'); }

    /** @return array<int,array<string,mixed>> */
    private function rounds(int $bookId): array { $items = get_post_meta($bookId, '_verbum_legal_rounds', true); return is_array($items) ? array_values(array_filter($items, 'is_array')) : []; }
    /** @param array<int,array<string,mixed>> $rounds */ private function storeRounds(int $bookId, array $rounds): void { update_post_meta($bookId, '_verbum_legal_rounds', array_values($rounds)); }
    /** @param array<int,array<string,mixed>> $rounds @return array<string,mixed>|null */
    private function currentRound(array $rounds, string $versionId, string $hash): ?array { foreach (array_reverse($rounds) as $round) if (is_array($round) && (string) ($round['versionId'] ?? '') === $versionId && (string) ($round['versionHash'] ?? '') === $hash) return $round; return null; }

    /** @return array<string,mixed> */
    private function editorialFields(int $bookId, string $versionId): array
    {
        $rounds = get_post_meta($bookId, '_verbum_editorial_desk_rounds', true); $rounds = is_array($rounds) ? $rounds : [];
        foreach (array_reverse($rounds) as $round) if (is_array($round) && (string) ($round['versionId'] ?? '') === $versionId && is_array($round['fields'] ?? null)) return $round['fields'];
        return [];
    }

    /** @return array<string,mixed> */
    private function approvedLayoutRound(int $bookId, string $versionId): array
    {
        $rounds = get_post_meta($bookId, '_verbum_layout_rounds', true); $rounds = is_array($rounds) ? $rounds : [];
        foreach (array_reverse($rounds) as $round) if (is_array($round) && (string) ($round['versionId'] ?? '') === $versionId && (string) ($round['status'] ?? '') === 'approved') return $round;
        return [];
    }

    /** @param array<string,mixed> $round @return array<string,mixed> */
    private function finalLayoutProof(array $round): array
    {
        $target = (string) ($round['finalProofId'] ?? ''); $proofs = is_array($round['proofs'] ?? null) ? $round['proofs'] : [];
        foreach ($proofs as $proof) if (is_array($proof) && (string) ($proof['id'] ?? '') === $target) return $proof;
        return is_array(end($proofs)) ? end($proofs) : [];
    }

    /** @param array<string,mixed> $version @return array<string,mixed> */
    private function versionSummary(array $version): array { return ['id' => (string) ($version['id'] ?? ''), 'number' => (string) ($version['number'] ?? ''), 'name' => (string) ($version['name'] ?? ''), 'hash' => (string) ($version['hash'] ?? ''), 'chapterCount' => (int) ($version['chapterCount'] ?? 0), 'wordCount' => (int) ($version['wordCount'] ?? 0), 'createdAt' => (string) ($version['createdAt'] ?? '')]; }

    /** @param array<string,mixed> $version @return array<string,mixed> */
    private function layoutSummary(int $bookId, array $version): array
    {
        $round = $this->approvedLayoutRound($bookId, (string) ($version['id'] ?? '')); $config = is_array($round['config'] ?? null) ? $round['config'] : [];
        return ['pageCount' => (int) get_post_meta($bookId, '_verbum_layout_final_page_count', true), 'proofId' => (string) get_post_meta($bookId, '_verbum_layout_final_proof_id', true), 'format' => (string) ($config['format']['name'] ?? ''), 'bleedMm' => (float) ($config['format']['bleedMm'] ?? 0), 'completedAt' => (string) get_post_meta($bookId, '_verbum_layout_completed_at', true)];
    }

    /** @param array<string,mixed> $round @return array<string,mixed> */
    private function roundSummary(array $round): array { return ['id' => (string) ($round['id'] ?? ''), 'number' => (int) ($round['number'] ?? 0), 'versionId' => (string) ($round['versionId'] ?? ''), 'versionHash' => (string) ($round['versionHash'] ?? ''), 'status' => (string) ($round['status'] ?? 'in_progress'), 'startedAt' => (string) ($round['startedAt'] ?? ''), 'updatedAt' => (string) ($round['updatedAt'] ?? ''), 'completedAt' => (string) ($round['completedAt'] ?? ''), 'legalSnapshotHash' => (string) ($round['legalSnapshotHash'] ?? '')]; }

    /** @param array<string,bool> $flags @return array<string,bool> */
    private function normalizeFlags(array $flags): array { $clean = []; foreach (array_keys(self::MANUAL_FLAGS) as $key) $clean[$key] = (bool) ($flags[$key] ?? false); return $clean; }

    /** @param array<mixed> $value @return array<mixed> */
    private function sanitizeArray(array $value): array
    {
        $clean = [];
        foreach ($value as $key => $item) {
            $target = is_int($key) ? $key : preg_replace('/[^A-Za-z0-9_-]/', '', (string) $key); if (! is_int($target) && (! is_string($target) || $target === '')) continue;
            if (is_array($item)) $clean[$target] = $this->sanitizeArray($item); elseif (is_bool($item)) $clean[$target] = $item; elseif (is_int($item) || is_float($item)) $clean[$target] = $item; else { $text = (string) $item; $clean[$target] = preg_match('/Url$/', (string) $target) ? esc_url_raw($text) : sanitize_textarea_field($text); }
        }
        return $clean;
    }

    /** @param array<string,string> $map @return array<int,array<string,string>> */
    private function options(array $map): array { $out = []; foreach ($map as $key => $label) $out[] = ['key' => $key, 'label' => $label]; return $out; }
}
