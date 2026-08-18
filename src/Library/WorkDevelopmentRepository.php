<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkDevelopmentRepository
{
    private const CHAPTER_STAGES = ['preparation'=>'Preparação','research'=>'Pesquisa','writing'=>'Redação','revision'=>'Revisão'];

    /** @return array<string, mixed> */
    public function data(int $userId, int $bookId): array
    {
        $chapters=$this->chaptersForBook($userId,$bookId); $chapterData=array_map(fn(\WP_Post $post):array=>$this->chapterData($post),$chapters); $total=count($chapterData);
        $summary=['total'=>$total,'completed'=>0,'preparation'=>0,'research'=>0,'writing'=>0,'revision'=>0,'progress'=>0,'words'=>0];
        foreach($chapterData as $chapter){$stage=(string)$chapter['stage'];$summary['words']+=(int)$chapter['wordCount'];if((bool)$chapter['completed'])$summary['completed']++;elseif(array_key_exists($stage,self::CHAPTER_STAGES))$summary[$stage]++;$summary['progress']+=(int)$chapter['progress'];}
        $summary['progress']=$total>0?(int)round($summary['progress']/$total):0;
        $completedStages=get_post_meta($bookId,'_verbum_completed_stages',true);$completedStages=is_array($completedStages)?$completedStages:[];$ready=$total>0&&$summary['completed']===$total;
        return ['summary'=>$summary,'chapters'=>$chapterData,'outline'=>$this->outline($bookId,$chapterData),'synchronization'=>$this->syncPreview($userId,$bookId),'syncHistory'=>$this->recentSyncHistory($bookId),'ready'=>$ready,'completed'=>in_array('development',$completedStages,true)];
    }

    /** @return array<string,mixed> */
    public function syncPreview(int $userId,int $bookId):array
    {
        $items=$this->indexItems($bookId);$chapters=array_map(fn(\WP_Post $post):array=>$this->chapterRecord($post),$this->chaptersForBook($userId,$bookId));$byId=[];foreach($chapters as $chapter)$byId[(string)$chapter['id']]=$chapter;
        $existing=[];$create=[];$conflicts=[];$linked=[];
        foreach($items as $item){if(($item['type']??'')!=='chapter')continue;$chapterId=preg_replace('/\D+/','',(string)($item['realChapterId']??''))?:'';$entry=['itemId'=>(string)($item['id']??''),'title'=>(string)($item['title']??''),'order'=>count($existing)+count($create)+count($conflicts)+1,'chapterId'=>$chapterId];if($chapterId===''){$create[]=$entry;continue;}if(!isset($byId[$chapterId])){$entry['reason']='O capítulo vinculado não existe mais ou pertence a outra obra.';$conflicts[]=$entry;continue;}$entry['chapterTitle']=$byId[$chapterId]['title'];$entry['hasContent']=$byId[$chapterId]['hasContent'];$entry['titleChanged']=$entry['title']!==$entry['chapterTitle'];$existing[]=$entry;$linked[]=$chapterId;}
        $unmatched=array_values(array_filter($chapters,static fn(array $chapter):bool=>!in_array((string)$chapter['id'],$linked,true)));
        return['planned'=>count($existing)+count($create)+count($conflicts),'existing'=>$existing,'create'=>$create,'conflicts'=>$conflicts,'unmatchedExisting'=>$unmatched,'summary'=>['planned'=>count($existing)+count($create)+count($conflicts),'existing'=>count($existing),'new'=>count($create),'removed'=>count($unmatched),'conflicts'=>count($conflicts)],'canConfirm'=>$conflicts===[]];
    }

    /** @param array<string,mixed> $options @return array<string,mixed> */
    public function synchronize(int $userId,int $bookId,array $options):array
    {
        if(!(bool)($options['confirmed']??false))throw new ValidationError('Veja a comparação e confirme a sincronização antes de aplicar alterações.');$preview=$this->syncPreview($userId,$bookId);if($preview['conflicts']!==[])throw new ValidationError('Resolva os conflitos de vínculo antes de sincronizar os capítulos.');
        $items=$this->indexItems($bookId);$rename=array_map('strval',is_array($options['rename_items']??null)?$options['rename_items']:[]);$created=[];$renamed=[];$reordered=[];$chapterOrder=0;
        foreach($items as &$item){if(($item['type']??'')!=='chapter')continue;$chapterOrder++;$itemId=(string)($item['id']??'');$chapterId=(int)($item['realChapterId']??0);if($chapterId<=0){$result=wp_insert_post(['post_type'=>LibraryPostTypes::CHAPTER,'post_status'=>'publish','post_title'=>(string)$item['title'],'post_content'=>'','post_author'=>$userId],true);if(is_wp_error($result))throw new \RuntimeException('Não foi possível criar um dos capítulos previstos.');$chapterId=(int)$result;$item['realChapterId']=(string)$chapterId;update_post_meta($chapterId,'_verbum_book_id',$bookId);update_post_meta($chapterId,'_verbum_planning_item_id',$itemId);update_post_meta($chapterId,'_verbum_structure_index_item_id',$itemId);update_post_meta($chapterId,'_verbum_chapter_stage','preparation');update_post_meta($chapterId,'_verbum_chapter_word_count',0);$created[]=(string)$chapterId;}else{update_post_meta($chapterId,'_verbum_structure_index_item_id',$itemId);if(in_array($itemId,$rename,true)&&get_the_title($chapterId)!==(string)$item['title']){wp_update_post(['ID'=>$chapterId,'post_title'=>(string)$item['title']]);$renamed[]=(string)$chapterId;}}
            if((bool)($options['sync_order']??true)&&(int)get_post_meta($chapterId,'_verbum_chapter_order',true)!==$chapterOrder){update_post_meta($chapterId,'_verbum_chapter_order',$chapterOrder);$reordered[]=(string)$chapterId;}
        }unset($item);
        update_post_meta($bookId,'_verbum_structure_index_items',$items);update_post_meta($bookId,'_verbum_structure_index_comparison_needed',0);$history=$this->recentSyncHistory($bookId,100);$history[]=['at'=>gmdate('c'),'userId'=>$userId,'created'=>$created,'renamed'=>$renamed,'reordered'=>$reordered,'preserved'=>array_column($preview['unmatchedExisting'],'id')];update_post_meta($bookId,'_verbum_chapter_sync_history',array_slice($history,-100));$this->touchBook($bookId);return$this->data($userId,$bookId);
    }

    /** @param array<int,mixed> $orderedIds @return array<string,mixed> */
    public function saveOrder(int $userId,int $bookId,array $orderedIds):array
    {
        $allowed=array_map(static fn(\WP_Post $post):string=>(string)$post->ID,$this->chaptersForBook($userId,$bookId));$clean=array_values(array_unique(array_map('strval',$orderedIds)));if(count($clean)!==count($allowed)||array_diff($clean,$allowed)!==[])throw new ValidationError('A ordem enviada não corresponde aos capítulos desta obra.');foreach($clean as $index=>$chapterId)update_post_meta((int)$chapterId,'_verbum_chapter_order',$index+1);$this->touchBook($bookId);return$this->data($userId,$bookId);
    }

    /** @return array<string, mixed> */
    public function chapter(int $userId,int $bookId,int $chapterId):array
    {
        $chapters=$this->chaptersForBook($userId,$bookId);$ids=array_map(static fn(\WP_Post $post):int=>(int)$post->ID,$chapters);$index=array_search($chapterId,$ids,true);if($index===false)throw new NotFoundError('Capítulo não encontrado.');
        $data=$this->chapterData($chapters[$index]);$data['previousId']=$index>0?(string)$ids[$index-1]:null;$data['nextId']=$index<count($ids)-1?(string)$ids[$index+1]:null;$data['position']=$index+1;$data['totalChapters']=count($ids);return $data;
    }

    /** @return array<string, mixed> */
    public function complete(int $userId,int $bookId):array
    {
        $data=$this->data($userId,$bookId);if(!$data['ready'])throw new ValidationError('Conclua a Revisão de todos os capítulos antes de avançar para a Revisão da Obra.');
        $completed=get_post_meta($bookId,'_verbum_completed_stages',true);$completed=is_array($completed)?$completed:[];if(!in_array('planning',$completed,true))throw new ValidationError('Conclua a Estrutura da Obra antes de avançar pelos capítulos.');if(!in_array('development',$completed,true))$completed[]='development';
        update_post_meta($bookId,'_verbum_completed_stages',array_values(array_unique($completed)));update_post_meta($bookId,'_verbum_stage','general_review');update_post_meta($bookId,'_verbum_development_completed_at',gmdate('c'));$this->touchBook($bookId);return $this->data($userId,$bookId);
    }

    /** @return \WP_Post[] */
    private function chaptersForBook(int $userId,int $bookId):array
    {
        $query=new \WP_Query(['post_type'=>LibraryPostTypes::CHAPTER,'post_status'=>'publish','author'=>$userId,'posts_per_page'=>-1,'meta_query'=>[['key'=>'_verbum_book_id','value'=>$bookId,'compare'=>'=','type'=>'NUMERIC']],'meta_key'=>'_verbum_chapter_order','orderby'=>'meta_value_num','order'=>'ASC','no_found_rows'=>true]);
        return array_values(array_filter($query->posts,static fn($post):bool=>$post instanceof \WP_Post));
    }

    /** @return array<string, mixed> */
    private function chapterData(\WP_Post $post):array
    {
        $stage=sanitize_key((string)(get_post_meta($post->ID,'_verbum_chapter_stage',true)?:'preparation'));if($stage==='completed')$stage='revision';if(!array_key_exists($stage,self::CHAPTER_STAGES))$stage='preparation';
        $completedStages=get_post_meta($post->ID,'_verbum_chapter_completed_stages',true);$completedStages=is_array($completedStages)?array_values(array_intersect(array_keys(self::CHAPTER_STAGES),$completedStages)):[];$isCompleted=in_array('revision',$completedStages,true)||(bool)get_post_meta($post->ID,'_verbum_chapter_completed',true);$progress=$isCompleted?100:(['preparation'=>25,'research'=>40,'writing'=>65,'revision'=>90][$stage]??0);
        $workflow=[];$stageKeys=array_keys(self::CHAPTER_STAGES);$currentIndex=array_search($stage,$stageKeys,true);foreach(self::CHAPTER_STAGES as $key=>$label){$index=array_search($key,$stageKeys,true);$done=$isCompleted||in_array($key,$completedStages,true)||($currentIndex!==false&&$index<$currentIndex);$workflow[]=['key'=>$key,'label'=>$label,'status'=>$done?'completed':($key===$stage&&!$isCompleted?'in_progress':'locked'),'order'=>$index+1];}
        return ['id'=>(string)$post->ID,'bookId'=>(string)get_post_meta($post->ID,'_verbum_book_id',true),'planningItemId'=>(string)get_post_meta($post->ID,'_verbum_planning_item_id',true),'number'=>max(1,(int)get_post_meta($post->ID,'_verbum_chapter_order',true)),'title'=>get_the_title($post),'stage'=>$stage,'stageLabel'=>self::CHAPTER_STAGES[$stage],'progress'=>$progress,'completed'=>$isCompleted,'completedStages'=>$completedStages,'workflow'=>$workflow,'wordCount'=>max(0,(int)get_post_meta($post->ID,'_verbum_chapter_word_count',true)),'lastEdited'=>mysql_to_rfc3339($post->post_modified_gmt?:$post->post_modified)];
    }

    /** @param array<int, array<string, mixed>> $chapters @return array<int, array<string, mixed>> */
    private function outline(int $bookId,array $chapters):array
    {
        $items=$this->indexItems($bookId);$chapterByItem=[];foreach($chapters as $chapter){$indexId=(string)get_post_meta((int)$chapter['id'],'_verbum_structure_index_item_id',true);$chapterByItem[$indexId!==''?$indexId:(string)$chapter['planningItemId']]=$chapter;}
        $result=[];$currentPart='';$currentChapterId=null;foreach($items as $item){if(!is_array($item))continue;$type=sanitize_key((string)($item['type']??'chapter'));$title=trim((string)($item['title']??''));$id=(string)($item['id']??'');if($title==='')continue;if($type==='part'){$currentPart=$title;$currentChapterId=null;$result[]=['type'=>'part','title'=>$title];}elseif($type==='chapter'&&isset($chapterByItem[$id])){$chapter=$chapterByItem[$id];$currentChapterId=(string)$chapter['id'];$result[]=['type'=>'chapter','part'=>$currentPart,'chapter'=>$chapter];}elseif($type==='subchapter')$result[]=['type'=>'subchapter','title'=>$title,'chapterId'=>$currentChapterId,'part'=>$currentPart];}
        if($result===[])foreach($chapters as $chapter)$result[]=['type'=>'chapter','part'=>'','chapter'=>$chapter];return $result;
    }
    /** @return array<int,array<string,mixed>> */ private function indexItems(int $bookId):array{$items=get_post_meta($bookId,'_verbum_structure_index_items',true);if(!is_array($items)||$items===[]){$items=get_post_meta($bookId,'_verbum_planning_structure_items',true);if(is_array($items))foreach($items as &$item)if(is_array($item)&&empty($item['realChapterId'])&&!empty($item['linkedChapterId']))$item['realChapterId']=(string)$item['linkedChapterId'];unset($item);}return is_array($items)?array_values(array_filter($items,'is_array')):[];}
    /** @return array<string,mixed> */ private function chapterRecord(\WP_Post $post):array{return['id'=>(string)$post->ID,'title'=>get_the_title($post),'hasContent'=>trim((string)$post->post_content)!==''||(int)get_post_meta($post->ID,'_verbum_chapter_word_count',true)>0||(array)get_post_meta($post->ID,'_verbum_chapter_completed_stages',true)!==[]];}
    /** @return array<int,mixed> */ private function recentSyncHistory(int $bookId,int $limit=20):array{$history=get_post_meta($bookId,'_verbum_chapter_sync_history',true);return is_array($history)?array_slice($history,-$limit):[];}
    private function touchBook(int $bookId):void{$post=get_post($bookId);if($post instanceof \WP_Post)wp_update_post(['ID'=>$bookId,'post_content'=>$post->post_content]);}
}
