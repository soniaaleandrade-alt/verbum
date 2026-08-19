<?php

declare(strict_types=1);

namespace VerbumStudio\Library;

use VerbumStudio\Exceptions\NotFoundError;
use VerbumStudio\Exceptions\ValidationError;

final class WorkPublicationRepository
{
    private const CHANNEL_TYPES = [
        'direct' => 'Venda direta', 'marketplace' => 'Marketplace', 'bookstore' => 'Livraria',
        'distributor' => 'Distribuidora', 'website' => 'Site próprio', 'publisher' => 'Editora', 'other' => 'Outro',
    ];
    private const CHANNEL_STATUSES = [
        'not_started' => 'Não iniciado', 'preparing' => 'Preparando', 'submitted' => 'Enviado',
        'under_review' => 'Em análise', 'approved' => 'Aprovado', 'scheduled' => 'Agendado',
        'published' => 'Publicado', 'requires_adjustment' => 'Requer ajuste', 'suspended' => 'Suspenso',
        'closed' => 'Encerrado', 'not_applicable' => 'Não aplicável',
    ];
    private const TASK_PHASES = ['prelaunch' => 'Pré-lançamento', 'launch' => 'Lançamento', 'postlaunch' => 'Pós-lançamento'];
    private const TASK_STATUSES = ['pending' => 'Pendente', 'done' => 'Concluída'];
    private const UPDATE_TYPES = ['correction' => 'Correção', 'metadata' => 'Metadados', 'file' => 'Arquivo', 'channel' => 'Canal', 'new_edition' => 'Nova edição', 'other' => 'Outro'];
    private const MANUAL_FLAGS = [
        'edition_checked' => 'Edição legal validada',
        'final_file_checked' => 'Arquivo final conferido',
        'cover_checked' => 'Capa final conferida',
        'identifier_checked' => 'Identificador/ISBN conferido',
        'metadata_checked' => 'Metadados comerciais conferidos',
        'description_approved' => 'Sinopse/descrição aprovada',
        'keywords_defined' => 'Palavras-chave definidas',
        'categories_defined' => 'Categorias definidas',
        'price_defined' => 'Preço definido',
        'channels_configured' => 'Canais de publicação cadastrados',
        'links_registered' => 'Links/identificadores registrados',
        'publication_record_checked' => 'Registro da edição conferido',
    ];

    /** @return array<string,mixed> */
    public function data(int $userId, int $bookId): array
    {
        $this->assertAvailable($userId, $bookId);
        $legal = $this->legalBaseline($bookId);
        $rounds = $this->rounds($bookId);
        $round = $this->currentRound($rounds, (string) $legal['snapshotHash']);
        if ($round === null) {
            $round = $this->newRound($bookId, $legal, count($rounds) + 1);
            $rounds[] = $round;
            $this->storeRounds($bookId, $rounds);
        }

        $state = is_array($round['state'] ?? null) ? $round['state'] : $this->initialState($bookId, $legal);
        $flags = $this->normalizeFlags(is_array($round['flags'] ?? null) ? $round['flags'] : []);
        $channels = $this->collection($round, 'channels');
        $tasks = $this->collection($round, 'tasks');
        $history = $this->collection($round, 'history');
        $finalConfirmation = (bool) ($round['finalConfirmation'] ?? false);
        $completed = (string) ($round['status'] ?? '') === 'published';
        $baselineValid = $this->baselineValid($bookId, $legal);

        $requiredChannels = array_values(array_filter($channels, static fn (array $item): bool => (bool) ($item['required'] ?? false)));
        $resolvedRequired = count(array_filter($requiredChannels, static fn (array $item): bool => in_array((string) ($item['status'] ?? ''), ['published', 'not_applicable'], true)));
        $publishedChannels = array_values(array_filter($channels, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'published'));
        $packageReady = trim((string) ($state['package']['finalFileUrl'] ?? '')) !== '' && trim((string) ($state['package']['coverUrl'] ?? '')) !== '';
        $metadataReady = $this->metadataReady($state);
        $pricingReady = $this->pricingReady($state);
        $channelsReady = count($requiredChannels) > 0 && $resolvedRequired === count($requiredChannels) && count($publishedChannels) > 0;
        $launchReady = trim((string) ($state['launch']['actualDate'] ?? '')) !== '';

        $checklist = [];
        foreach (self::MANUAL_FLAGS as $key => $label) $checklist[] = ['key' => $key, 'label' => $label, 'completed' => (bool) ($flags[$key] ?? false), 'automatic' => false];
        $checklist[] = ['key' => 'baseline_valid', 'label' => 'Baseline legal íntegra', 'completed' => $baselineValid, 'automatic' => true];
        $checklist[] = ['key' => 'package_ready', 'label' => 'Pacote final possui arquivo e capa', 'completed' => $packageReady, 'automatic' => true];
        $checklist[] = ['key' => 'metadata_ready', 'label' => 'Metadados mínimos preenchidos', 'completed' => $metadataReady, 'automatic' => true];
        $checklist[] = ['key' => 'pricing_ready', 'label' => 'Preço definido por formato', 'completed' => $pricingReady, 'automatic' => true];
        $checklist[] = ['key' => 'channels_resolved', 'label' => 'Canais obrigatórios resolvidos', 'completed' => $channelsReady, 'automatic' => true];
        $checklist[] = ['key' => 'launch_date', 'label' => 'Data efetiva de lançamento definida', 'completed' => $launchReady, 'automatic' => true];
        $checklist[] = ['key' => 'author_confirmation', 'label' => 'Autor confirma a publicação', 'completed' => $finalConfirmation, 'automatic' => true];
        $checklist[] = ['key' => 'completed', 'label' => 'Publicação concluída', 'completed' => $completed, 'automatic' => true];
        $completedCount = count(array_filter($checklist, static fn (array $item): bool => (bool) $item['completed']));
        $manualReady = count(array_filter(array_keys(self::MANUAL_FLAGS), static fn (string $key): bool => (bool) ($flags[$key] ?? false))) === count(self::MANUAL_FLAGS);
        $ready = ! $completed && $baselineValid && $manualReady && $packageReady && $metadataReady && $pricingReady && $channelsReady && $launchReady && $finalConfirmation;

        return [
            'bookId' => (string) $bookId,
            'title' => (string) ($state['identity']['title'] ?? get_the_title($bookId)),
            'legal' => $this->legalSummary($legal),
            'round' => $this->roundSummary($round),
            'rounds' => array_map(fn (array $item): array => $this->roundSummary($item), array_reverse($rounds)),
            'state' => $state, 'channels' => $channels, 'tasks' => $tasks, 'history' => array_reverse($history),
            'records' => $this->records($bookId), 'updates' => $this->updates($bookId),
            'flags' => $flags, 'finalConfirmation' => $finalConfirmation,
            'channelTypes' => $this->options(self::CHANNEL_TYPES), 'channelStatuses' => $this->options(self::CHANNEL_STATUSES),
            'taskPhases' => $this->options(self::TASK_PHASES), 'taskStatuses' => $this->options(self::TASK_STATUSES), 'updateTypes' => $this->options(self::UPDATE_TYPES),
            'requiredChannelCount' => count($requiredChannels), 'resolvedRequiredCount' => $resolvedRequired, 'publishedChannelCount' => count($publishedChannels),
            'formatCount' => count((array) ($state['identity']['publicationFormats'] ?? [])), 'baselineValid' => $baselineValid,
            'consistencyWarnings' => $this->consistencyWarnings($state, $legal),
            'checklist' => $checklist, 'progress' => (int) round(($completedCount / max(1, count($checklist))) * 100),
            'completedCount' => $completedCount, 'total' => count($checklist), 'ready' => $ready, 'completed' => $completed,
            'publishedAt' => (string) ($round['publishedAt'] ?? ''), 'editionHash' => (string) ($round['editionHash'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    public function journeyData(int $userId, int $bookId): array
    {
        $book = get_post($bookId);
        if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) {
            throw new NotFoundError('Obra não encontrada.');
        }

        $preparation = get_post_meta($bookId, '_verbum_editorial_preparation', true);
        $preparation = is_array($preparation) ? $preparation : [];
        $completedPreparation = in_array('final_files', (array) ($preparation['completed'] ?? []), true)
            && is_array($preparation['editorialVersion'] ?? null);
        $state = $this->publicationJourneyState($bookId, $preparation);
        $steps = ['planning', 'channels', 'launch', 'published'];
        $progress = [];
        foreach ($steps as $step) {
            $progress[$step] = in_array($step, (array) $state['completed'], true)
                ? 100
                : $this->publicationJourneyProgress($step, $state, $completedPreparation);
        }

        return array_merge($state, [
            'bookId' => (string) $bookId,
            'bookTitle' => (string) get_the_title($bookId),
            'preparationComplete' => $completedPreparation,
            'preparation' => [
                'version' => $preparation['editorialVersion'] ?? null,
                'packages' => array_values(array_filter((array) ($preparation['packages'] ?? []), 'is_array')),
                'files' => array_values(array_filter((array) ($preparation['finalFiles'] ?? []), 'is_array')),
                'metadata' => is_array($preparation['metadata'] ?? null) ? $preparation['metadata'] : [],
            ],
            'progress' => $progress,
            'overallProgress' => (int) round(array_sum($progress) / 4),
            'publishedEditions' => $this->publicationEditions($bookId),
            'locked' => (bool) ($state['locked'] ?? false),
        ]);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function journeyAction(int $userId, int $bookId, array $payload): array
    {
        $data = $this->journeyData($userId, $bookId);
        $state = $this->publicationJourneyState($bookId, (array) get_post_meta($bookId, '_verbum_editorial_preparation', true));
        $action = sanitize_key((string) ($payload['action'] ?? 'save'));

        if ($action === 'save') {
            if (! empty($state['locked'])) throw new ValidationError('A edição publicada está protegida. Inicie uma nova versão ou edição para continuar.');
            foreach (['planning', 'tasks', 'channels', 'distribution', 'prices', 'channelMetadata', 'launchActions', 'event', 'messages', 'materials', 'publication', 'availability', 'memory', 'finalChecklist', 'finalConfirmation'] as $key) {
                if (array_key_exists($key, $payload)) $state[$key] = $this->sanitizeArray(is_array($payload[$key]) ? $payload[$key] : ['value' => $payload[$key]])['value'] ?? $this->sanitizeArray((array) $payload[$key]);
            }
            $state['history'][] = $this->publicationJourneyEvent($userId, 'Rascunho da Publicação salvo');
            $this->syncPublicationCalendar($bookId, (array) $state['launchActions']);
        } elseif ($action === 'complete_step') {
            if (! $data['preparationComplete']) throw new ValidationError('Conclua a Preparação Editorial e seus pacotes finais antes de avançar.');
            if (! empty($state['locked'])) throw new ValidationError('A edição publicada está protegida.');
            $step = sanitize_key((string) ($payload['step'] ?? ''));
            $order = ['planning', 'channels', 'launch'];
            if (! in_array($step, $order, true)) throw new ValidationError('Etapa da Publicação inválida.');
            $position = array_search($step, $order, true);
            if ($position > 0 && ! in_array($order[$position - 1], (array) $state['completed'], true)) throw new ValidationError('Conclua a etapa anterior antes de avançar.');
            $this->assertPublicationJourneyStep($step, $state, $data);
            if (! in_array($step, (array) $state['completed'], true)) $state['completed'][] = $step;
            $state['active'] = $order[$position + 1] ?? 'published';
            $state['history'][] = $this->publicationJourneyEvent($userId, 'Etapa concluída: ' . $step);
            if ($step === 'launch') $this->syncPublicationCalendar($bookId, (array) $state['launchActions']);
        } elseif ($action === 'confirm_publication') {
            foreach (['planning', 'channels', 'launch'] as $required) if (! in_array($required, (array) $state['completed'], true)) throw new ValidationError('Conclua Planejamento, Canais e Lançamento antes de confirmar a edição.');
            $this->assertPublicationJourneyStep('published', $state, $data);
            $confirmationKey = hash('sha256', wp_json_encode([$state['publication'], $state['availability'], $data['preparation']['version']], JSON_UNESCAPED_UNICODE));
            foreach ($this->publicationEditions($bookId) as $edition) if (($edition['confirmationKey'] ?? '') === $confirmationKey) return $this->journeyData($userId, $bookId);
            $edition = [
                'id' => 'published-edition-' . substr($confirmationKey, 0, 16),
                'confirmationKey' => $confirmationKey,
                'number' => (string) ($state['publication']['editionNumber'] ?? ''),
                'publishedAt' => (string) ($state['publication']['date'] ?? ''),
                'confirmedAt' => gmdate('c'), 'confirmedBy' => $userId,
                'sourceEditorialVersion' => $data['preparation']['version'],
                'publication' => $state['publication'], 'availability' => $state['availability'],
                'packages' => $data['preparation']['packages'], 'files' => $data['preparation']['files'],
                'memory' => $state['memory'], 'checklist' => $state['finalChecklist'],
            ];
            $editions = $this->publicationEditions($bookId); $editions[] = $edition;
            update_post_meta($bookId, '_verbum_published_editions', $editions);
            $state['completed'][] = 'published'; $state['active'] = 'published'; $state['locked'] = true; $state['publishedEditionId'] = $edition['id'];
            $state['history'][] = $this->publicationJourneyEvent($userId, 'Edição publicada confirmada');
            update_post_meta($bookId, '_verbum_status', 'published');
            update_post_meta($bookId, '_verbum_workflow_status', 'Publicada');
            update_post_meta($bookId, '_verbum_progress', 100);
            update_post_meta($bookId, '_verbum_published_at', $edition['publishedAt']);
            $completed = get_post_meta($bookId, '_verbum_completed_stages', true); $completed = is_array($completed) ? $completed : [];
            if (! in_array('publication', $completed, true)) $completed[] = 'publication';
            update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed)));
        } elseif ($action === 'start_edition') {
            if (empty($state['locked'])) throw new ValidationError('Confirme a edição atual antes de iniciar outra.');
            $kind = sanitize_key((string) ($payload['kind'] ?? 'version'));
            if (! in_array($kind, ['version', 'edition'], true)) throw new ValidationError('Escolha nova versão ou nova edição.');
            $reason = trim(sanitize_textarea_field((string) ($payload['reason'] ?? '')));
            if ($reason === '') throw new ValidationError('Registre o motivo da nova versão ou edição.');
            $previous = (string) ($state['publishedEditionId'] ?? '');
            $state['locked'] = false; $state['completed'] = []; $state['active'] = 'planning'; $state['finalConfirmation'] = false;
            $state['finalChecklist'] = array_fill_keys(array_keys((array) $state['finalChecklist']), false);
            $state['origin'] = ['kind' => $kind, 'reason' => $reason, 'previousEditionId' => $previous, 'createdAt' => gmdate('c'), 'createdBy' => $userId];
            $state['history'][] = $this->publicationJourneyEvent($userId, ($kind === 'edition' ? 'Nova edição iniciada' : 'Nova versão iniciada') . ': ' . $reason);
        } else {
            throw new ValidationError('Ação da Publicação não reconhecida.');
        }

        $state['updatedAt'] = gmdate('c');
        update_post_meta($bookId, '_verbum_publication_journey', $state);
        return $this->journeyData($userId, $bookId);
    }

    /** @return array<string,mixed> */
    public function publishedDashboard(int $userId,int $bookId,string $editionId): array
    {
        $book=get_post($bookId); if(!$book instanceof \WP_Post||$book->post_type!==LibraryPostTypes::BOOK||(int)$book->post_author!==$userId) throw new NotFoundError('Obra não encontrada.');
        $editions=$this->publicationEditions($bookId); $edition=null;
        if($editionId==='latest'&&!empty($editions)){$edition=end($editions);$editionId=(string)($edition['id']??'');}
        else foreach($editions as $item) if(($item['id']??'')===$editionId){$edition=$item;break;}
        if(!is_array($edition)) throw new NotFoundError('Edição publicada não encontrada.');
        $ops=get_post_meta($bookId,'_verbum_published_operations',true); $ops=is_array($ops)?$ops:[]; $history=array_values(array_filter((array)get_post_meta($bookId,'_verbum_publication_history',true),'is_array'));
        $journey=get_post_meta($bookId,'_verbum_publication_journey',true); $journey=is_array($journey)?$journey:[];
        $publication=(array)($edition['publication']??[]); $availability=array_values(array_filter((array)($edition['availability']??[]),'is_array')); $files=array_values(array_filter((array)($edition['files']??[]),'is_array'));
        $editionOps=array_values(array_filter($ops,static fn($op):bool=>is_array($op)&&(string)($op['editionId']??'')===$editionId));
        foreach($editionOps as$op){if(($op['type']??'')!=='update_channel')continue;$channelId=(string)($op['payload']['channel_id']??'');foreach($availability as&$channel){if((string)($channel['id']??$channel['channel']??'')!==$channelId)continue;foreach((array)($op['changes']??[])as$key=>$value)$channel[$key]=$value;break;}unset($channel);}
        return ['bookId'=>(string)$bookId,'editionId'=>$editionId,'title'=>(string)get_the_title($bookId),'subtitle'=>(string)get_post_meta($bookId,'_verbum_subtitle',true),'author'=>(string)get_post_meta($bookId,'_verbum_author_name',true),'coverUrl'=>(string)get_post_meta($bookId,'_verbum_cover_url',true),'edition'=>$edition,'publication'=>$publication,'availability'=>$availability,'files'=>$files,'packages'=>(array)($edition['packages']??[]),'operations'=>$editionOps,'history'=>array_reverse(array_merge((array)($journey['history']??[]),$history)),'chapterCount'=>(int)get_post_meta($bookId,'_verbum_planned_chapters',true),'wordCount'=>(int)get_post_meta($bookId,'_verbum_word_count',true),'status'=>'published','progress'=>100,'protected'=>true];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function publishedAction(int $userId,int $bookId,string $editionId,array $payload): array
    {
        $data=$this->publishedDashboard($userId,$bookId,$editionId); $editionId=(string)$data['editionId']; $action=sanitize_key((string)($payload['action']??''));
        $ops=get_post_meta($bookId,'_verbum_published_operations',true); $ops=is_array($ops)?$ops:[]; $entry=['id'=>'published-operation-'.substr(md5($action.microtime(true)),0,14),'editionId'=>$editionId,'type'=>$action,'createdAt'=>gmdate('c'),'createdBy'=>$userId,'payload'=>$this->sanitizeArray($payload)];
        if($action==='update_channel'){if(empty($payload['channel_id']))throw new ValidationError('Selecione o canal.');$allowed=['status','url','stock','availability','notes','verified_at'];$entry['changes']=[];foreach($allowed as$key)if(array_key_exists($key,$payload))$entry['changes'][$key]=$this->sanitizeArray(['v'=>$payload[$key]])['v'];}
        elseif($action==='reprint'){if(empty($payload['date'])||empty($payload['quantity']))throw new ValidationError('Informe data e quantidade da reimpressão.');}
        elseif(in_array($action,['new_version','new_edition'],true)){$reason=trim(sanitize_textarea_field((string)($payload['reason']??'')));if($reason==='')throw new ValidationError('Registre o motivo.');$this->journeyAction($userId,$bookId,['action'=>'start_edition','kind'=>$action==='new_edition'?'edition':'version','reason'=>$reason]);}
        elseif($action==='duplicate'){$title=trim(sanitize_text_field((string)($payload['title']??'')));if($title==='')throw new ValidationError('Informe o título provisório da nova obra.');$new=wp_insert_post(['post_type'=>LibraryPostTypes::BOOK,'post_status'=>'publish','post_title'=>$title,'post_author'=>$userId],true);if(is_wp_error($new))throw new ValidationError('Não foi possível duplicar a obra.');update_post_meta((int)$new,'_verbum_status','active');update_post_meta((int)$new,'_verbum_stage','identification');update_post_meta((int)$new,'_verbum_completed_stages',[]);update_post_meta((int)$new,'_verbum_origin_book_id',$bookId);update_post_meta((int)$new,'_verbum_origin_edition_id',$editionId);$entry['newBookId']=(int)$new;}
        elseif($action==='administrative_correction'){if(empty($payload['reason'])||empty($payload['field']))throw new ValidationError('Informe campo e justificativa da correção administrativa.');}
        elseif($action==='master_download'){$entry['packageIds']=array_values(array_map(static fn($p):string=>(string)($p['id']??''),(array)$data['packages']));}
        else throw new ValidationError('Ação da obra publicada não reconhecida.');
        $ops[]=$entry;update_post_meta($bookId,'_verbum_published_operations',$ops);$history=(array)get_post_meta($bookId,'_verbum_publication_history',true);$history[]=['id'=>$entry['id'],'editionId'=>$editionId,'type'=>$action,'label'=>'Ação pós-publicação registrada: '.$action,'userId'=>$userId,'at'=>gmdate('c')];update_post_meta($bookId,'_verbum_publication_history',$history);return$this->publishedDashboard($userId,$bookId,$editionId);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function saveState(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $this->assertMutable($round);
            if (array_key_exists('state', $payload)) $round['state'] = $this->sanitizeArray(is_array($payload['state']) ? $payload['state'] : []);
            if (array_key_exists('flags', $payload)) $round['flags'] = $this->normalizeFlags(is_array($payload['flags']) ? $payload['flags'] : []);
            if (array_key_exists('final_confirmation', $payload)) $round['finalConfirmation'] = (bool) $payload['final_confirmation'];
            $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, 'Publicação atualizada', 'Metadados, pacote ou checklist foram atualizados.'); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createChannel(int $userId, int $bookId, array $payload): array
    {
        $name = trim(sanitize_text_field((string) ($payload['name'] ?? ''))); if ($name === '') throw new ValidationError('Informe o nome do canal de publicação.');
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round);
            $items = $this->collection($round, 'channels');
            $items[] = $this->normalizeChannel(array_merge($payload, ['id' => 'pub-channel-' . substr(md5($name . '|' . microtime(true)), 0, 14), 'name' => $name, 'createdAt' => gmdate('c')]));
            $round['channels'] = $items; $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, 'Canal adicionado', $name); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function updateChannel(int $userId, int $bookId, string $channelId, array $payload): array
    {
        return $this->updateRoundItem($userId, $bookId, 'channels', $channelId, fn (array $item): array => $this->normalizeChannel(array_merge($item, $payload)), 'Canal atualizado');
    }

    /** @return array<string,mixed> */
    public function deleteChannel(int $userId, int $bookId, string $channelId): array { return $this->deleteRoundItem($userId, $bookId, 'channels', $channelId, 'Canal removido'); }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createTask(int $userId, int $bookId, array $payload): array
    {
        $description = trim(sanitize_text_field((string) ($payload['description'] ?? ''))); if ($description === '') throw new ValidationError('Informe a tarefa de lançamento.');
        $phase = sanitize_key((string) ($payload['phase'] ?? 'prelaunch')); if (! isset(self::TASK_PHASES[$phase])) $phase = 'prelaunch';
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId);
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round);
            $items = $this->collection($round, 'tasks');
            $items[] = ['id' => 'pub-task-' . substr(md5($description . '|' . microtime(true)), 0, 14), 'description' => $description, 'phase' => $phase, 'phaseLabel' => self::TASK_PHASES[$phase], 'status' => 'pending', 'statusLabel' => self::TASK_STATUSES['pending'], 'createdAt' => gmdate('c'), 'updatedAt' => gmdate('c')];
            $round['tasks'] = $items; $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, 'Tarefa de lançamento adicionada', $description); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function updateTask(int $userId, int $bookId, string $taskId, array $payload): array
    {
        return $this->updateRoundItem($userId, $bookId, 'tasks', $taskId, function (array $item) use ($payload): array {
            if (array_key_exists('status', $payload)) { $status = sanitize_key((string) $payload['status']); if (isset(self::TASK_STATUSES[$status])) { $item['status'] = $status; $item['statusLabel'] = self::TASK_STATUSES[$status]; } }
            if (array_key_exists('description', $payload)) $item['description'] = trim(sanitize_text_field((string) $payload['description']));
            $item['updatedAt'] = gmdate('c'); return $item;
        }, 'Tarefa de lançamento atualizada');
    }

    /** @return array<string,mixed> */
    public function deleteTask(int $userId, int $bookId, string $taskId): array { return $this->deleteRoundItem($userId, $bookId, 'tasks', $taskId, 'Tarefa de lançamento removida'); }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function registerUpdate(int $userId, int $bookId, array $payload): array
    {
        $data = $this->data($userId, $bookId); if (! $data['completed']) throw new ValidationError('As atualizações pós-publicação só podem ser registradas depois da publicação concluída.');
        $description = trim(sanitize_textarea_field((string) ($payload['description'] ?? ''))); if ($description === '') throw new ValidationError('Descreva a atualização pós-publicação.');
        $type = sanitize_key((string) ($payload['type'] ?? 'other')); if (! isset(self::UPDATE_TYPES[$type])) $type = 'other';
        $updates = $this->updates($bookId);
        $updates[] = ['id' => 'pub-update-' . substr(md5($description . '|' . microtime(true)), 0, 14), 'type' => $type, 'typeLabel' => self::UPDATE_TYPES[$type], 'description' => $description, 'version' => trim(sanitize_text_field((string) ($payload['version'] ?? ''))), 'fileUrl' => esc_url_raw((string) ($payload['file_url'] ?? '')), 'publishedAt' => trim(sanitize_text_field((string) ($payload['published_at'] ?? ''))), 'createdAt' => gmdate('c')];
        update_post_meta($bookId, '_verbum_publication_updates', $updates); return $this->data($userId, $bookId);
    }

    /** @return array<string,mixed> */
    public function assistantContext(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        return ['legal' => $data['legal'], 'state' => $data['state'], 'channels' => $data['channels'], 'tasks' => $data['tasks'], 'warnings' => $data['consistencyWarnings'], 'checklist' => $data['checklist']];
    }

    /** @return array<string,mixed> */
    public function complete(int $userId, int $bookId): array
    {
        $data = $this->data($userId, $bookId);
        if (! $data['ready']) throw new ValidationError('Conclua o checklist, configure e resolva os canais obrigatórios, defina arquivos, metadados, preços e data efetiva antes de concluir a Publicação.');
        $rounds = $this->rounds($bookId); $editionHash = ''; $records = [];
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue;
            $snapshot = ['state' => $data['state'], 'channels' => $data['channels'], 'tasks' => $data['tasks'], 'flags' => $data['flags'], 'legal' => $data['legal']];
            $editionHash = hash('sha256', wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE));
            $round['status'] = 'published'; $round['publishedAt'] = (string) $data['state']['launch']['actualDate']; $round['completedAt'] = gmdate('c'); $round['publicationSnapshot'] = $snapshot; $round['editionHash'] = $editionHash;
            foreach ($data['channels'] as $channel) {
                if (! is_array($channel) || (string) ($channel['status'] ?? '') !== 'published') continue;
                $reference = (string) (($channel['fileUrl'] ?? '') ?: ($data['state']['package']['finalFileUrl'] ?? ''));
                $records[] = ['id' => 'publication-record-' . substr(md5((string) ($channel['id'] ?? '') . '|' . $editionHash), 0, 14), 'channelId' => (string) ($channel['id'] ?? ''), 'channel' => (string) ($channel['name'] ?? ''), 'type' => (string) ($channel['type'] ?? ''), 'format' => (string) ($channel['format'] ?? ''), 'identifier' => (string) ($channel['externalId'] ?? ''), 'url' => (string) ($channel['url'] ?? ''), 'fileUrl' => $reference, 'fileReferenceHash' => hash('sha256', $reference), 'editionHash' => $editionHash, 'publishedAt' => (string) (($channel['publishedAt'] ?? '') ?: $data['state']['launch']['actualDate']), 'price' => (string) ($channel['price'] ?? ''), 'currency' => (string) ($channel['currency'] ?? ''), 'createdAt' => gmdate('c')];
            }
            $this->appendHistory($round, 'Publicação concluída', 'Edição publicada e congelada com hash ' . substr($editionHash, 0, 12) . '.'); break;
        }
        unset($round); $this->storeRounds($bookId, $rounds); update_post_meta($bookId, '_verbum_publication_records', $records);
        update_post_meta($bookId, '_verbum_publication_snapshot_hash', $editionHash); update_post_meta($bookId, '_verbum_publication_completed_at', gmdate('c')); update_post_meta($bookId, '_verbum_published_at', (string) $data['state']['launch']['actualDate']);
        update_post_meta($bookId, '_verbum_status', 'published'); update_post_meta($bookId, '_verbum_workflow_status', 'Publicado');
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true); $completed = is_array($completed) ? $completed : []; if (! in_array('publication', $completed, true)) $completed[] = 'publication';
        update_post_meta($bookId, '_verbum_completed_stages', array_values(array_unique($completed))); update_post_meta($bookId, '_verbum_stage', 'publication');
        return $this->data($userId, $bookId);
    }

    private function assertAvailable(int $userId, int $bookId): void
    {
        $book = get_post($bookId); if (! $book instanceof \WP_Post || $book->post_type !== LibraryPostTypes::BOOK || (int) $book->post_author !== $userId) throw new NotFoundError('Obra não encontrada.');
        $completed = get_post_meta($bookId, '_verbum_completed_stages', true); $completed = is_array($completed) ? $completed : [];
        if (! in_array('legal', $completed, true)) throw new ValidationError('Conclua os Trâmites Legais antes de iniciar a Publicação.');
        if ((string) get_post_meta($bookId, '_verbum_legal_snapshot_hash', true) === '') throw new ValidationError('Os Trâmites Legais não possuem snapshot final válido para Publicação.');
    }

    /** @return array<string,mixed> */
    private function legalBaseline(int $bookId): array
    {
        $rounds = get_post_meta($bookId, '_verbum_legal_rounds', true); $rounds = is_array($rounds) ? $rounds : [];
        $expectedHash = (string) get_post_meta($bookId, '_verbum_legal_snapshot_hash', true); $versionId = (string) get_post_meta($bookId, '_verbum_legal_approved_version_id', true); $versionHash = (string) get_post_meta($bookId, '_verbum_legal_approved_hash', true);
        foreach (array_reverse($rounds) as $round) {
            if (! is_array($round) || (string) ($round['status'] ?? '') !== 'completed' || ! is_array($round['legalSnapshot'] ?? null)) continue;
            $snapshot = $round['legalSnapshot']; $hash = hash('sha256', wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE));
            if ($hash !== $expectedHash || (string) ($snapshot['version']['id'] ?? '') !== $versionId || (string) ($snapshot['version']['hash'] ?? '') !== $versionHash) continue;
            return ['roundId' => (string) ($round['id'] ?? ''), 'snapshot' => $snapshot, 'snapshotHash' => $hash, 'versionId' => $versionId, 'versionHash' => $versionHash, 'completedAt' => (string) ($round['completedAt'] ?? '')];
        }
        throw new ValidationError('Não foi possível localizar a edição legal congelada para Publicação.');
    }

    /** @param array<string,mixed> $legal */
    private function baselineValid(int $bookId, array $legal): bool
    {
        return (string) get_post_meta($bookId, '_verbum_legal_snapshot_hash', true) === (string) $legal['snapshotHash']
            && (string) get_post_meta($bookId, '_verbum_legal_approved_version_id', true) === (string) $legal['versionId']
            && (string) get_post_meta($bookId, '_verbum_legal_approved_hash', true) === (string) $legal['versionHash'];
    }

    /** @param array<string,mixed> $legal @return array<string,mixed> */
    private function initialState(int $bookId, array $legal): array
    {
        $snapshot = is_array($legal['snapshot'] ?? null) ? $legal['snapshot'] : []; $legalState = is_array($snapshot['state'] ?? null) ? $snapshot['state'] : [];
        $identity = is_array($legalState['identity'] ?? null) ? $legalState['identity'] : []; $finalFiles = is_array($legalState['finalFiles'] ?? null) ? $legalState['finalFiles'] : []; $isbn = is_array($legalState['isbn'] ?? null) ? $legalState['isbn'] : [];
        $keywords = get_post_meta($bookId, '_verbum_keywords', true); $keywords = is_array($keywords) ? array_values(array_map('strval', $keywords)) : [];
        $formats = is_array($identity['publicationFormats'] ?? null) ? array_values(array_filter(array_map('strval', $identity['publicationFormats']))) : []; if ($formats === []) $formats = ['printed'];
        $pricing = []; foreach ($formats as $format) $pricing[$format] = ['label' => $format === 'digital' ? 'Digital' : 'Impresso', 'price' => '', 'currency' => 'BRL', 'unitCost' => '', 'channelFeePercent' => '', 'promotionalPrice' => '', 'promotionStart' => '', 'promotionEnd' => ''];
        return [
            'identity' => $identity,
            'metadata' => ['title' => (string) ($identity['title'] ?? get_the_title($bookId)), 'subtitle' => (string) ($identity['subtitle'] ?? ''), 'author' => (string) ($identity['author'] ?? ''), 'shortDescription' => (string) get_post_meta($bookId, '_verbum_synopsis', true), 'description' => (string) get_post_meta($bookId, '_verbum_synopsis', true), 'keywords' => $keywords, 'primaryCategory' => (string) get_post_meta($bookId, '_verbum_category', true), 'secondaryCategory' => '', 'language' => (string) ($identity['language'] ?? get_post_meta($bookId, '_verbum_language', true)), 'edition' => (string) ($identity['edition'] ?? ''), 'year' => (string) ($identity['year'] ?? ''), 'publisher' => (string) ($identity['publisherName'] ?? '')],
            'package' => ['finalFileUrl' => (string) (($finalFiles['selectedFileUrl'] ?? '') ?: get_post_meta($bookId, '_verbum_legal_final_file', true)), 'coverUrl' => (string) ($finalFiles['coverUrl'] ?? ''), 'digitalFileUrl' => (string) ($finalFiles['digitalFileUrl'] ?? ''), 'digitalCoverUrl' => (string) ($finalFiles['digitalCoverUrl'] ?? ''), 'legalSnapshotHash' => (string) $legal['snapshotHash'], 'isbn' => $isbn],
            'pricing' => $pricing,
            'launch' => ['mode' => 'scheduled', 'plannedDate' => '', 'actualDate' => '', 'time' => '', 'notes' => ''],
            'materials' => ['cover3dUrl' => '', 'coverImageUrl' => (string) ($finalFiles['coverUrl'] ?? ''), 'bannerUrl' => '', 'releaseUrl' => '', 'socialText' => '', 'buyLink' => ''],
            'release' => ['title' => '', 'summary' => '', 'presentation' => '', 'aboutAuthor' => '', 'highlights' => '', 'publicationInfo' => ''],
        ];
    }

    /** @param array<string,mixed> $state */
    private function metadataReady(array $state): bool
    {
        $m = is_array($state['metadata'] ?? null) ? $state['metadata'] : []; $keywords = is_array($m['keywords'] ?? null) ? array_filter($m['keywords'], static fn ($v): bool => trim((string) $v) !== '') : [];
        return trim((string) ($m['title'] ?? '')) !== '' && trim((string) ($m['author'] ?? '')) !== '' && trim((string) ($m['description'] ?? '')) !== '' && count($keywords) > 0 && trim((string) ($m['primaryCategory'] ?? '')) !== '';
    }

    /** @param array<string,mixed> $state */
    private function pricingReady(array $state): bool
    {
        $pricing = is_array($state['pricing'] ?? null) ? $state['pricing'] : []; if ($pricing === []) return false;
        foreach ($pricing as $item) {
            if (! is_array($item)) return false; $price = str_replace(',', '.', (string) ($item['price'] ?? ''));
            if ($price === '' || ! is_numeric($price) || (float) $price < 0 || trim((string) ($item['currency'] ?? '')) === '') return false;
        }
        return true;
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $legal @return array<int,string> */
    private function consistencyWarnings(array $state, array $legal): array
    {
        $warnings = []; $snapshot = is_array($legal['snapshot'] ?? null) ? $legal['snapshot'] : []; $legalState = is_array($snapshot['state'] ?? null) ? $snapshot['state'] : [];
        $identity = is_array($legalState['identity'] ?? null) ? $legalState['identity'] : []; $metadata = is_array($state['metadata'] ?? null) ? $state['metadata'] : [];
        foreach ([['title','Título'],['author','Autor'],['edition','Edição']] as $pair) {
            $commercial = trim((string) ($metadata[$pair[0]] ?? '')); $legalValue = trim((string) ($identity[$pair[0]] ?? ''));
            if ($commercial !== '' && $legalValue !== '' && $commercial !== $legalValue) $warnings[] = $pair[1] . ' comercial difere da identificação legal da edição.';
        }
        $selected = (string) ($legalState['finalFiles']['selectedFileUrl'] ?? ''); if ($selected !== '' && (string) ($state['package']['finalFileUrl'] ?? '') !== $selected) $warnings[] = 'O arquivo do pacote de publicação difere do arquivo final selecionado nos Trâmites Legais.';
        return $warnings;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function normalizeChannel(array $payload): array
    {
        $type = sanitize_key((string) ($payload['type'] ?? 'other')); if (! isset(self::CHANNEL_TYPES[$type])) $type = 'other';
        $status = sanitize_key((string) ($payload['status'] ?? 'not_started')); if (! isset(self::CHANNEL_STATUSES[$status])) $status = 'not_started';
        return [
            'id' => (string) ($payload['id'] ?? ''), 'name' => trim(sanitize_text_field((string) ($payload['name'] ?? ''))), 'type' => $type, 'typeLabel' => self::CHANNEL_TYPES[$type],
            'format' => trim(sanitize_text_field((string) ($payload['format'] ?? 'printed'))), 'required' => (bool) ($payload['required'] ?? false), 'status' => $status, 'statusLabel' => self::CHANNEL_STATUSES[$status],
            'url' => esc_url_raw((string) ($payload['url'] ?? '')), 'externalId' => trim(sanitize_text_field((string) ($payload['external_id'] ?? $payload['externalId'] ?? ''))),
            'fileUrl' => esc_url_raw((string) ($payload['file_url'] ?? $payload['fileUrl'] ?? '')), 'submittedAt' => trim(sanitize_text_field((string) ($payload['submitted_at'] ?? $payload['submittedAt'] ?? ''))),
            'approvedAt' => trim(sanitize_text_field((string) ($payload['approved_at'] ?? $payload['approvedAt'] ?? ''))), 'publishedAt' => trim(sanitize_text_field((string) ($payload['published_at'] ?? $payload['publishedAt'] ?? ''))),
            'price' => trim(sanitize_text_field((string) ($payload['price'] ?? ''))), 'currency' => trim(sanitize_text_field((string) ($payload['currency'] ?? 'BRL'))), 'notes' => trim(sanitize_textarea_field((string) ($payload['notes'] ?? ''))),
            'createdAt' => (string) ($payload['createdAt'] ?? gmdate('c')), 'updatedAt' => gmdate('c'),
        ];
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $mutator @return array<string,mixed> */
    private function updateRoundItem(int $userId, int $bookId, string $collection, string $id, callable $mutator, string $event): array
    {
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId); $found = false;
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round); $items = $this->collection($round, $collection);
            foreach ($items as &$item) { if ((string) ($item['id'] ?? '') !== $id) continue; $item = $mutator($item); $found = true; break; }
            unset($item); $round[$collection] = $items; if ($found) { $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, $event, $id); } break;
        }
        unset($round); if (! $found) throw new NotFoundError('Registro de Publicação não encontrado.'); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @return array<string,mixed> */
    private function deleteRoundItem(int $userId, int $bookId, string $collection, string $id, string $event): array
    {
        $data = $this->data($userId, $bookId); $rounds = $this->rounds($bookId); $deleted = false;
        foreach ($rounds as &$round) {
            if ((string) ($round['id'] ?? '') !== (string) $data['round']['id']) continue; $this->assertMutable($round); $items = $this->collection($round, $collection);
            $next = array_values(array_filter($items, static fn (array $item): bool => (string) ($item['id'] ?? '') !== $id)); $deleted = count($next) !== count($items); $round[$collection] = $next;
            if ($deleted) { $round['updatedAt'] = gmdate('c'); $this->appendHistory($round, $event, $id); } break;
        }
        unset($round); if (! $deleted) throw new NotFoundError('Registro de Publicação não encontrado.'); $this->storeRounds($bookId, $rounds); return $this->data($userId, $bookId);
    }

    /** @param array<string,mixed> $legal @return array<string,mixed> */
    private function newRound(int $bookId, array $legal, int $number): array
    {
        return ['id' => 'publication-round-' . substr(md5((string) $legal['snapshotHash'] . '|' . microtime(true)), 0, 14), 'number' => $number, 'legalSnapshotHash' => (string) $legal['snapshotHash'], 'state' => $this->initialState($bookId, $legal), 'flags' => $this->normalizeFlags([]), 'channels' => [], 'tasks' => [], 'history' => [['id' => 'pub-history-' . substr(md5((string) microtime(true)), 0, 12), 'event' => 'Publicação iniciada', 'detail' => 'Edição legal congelada vinculada ao pacote de publicação.', 'createdAt' => gmdate('c')]], 'finalConfirmation' => false, 'status' => 'preparing', 'startedAt' => gmdate('c'), 'updatedAt' => gmdate('c'), 'completedAt' => '', 'publishedAt' => '', 'editionHash' => ''];
    }

    /** @param array<int,array<string,mixed>> $rounds @return array<string,mixed>|null */
    private function currentRound(array $rounds, string $snapshotHash): ?array { foreach (array_reverse($rounds) as $round) if (is_array($round) && (string) ($round['legalSnapshotHash'] ?? '') === $snapshotHash) return $round; return null; }
    /** @return array<int,array<string,mixed>> */ private function rounds(int $bookId): array { $items = get_post_meta($bookId, '_verbum_publication_rounds', true); return is_array($items) ? array_values(array_filter($items, 'is_array')) : []; }
    /** @param array<int,array<string,mixed>> $rounds */ private function storeRounds(int $bookId, array $rounds): void { update_post_meta($bookId, '_verbum_publication_rounds', array_values($rounds)); }
    /** @return array<int,array<string,mixed>> */ private function records(int $bookId): array { $items = get_post_meta($bookId, '_verbum_publication_records', true); return is_array($items) ? array_values(array_filter($items, 'is_array')) : []; }
    /** @return array<int,array<string,mixed>> */ private function updates(int $bookId): array { $items = get_post_meta($bookId, '_verbum_publication_updates', true); return is_array($items) ? array_values(array_filter($items, 'is_array')) : []; }
    /** @return array<int,array<string,mixed>> */ private function collection(array $round, string $key): array { $items = is_array($round[$key] ?? null) ? $round[$key] : []; return array_values(array_filter($items, 'is_array')); }
    /** @param array<string,mixed> $round */ private function assertMutable(array $round): void { if ((string) ($round['status'] ?? '') === 'published') throw new ValidationError('A edição publicada está congelada. Registre alterações posteriores no histórico de atualização.'); }
    /** @param array<string,mixed> $round */ private function appendHistory(array &$round, string $event, string $detail): void { $history = $this->collection($round, 'history'); $history[] = ['id' => 'pub-history-' . substr(md5($event . '|' . microtime(true)), 0, 12), 'event' => $event, 'detail' => $detail, 'createdAt' => gmdate('c')]; $round['history'] = array_slice($history, -80); }
    /** @return array<string,bool> */ private function normalizeFlags(array $flags): array { $clean = []; foreach (self::MANUAL_FLAGS as $key => $_) $clean[$key] = (bool) ($flags[$key] ?? false); return $clean; }
    /** @return array<int,array{key:string,label:string}> */ private function options(array $items): array { $out = []; foreach ($items as $key => $label) $out[] = ['key' => $key, 'label' => $label]; return $out; }
    /** @return array<string,mixed> */ private function roundSummary(array $round): array { return ['id' => (string) ($round['id'] ?? ''), 'number' => (int) ($round['number'] ?? 0), 'status' => (string) ($round['status'] ?? ''), 'startedAt' => (string) ($round['startedAt'] ?? ''), 'updatedAt' => (string) ($round['updatedAt'] ?? ''), 'completedAt' => (string) ($round['completedAt'] ?? ''), 'publishedAt' => (string) ($round['publishedAt'] ?? ''), 'editionHash' => (string) ($round['editionHash'] ?? '')]; }
    /** @param array<string,mixed> $legal @return array<string,mixed> */ private function legalSummary(array $legal): array { $s = is_array($legal['snapshot'] ?? null) ? $legal['snapshot'] : []; return ['snapshotHash' => (string) $legal['snapshotHash'], 'version' => $s['version'] ?? [], 'layout' => $s['layout'] ?? [], 'identity' => $s['state']['identity'] ?? [], 'finalFiles' => $s['state']['finalFiles'] ?? [], 'isbn' => $s['state']['isbn'] ?? [], 'completedAt' => (string) ($legal['completedAt'] ?? '')]; }

    /** @param array<string,mixed> $preparation @return array<string,mixed> */
    private function publicationJourneyState(int $bookId, array $preparation): array
    {
        $stored = get_post_meta($bookId, '_verbum_publication_journey', true); $stored = is_array($stored) ? $stored : [];
        $metadata = is_array($preparation['metadata'] ?? null) ? $preparation['metadata'] : [];
        $formats = is_array($preparation['edition']['formats'] ?? null) ? array_values($preparation['edition']['formats']) : ['printed', 'digital'];
        $defaults = [
            'active'=>'planning','completed'=>[],'locked'=>false,'publishedEditionId'=>'','origin'=>null,
            'planning'=>['model'=>'','publisherName'=>'','responsible'=>'','contract'=>'','responsibilities'=>'','plannedDate'=>'','territory'=>'Brasil','language'=>(string)($metadata['language']??'Português'),'availability'=>'national','formats'=>$formats,'printOnDemand'=>false,'printRun'=>'','printPrice'=>'','digitalPrice'=>'','currency'=>'BRL','priceDecision'=>'','notes'=>''],
            'tasks'=>[],'channels'=>[],'distribution'=>[],'prices'=>[],'channelMetadata'=>[],'launchActions'=>[],
            'event'=>['format'=>'','date'=>'','time'=>'','location'=>'','responsible'=>'','capacity'=>'','registration'=>'','broadcast'=>'','script'=>'','suppliers'=>'','notes'=>'','decision'=>''],
            'messages'=>['main'=>'','short'=>'','invitation'=>'','social'=>'','institutional'=>'','versions'=>[]],'materials'=>[],
            'publication'=>['status'=>'','editionNumber'=>(string)($metadata['edition']??'1ª edição'),'date'=>'','publisher'=>(string)($metadata['publisher']??''),'formats'=>$formats,'isbnPrint'=>(string)($metadata['isbn']??''),'isbnDigital'=>'','identifiers'=>'','proofs'=>[]],
            'availability'=>[],'memory'=>['decisions'=>'','futureCorrections'=>'','distributionNotes'=>'','documents'=>[]],
            'finalChecklist'=>['publicAvailable'=>false,'bibliographicChecked'=>false,'filesPreserved'=>false,'channelsVerified'=>false,'permissionsArchived'=>false],
            'finalConfirmation'=>false,'history'=>[],'updatedAt'=>'',
        ];
        return array_replace_recursive($defaults, $stored);
    }

    /** @param array<string,mixed> $state */
    private function publicationJourneyProgress(string $step, array $state, bool $preparationComplete): int
    {
        if ($step === 'planning') { $plan=(array)$state['planning']; $checks=[$preparationComplete,!empty($plan['model']),!empty($plan['formats']),!empty($plan['plannedDate']),!empty($plan['responsible']),!empty($plan['priceDecision'])]; }
        elseif ($step === 'channels') { $channels=(array)$state['channels']; $checks=[!empty($channels),count(array_filter($channels,static fn($c):bool=>is_array($c)&&!empty($c['formats'])))===count($channels),count(array_filter($channels,static fn($c):bool=>is_array($c)&&!empty($c['responsible'])))===count($channels),!empty($state['prices'])]; }
        elseif ($step === 'launch') { $checks=[!empty($state['planning']['plannedDate']),!empty($state['messages']['main']),!empty($state['materials']),!empty($state['event']['decision']),!empty($state['launchActions'])]; }
        else { $checks=[!empty($state['publication']['status']),!empty($state['publication']['date']),!empty($state['availability']),count(array_filter((array)$state['finalChecklist']))===count((array)$state['finalChecklist']),!empty($state['finalConfirmation'])]; }
        return (int) round(count(array_filter($checks))*100/max(1,count($checks)));
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $data */
    private function assertPublicationJourneyStep(string $step, array $state, array $data): void
    {
        if ($step === 'planning') { $p=(array)$state['planning']; if(empty($p['model'])||empty($p['formats'])||empty($p['plannedDate'])||empty($p['responsible'])||empty($p['priceDecision'])) throw new ValidationError('Defina modelo, formatos, data, pessoa responsável e decisão sobre preços.'); }
        elseif ($step === 'channels') {
            $channels=array_values(array_filter((array)$state['channels'],'is_array')); if($channels===[]) throw new ValidationError('Cadastre ao menos um canal de publicação.');
            foreach((array)$state['planning']['formats'] as $format) if(count(array_filter($channels,static fn(array$c):bool=>in_array($format,(array)($c['formats']??[]),true)))===0) throw new ValidationError('Cadastre ao menos um canal para cada formato selecionado.');
            foreach($channels as $channel) if(empty($channel['responsible'])) throw new ValidationError('Defina a pessoa responsável por cada canal.');
            $run=(int)($state['planning']['printRun']??0); $distributed=array_sum(array_map('intval',(array)$state['distribution'])); if($run>0&&$distributed>$run) throw new ValidationError('A distribuição não pode ultrapassar a tiragem inicial.');
            if(empty($state['prices'])&&empty($state['planning']['priceDecision'])) throw new ValidationError('Defina preços ou registre a justificativa correspondente.');
        } elseif ($step === 'launch') {
            if(empty($state['planning']['plannedDate'])||empty($state['messages']['main'])||empty($state['materials'])||empty($state['event']['decision'])||empty($state['launchActions'])) throw new ValidationError('Defina data, mensagem, materiais, decisão sobre evento e ações principais.');
            foreach((array)$state['launchActions'] as $item) if(is_array($item)&&empty($item['notApplicable'])&&empty($item['responsible'])) throw new ValidationError('Defina a pessoa responsável pelas ações principais ou registre a não aplicação.');
        } else {
            if(empty($state['publication']['status'])||empty($state['publication']['editionNumber'])||empty($state['publication']['date'])) throw new ValidationError('Informe situação, número e data da edição efetivamente publicada.');
            if(empty($state['availability'])) throw new ValidationError('Registre a disponibilidade confirmada em ao menos um canal.');
            foreach((array)$state['availability'] as $channel) if(is_array($channel)&&($channel['status']??'')==='available'&&empty($channel['confirmed'])) throw new ValidationError('Confirme explicitamente os canais marcados como disponíveis.');
            if(empty($data['preparation']['packages'])||empty($data['preparation']['files'])) throw new ValidationError('Os pacotes e arquivos finais aprovados precisam estar preservados.');
            foreach((array)$state['finalChecklist'] as $checked) if(!$checked) throw new ValidationError('Conclua toda a conferência final.');
            if(empty($state['finalConfirmation'])) throw new ValidationError('Confirme que os dados correspondem à edição efetivamente publicada.');
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function publicationEditions(int $bookId): array { $items=get_post_meta($bookId,'_verbum_published_editions',true); return is_array($items)?array_values(array_filter($items,'is_array')):[]; }
    /** @return array<string,mixed> */
    private function publicationJourneyEvent(int $userId,string $label): array { return ['id'=>'publication-event-'.substr(md5($label.microtime(true)),0,14),'label'=>$label,'userId'=>$userId,'at'=>gmdate('c')]; }
    /** @param array<int,mixed> $actions */
    private function syncPublicationCalendar(int $bookId,array $actions): void
    {
        $calendar=get_post_meta($bookId,'_verbum_editorial_calendar_events',true); $calendar=is_array($calendar)?$calendar:[];
        $next=array_values(array_filter($calendar,static fn($item):bool=>!is_array($item)||($item['source']??'')!=='publication'));
        foreach($actions as $action) if(is_array($action)&&!empty($action['title'])&&!empty($action['date'])) $next[]=['id'=>(string)($action['id']??'publication-calendar-'.substr(md5((string)$action['title'].$action['date']),0,12)),'bookId'=>$bookId,'source'=>'publication','title'=>(string)$action['title'],'date'=>(string)$action['date'],'responsible'=>(string)($action['responsible']??''),'status'=>(string)($action['status']??'planned'),'updatedAt'=>gmdate('c')];
        update_post_meta($bookId,'_verbum_editorial_calendar_events',$next);
    }

    /** @return array<string,mixed> */
    private function sanitizeArray(array $value): array
    {
        $clean = [];
        foreach ($value as $key => $item) {
            $safeKey = is_int($key) ? $key : (preg_replace('/[^A-Za-z0-9_-]/', '', (string) $key) ?? '');
            if ($safeKey === '') continue;
            if (is_array($item)) $clean[$safeKey] = $this->sanitizeArray($item);
            elseif (is_bool($item) || is_int($item) || is_float($item)) $clean[$safeKey] = $item;
            else { $raw = (string) $item; $clean[$safeKey] = filter_var($raw, FILTER_VALIDATE_URL) ? esc_url_raw($raw) : sanitize_textarea_field($raw); }
        }
        return $clean;
    }
}
