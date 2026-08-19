<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class LibraryRepository
{
    private const WORKFLOW_STAGES = [
        'identification' => 'Identificação',
        'project' => 'Projeto da Obra',
        'planning' => 'Planejamento',
        'development' => 'Desenvolvimento',
        'general_review' => 'Revisão Geral',
        'versions' => 'Controle de Versões',
        'audit' => 'Auditoria',
        'editorial_desk' => 'Mesa Editorial',
        'layout' => 'Diagramação',
        'legal' => 'Trâmites Legais',
        'publication' => 'Publicação',
    ];

    private const IDENTIFICATION_CHECKLIST = [
        'title' => 'Título provisório',
        'genre' => 'Gênero',
        'language' => 'Idioma',
    ];

    private const BOOK_META_FIELDS = [
        'subtitle',
        'series',
        'category',
        'genre',
        'audience',
        'age_range',
        'language',
        'country',
        'author_name',
        'coauthor_name',
        'main_objective',
        'reader_problem',
        'reader_transformation',
        'proposal_summary',
        'synopsis',
        'keyword',
        'keywords',
        'planned_chapters',
        'word_goal',
        'target_date',
        'workflow_status',
        'tags',
        'collection',
        'priority',
        'cover_id',
        'cover_url',
        'color',
        'icon',
        'notes',
        'internal_name',
        'administrative_notes',
        'format',
    ];

    /** @return array{projects: array<int, array<string, mixed>>, books: array<int, array<string, mixed>>} */
    public function libraryForUser(int $userId): array
    {
        $projects = $this->queryUserPosts(LibraryPostTypes::PROJECT, $userId);
        $books = array_merge(
            $this->queryUserPosts(LibraryPostTypes::BOOK, $userId),
            $this->queryUserPosts(LibraryPostTypes::BOOK, $userId, 'trash')
        );

        return [
            'projects' => array_map(fn (\WP_Post $post): array => $this->projectData($post), $projects),
            'books' => array_map(fn (\WP_Post $post): array => $this->bookData($post), $books),
        ];
    }

    /** @return array<string, mixed> */
    public function workspaceForBook(int $userId, int $bookId): array
    {
        $book = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        $projectId = (int) get_post_meta($bookId, '_verbum_project_id', true);
        $project = $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);
        $currentStage = (string) (get_post_meta($bookId, '_verbum_stage', true) ?: 'identification');

        if (! array_key_exists($currentStage, self::WORKFLOW_STAGES)) {
            $currentStage = 'identification';
        }

        $stageKeys = array_keys(self::WORKFLOW_STAGES);
        $currentIndex = array_search($currentStage, $stageKeys, true);
        $completedMeta = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completedMeta = is_array($completedMeta) ? $completedMeta : [];
        $workflow = [];
        $completedCount = 0;

        foreach (self::WORKFLOW_STAGES as $key => $label) {
            $index = array_search($key, $stageKeys, true);
            $completed = in_array($key, $completedMeta, true) || ($currentIndex !== false && $index < $currentIndex);
            $status = $completed ? 'completed' : ($key === $currentStage ? 'in_progress' : 'locked');
            if ($completed) {
                $completedCount++;
            }
            $workflow[] = [
                'key' => $key,
                'label' => $label,
                'status' => $status,
                'order' => $index + 1,
            ];
        }

        $progress = (int) round(($completedCount / count(self::WORKFLOW_STAGES)) * 100);

        return [
            'book' => $this->bookData($book),
            'project' => $this->projectData($project),
            'currentStage' => $currentStage,
            'workflow' => $workflow,
            'identification' => $this->identificationData($book),
            'metrics' => [
                'imo' => null,
                'rme' => null,
                'progress' => $progress,
                'chapters' => 0,
                'words' => (int) get_post_meta($bookId, '_verbum_word_count', true),
                'lastEdited' => mysql_to_rfc3339($book->post_modified_gmt ?: $book->post_modified),
            ],
        ];
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function saveIdentification(int $userId, int $bookId, array $fields): array
    {
        $book = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);

        if (array_key_exists('title', $fields)) {
            $result = wp_update_post([
                'ID' => $bookId,
                'post_title' => trim((string) $fields['title']),
                'post_content' => $book->post_content,
            ], true);
            if (is_wp_error($result)) {
                throw new \RuntimeException('Não foi possível salvar a Identificação da Obra.');
            }
        }

        $this->saveBookMeta($bookId, $fields);
        $this->touchBook($bookId);
        $book = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        $identification = $this->identificationData($book);

        if (! $identification['ready']) {
            $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
            $completed = is_array($completed) ? $completed : [];
            if (in_array('identification', $completed, true)) {
                $completed = array_values(array_diff($completed, ['identification']));
                update_post_meta($bookId, '_verbum_completed_stages', $completed);
                update_post_meta($bookId, '_verbum_stage', 'identification');
            }
        }

        return $this->workspaceForBook($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function completeIdentification(int $userId, int $bookId): array
    {
        $book = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        $identification = $this->identificationData($book);

        if (! $identification['ready']) {
            $pending = array_map(
                static fn (array $item): string => (string) $item['label'],
                array_values(array_filter($identification['checklist'], static fn (array $item): bool => ! $item['completed']))
            );
            throw new ValidationError('Complete a Identificação antes de continuar: ' . implode(', ', $pending) . '.');
        }

        $completed = get_post_meta($bookId, '_verbum_completed_stages', true);
        $completed = is_array($completed) ? $completed : [];
        if (! in_array('identification', $completed, true)) {
            $completed[] = 'identification';
        }

        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        update_post_meta($bookId, '_verbum_stage', 'project');
        $this->touchBook($bookId);

        return $this->workspaceForBook($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function setBookCover(int $userId, int $bookId, int $attachmentId, string $url): array
    {
        $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        if ($this->publishedEditions($bookId) !== []) {
            throw new ValidationError('A capa da edição publicada está protegida. Crie uma nova edição para alterá-la.');
        }
        update_post_meta($bookId, '_verbum_cover_id', $attachmentId);
        update_post_meta($bookId, '_verbum_cover_url', esc_url_raw($url));
        $this->touchBook($bookId);

        return $this->workspaceForBook($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function removeBookCover(int $userId, int $bookId): array
    {
        $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        if ($this->publishedEditions($bookId) !== []) {
            throw new ValidationError('A capa da edição publicada está protegida. Crie uma nova edição para alterá-la.');
        }
        update_post_meta($bookId, '_verbum_cover_id', 0);
        update_post_meta($bookId, '_verbum_cover_url', '');
        $this->touchBook($bookId);

        return $this->workspaceForBook($userId, $bookId);
    }

    /** @return array<string, mixed> */
    public function createProject(int $userId, string $name, string $description = ''): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new ValidationError('Informe o nome do projeto.');
        }

        $id = wp_insert_post([
            'post_type' => LibraryPostTypes::PROJECT,
            'post_status' => 'publish',
            'post_title' => $name,
            'post_content' => $description,
            'post_author' => $userId,
        ], true);

        if (is_wp_error($id)) {
            throw new \RuntimeException('Não foi possível criar o projeto.');
        }

        update_post_meta((int) $id, '_verbum_status', 'active');

        return $this->projectData($this->ownedPost((int) $id, LibraryPostTypes::PROJECT, $userId));
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function createBook(int $userId, int $projectId, array $fields): array
    {
        $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);

        $title = trim((string) ($fields['title'] ?? ''));
        if ($title === '') {
            throw new ValidationError('Informe o título da obra.');
        }

        $id = wp_insert_post([
            'post_type' => LibraryPostTypes::BOOK,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => '',
            'post_author' => $userId,
        ], true);

        if (is_wp_error($id)) {
            throw new \RuntimeException('Não foi possível criar a obra.');
        }

        $bookId = (int) $id;
        update_post_meta($bookId, '_verbum_project_id', $projectId);
        update_post_meta($bookId, '_verbum_status', 'active');
        update_post_meta($bookId, '_verbum_stage', 'identification');
        update_post_meta($bookId, '_verbum_completed_stages', []);
        if (! array_key_exists('workflow_status', $fields)) {
            $fields['workflow_status'] = 'Identificação';
        }
        if (! array_key_exists('language', $fields) || trim((string) $fields['language']) === '') {
            $fields['language'] = 'Português (BR)';
        }
        $this->saveBookMeta($bookId, $fields);

        return $this->bookData($this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId));
    }

    /** @return array<string, mixed> */
    public function updateProject(int $userId, int $projectId, string $name, string $description = ''): array
    {
        $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);
        $name = trim($name);
        if ($name === '') {
            throw new ValidationError('Informe o nome do projeto.');
        }

        $result = wp_update_post([
            'ID' => $projectId,
            'post_title' => $name,
            'post_content' => $description,
        ], true);

        if (is_wp_error($result)) {
            throw new \RuntimeException('Não foi possível atualizar o projeto.');
        }

        return $this->projectData($this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId));
    }

    /** @param array<string, mixed> $fields
     *  @return array<string, mixed>
     */
    public function updateBook(int $userId, int $bookId, array $fields): array
    {
        $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        if ($this->publishedEditions($bookId) !== []) {
            unset($fields['title'], $fields['subtitle']);
        }

        if (array_key_exists('project_id', $fields)) {
            $projectId = (int) $fields['project_id'];
            $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);
            update_post_meta($bookId, '_verbum_project_id', $projectId);
        }

        if (array_key_exists('title', $fields)) {
            $title = trim((string) $fields['title']);
            if ($title === '') {
                throw new ValidationError('Informe o título da obra.');
            }
            $result = wp_update_post(['ID' => $bookId, 'post_title' => $title], true);
            if (is_wp_error($result)) {
                throw new \RuntimeException('Não foi possível atualizar a obra.');
            }
        }

        $this->saveBookMeta($bookId, $fields);

        return $this->bookData($this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId));
    }

    /** @return array<string, mixed> */
    public function archiveProject(int $userId, int $projectId): array
    {
        $post = $this->ownedPost($projectId, LibraryPostTypes::PROJECT, $userId);
        update_post_meta($projectId, '_verbum_status', 'archived');

        foreach ($this->queryUserPosts(LibraryPostTypes::BOOK, $userId) as $book) {
            if ((int) get_post_meta($book->ID, '_verbum_project_id', true) === $projectId) {
                update_post_meta($book->ID, '_verbum_status', 'archived');
            }
        }

        return $this->projectData($post);
    }

    /** @return array<string, mixed> */
    public function archiveBook(int $userId, int $bookId): array
    {
        $post = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        update_post_meta($bookId, '_verbum_status', 'archived');

        return $this->bookData($post);
    }

    /** @return array<string, mixed> */
    public function restoreBook(int $userId, int $bookId): array
    {
        $post = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        $published = $this->publishedEditions($bookId) !== [];
        update_post_meta($bookId, '_verbum_status', $published ? 'published' : 'active');
        $this->appendLibraryHistory($bookId, $userId, 'Obra restaurada para a visualização principal');
        return $this->bookData($post);
    }

    /** @return array<string, mixed> */
    public function duplicateBook(int $userId, int $bookId): array
    {
        $source = $this->ownedPost($bookId, LibraryPostTypes::BOOK, $userId);
        $id = wp_insert_post(['post_type'=>LibraryPostTypes::BOOK,'post_status'=>'publish','post_title'=>get_the_title($source).' — cópia','post_content'=>'','post_author'=>$userId], true);
        if (is_wp_error($id)) throw new \RuntimeException('Não foi possível duplicar a obra.');
        $newId=(int)$id; update_post_meta($newId,'_verbum_project_id',(int)get_post_meta($bookId,'_verbum_project_id',true));
        update_post_meta($newId,'_verbum_status','active'); update_post_meta($newId,'_verbum_stage','identification'); update_post_meta($newId,'_verbum_completed_stages',[]);
        foreach(self::BOOK_META_FIELDS as$field){if(in_array($field,['workflow_status','cover_id','cover_url'],true))continue;$value=get_post_meta($bookId,'_verbum_'.$field,true);if($field==='internal_name'&&trim((string)$value)!=='')$value=trim((string)$value).' — cópia';if($value!==''&&$value!==[])update_post_meta($newId,'_verbum_'.$field,$value);}
        update_post_meta($newId,'_verbum_origin_book_id',$bookId); $this->appendLibraryHistory($bookId,$userId,'Obra duplicada como novo projeto editorial');
        return $this->bookData($this->ownedPost($newId, LibraryPostTypes::BOOK, $userId));
    }

    /** @return \WP_Post[] */
    private function queryUserPosts(string $postType, int $userId, string $postStatus = 'publish'): array
    {
        $query = new \WP_Query([
            'post_type' => $postType,
            'post_status' => $postStatus,
            'author' => $userId,
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        return array_values(array_filter($query->posts, static fn ($post): bool => $post instanceof \WP_Post));
    }

    private function ownedPost(int $postId, string $postType, int $userId): \WP_Post
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== $postType || (int) $post->post_author !== $userId) {
            throw new NotFoundError('Registro não encontrado.');
        }

        return $post;
    }

    /** @param array<string, mixed> $fields */
    private function saveBookMeta(int $bookId, array $fields): void
    {
        foreach (self::BOOK_META_FIELDS as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            $value = $fields[$field];
            if (is_array($value)) {
                $value = array_values(array_filter(array_map('sanitize_text_field', $value), static fn (string $item): bool => $item !== ''));
            } else {
                $value = sanitize_textarea_field((string) $value);
            }

            update_post_meta($bookId, '_verbum_' . $field, $value);
        }
    }

    /** @return array<string, mixed> */
    private function identificationData(\WP_Post $post): array
    {
        $values = [
            'title' => trim((string) get_the_title($post)),
            'genre' => trim((string) get_post_meta($post->ID, '_verbum_genre', true)),
            'language' => trim((string) get_post_meta($post->ID, '_verbum_language', true)),
        ];

        $checklist = [];
        $completedCount = 0;
        foreach (self::IDENTIFICATION_CHECKLIST as $key => $label) {
            $completed = trim((string) $values[$key]) !== '';
            if ($completed) {
                $completedCount++;
            }
            $checklist[] = [
                'key' => $key,
                'label' => $label,
                'completed' => $completed,
            ];
        }

        $total = count(self::IDENTIFICATION_CHECKLIST);
        $completedStages = get_post_meta($post->ID, '_verbum_completed_stages', true);
        $completedStages = is_array($completedStages) ? $completedStages : [];

        return [
            'progress' => (int) round(($completedCount / $total) * 100),
            'completedCount' => $completedCount,
            'total' => $total,
            'ready' => $completedCount === $total,
            'completed' => in_array('identification', $completedStages, true),
            'checklist' => $checklist,
        ];
    }

    private function touchBook(int $bookId): void
    {
        $post = get_post($bookId);
        if (! $post instanceof \WP_Post) {
            return;
        }
        wp_update_post([
            'ID' => $bookId,
            'post_content' => $post->post_content,
        ]);
    }

    /** @return array<string, mixed> */
    private function projectData(\WP_Post $post): array
    {
        return [
            'id' => (string) $post->ID,
            'name' => get_the_title($post),
            'description' => (string) $post->post_content,
            'status' => (string) (get_post_meta($post->ID, '_verbum_status', true) ?: 'active'),
            'createdAt' => mysql_to_rfc3339($post->post_date_gmt ?: $post->post_date),
            'updatedAt' => mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified),
        ];
    }

    /** @return array<string, mixed> */
    private function bookData(\WP_Post $post): array
    {
        $inTrash = $post->post_status === 'trash';
        $data = [
            'id' => (string) $post->ID,
            'projectId' => (string) get_post_meta($post->ID, '_verbum_project_id', true),
            'title' => get_the_title($post),
            'status' => $inTrash ? 'trash' : (string) (get_post_meta($post->ID, '_verbum_status', true) ?: 'active'),
            'stage' => (string) (get_post_meta($post->ID, '_verbum_stage', true) ?: 'identification'),
            'createdAt' => mysql_to_rfc3339($post->post_date_gmt ?: $post->post_date),
            'updatedAt' => mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified),
            'trashedAt' => $inTrash ? (string) (get_post_meta($post->ID, '_verbum_trashed_at', true) ?: mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified)) : '',
        ];

        foreach (self::BOOK_META_FIELDS as $field) {
            $data[$this->camelCase($field)] = get_post_meta($post->ID, '_verbum_' . $field, true);
        }

        $data['workflowStatus'] = $this->automaticWorkflowStatus($post);
        $history = get_post_meta($post->ID, '_verbum_library_history', true);
        $data['libraryHistory'] = is_array($history) ? array_values($history) : [];
        $official=$this->officialStageData($post);
        $data=array_merge($data,$official);

        return $data;
    }

    /** @return array<string,mixed> */
    private function officialStageData(\WP_Post $post): array
    {
        $id=(int)$post->ID; $stage=(string)(get_post_meta($id,'_verbum_stage',true)?:'identification'); $published=$this->publishedEditions($id);
        $map=['identification'=>0,'project'=>1,'planning'=>2,'development'=>3,'general_review'=>4,'versions'=>5,'audit'=>5,'editorial_desk'=>6,'layout'=>6,'legal'=>6,'publication'=>7];
        $index=$map[$stage]??0; $isPublished=$published!==[];
        $substeps=[]; $substage='Dados principais';
        if($stage==='project'){$substeps=(array)get_post_meta($id,'_verbum_foundation_substeps',true);$order=['letter-soul'=>'Carta e Alma','intention'=>'Intenção','reader-result'=>'Leitor e Resultado','truth-central'=>'Verdade Central'];$substage=$this->nextSubstage($order,$substeps);}
        elseif($stage==='planning'){$substeps=(array)get_post_meta($id,'_verbum_structure_substeps',true);$substage=$this->nextSubstage(['direction'=>'Direção','architecture'=>'Arquitetura','elements'=>'Elementos','provisional-index'=>'Índice Provisório'],$substeps);}
        elseif($stage==='development'){$chapter=$this->nextChapter($id);$substage=$chapter['label'];}
        elseif($stage==='general_review'){$substeps=(array)get_post_meta($id,'_verbum_general_review_substeps',true);$substage=$this->nextSubstage(['structure'=>'Estrutura','argument'=>'Argumento','doctrine'=>'Doutrina e Fontes','unity'=>'Unidade e Estilo','closing'=>'Fechamento'],$substeps);}
        elseif(in_array($stage,['versions','audit'],true)){$validation=get_post_meta($id,'_verbum_validation_process',true);$validation=is_array($validation)?$validation:[];$keys=['preparation'=>'Preparação','opinions'=>'Pareceres','corrections'=>'Correções','approval'=>'Aprovação'];$substage=$keys[$validation['active']??'preparation']??'Preparação';$substeps=(array)($validation['completed']??[]);}
        elseif(in_array($stage,['editorial_desk','layout','legal'],true)){$editorial=get_post_meta($id,'_verbum_editorial_preparation',true);$editorial=is_array($editorial)?$editorial:[];$keys=['definitive_text'=>'Texto Definitivo','rights'=>'Direitos e Registros','graphic'=>'Projeto Gráfico','proofs'=>'Provas','final_files'=>'Arquivos Finais'];$substage=$keys[$editorial['active']??'definitive_text']??'Texto Definitivo';$substeps=(array)($editorial['completed']??[]);}
        elseif($stage==='publication'){$journey=get_post_meta($id,'_verbum_publication_journey',true);$journey=is_array($journey)?$journey:[];$keys=['planning'=>'Planejamento','channels'=>'Canais e Distribuição','launch'=>'Lançamento','published'=>'Edição Publicada'];$substage=$isPublished?(string)($published[count($published)-1]['number']??'Edição publicada'):($keys[$journey['active']??'planning']??'Planejamento');$substeps=(array)($journey['completed']??[]);}
        $fractions=[0=>3,1=>4,2=>4,3=>4,4=>5,5=>4,6=>5,7=>4]; $inside=$isPublished?1:min(1,count($substeps)/($fractions[$index]??1));
        if($index===0){$inside=count(array_filter([trim((string)get_the_title($post)),trim((string)get_post_meta($id,'_verbum_genre',true)),trim((string)get_post_meta($id,'_verbum_language',true))]))/3;}
        if($stage==='development'){$chap=$this->chapterMetrics($id);$inside=$chap['count']>0?$chap['completed']/$chap['count']:0;}
        $progress=$isPublished?100:(int)round((($index+$inside)/8)*100); $metrics=$this->chapterMetrics($id);
        $labels=['Identificação','Fundação','Estrutura','Capítulos','Revisão Geral','Validação','Preparação Editorial','Publicação'];
        return ['officialStageKey'=>$isPublished?'published':['identification','foundation','structure','chapters','general_review','validation','editorial','publication'][$index],'officialStage'=>$isPublished?'Publicada':$labels[$index],'officialStageIndex'=>$index,'substage'=>$substage,'progress'=>$progress,'chapterCount'=>$metrics['count'],'wordCount'=>$metrics['words'],'completedOfficialStages'=>$isPublished?8:$index,'hasPublishedEdition'=>$published!==[],'publishedEditionCount'=>count($published),'publishedEditionNumber'=>$published!==[]?(string)($published[count($published)-1]['number']??''):'' ,'nextAction'=>$this->nextActionLabel($index,$substage,$isPublished)];
    }

    /** @param array<string,string> $order @param array<int,mixed> $completed */
    private function nextSubstage(array$order,array$completed):string{foreach($order as$key=>$label)if(!in_array($key,$completed,true))return$label;return(string)end($order);}
    /** @return array{label:string,id:int} */
    private function nextChapter(int$id):array{$chapters=get_posts(['post_type'=>LibraryPostTypes::CHAPTER,'post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_verbum_chapter_order','orderby'=>'meta_value_num','order'=>'ASC','meta_query'=>[['key'=>'_verbum_book_id','value'=>$id,'compare'=>'=','type'=>'NUMERIC']]]);$labels=['preparation'=>'Preparação','research'=>'Pesquisa','writing'=>'Redação','revision'=>'Revisão'];foreach((array)$chapters as$chapterId){$completed=(array)get_post_meta((int)$chapterId,'_verbum_chapter_completed_stages',true);$stage=(string)(get_post_meta((int)$chapterId,'_verbum_chapter_stage',true)?:'preparation');if(!in_array('revision',$completed,true)){return['label'=>($labels[$stage]??'Preparação').' do capítulo '.max(1,(int)get_post_meta((int)$chapterId,'_verbum_chapter_order',true)),'id'=>(int)$chapterId];}}return['label'=>'Desenvolvimento dos capítulos','id'=>0];}
    /** @return array{count:int,completed:int,words:int} */
    private function chapterMetrics(int$id):array{$chapters=get_posts(['post_type'=>LibraryPostTypes::CHAPTER,'post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_verbum_book_id','meta_value'=>$id]);$words=0;$completed=0;foreach((array)$chapters as$chapterId){$words+=(int)get_post_meta((int)$chapterId,'_verbum_chapter_word_count',true);if((bool)get_post_meta((int)$chapterId,'_verbum_chapter_completed',true))$completed++;}return['count'=>count((array)$chapters),'completed'=>$completed,'words'=>$words];}
    private function nextActionLabel(int$index,string$substage,bool$published):string{if($published)return'Ver edição publicada';$verbs=['Continuar identificação','Continuar fundação','Continuar estrutura','Continuar capítulos','Continuar revisão','Continuar validação','Continuar preparação editorial','Continuar publicação'];if($index===3&&stripos(remove_accents($substage),'preparacao')!==false)return'Preparar capítulo';return$verbs[$index]??'Continuar obra';}
    /** @return array<int,array<string,mixed>> */ private function publishedEditions(int$id):array{$items=get_post_meta($id,'_verbum_published_editions',true);return is_array($items)?array_values(array_filter($items,'is_array')):[];}
    private function appendLibraryHistory(int$id,int$userId,string$label):void{$history=get_post_meta($id,'_verbum_library_history',true);$history=is_array($history)?$history:[];$history[]=['label'=>$label,'userId'=>$userId,'at'=>gmdate('c')];update_post_meta($id,'_verbum_library_history',$history);}

    private function automaticWorkflowStatus(\WP_Post $post): string
    {
        if ($post->post_status === 'trash') {
            return 'Na lixeira';
        }
        $recordStatus = strtolower(trim((string) get_post_meta($post->ID, '_verbum_status', true)));
        $savedStatus = trim((string) get_post_meta($post->ID, '_verbum_workflow_status', true));
        $normalized = strtolower(remove_accents($savedStatus));

        if ($recordStatus === 'archived' || in_array($normalized, ['arquivada', 'arquivado'], true)) {
            return 'Arquivada';
        }
        if ($recordStatus === 'paused' || in_array($normalized, ['pausada', 'pausado', 'em pausa'], true)) {
            return 'Pausada';
        }

        $stage = (string) (get_post_meta($post->ID, '_verbum_stage', true) ?: 'identification');
        $statuses = [
            'identification' => 'Identificação',
            'project' => 'Em planejamento',
            'planning' => 'Em planejamento',
            'development' => 'Em escrita',
            'general_review' => 'Em revisão',
            'versions' => 'Em revisão',
            'audit' => 'Em revisão',
            'editorial_desk' => 'Preparação editorial',
            'layout' => 'Preparação editorial',
            'legal' => 'Preparação editorial',
            'publication' => $this->publishedEditions((int) $post->ID) !== [] ? 'Publicada' : 'Em publicação',
        ];
        $status = $statuses[$stage] ?? 'Identificação';

        if ($savedStatus !== $status) {
            update_post_meta($post->ID, '_verbum_workflow_status', $status);
        }

        return $status;
    }

    private function camelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
