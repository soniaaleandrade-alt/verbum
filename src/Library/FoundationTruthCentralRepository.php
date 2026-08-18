<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class FoundationTruthCentralRepository
{
    private const THESIS_META = '_verbum_foundation_thesis_html';
    private const PHRASE_META = '_verbum_foundation_synthesis_phrase';
    private const REVISION_META = '_verbum_foundation_truth_central_revision';
    private const UPDATED_META = '_verbum_foundation_truth_central_updated_at';
    private const COMPLETED_AT_META = '_verbum_foundation_truth_central_completed_at';
    private const HISTORY_META = '_verbum_foundation_truth_central_history';
    private const SUBSTEPS_META = '_verbum_foundation_substeps';

    /** @return array<string, mixed> */
    public function data(int $bookId): array
    {
        $thesis = (string) get_post_meta($bookId, self::THESIS_META, true);
        $legacyThesis = trim((string) get_post_meta($bookId, '_verbum_planning_main_thesis', true));
        if (trim(wp_strip_all_tags($thesis)) === '' && $legacyThesis !== '') $thesis = wpautop(esc_html($legacyThesis));
        $phrase = (string) get_post_meta($bookId, self::PHRASE_META, true);
        $substeps = get_post_meta($bookId, self::SUBSTEPS_META, true);
        $substeps = is_array($substeps) ? $substeps : [];
        $completedAt = (string) get_post_meta($bookId, self::COMPLETED_AT_META, true);
        $updatedAt = (string) get_post_meta($bookId, self::UPDATED_META, true);
        $checks = $this->checks($bookId, $thesis, $phrase);

        return [
            'substep' => 'truth-central', 'order' => 4, 'total' => 4,
            'thesisHtml' => $thesis, 'synthesisPhrase' => $phrase,
            'revision' => max(0, (int) get_post_meta($bookId, self::REVISION_META, true)),
            'updatedAt' => $updatedAt, 'completedAt' => $completedAt,
            'completed' => in_array('truth-central', $substeps, true),
            'completedSubsteps' => array_values($substeps),
            'updatedAfterCompletion' => $completedAt !== '' && $updatedAt !== '' && strtotime($updatedAt) > strtotime($completedAt),
            'readerResultCompleted' => in_array('reader-result', $substeps, true),
            'ready' => $checks['all'] && $this->plain($thesis) !== '' && $this->phraseValid($phrase),
            'checks' => $checks,
            'legacy' => [
                'mainThesis' => $legacyThesis,
                'centralMessage' => trim((string) get_post_meta($bookId, '_verbum_work_project_central_message', true)),
                'centralQuestion' => trim((string) get_post_meta($bookId, '_verbum_planning_central_question', true)),
                'guidingPhrase' => trim((string) get_post_meta($bookId, '_verbum_work_project_guiding_phrase', true)),
                'overview' => trim((string) get_post_meta($bookId, '_verbum_planning_overview', true)),
            ],
        ];
    }

    /** @param array<string, mixed> $fields @return array<string, mixed> */
    public function save(int $bookId, array $fields): array
    {
        $revision = max(0, (int) get_post_meta($bookId, self::REVISION_META, true));
        $base = array_key_exists('base_revision', $fields) ? max(0, (int) $fields['base_revision']) : $revision;
        if ($base !== $revision) throw new ValidationError('Este rascunho foi atualizado em outra sessão. Recarregue a página antes de salvar novamente.');
        $oldThesis = (string) get_post_meta($bookId, self::THESIS_META, true);
        $oldPhrase = (string) get_post_meta($bookId, self::PHRASE_META, true);
        $thesis = array_key_exists('thesis_html', $fields) ? $this->sanitizeThesis((string) $fields['thesis_html']) : $oldThesis;
        $phrase = array_key_exists('synthesis_phrase', $fields) ? trim(sanitize_textarea_field((string) $fields['synthesis_phrase'])) : $oldPhrase;
        if ($this->length($phrase) > 180 && $oldPhrase !== $phrase) throw new ValidationError('A frase que resume a obra deve ter no máximo 180 caracteres.');
        if ($thesis !== $oldThesis || $phrase !== $oldPhrase) $this->appendHistory($bookId, $revision, $oldThesis, $oldPhrase);
        update_post_meta($bookId, self::THESIS_META, $thesis);
        update_post_meta($bookId, self::PHRASE_META, $phrase);
        update_post_meta($bookId, '_verbum_planning_main_thesis', $this->plain($thesis));
        update_post_meta($bookId, self::REVISION_META, $revision + 1);
        update_post_meta($bookId, self::UPDATED_META, gmdate('c'));
        $this->touchBook($bookId);
        return $this->data($bookId);
    }

    /** @return array<string, mixed> */
    public function complete(int $bookId): array
    {
        $data = $this->data($bookId); $pending = $this->pending($bookId, (string) $data['thesisHtml'], (string) $data['synthesisPhrase']);
        if ($pending !== []) throw new ValidationError('Complete a Fundação da Obra antes de continuar: ' . implode('; ', $pending) . '.');
        $substeps = get_post_meta($bookId, self::SUBSTEPS_META, true); $substeps = is_array($substeps) ? $substeps : [];
        if (! in_array('truth-central', $substeps, true)) $substeps[] = 'truth-central';
        update_post_meta($bookId, self::SUBSTEPS_META, array_values(array_unique($substeps)));
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true); $completed = is_array($completed) ? $completed : [];
        if (! in_array('identification', $completed, true)) throw new ValidationError('Conclua a Identificação da Obra antes da Fundação.');
        if (! in_array('project', $completed, true)) $completed[] = 'project';
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        $current = (string) (get_post_meta($bookId, '_verbum_stage', true) ?: 'project');
        if (in_array($current, ['identification', 'project'], true)) update_post_meta($bookId, '_verbum_stage', 'planning');
        if ((string) get_post_meta($bookId, self::COMPLETED_AT_META, true) === '') update_post_meta($bookId, self::COMPLETED_AT_META, gmdate('c'));
        if ((string) get_post_meta($bookId, '_verbum_work_project_completed_at', true) === '') update_post_meta($bookId, '_verbum_work_project_completed_at', gmdate('c'));
        $this->touchBook($bookId);
        return $this->data($bookId);
    }

    /** @return array<string, bool> */
    private function checks(int $id, string $thesis, string $phrase): array
    {
        $letter = trim(wp_strip_all_tags((string) get_post_meta($id, '_verbum_foundation_letter_html', true))) !== '';
        $soul = trim((string) get_post_meta($id, '_verbum_foundation_soul', true)) !== '';
        $problem = trim((string) get_post_meta($id, '_verbum_reader_problem', true)) !== '';
        $purpose = trim((string) get_post_meta($id, '_verbum_work_project_purpose', true)) !== '';
        $general = trim((string) get_post_meta($id, '_verbum_work_project_general_objective', true)) !== '';
        $objectives = get_post_meta($id, '_verbum_work_project_specific_objectives', true); $objectives = is_array($objectives) ? $objectives : [];
        $objective = count(array_filter($objectives, static fn ($x): bool => is_array($x) && trim((string) ($x['text'] ?? '')) !== '')) > 0;
        $audience = trim((string) get_post_meta($id, '_verbum_audience', true)) !== '';
        $needs = trim((string) get_post_meta($id, '_verbum_foundation_reader_needs', true)) !== '';
        $transformation = trim((string) get_post_meta($id, '_verbum_work_project_transformation', true)) !== '';
        $differential = trim((string) get_post_meta($id, '_verbum_work_project_differentials', true)) !== '';
        $r = ['originNeed' => $letter && $soul && $problem, 'purposeObjectives' => $purpose && $general && $objective, 'readerTransformation' => $audience && $needs && $transformation && $differential, 'truth' => $this->plain($thesis) !== '' && $this->phraseValid($phrase)];
        $r['all'] = ! in_array(false, $r, true); return $r;
    }

    /** @return string[] */
    private function pending(int $id, string $thesis, string $phrase): array
    {
        $p=[]; $required=[
            ['Fundação 1','Carta aos Leitores','_verbum_foundation_letter_html',true],['Fundação 1','Alma da Obra','_verbum_foundation_soul',false],
            ['Fundação 2','Problema ou necessidade','_verbum_reader_problem',false],['Fundação 2','Propósito','_verbum_work_project_purpose',false],['Fundação 2','Objetivo geral','_verbum_work_project_general_objective',false],
            ['Fundação 3','Público principal','_verbum_audience',false],['Fundação 3','Necessidades do leitor','_verbum_foundation_reader_needs',false],['Fundação 3','Transformação esperada','_verbum_work_project_transformation',false],['Fundação 3','Diferencial da obra','_verbum_work_project_differentials',false],
        ];
        foreach($required as $x){$v=(string)get_post_meta($id,$x[2],true);if(trim($x[3]?wp_strip_all_tags($v):$v)==='')$p[]=$x[0].' — '.$x[1];}
        $objectives=get_post_meta($id,'_verbum_work_project_specific_objectives',true);$objectives=is_array($objectives)?$objectives:[];
        if(!array_filter($objectives,static fn($x):bool=>is_array($x)&&trim((string)($x['text']??''))!==''))$p[]='Fundação 2 — pelo menos um Objetivo específico';
        if($this->plain($thesis)==='')$p[]='Fundação 4 — Tese principal';
        if(!$this->phraseValid($phrase))$p[]='Fundação 4 — Frase que resume a obra (até 180 caracteres)';
        return $p;
    }

    private function sanitizeThesis(string $html): string { return wp_kses($html, ['p'=>[],'br'=>[],'strong'=>[],'b'=>[],'em'=>[],'i'=>[],'u'=>[],'ul'=>[],'ol'=>[],'li'=>[],'a'=>['href'=>true,'title'=>true,'target'=>true,'rel'=>true]]); }
    private function plain(string $html): string { return trim(preg_replace('/\s+/u',' ',wp_strip_all_tags($html)) ?? ''); }
    private function length(string $v): int { return function_exists('mb_strlen') ? mb_strlen($v) : strlen($v); }
    private function phraseValid(string $v): bool { return trim($v) !== '' && $this->length(trim($v)) <= 180; }
    private function appendHistory(int $id,int $revision,string $thesis,string $phrase):void{$h=get_post_meta($id,self::HISTORY_META,true);$h=is_array($h)?$h:[];$h[]=['revision'=>$revision,'thesisHtml'=>$thesis,'synthesisPhrase'=>$phrase,'savedAt'=>gmdate('c'),'userId'=>get_current_user_id()];update_post_meta($id,self::HISTORY_META,array_slice($h,-25));}
    private function touchBook(int $id):void{$post=get_post($id);if($post instanceof \WP_Post)wp_update_post(['ID'=>$id,'post_content'=>$post->post_content]);}
}
