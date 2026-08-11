<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkLayoutRepository
{
    private const ISSUE_CATEGORIES = [
        'typography' => 'Tipografia', 'page' => 'Página', 'image' => 'Imagem', 'title' => 'Título',
        'citation' => 'Citação', 'note' => 'Nota', 'toc' => 'Sumário', 'element' => 'Elemento editorial',
        'spacing' => 'Espaçamento', 'break' => 'Quebra', 'other' => 'Outro',
    ];
    private const ISSUE_STATUSES = ['open' => 'Aberta', 'resolved' => 'Resolvida'];
    private const MANUAL_FLAGS = [
        'format_defined' => 'Formato definido',
        'margins_defined' => 'Margens definidas',
        'typography_defined' => 'Tipografia definida',
        'styles_configured' => 'Estilos configurados',
        'chapter_openings_checked' => 'Aberturas de capítulos revisadas',
        'headers_footers_defined' => 'Cabeçalhos e rodapés definidos',
        'pagination_checked' => 'Paginação conferida',
        'toc_checked' => 'Sumário conferido',
        'front_matter_checked' => 'Elementos pré-textuais conferidos',
        'images_checked' => 'Imagens conferidas',
        'preview_checked' => 'Prévia integral conferida',
    ];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId): array
    {
        $this->assertAvailable($userId, $bookId);
        $version = $this->approvedEditorialVersion($bookId);
        $rounds = $this->rounds($bookId);
        $round = $this->currentRound($rounds, (string) $version['id']);
        if ($round === null) {
            $round = $this->newRound($bookId, $version, count($rounds) + 1);
            $rounds[] = $round;
            $this->storeRounds($bookId, $rounds);
        }

        $config = is_array($round['config'] ?? null) ? $round['config'] : $this->defaultConfig($bookId);
        $flags = $this->normalizeFlags(is_array($round['flags'] ?? null) ? $round['flags'] : []);
        $issues = is_array($round['issues'] ?? null) ? array_values(array_filter($round['issues'], 'is_array')) : [];
        $proofs = is_array($round['proofs'] ?? null) ? array_values(array_filter($round['proofs'], 'is_array')) : [];
        $openIssues = count(array_filter($issues, static fn (array $item): bool => (string) ($item['status'] ?? 'open') === 'open'));
        $baselineValid = (string) get_post_meta($bookId, '_verbum_editorial_approved_version_id', true) === (string) $version['id']
            && (string) get_post_meta($bookId, '_verbum_editorial_approved_hash', true) === (string) $version['hash'];
        $completed = (string) ($round['status'] ?? '') === 'approved';
        $finalConfirmation = (bool) ($round['finalConfirmation'] ?? false);
        $pageCount = $this->estimatePageCount($version, $config, $bookId);

        $checklist = [];
        foreach (self::MANUAL_FLAGS as $key => $label) {
            $checklist[] = ['key' => $key, 'label' => $label, 'completed' => (bool) ($flags[$key] ?? false), 'automatic' => false];
        }
        $checklist[] = ['key' => 'issues_resolved', 'label' => 'Pendências resolvidas', 'completed' => $openIssues === 0, 'automatic' => true];
        $checklist[] = ['key' => 'proof_generated', 'label' => 'PDF de prova gerado', 'completed' => count($proofs) > 0, 'automatic' => true];
        $checklist[] = ['key' => 'baseline_valid', 'label' => 'Baseline editorial válida', 'completed' => $baselineValid, 'automatic' => true];
        $checklist[] = ['key' => 'completed', 'label' => 'Diagramação aprovada', 'completed' => $completed, 'automatic' => true];
        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $manualReady = count(array_filter(array_keys(self::MANUAL_FLAGS), static fn (string $key): bool => (bool) ($flags[$key] ?? false))) === count(self::MANUAL_FLAGS);
        $ready = ! $completed && $baselineValid && $manualReady && $openIssues === 0 && count($proofs) > 0 && $finalConfirmation;
        $editorial = $this->editorialFields($bookId, (string) $version['id']);

        return [
            'bookId' => (string) $bookId,
            'title' => (string) (($editorial['identity']['titleFinal'] ?? '') ?: get_the_title($bookId)),
            'version' => $this->versionSummary($version),
            'round' => $this->roundSummary($round),
            'rounds' => array_map(fn (array $item): array => $this->roundSummary($item), array_reverse($rounds)),
            'config' => $config,
            'editorial' => [
                'identity' => $editorial['identity'] ?? [], 'edition' => $editorial['edition'] ?? [],
                'elements' => $editorial['elements'] ?? [], 'elementOrder' => $editorial['elementOrder'] ?? [],
                'layoutBrief' => $editorial['layoutBrief'] ?? [], 'coverBrief' => $editorial['coverBrief'] ?? [],
            ],
            'issueCategories' => $this->options(self::ISSUE_CATEGORIES), 'issueStatuses' => $this->options(self::ISSUE_STATUSES),
            'issues' => $issues, 'openIssueCount' => $openIssues, 'proofs' => array_reverse($proofs),
            'pageCount' => $pageCount, 'chapterCount' => (int) ($version['chapterCount'] ?? 0),
            'flags' => $flags, 'finalConfirmation' => $finalConfirmation, 'baselineValid' => $baselineValid,
            'checklist' => $checklist, 'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            'completedCount' => $completedCount, 'total' => count($checklist), 'ready' => $ready, 'completed' => $completed,
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function saveState(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId);
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertMutable($round);
            if (array_key_exists('config', $payload)) $round['config'] = $this->sanitizeArray(is_array($payload['config']) ? $payload['config'] : []);
            if (array_key_exists('flags', $payload)) $round['flags'] = $this->normalizeFlags(is_array($payload['flags']) ? $payload['flags'] : []);
            if (array_key_exists('final_confirmation', $payload)) $round['finalConfirmation'] = (bool) $payload['final_confirmation'];
            $round['updatedAt'] = gmdate('c');
            break;
        }
        unset($round);
        $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createIssue(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId);
        $description = trim(sanitize_textarea_field((string) ($payload['description'] ?? '')));
        if ($description === '') throw new ValidationError('Descreva a pendência de Diagramação.');
        $category = sanitize_key((string) ($payload['category'] ?? 'other'));
        if (! isset(self::ISSUE_CATEGORIES[$category])) $category = 'other';
        $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertMutable($round);
            $items = is_array($round['issues'] ?? null) ? $round['issues'] : [];
            $items[] = [
                'id' => 'layout-issue-' . substr(md5($description . '|' . microtime(true)), 0, 14),
                'category' => $category, 'categoryLabel' => self::ISSUE_CATEGORIES[$category],
                'description' => $description, 'status' => 'open', 'statusLabel' => self::ISSUE_STATUSES['open'],
                'createdAt' => gmdate('c'), 'updatedAt' => gmdate('c'),
            ];
            $round['issues'] = $items; $round['updatedAt'] = gmdate('c'); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds);
        return $this->data($userId, $bookId);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateIssue(int $userId, int $bookId, string $issueId, array $payload): array
    {
        $data = $this->data($userId, $bookId); $found = false; $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertMutable($round); $items = is_array($round['issues'] ?? null) ? $round['issues'] : [];
            foreach ($items as &$item) {
                if ((string) ($item['id'] ?? '') !== $issueId) continue;
                $found = true; $status = sanitize_key((string) ($payload['status'] ?? $item['status'] ?? 'open'));
                if (! isset(self::ISSUE_STATUSES[$status])) $status = 'open';
                $item['status'] = $status; $item['statusLabel'] = self::ISSUE_STATUSES[$status]; $item['updatedAt'] = gmdate('c'); break;
            }
            unset($item); $round['issues'] = $items; break;
        }
        unset($round); if (! $found) throw new NotFoundError('Pendência de Diagramação não encontrada.');
        $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function deleteIssue(int $userId, int $bookId, string $issueId): array
    {
        $data = $this->data($userId, $bookId); $deleted = false; $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertMutable($round); $items = is_array($round['issues'] ?? null) ? $round['issues'] : [];
            $next = array_values(array_filter($items, static fn (array $item): bool => (string) ($item['id'] ?? '') !== $issueId));
            $deleted = count($next) !== count($items); $round['issues'] = $next; break;
        }
        unset($round); if (! $deleted) throw new NotFoundError('Pendência de Diagramação não encontrada.');
        $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function preview(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId); $version = $this->approvedEditorialVersion($bookId);
        $pages = $this->previewPages($version, $data['config'], $bookId);
        return ['pages' => $pages, 'pageCount' => count($pages), 'version' => $data['version'], 'config' => $data['config']];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function generateProof(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId);
        $version = $this->approvedEditorialVersion($bookId); $pages = $this->previewPages($version, $data['config'], $bookId);
        $number = count($data['proofs']) + 1; $note = trim(sanitize_textarea_field((string) ($payload['note'] ?? '')));
        $file = $this->writeProofPdf($bookId, $pages, $number);
        $proof = ['id' => 'layout-proof-' . substr(md5((string) $number . '|' . microtime(true)), 0, 14), 'number' => $number,
            'label' => 'Prova ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'createdAt' => gmdate('c'), 'pageCount' => count($pages),
            'note' => $note, 'url' => $file['url'], 'path' => $file['path'], 'watermark' => 'PROVA — NÃO PUBLICAR'];
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertMutable($round); $proofs = is_array($round['proofs'] ?? null) ? $round['proofs'] : []; $proofs[] = $proof; $round['proofs'] = $proofs; $round['updatedAt'] = gmdate('c'); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function assistantContext(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        return ['version' => $data['version'], 'config' => $data['config'], 'editorial' => $data['editorial'], 'pageCount' => $data['pageCount'], 'issues' => $data['issues']];
    }

    /** @return array<string, mixed> */
    public function complete(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        if (! $data['ready']) throw new ValidationError('Conclua o checklist, resolva as pendências, gere uma prova e confirme a prévia final antes de concluir a Diagramação.');
        $rounds = $this->rounds($bookId); $finalProof = is_array($data['proofs'][0] ?? null) ? $data['proofs'][0] : [];
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $round['status'] = 'approved'; $round['completedAt'] = gmdate('c'); $round['finalPageCount'] = (int) $data['pageCount'];
            $round['finalProofId'] = (string) ($finalProof['id'] ?? ''); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds);
        update_post_meta($bookId, '_verbum_layout_approved_version_id', (string) $data['version']['id']);
        update_post_meta($bookId, '_verbum_layout_approved_hash', (string) $data['version']['hash']);
        update_post_meta($bookId, '_verbum_layout_final_page_count', (int) $data['pageCount']);
        update_post_meta($bookId, '_verbum_layout_final_proof_id', (string) ($finalProof['id'] ?? ''));
        update_post_meta($bookId, '_verbum_layout_completed_at', gmdate('c'));
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true); $completed = is_array($completed) ? $completed : [];
        if (! in_array('layout', $completed, true)) $completed[] = 'layout';
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed))); update_post_meta($bookId, '_verbum_stage', 'legal');
        return $this->data($userId, $bookId);
    }

    private function assertAvailable(int $userId, int $bookId): void
    {
        $book = get_post($bookId);
        if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) throw new NotFoundError('Obra não encontrada.');
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true); $completed = is_array($completed) ? $completed : [];
        if (! in_array('editorial_desk', $completed, true)) throw new ValidationError('Conclua a Mesa Editorial antes de iniciar a Diagramação.');
        if ((string) get_post_meta($bookId, '_verbum_editorial_approved_version_id', true) === '') throw new ValidationError('A Mesa Editorial ainda não definiu uma versão aprovada para Diagramação.');
    }

    /** @return array<string, mixed> */
    private function approvedEditorialVersion(int $bookId): array
    {
        $id = (string) get_post_meta($bookId, '_verbum_editorial_approved_version_id', true); $hash = (string) get_post_meta($bookId, '_verbum_editorial_approved_hash', true);
        $versions = get_post_meta($bookId, '_verbum_work_versions', true); $versions = is_array($versions) ? $versions : [];
        foreach ($versions as $version) {
            if (! is_array($version) || (string) ($version['id'] ?? '') !== $id) continue;
            if ($hash === '' || (string) ($version['hash'] ?? '') !== $hash) throw new ValidationError('A versão aprovada pela Mesa Editorial perdeu a integridade esperada.');
            return $version;
        }
        throw new ValidationError('A versão aprovada pela Mesa Editorial não foi encontrada.');
    }

    /** @param array<string, mixed> $version @return array<string, mixed> */
    private function newRound(int $bookId, array $version, int $number): array
    {
        return ['id' => 'layout-round-' . substr(md5((string) $version['id'] . '|' . microtime(true)), 0, 14), 'number' => $number,
            'versionId' => (string) $version['id'], 'versionNumber' => (string) $version['number'], 'versionHash' => (string) $version['hash'],
            'config' => $this->defaultConfig($bookId), 'flags' => $this->normalizeFlags([]), 'issues' => [], 'proofs' => [], 'finalConfirmation' => false,
            'status' => 'in_progress', 'startedAt' => gmdate('c'), 'updatedAt' => gmdate('c'), 'completedAt' => ''];
    }

    /** @return array<string, mixed> */
    private function defaultConfig(int $bookId): array
    {
        $editorial = $this->editorialFields($bookId, (string) get_post_meta($bookId, '_verbum_editorial_approved_version_id', true));
        $trim = (string) ($editorial['edition']['trimSize'] ?? '14 × 21 cm');
        $style = (string) ($editorial['layoutBrief']['style'] ?? 'Clássico');
        return [
            'preset' => $style !== '' ? $style : 'Clássico',
            'format' => ['name' => $trim !== '' ? $trim : '14 × 21 cm', 'widthMm' => 140, 'heightMm' => 210, 'bleedMm' => 3],
            'margins' => ['topMm' => 18, 'bottomMm' => 20, 'innerMm' => 22, 'outerMm' => 17, 'mirrored' => true],
            'typography' => ['bodyFont' => 'Georgia', 'bodySizePt' => 11, 'lineHeight' => 1.45, 'color' => '#222222', 'align' => 'justify', 'headingFont' => 'Georgia'],
            'paragraph' => ['firstLineCm' => 0.7, 'spaceBeforePt' => 0, 'spaceAfterPt' => 5, 'noIndentAfterHeading' => true, 'hyphenation' => false, 'widowsOrphans' => true],
            'masterPage' => ['evenHeader' => 'Título da obra', 'oddHeader' => 'Título do capítulo', 'footer' => '', 'pageNumberPosition' => 'bottom-outer', 'hideOnChapterOpening' => true, 'frontMatterRoman' => true],
            'chapter' => ['start' => 'new-page', 'titleAlign' => 'center', 'topSpaceMm' => 35, 'showNumber' => true, 'dropCaps' => (bool) ($editorial['layoutBrief']['dropCaps'] ?? false), 'dropCapLines' => 3],
            'quotes' => ['font' => 'Georgia', 'sizePt' => 10, 'indentCm' => 1.0, 'align' => 'left'],
            'religiousStyles' => ['verse' => true, 'catechism' => true, 'magisterium' => true, 'saints' => true],
            'notes' => ['mode' => (bool) ($editorial['layoutBrief']['footnotes'] ?? false) ? 'footnotes' : 'endnotes'],
            'toc' => ['parts' => true, 'chapters' => true, 'subchapters' => true, 'levels' => 3],
            'preview' => ['spread' => false], 'cover' => ['status' => 'waiting'],
        ];
    }

    /** @return array<string, mixed> */
    private function editorialFields(int $bookId, string $versionId): array
    {
        $rounds = get_post_meta($bookId, '_verbum_editorial_desk_rounds', true); $rounds = is_array($rounds) ? $rounds : [];
        foreach (array_reverse($rounds) as $round) if (is_array($round) && (string) ($round['versionId'] ?? '') === $versionId && is_array($round['fields'] ?? null)) return $round['fields'];
        return [];
    }

    /** @param array<string, mixed> $version @param array<string, mixed> $config */
    private function estimatePageCount(array $version, array $config, int $bookId): int
    {
        return count($this->previewPages($version, $config, $bookId));
    }

    /** @param array<string, mixed> $version @param array<string, mixed> $config @return array<int, array<string, mixed>> */
    private function previewPages(array $version, array $config, int $bookId): array
    {
        $snapshot = is_array($version['snapshot'] ?? null) ? $version['snapshot'] : []; $editorial = $this->editorialFields($bookId, (string) ($version['id'] ?? ''));
        $pages = []; $number = 1; $identity = is_array($editorial['identity'] ?? null) ? $editorial['identity'] : [];
        $pages[] = ['number' => $number++, 'kind' => 'title', 'title' => (string) ($identity['titleFinal'] ?? get_the_title($bookId)), 'content' => (string) ($identity['subtitleFinal'] ?? '')];
        $order = is_array($editorial['elementOrder'] ?? null) ? $editorial['elementOrder'] : [];
        $front = is_array($snapshot['frontMatter'] ?? null) ? $snapshot['frontMatter'] : [];
        $frontMap = ['preface' => 'Prefácio', 'presentation' => 'Apresentação', 'author_note' => 'Nota do Autor', 'introduction' => 'Introdução'];
        foreach ($order as $key) {
            $key = (string) $key; if (! isset($frontMap[$key])) continue;
            $sourceKey = $key === 'author_note' ? 'authorNote' : $key; $text = trim(wp_strip_all_tags((string) ($front[$sourceKey] ?? ''))); if ($text === '') continue;
            foreach ($this->textChunks($text, 360) as $index => $chunk) $pages[] = ['number' => $number++, 'kind' => 'front', 'title' => $index === 0 ? $frontMap[$key] : '', 'content' => $chunk];
        }
        $pages[] = ['number' => $number++, 'kind' => 'toc', 'title' => 'Sumário', 'content' => implode("\n", array_map(static fn (array $chapter): string => 'Capítulo ' . (int) ($chapter['number'] ?? 0) . ' — ' . (string) ($chapter['title'] ?? ''), array_filter((array) ($snapshot['chapters'] ?? []), 'is_array')))];
        $bodySize = max(8, (int) ($config['typography']['bodySizePt'] ?? 11)); $wordsPerPage = max(180, (int) round(390 * (11 / $bodySize)));
        foreach ((array) ($snapshot['chapters'] ?? []) as $chapter) {
            if (! is_array($chapter)) continue; $plain = trim(wp_strip_all_tags((string) ($chapter['content'] ?? ''))); $chunks = $this->textChunks($plain, $wordsPerPage);
            if ($chunks === []) $chunks = [''];
            foreach ($chunks as $index => $chunk) $pages[] = ['number' => $number++, 'kind' => 'chapter', 'chapterId' => (string) ($chapter['id'] ?? ''), 'chapterNumber' => (int) ($chapter['number'] ?? 0), 'title' => $index === 0 ? (string) ($chapter['title'] ?? '') : '', 'content' => $chunk];
        }
        $conclusion = trim(wp_strip_all_tags((string) ($front['conclusion'] ?? '')));
        if ($conclusion !== '') foreach ($this->textChunks($conclusion, $wordsPerPage) as $index => $chunk) $pages[] = ['number' => $number++, 'kind' => 'back', 'title' => $index === 0 ? 'Conclusão' : '', 'content' => $chunk];
        return $pages;
    }

    /** @return array<int, string> */
    private function textChunks(string $text, int $wordsPerPage): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: []; $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
        if ($words === []) return []; $chunks = [];
        for ($i = 0; $i < count($words); $i += $wordsPerPage) $chunks[] = implode(' ', array_slice($words, $i, $wordsPerPage));
        return $chunks;
    }

    /** @param array<int, array<string, mixed>> $pages @return array{path:string,url:string} */
    private function writeProofPdf(int $bookId, array $pages, int $proofNumber): array
    {
        $uploads = wp_upload_dir(); if (! is_array($uploads) || ! empty($uploads['error'])) throw new ValidationError('Não foi possível acessar a pasta de uploads para gerar a prova.');
        $dir = rtrim((string) $uploads['basedir'], '/\\') . '/verbum-proofs'; if (! is_dir($dir) && ! wp_mkdir_p($dir)) throw new ValidationError('Não foi possível criar a pasta das provas de Diagramação.');
        $fileName = 'verbum-' . $bookId . '-prova-' . str_pad((string) $proofNumber, 2, '0', STR_PAD_LEFT) . '-' . gmdate('Ymd-His') . '.pdf'; $path = $dir . '/' . $fileName;
        $objects = []; $pageRefs = []; $fontObj = 3; $obj = 4;
        foreach ($pages as $page) {
            $pageObj = $obj++; $contentObj = $obj++; $pageRefs[] = $pageObj . ' 0 R';
            $lines = ['PROVA - NAO PUBLICAR', (string) ($page['title'] ?? '')];
            $text = (string) ($page['content'] ?? ''); foreach (explode("\n", wordwrap($text, 86, "\n", true)) as $line) { if (count($lines) >= 48) break; $lines[] = $line; }
            $stream = "BT /F1 10 Tf 48 790 Td 14 TL "; foreach ($lines as $line) $stream .= '(' . $this->pdfEscape($line) . ") Tj T* "; $stream .= 'ET';
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontObj . ' 0 R >> >> /Contents ' . $contentObj . ' 0 R >>';
            $objects[$contentObj] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream";
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>'; $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pageRefs) . ' >>'; $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; ksort($objects);
        $pdf = "%PDF-1.4\n"; $offsets = [0 => 0]; foreach ($objects as $id => $body) { $offsets[$id] = strlen($pdf); $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n"; }
        $xref = strlen($pdf); $max = max(array_keys($objects)); $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        if (file_put_contents($path, $pdf) === false) throw new ValidationError('Não foi possível gravar o PDF de prova.');
        return ['path' => $path, 'url' => rtrim((string) $uploads['baseurl'], '/') . '/verbum-proofs/' . rawurlencode($fileName)];
    }

    private function pdfEscape(string $text): string
    {
        $latin = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) : $text; if (! is_string($latin)) $latin = $text;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $latin);
    }

    /** @return array<int, array<string, mixed>> */
    private function rounds(int $bookId): array { $value = get_post_meta($bookId, '_verbum_layout_rounds', true); return is_array($value) ? array_values(array_filter($value, 'is_array')) : []; }
    /** @param array<int, array<string, mixed>> $rounds */ private function storeRounds(int $bookId, array $rounds): void { update_post_meta($bookId, '_verbum_layout_rounds', array_values($rounds)); }
    /** @param array<int, array<string, mixed>> $rounds @return array<string, mixed>|null */ private function currentRound(array $rounds, string $versionId) { foreach (array_reverse($rounds) as $round) if (is_array($round) && (string) ($round['versionId'] ?? '') === $versionId) return $round; return null; }
    /** @param array<string, mixed> $round @return array<string, mixed> */ private function roundSummary(array $round): array { return ['id' => (string) ($round['id'] ?? ''), 'number' => (int) ($round['number'] ?? 0), 'versionId' => (string) ($round['versionId'] ?? ''), 'versionNumber' => (string) ($round['versionNumber'] ?? ''), 'status' => (string) ($round['status'] ?? 'in_progress'), 'startedAt' => (string) ($round['startedAt'] ?? ''), 'completedAt' => (string) ($round['completedAt'] ?? '')]; }
    /** @param array<string, mixed> $version @return array<string, mixed> */ private function versionSummary(array $version): array { return ['id' => (string) ($version['id'] ?? ''), 'number' => (string) ($version['number'] ?? ''), 'name' => (string) ($version['name'] ?? ''), 'hash' => (string) ($version['hash'] ?? ''), 'chapterCount' => (int) ($version['chapterCount'] ?? 0), 'wordCount' => (int) ($version['wordCount'] ?? 0), 'createdAt' => (string) ($version['createdAt'] ?? '')]; }
    /** @param array<string, bool> $flags @return array<string, bool> */ private function normalizeFlags(array $flags): array { $clean = []; foreach (array_keys(self::MANUAL_FLAGS) as $key) $clean[$key] = (bool) ($flags[$key] ?? false); return $clean; }
    /** @param array<mixed> $value @return array<mixed> */ private function sanitizeArray(array $value): array { $clean = []; foreach ($value as $key => $item) { $target = is_int($key) ? $key : preg_replace('/[^A-Za-z0-9_-]/', '', (string) $key); if (! is_int($target) && (! is_string($target) || $target === '')) continue; if (is_array($item)) $clean[$target] = $this->sanitizeArray($item); elseif (is_bool($item)) $clean[$target] = $item; elseif (is_int($item) || is_float($item)) $clean[$target] = $item; else $clean[$target] = sanitize_textarea_field((string) $item); } return $clean; }
    /** @param array<string, string> $items @return array<int, array<string, string>> */ private function options(array $items): array { $result = []; foreach ($items as $key => $label) $result[] = ['key' => $key, 'label' => $label]; return $result; }
    /** @param array<string, mixed> $round */ private function assertMutable(array $round): void { if ((string) ($round['status'] ?? '') === 'approved') throw new ValidationError('A Diagramação aprovada é imutável.'); }
}
