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
        return ['summary'=>$summary,'chapters'=>$chapterData,'outline'=>$this->outline($bookId,$chapterData),'ready'=>$ready,'completed'=>in_array('development',$completedStages,true)];
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
        $completedStages=get_post_meta($post->ID,'_verbum_chapter_completed_stages',true);$completedStages=is_array($completedStages)?array_values(array_intersect(array_keys(self::CHAPTER_STAGES),$completedStages)):[];$isCompleted=in_array('revision',$completedStages,true)||(bool)get_post_meta($post->ID,'_verbum_chapter_completed',true);$completedCount=$isCompleted?count(self::CHAPTER_STAGES):count($completedStages);$progress=(int)round(($completedCount/count(self::CHAPTER_STAGES))*100);
        $workflow=[];$stageKeys=array_keys(self::CHAPTER_STAGES);$currentIndex=array_search($stage,$stageKeys,true);foreach(self::CHAPTER_STAGES as $key=>$label){$index=array_search($key,$stageKeys,true);$done=$isCompleted||in_array($key,$completedStages,true)||($currentIndex!==false&&$index<$currentIndex);$workflow[]=['key'=>$key,'label'=>$label,'status'=>$done?'completed':($key===$stage&&!$isCompleted?'in_progress':'locked'),'order'=>$index+1];}
        return ['id'=>(string)$post->ID,'bookId'=>(string)get_post_meta($post->ID,'_verbum_book_id',true),'planningItemId'=>(string)get_post_meta($post->ID,'_verbum_planning_item_id',true),'number'=>max(1,(int)get_post_meta($post->ID,'_verbum_chapter_order',true)),'title'=>get_the_title($post),'stage'=>$stage,'stageLabel'=>self::CHAPTER_STAGES[$stage],'progress'=>$progress,'completed'=>$isCompleted,'completedStages'=>$completedStages,'workflow'=>$workflow,'wordCount'=>max(0,(int)get_post_meta($post->ID,'_verbum_chapter_word_count',true)),'lastEdited'=>mysql_to_rfc3339($post->post_modified_gmt?:$post->post_modified)];
    }

    /** @param array<int, array<string, mixed>> $chapters @return array<int, array<string, mixed>> */
    private function outline(int $bookId,array $chapters):array
    {
        $items=get_post_meta($bookId,'_verbum_planning_structure_items',true);$items=is_array($items)?$items:[];$chapterByItem=[];foreach($chapters as $chapter)$chapterByItem[(string)$chapter['planningItemId']]=$chapter;
        $result=[];$currentPart='';$currentChapterId=null;foreach($items as $item){if(!is_array($item))continue;$type=sanitize_key((string)($item['type']??'chapter'));$title=trim((string)($item['title']??''));$id=(string)($item['id']??'');if($title==='')continue;if($type==='part'){$currentPart=$title;$currentChapterId=null;$result[]=['type'=>'part','title'=>$title];}elseif($type==='chapter'&&isset($chapterByItem[$id])){$chapter=$chapterByItem[$id];$currentChapterId=(string)$chapter['id'];$result[]=['type'=>'chapter','part'=>$currentPart,'chapter'=>$chapter];}elseif($type==='subchapter')$result[]=['type'=>'subchapter','title'=>$title,'chapterId'=>$currentChapterId,'part'=>$currentPart];}
        if($result===[])foreach($chapters as $chapter)$result[]=['type'=>'chapter','part'=>'','chapter'=>$chapter];return $result;
    }
    private function touchBook(int $bookId):void{$post=get_post($bookId);if($post instanceof \WP_Post)wp_update_post(['ID'=>$bookId,'post_content'=>$post->post_content]);}
}
