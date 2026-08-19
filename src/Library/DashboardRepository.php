<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class DashboardRepository
{
    private LibraryRepository $library;

    public function __construct(LibraryRepository $library) { $this->library = $library; }

    /** @return array<string,mixed> */
    public function data(int $userId): array
    {
        $library = $this->library->libraryForUser($userId);
        $books = array_values(array_filter($library['books'], static fn (array $book): bool => ($book['status'] ?? '') !== 'archived'));
        $today = current_time('Y-m-d');
        $goals = get_user_meta($userId, '_verbum_dashboard_goals', true);
        $goals = is_array($goals) ? $goals : ['words'=>1500,'minutes'=>60,'chapters'=>1,'days'=>[1,2,3,4,5,6,7],'preferredTime'=>'','scope'=>'general','bookId'=>'','enabled'=>true];
        $sessions = $this->records($userId, '_verbum_writing_sessions');
        $tasks = $this->records($userId, '_verbum_dashboard_tasks');
        $activeSession = null; $todayWords = 0; $todayMinutes = 0; $todayChapters = [];
        foreach ($sessions as $session) {
            if (in_array($session['status'] ?? '', ['active','paused'], true)) $activeSession = $session;
            if (substr((string)($session['startedAt'] ?? ''), 0, 10) !== $today || ($session['status'] ?? '') !== 'finished') continue;
            $todayWords += (int)($session['wordsWritten'] ?? 0); $todayMinutes += (int)($session['netMinutes'] ?? 0);
            if (! empty($session['chapterId'])) $todayChapters[(string)$session['chapterId']] = true;
        }
        $calendar = $this->calendar($books, $today); $activities = $this->activities($books);
        $active = array_values(array_filter($books, static fn (array $book): bool => !($book['hasPublishedEdition'] ?? false) && ($book['status'] ?? '') === 'active'));
        $published = array_values(array_filter($books, static fn (array $book): bool => (bool)($book['hasPublishedEdition'] ?? false)));
        usort($active, static fn (array $a,array $b): int => strcmp((string)($b['updatedAt'] ?? ''),(string)($a['updatedAt'] ?? '')));
        $recentSession = $activeSession;
        if ($recentSession === null && $sessions !== []) $recentSession = $sessions[array_key_last($sessions)];
        $sessionBookId = (string)($recentSession['bookId'] ?? '');
        $sessionBook = null;
        foreach ($active as $book) if ((string)$book['id'] === $sessionBookId) { $sessionBook = $book; break; }
        $current = $sessionBook ?? ($active[0] ?? ($published[0] ?? null));
        return [
            'goals'=>$goals,
            'today'=>['date'=>$today,'words'=>$todayWords,'minutes'=>$todayMinutes,'chapters'=>count($todayChapters)],
            'activeSession'=>$activeSession,
            'sessions'=>array_slice(array_reverse($sessions),0,20),
            'tasks'=>array_values(array_filter($tasks, static fn (array $task): bool => empty($task['completedAt']) && (empty($task['date']) || $task['date'] <= $today))),
            'agenda'=>array_slice($calendar,0,5),
            'activities'=>array_slice($activities,0,8),
            'currentBook'=>$current,
            'chapterOptions'=>$this->chapterOptions($active),
            'stats'=>['activeBooks'=>count($active),'publishedBooks'=>count($published),'chapters'=>array_sum(array_map(static fn(array $b):int=>(int)($b['chapterCount']??0),$active)),'words'=>array_sum(array_map(static fn(array $b):int=>(int)($b['wordCount']??0),$active))],
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function action(int $userId, array $payload): array
    {
        $action = sanitize_key((string)($payload['action'] ?? ''));
        if ($action === 'save_goals') $this->saveGoals($userId, $payload);
        elseif ($action === 'create_task') $this->createTask($userId, $payload);
        elseif ($action === 'complete_task') $this->completeTask($userId, (string)($payload['id'] ?? ''));
        elseif ($action === 'start_session') $this->startSession($userId, $payload);
        elseif (in_array($action, ['pause_session','resume_session','finish_session'], true)) $this->updateSession($userId, $action);
        else throw new ValidationError('Ação do Painel não reconhecida.');
        return $this->data($userId);
    }

    /** @param array<string,mixed> $payload */
    private function saveGoals(int $userId,array $payload): void
    {
        $goals=['words'=>max(0,(int)($payload['words']??0)),'minutes'=>max(0,(int)($payload['minutes']??0)),'chapters'=>max(0,(int)($payload['chapters']??0)),'days'=>array_values(array_filter(array_map('intval',(array)($payload['days']??[])),static fn(int$n):bool=>$n>=1&&$n<=7)),'preferredTime'=>sanitize_text_field((string)($payload['preferredTime']??'')),'scope'=>in_array(($payload['scope']??''),['general','book'],true)?$payload['scope']:'general','bookId'=>(string)(int)($payload['bookId']??0),'enabled'=>(bool)($payload['enabled']??false)];
        update_user_meta($userId,'_verbum_dashboard_goals',$goals);
    }

    /** @param array<string,mixed> $payload */
    private function createTask(int $userId,array $payload): void
    {
        $title=trim(sanitize_text_field((string)($payload['title']??''))); if($title==='')throw new ValidationError('Informe o título da tarefa.');
        $tasks=$this->records($userId,'_verbum_dashboard_tasks');$tasks[]=['id'=>'task-'.wp_generate_uuid4(),'title'=>$title,'bookId'=>(string)(int)($payload['bookId']??0),'stage'=>sanitize_key((string)($payload['stage']??'')),'chapterId'=>(string)(int)($payload['chapterId']??0),'date'=>sanitize_text_field((string)($payload['date']??'')),'time'=>sanitize_text_field((string)($payload['time']??'')),'priority'=>sanitize_key((string)($payload['priority']??'normal')),'recurrence'=>sanitize_key((string)($payload['recurrence']??'none')),'notes'=>sanitize_textarea_field((string)($payload['notes']??'')),'createdAt'=>current_time('c'),'completedAt'=>''];update_user_meta($userId,'_verbum_dashboard_tasks',$tasks);
    }

    private function completeTask(int$userId,string$id):void{$tasks=$this->records($userId,'_verbum_dashboard_tasks');$found=false;foreach($tasks as&$task){if(($task['id']??'')!==$id)continue;$task['completedAt']=current_time('c');$found=true;break;}unset($task);if(!$found)throw new NotFoundError('Tarefa não encontrada.');update_user_meta($userId,'_verbum_dashboard_tasks',$tasks);}

    /** @param array<string,mixed> $payload */
    private function startSession(int$userId,array$payload):void
    {
        $sessions=$this->records($userId,'_verbum_writing_sessions');foreach($sessions as$session)if(in_array($session['status']??'',['active','paused'],true))throw new ValidationError('Finalize ou retome a sessão atual antes de iniciar outra.');
        $bookId=(int)($payload['bookId']??0);$book=get_post($bookId);if(!$book instanceof \WP_Post||$book->post_type!==LibraryPostTypes::BOOK||(int)$book->post_author!==$userId)throw new NotFoundError('Obra não encontrada.');
        $chapterId=(int)($payload['chapterId']??0);$initial=$chapterId>0?$this->chapterWords($chapterId,$bookId):0;
        $sessions[]=['id'=>'session-'.wp_generate_uuid4(),'bookId'=>(string)$bookId,'chapterId'=>(string)$chapterId,'focusMode'=>(bool)($payload['focusMode']??false),'status'=>'active','startedAt'=>current_time('c'),'endedAt'=>'','initialWords'=>$initial,'finalWords'=>$initial,'wordsWritten'=>0,'pausedSeconds'=>0,'pauseStartedAt'=>'','netMinutes'=>0];update_user_meta($userId,'_verbum_writing_sessions',$sessions);
    }

    private function updateSession(int$userId,string$action):void
    {
        $sessions=$this->records($userId,'_verbum_writing_sessions');$index=null;foreach($sessions as$i=>$session)if(in_array($session['status']??'',['active','paused'],true))$index=$i;if($index===null)throw new NotFoundError('Nenhuma sessão de escrita está ativa.');$now=current_time('timestamp');$session=$sessions[$index];
        if($action==='pause_session'&&$session['status']==='active'){$session['status']='paused';$session['pauseStartedAt']=current_time('c');}
        elseif($action==='resume_session'&&$session['status']==='paused'){$session['pausedSeconds']+=(int)max(0,$now-strtotime((string)$session['pauseStartedAt']));$session['status']='active';$session['pauseStartedAt']='';}
        elseif($action==='finish_session'){if($session['status']==='paused')$session['pausedSeconds']+=(int)max(0,$now-strtotime((string)$session['pauseStartedAt']));$final=$this->chapterWords((int)$session['chapterId'],(int)$session['bookId']);$session['status']='finished';$session['endedAt']=current_time('c');$session['finalWords']=$final;$session['wordsWritten']=max(0,$final-(int)$session['initialWords']);$session['netMinutes']=(int)max(0,floor(($now-strtotime((string)$session['startedAt'])-(int)$session['pausedSeconds'])/60));}
        $sessions[$index]=$session;update_user_meta($userId,'_verbum_writing_sessions',$sessions);
    }

    private function chapterWords(int$chapterId,int$bookId):int{if($chapterId<=0)return 0;$post=get_post($chapterId);if(!$post instanceof \WP_Post||$post->post_type!==LibraryPostTypes::CHAPTER||(int)get_post_meta($chapterId,'_verbum_book_id',true)!==$bookId)throw new NotFoundError('Capítulo não encontrado.');$saved=(int)get_post_meta($chapterId,'_verbum_chapter_word_count',true);return$saved>0?$saved:str_word_count(wp_strip_all_tags((string)$post->post_content));}
    /** @return array<int,array<string,mixed>> */ private function records(int$userId,string$key):array{$value=get_user_meta($userId,$key,true);return is_array($value)?array_values(array_filter($value,'is_array')):[];}
    /** @param array<int,array<string,mixed>> $books @return array<int,array<string,mixed>> */
    private function calendar(array$books,string$today):array{$events=[];foreach($books as$book){foreach((array)get_post_meta((int)$book['id'],'_verbum_editorial_calendar_events',true)as$event){if(!is_array($event))continue;$date=substr((string)($event['date']??$event['at']??''),0,10);if($date!==''&&$date<$today)continue;$event['bookId']=$book['id'];$event['bookTitle']=$book['title'];$events[]=$event;}}usort($events,static fn(array$a,array$b):int=>strcmp((string)($a['date']??$a['at']??''),(string)($b['date']??$b['at']??'')));return$events;}
    /** @param array<int,array<string,mixed>> $books @return array<int,array<string,mixed>> */
    private function activities(array$books):array{$items=[];foreach($books as$book){foreach(['_verbum_library_history','_verbum_publication_history']as$key)foreach((array)get_post_meta((int)$book['id'],$key,true)as$event){if(!is_array($event))continue;$items[]=['id'=>(string)($event['id']??md5(wp_json_encode($event))),'label'=>(string)($event['label']??'Obra atualizada'),'at'=>(string)($event['at']??''),'bookId'=>$book['id'],'bookTitle'=>$book['title']];}$items[]=['id'=>'book-'.$book['id'].'-'.$book['updatedAt'],'label'=>'Obra atualizada','at'=>$book['updatedAt'],'bookId'=>$book['id'],'bookTitle'=>$book['title']];}usort($items,static fn(array$a,array$b):int=>strcmp((string)$b['at'],(string)$a['at']));return$items;}
    /** @param array<int,array<string,mixed>> $books @return array<int,array<string,mixed>> */
    private function chapterOptions(array$books):array{$out=[];foreach($books as$book){$ids=get_posts(['post_type'=>LibraryPostTypes::CHAPTER,'post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_verbum_book_id','meta_value'=>(int)$book['id'],'orderby'=>'menu_order','order'=>'ASC']);foreach((array)$ids as$id)$out[]=['id'=>(string)$id,'bookId'=>(string)$book['id'],'title'=>get_the_title((int)$id)];}return$out;}
}
