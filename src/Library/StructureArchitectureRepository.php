<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\ValidationError;

final class StructureArchitectureRepository
{
    private const PREFIX='_verbum_structure_architecture_';
    private const SUBSTEPS='_verbum_structure_substeps';

    /** @return array<string,mixed> */
    public function data(int $bookId):array
    {
        $sub=get_post_meta($bookId,self::SUBSTEPS,true);$sub=is_array($sub)?$sub:[];
        $parts=get_post_meta($bookId,self::PREFIX.'parts',true);$parts=is_array($parts)?$this->normalize($parts):[];
        $legacy=$this->legacy($bookId);
        return ['substep'=>'architecture','order'=>2,'total'=>4,'parts'=>$parts,'selectedPartId'=>(string)get_post_meta($bookId,self::PREFIX.'selected_part_id',true),'revision'=>max(0,(int)get_post_meta($bookId,self::PREFIX.'revision',true)),'updatedAt'=>(string)get_post_meta($bookId,self::PREFIX.'updated_at',true),'completed'=>in_array('architecture',$sub,true),'completedSubsteps'=>array_values($sub),'direction'=>$this->direction($bookId),'legacy'=>$legacy];
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function save(int $bookId,array $fields):array
    {
        $revision=max(0,(int)get_post_meta($bookId,self::PREFIX.'revision',true));$base=array_key_exists('base_revision',$fields)?max(0,(int)$fields['base_revision']):$revision;
        if($base!==$revision)throw new ValidationError('Esta Arquitetura foi atualizada em outra sessão. Recarregue a página antes de salvar novamente.');
        $before=$this->snapshot($bookId);$parts=$this->normalize((array)($fields['parts']??[]));$this->assertMovements($bookId,$parts);
        update_post_meta($bookId,self::PREFIX.'parts',$parts);update_post_meta($bookId,self::PREFIX.'selected_part_id',sanitize_key((string)($fields['selected_part_id']??'')));
        if($before!==['parts'=>$parts,'selectedPartId'=>sanitize_key((string)($fields['selected_part_id']??''))])$this->history($bookId,$revision,$before,'save');
        update_post_meta($bookId,self::PREFIX.'revision',$revision+1);update_post_meta($bookId,self::PREFIX.'updated_at',gmdate('c'));$this->touch($bookId);return $this->data($bookId);
    }

    /** @return array<string,mixed> */
    public function complete(int $bookId):array
    {
        $d=$this->data($bookId);if(!(bool)$d['direction']['completed'])throw new ValidationError('Conclua Estrutura 1 — Direção antes de avançar.');
        $parts=(array)$d['parts'];if($parts===[])throw new ValidationError('Adicione pelo menos uma parte válida.');$pending=[];
        foreach($parts as $i=>$p){$miss=[];foreach(['title'=>'título','function'=>'função','theme'=>'tema central','expectedResult'=>'resultado esperado','movementId'=>'movimento relacionado']as$k=>$label)if(trim((string)($p[$k]??''))==='')$miss[]=$label;if($miss!==[])$pending[]='Parte '.($i+1).': '.implode(', ',$miss);}
        if($pending!==[])throw new ValidationError('Complete as partes pendentes: '.implode('; ',$pending).'.');
        $sub=(array)$d['completedSubsteps'];if(!in_array('architecture',$sub,true))$sub[]='architecture';update_post_meta($bookId,self::SUBSTEPS,array_values(array_unique($sub)));update_post_meta($bookId,self::PREFIX.'completed_at',gmdate('c'));$this->touch($bookId);return$this->data($bookId);
    }

    /** @param array<int,mixed> $items @return array<int,array<string,mixed>> */
    private function normalize(array $items):array{$out=[];$seen=[];foreach($items as$i=>$x){if(!is_array($x))continue;$id=sanitize_key((string)($x['id']??''));if($id===''||strpos($id,'new-')===0)$id='part-'.substr(md5(wp_json_encode($x).'|'.$i.'|'.microtime(true)),0,12);if(isset($seen[$id]))$id.='-'.($i+1);$seen[$id]=1;$origin=sanitize_key((string)($x['origin']??'manual'));if(!in_array($origin,['imported','ai','manual'],true))$origin='manual';$state=sanitize_key((string)($x['state']??'analysis'));if(!in_array($state,['approved','analysis','changed'],true))$state='analysis';$out[]=['id'=>$id,'legacyId'=>sanitize_key((string)($x['legacyId']??'')),'title'=>trim(sanitize_text_field((string)($x['title']??''))),'function'=>sanitize_textarea_field((string)($x['function']??'')),'theme'=>sanitize_textarea_field((string)($x['theme']??'')),'expectedResult'=>sanitize_textarea_field((string)($x['expectedResult']??'')),'entryTransition'=>sanitize_textarea_field((string)($x['entryTransition']??'')),'nextTransition'=>sanitize_textarea_field((string)($x['nextTransition']??'')),'movementId'=>sanitize_key((string)($x['movementId']??'')),'estimatedChapters'=>max(0,(int)($x['estimatedChapters']??0)),'linkedChapterCount'=>max(0,(int)($x['linkedChapterCount']??0)),'linkedChapterHasContent'=>(bool)($x['linkedChapterHasContent']??false),'origin'=>$origin,'state'=>$state,'order'=>count($out)+1];}return$out;}
    /** @param array<int,array<string,mixed>> $parts */ private function assertMovements(int$id,array$parts):void{$valid=[];foreach((array)get_post_meta($id,'_verbum_structure_direction_movement',true)as$x)if(is_array($x))$valid[]=(string)($x['id']??'');foreach($parts as$p){$m=(string)$p['movementId'];if($m!==''&&!in_array($m,$valid,true))throw new ValidationError('Uma parte está vinculada a um movimento que não existe mais na Direção.');}}
    /** @return array<string,mixed> */ private function direction(int$id):array{$sub=get_post_meta($id,self::SUBSTEPS,true);$sub=is_array($sub)?$sub:[];$movement=get_post_meta($id,'_verbum_structure_direction_movement',true);return['completed'=>in_array('direction',$sub,true),'axis'=>(string)get_post_meta($id,'_verbum_structure_direction_axis',true),'thread'=>(string)get_post_meta($id,'_verbum_structure_direction_thread',true),'theologicalOrder'=>(array)get_post_meta($id,'_verbum_structure_direction_theological_order',true),'startingPoint'=>(string)get_post_meta($id,'_verbum_structure_direction_starting_point',true),'arrivalPoint'=>(string)get_post_meta($id,'_verbum_structure_direction_arrival_point',true),'movement'=>is_array($movement)?$movement:[]];}
    /** @return array<string,mixed> */ private function legacy(int$id):array{$items=get_post_meta($id,'_verbum_planning_structure_items',true);$items=is_array($items)?$items:[];$parts=[];foreach($items as$p)if(is_array($p)&&($p['type']??'')==='part'){$pid=(string)($p['id']??'');$count=0;$has=false;foreach($items as$c)if(is_array($c)&&($c['type']??'')==='chapter'&&($c['parentId']??'')===$pid){$count++;$has=$has||!empty($c['linkedChapterHasContent']);}$parts[]=['id'=>$pid,'title'=>(string)($p['title']??''),'description'=>(string)($p['description']??''),'order'=>(int)($p['order']??count($parts)+1),'linkedChapterCount'=>$count,'linkedChapterHasContent'=>$has];}return['generalStructure'=>(string)get_post_meta($id,'_verbum_planning_general_structure',true),'editorialNotes'=>(string)get_post_meta($id,'_verbum_planning_editorial_notes',true),'parts'=>$parts,'unmigrated'=>(array)get_post_meta($id,self::PREFIX.'unmigrated',true)];}
    /** @return array<string,mixed> */ private function snapshot(int$id):array{return['parts'=>(array)get_post_meta($id,self::PREFIX.'parts',true),'selectedPartId'=>(string)get_post_meta($id,self::PREFIX.'selected_part_id',true)];}
    /** @param array<string,mixed> $before */ private function history(int$id,int$rev,array$before,string$action):void{$h=get_post_meta($id,self::PREFIX.'history',true);$h=is_array($h)?$h:[];$h[]=['revision'=>$rev,'action'=>$action,'values'=>$before,'savedAt'=>gmdate('c'),'userId'=>get_current_user_id()];update_post_meta($id,self::PREFIX.'history',array_slice($h,-30));}
    private function touch(int$id):void{$p=get_post($id);if($p instanceof \WP_Post)wp_update_post(['ID'=>$id,'post_content'=>$p->post_content]);}
}
