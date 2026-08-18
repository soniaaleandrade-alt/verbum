<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class FoundationLetterSoulRepository
{
    private const LETTER_META = '_verbum_foundation_letter_html';
    private const SOUL_META = '_verbum_foundation_soul';
    private const REVISION_META = '_verbum_foundation_letter_soul_revision';
    private const UPDATED_META = '_verbum_foundation_letter_soul_updated_at';
    private const HISTORY_META = '_verbum_foundation_letter_soul_history';
    private const SUBSTEPS_META = '_verbum_foundation_substeps';
    private const COMPLETED_AT_META = '_verbum_foundation_letter_soul_completed_at';

    /** @return array<string, mixed> */
    public function data(int $bookId): array
    {
        $letter = (string) get_post_meta($bookId, self::LETTER_META, true);
        $soul = (string) get_post_meta($bookId, self::SOUL_META, true);
        $completed = get_post_meta($bookId, self::SUBSTEPS_META, true);
        $completed = is_array($completed) ? $completed : [];

        return [
            'substep' => 'letter-soul',
            'order' => 1,
            'total' => 4,
            'letterHtml' => $letter,
            'soul' => $soul,
            'revision' => max(0, (int) get_post_meta($bookId, self::REVISION_META, true)),
            'updatedAt' => (string) get_post_meta($bookId, self::UPDATED_META, true),
            'completedAt' => (string) get_post_meta($bookId, self::COMPLETED_AT_META, true),
            'completed' => in_array('letter-soul', $completed, true),
            'ready' => trim(wp_strip_all_tags($letter)) !== '' && trim($soul) !== '',
            'legacy' => [
                'motivation' => trim((string) get_post_meta($bookId, '_verbum_work_project_motivation', true)),
                'centralMessage' => trim((string) get_post_meta($bookId, '_verbum_work_project_central_message', true)),
                'purpose' => trim((string) get_post_meta($bookId, '_verbum_work_project_purpose', true)),
                'theme' => trim((string) get_post_meta($bookId, '_verbum_work_project_theme', true)),
            ],
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function save(int $bookId, array $fields): array
    {
        $currentRevision = max(0, (int) get_post_meta($bookId, self::REVISION_META, true));
        $baseRevision = array_key_exists('base_revision', $fields) ? max(0, (int) $fields['base_revision']) : $currentRevision;
        if ($baseRevision !== $currentRevision) {
            throw new ValidationError('Este rascunho foi atualizado em outra sessão. Recarregue a página antes de salvar novamente.');
        }

        $currentLetter = (string) get_post_meta($bookId, self::LETTER_META, true);
        $currentSoul = (string) get_post_meta($bookId, self::SOUL_META, true);
        $nextLetter = array_key_exists('letter_html', $fields) ? $this->sanitizeLetter((string) $fields['letter_html']) : $currentLetter;
        $nextSoul = array_key_exists('soul', $fields) ? sanitize_textarea_field((string) $fields['soul']) : $currentSoul;

        if ($nextLetter !== $currentLetter || $nextSoul !== $currentSoul) {
            $this->appendHistory($bookId, $currentRevision, $currentLetter, $currentSoul);
        }

        update_post_meta($bookId, self::LETTER_META, $nextLetter);
        update_post_meta($bookId, self::SOUL_META, $nextSoul);
        update_post_meta($bookId, self::REVISION_META, $currentRevision + 1);
        update_post_meta($bookId, self::UPDATED_META, gmdate('c'));
        $this->touchBook($bookId);

        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function complete(int $bookId): array
    {
        $data = $this->data($bookId);
        $pending = [];
        if (trim(wp_strip_all_tags((string) $data['letterHtml'])) === '') {
            $pending[] = 'Carta aos Leitores';
        }
        if (trim((string) $data['soul']) === '') {
            $pending[] = 'Alma da Obra';
        }
        if ($pending !== []) {
            throw new ValidationError('Complete Fundação 1 — Carta e Alma antes de avançar: ' . implode(', ', $pending) . '.');
        }

        $completed = get_post_meta($bookId, self::SUBSTEPS_META, true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('letter-soul', $completed, true)) {
            $completed[] = 'letter-soul';
        }
        update_post_meta($bookId, self::SUBSTEPS_META, array_values(array_unique($completed)));
        update_post_meta($bookId, self::COMPLETED_AT_META, gmdate('c'));
        update_post_meta($bookId, self::UPDATED_META, gmdate('c'));
        $this->touchBook($bookId);

        return $this->data($bookId);
    }

    private function sanitizeLetter(string $html): string
    {
        $allowed = [
            'p' => ['style' => true],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'div' => ['style' => true],
            'span' => ['style' => true],
            'a' => ['href' => true, 'title' => true, 'target' => true, 'rel' => true],
        ];
        return trim(wp_kses($html, $allowed));
    }

    private function appendHistory(int $bookId, int $revision, string $letter, string $soul): void
    {
        if ($letter === '' && $soul === '') {
            return;
        }
        $history = get_post_meta($bookId, self::HISTORY_META, true);
        $history = is_array($history) ? $history : [];
        $history[] = [
            'revision' => $revision,
            'letterHtml' => $letter,
            'soul' => $soul,
            'savedAt' => gmdate('c'),
        ];
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        update_post_meta($bookId, self::HISTORY_META, $history);
    }

    private function touchBook(int $bookId): void
    {
        $post = get_post($bookId);
        if ($post instanceof \WP_Post) {
            wp_update_post(['ID' => $bookId, 'post_content' => $post->post_content]);
        }
    }
}
