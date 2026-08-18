<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class StructureDirectionRepository
{
    private const PREFIX = '_verbum_structure_direction_';
    private const SUBSTEPS_META = '_verbum_structure_substeps';

    /** @return array<string,mixed> */
    public function data(int $bookId): array
    {
        $completedStages=get_post_meta($bookId,'_verbum_completed_stages',true);$completedStages=is_array($completedStages)?$completedStages:[];
        $substeps=get_post_meta($bookId,self::SUBSTEPS_META,true);$substeps=is_array($substeps)?$substeps:[];
        return [
            'substep'=>'direction','order'=>1,'total'=>4,
            'axis'=>trim((string)get_post_meta($bookId,self::PREFIX.'axis',true)),
            'thread'=>trim((string)get_post_meta($bookId,self::PREFIX.'thread',true)),
            'startingPoint'=>trim((string)get_post_meta($bookId,self::PREFIX.'starting_point',true)),
            'arrivalPoint'=>trim((string)get_post_meta($bookId,self::PREFIX.'arrival_point',true)),
            'theologicalOrder'=>$this->items($bookId,self::PREFIX.'theological_order'),
            'movement'=>$this->items($bookId,self::PREFIX.'movement'),
            'revision'=>max(0,(int)get_post_meta($bookId,self::PREFIX.'revision',true)),
            'updatedAt'=>(string)get_post_meta($bookId,self::PREFIX.'updated_at',true),
            'completedAt'=>(string)get_post_meta($bookId,self::PREFIX.'completed_at',true),
            'completed'=>in_array('direction',$substeps,true),'completedSubsteps'=>array_values($substeps),
            'foundation'=>[
                'completed'=>in_array('project',$completedStages,true),
                'thesis'=>trim(wp_strip_all_tags((string)get_post_meta($bookId,'_verbum_foundation_thesis_html',true))) ?: trim((string)get_post_meta($bookId,'_verbum_planning_main_thesis',true)),
                'synthesisPhrase'=>trim((string)get_post_meta($bookId,'_verbum_foundation_synthesis_phrase',true)),
            ],
            'legacy'=>[
                'generalStructure'=>trim((string)get_post_meta($bookId,'_verbum_planning_general_structure',true)),
                'overview'=>trim((string)get_post_meta($bookId,'_verbum_planning_overview',true)),
                'writingStrategy'=>trim((string)get_post_meta($bookId,'_verbum_planning_writing_strategy',true)),
            ],
        ];
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function save(int $bookId,array $fields): array
    {
        $revision=max(0,(int)get_post_meta($bookId,self::PREFIX.'revision',true));$base=array_key_exists('base_revision',$fields)?max(0,(int)$fields['base_revision']):$revision;
        if($base!==$revision)throw new ValidationError('Este rascunho foi atualizado em outra sessão. Recarregue a página antes de salvar novamente.');
        $before=$this->snapshot($bookId);
        foreach(['axis','thread','starting_point','arrival_point'] as $field)if(array_key_exists($field,$fields))update_post_meta($bookId,self::PREFIX.$field,sanitize_textarea_field((string)$fields[$field]));
        foreach(['theological_order','movement'] as $field)if(array_key_exists($field,$fields))update_post_meta($bookId,self::PREFIX.$field,$this->normalizeItems(is_array($fields[$field])?$fields[$field]:[]));
        $after=$this->snapshot($bookId);if($before!==$after)$this->appendHistory($bookId,$revision,$before);
        update_post_meta($bookId,self::PREFIX.'revision',$revision+1);update_post_meta($bookId,self::PREFIX.'updated_at',gmdate('c'));$this->touch($bookId);return $this->data($bookId);
    }

    /** @return array<string,mixed> */
    public function complete(int $bookId):array
    {
        $d=$this->data($bookId);$pending=[];
        if(!(bool)$d['foundation']['completed'])$pending[]='Fundação da Obra concluída';
        if($d['axis']==='')$pending[]='Eixo da obra';if($d['thread']==='')$pending[]='Fio condutor';
        if(count($d['theologicalOrder'])<2)$pending[]='pelo menos duas etapas na Ordem teológica';
        if($d['startingPoint']==='')$pending[]='Ponto de partida';if($d['arrivalPoint']==='')$pending[]='Ponto de chegada';
        if(count($d['movement'])<2)$pending[]='pelo menos duas etapas no Movimento da obra';
        if($pending!==[])throw new ValidationError('Complete Estrutura 1 — Direção antes de avançar: '.implode(', ',$pending).'.');
        $sub=get_post_meta($bookId,self::SUBSTEPS_META,true);$sub=is_array($sub)?$sub:[];if(!in_array('direction',$sub,true))$sub[]='direction';
        update_post_meta($bookId,self::SUBSTEPS_META,array_values(array_unique($sub)));if((string)get_post_meta($bookId,self::PREFIX.'completed_at',true)==='')update_post_meta($bookId,self::PREFIX.'completed_at',gmdate('c'));$this->touch($bookId);return $this->data($bookId);
    }

    /** @param mixed $value @return array<int,array<string,mixed>> */
    private function normalizeItems($value):array{$out=[];$seen=[];foreach(is_array($value)?$value:[] as $i=>$x){if(!is_array($x))continue;$text=trim(sanitize_text_field((string)($x['text']??'')));if($text==='')continue;$id=sanitize_key((string)($x['id']??''));if($id===''||strpos($id,'new-')===0)$id='step-'.substr(md5($text.'|'.$i.'|'.microtime(true)),0,12);if(isset($seen[$id]))$id.='-'.($i+1);$seen[$id]=true;$out[]=['id'=>$id,'text'=>$text,'order'=>count($out)+1];}return$out;}
    /** @return array<int,array<string,mixed>> */
    private function items(int $id,string $meta):array{return$this->normalizeItems(get_post_meta($id,$meta,true));}
    /** @return array<string,mixed> */
    private function snapshot(int $id):array{return['axis'=>(string)get_post_meta($id,self::PREFIX.'axis',true),'thread'=>(string)get_post_meta($id,self::PREFIX.'thread',true),'startingPoint'=>(string)get_post_meta($id,self::PREFIX.'starting_point',true),'arrivalPoint'=>(string)get_post_meta($id,self::PREFIX.'arrival_point',true),'theologicalOrder'=>get_post_meta($id,self::PREFIX.'theological_order',true),'movement'=>get_post_meta($id,self::PREFIX.'movement',true)];}
    /** @param array<string,mixed> $snapshot */
    private function appendHistory(int $id,int $revision,array $snapshot):void{$h=get_post_meta($id,self::PREFIX.'history',true);$h=is_array($h)?$h:[];$h[]=['revision'=>$revision,'values'=>$snapshot,'savedAt'=>gmdate('c'),'userId'=>get_current_user_id()];update_post_meta($id,self::PREFIX.'history',array_slice($h,-25));}
    private function touch(int $id):void{$p=get_post($id);if($p instanceof \WP_Post)wp_update_post(['ID'=>$id,'post_content'=>$p->post_content]);}
}
