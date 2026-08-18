<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\FoundationIntentionRepository;
use VerbumStudio\Library\FoundationLetterSoulRepository;
use VerbumStudio\Library\FoundationReaderResultRepository;
use VerbumStudio\Library\LibraryRepository;

final class FoundationReaderResultController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private FoundationReaderResultRepository $readerResult;
    private FoundationLetterSoulRepository $letterSoul;
    private FoundationIntentionRepository $intention;

    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library, FoundationReaderResultRepository $readerResult, FoundationLetterSoulRepository $letterSoul, FoundationIntentionRepository $intention)
    {
        $this->config = $config; $this->responses = $responses; $this->capabilities = $capabilities;
        $this->library = $library; $this->readerResult = $readerResult; $this->letterSoul = $letterSoul; $this->intention = $intention;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace = $this->config->get('api_namespace'); $permission = [$this, 'canAccess'];
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/reader-result', [
                ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $permission],
                ['methods' => 'PATCH', 'callback' => [$this, 'save'], 'permission_callback' => $permission],
            ]);
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/reader-result/complete', ['methods' => 'POST', 'callback' => [$this, 'complete'], 'permission_callback' => $permission]);
            register_rest_route($namespace, '/books/(?P<id>\d+)/foundation/reader-result/coherence', ['methods' => 'POST', 'callback' => [$this, 'coherence'], 'permission_callback' => $permission]);
        });
    }

    public function canAccess(): bool { return $this->capabilities->currentUserCanAccess(); }
    public function show(\WP_REST_Request $request): \WP_REST_Response { try { $id=(int)$request['id']; $this->assertOwned($id); return $this->responses->success($this->readerResult->data($id)); } catch (\Throwable $e) { return $this->responses->error($e); } }
    public function save(\WP_REST_Request $request): \WP_REST_Response { try { $id=(int)$request['id']; $this->assertOwned($id); return $this->responses->success($this->readerResult->save($id,$this->payload($request))); } catch (\Throwable $e) { return $this->responses->error($e); } }
    public function complete(\WP_REST_Request $request): \WP_REST_Response { try { $id=(int)$request['id']; $this->assertOwned($id); return $this->responses->success($this->readerResult->complete($id)); } catch (\Throwable $e) { return $this->responses->error($e); } }

    public function coherence(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id=(int)$request['id']; $this->assertOwned($id); $current=$this->payload($request);
            $reader=$this->readerResult->data($id); $letter=$this->letterSoul->data($id); $intention=$this->intention->data($id);
            foreach (['needs','transformation','differential'] as $required) if (trim((string)($current[$required]??''))==='') throw new ValidationError('Preencha os campos essenciais de Leitor e Resultado antes de verificar a coerência.');
            $theme=trim((string)get_post_meta($id,'_verbum_work_project_theme',true));
            $genre=trim((string)get_post_meta($id,'_verbum_genre',true));
            $approach=trim((string)get_post_meta($id,'_verbum_planning_approach',true));
            $objectives=array_map(static fn(array $o):string=>(string)($o['text']??''),(array)($intention['specificObjectives']??[]));
            $input="PÚBLICO PRINCIPAL\n".$reader['audience']."\n\nTEMA\n{$theme}\n\nGÊNERO\n{$genre}\n\nABORDAGEM\n{$approach}\n\nCARTA\n".wp_strip_all_tags((string)$letter['letterHtml'])."\n\nALMA\n".$letter['soul']."\n\nPROBLEMA\n".$intention['problem']."\n\nPROPÓSITO\n".$intention['purpose']."\n\nOBJETIVO GERAL\n".$intention['generalObjective']."\n\nOBJETIVOS ESPECÍFICOS\n".implode("\n",$objectives)."\n\nNECESSIDADES DO LEITOR\n".$current['needs']."\n\nTRANSFORMAÇÃO\n".$current['transformation']."\n\nDIFERENCIAL\n".$current['differential']."\n\nABORDARÁ\n".($current['scope_included']??'')."\n\nNÃO ABORDARÁ\n".($current['scope_excluded']??'');
            $json=$this->callOpenAIJson('Você é o Assistente de Coerência do Verbum Studio. Analise somente os textos fornecidos. Verifique alinhamento entre público, necessidades, problema, transformação, objetivos, diferencial e limites; identifique repetições, lacunas e contradições. Não invente dados. Responda somente JSON válido com arrays coherent_points, attention_points, contradictions e suggestions. Observações usam fields e observation. Sugestões usam field (needs, transformation, differential, scope_included ou scope_excluded), current_text, suggested_text e reason.',$input,2400);
            return $this->responses->success(['coherentPoints'=>$this->observations($json['coherent_points']??[]),'attentionPoints'=>$this->observations($json['attention_points']??[]),'contradictions'=>$this->observations($json['contradictions']??[]),'suggestions'=>$this->suggestions($json['suggestions']??[])]);
        } catch (\Throwable $e) { return $this->responses->error($e); }
    }

    private function assertOwned(int $id): void { $this->library->workspaceForBook(get_current_user_id(),$id); }
    /** @return array<string,mixed> */
    private function payload(\WP_REST_Request $request): array
    {
        $p=$request->get_json_params(); $p=is_array($p)?$p:[]; $clean=[];
        foreach(['needs','transformation','differential','scope_included','scope_excluded'] as $f) if(array_key_exists($f,$p)) $clean[$f]=sanitize_textarea_field((string)$p[$f]);
        if(array_key_exists('base_revision',$p)) $clean['base_revision']=max(0,(int)$p['base_revision']);
        return $clean;
    }
    /** @return array<string,mixed> */
    private function callOpenAIJson(string $instructions,string $input,int $max): array
    {
        $key=trim((string)$this->config->get('openai_api_key','')); if($key==='') throw new ValidationError('A Assistência de coerência está indisponível porque VERBUM_OPENAI_API_KEY ainda não foi configurada no servidor.');
        $response=wp_remote_post('https://api.openai.com/v1/responses',['timeout'=>60,'headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],'body'=>wp_json_encode(['model'=>'gpt-5.6-luna','instructions'=>$instructions,'input'=>$input,'max_output_tokens'=>$max])]);
        if(is_wp_error($response)) throw new ValidationError('Não foi possível acessar a inteligência artificial neste momento.');
        $status=(int)wp_remote_retrieve_response_code($response); $body=json_decode((string)wp_remote_retrieve_body($response),true); if($status<200||$status>=300||!is_array($body)) throw new ValidationError('A inteligência artificial não conseguiu concluir a solicitação. Tente novamente.');
        $text=''; foreach((array)($body['output']??[]) as $item){if(!is_array($item)||($item['type']??'')!=='message')continue;foreach((array)($item['content']??[]) as $content)if(is_array($content)&&($content['type']??'')==='output_text')$text.=(string)($content['text']??'');}
        if(preg_match('/^```(?:json)?\s*(.*?)\s*```$/s',trim($text),$m))$text=trim((string)($m[1]??'')); $json=json_decode(trim($text),true); if(!is_array($json))throw new ValidationError('A inteligência artificial retornou uma resposta inválida. Tente novamente.'); return $json;
    }
    /** @param mixed $items @return array<int,array<string,mixed>> */
    private function observations($items): array { $r=[];foreach(is_array($items)?$items:[] as $item){if(!is_array($item))continue;$o=trim(sanitize_textarea_field((string)($item['observation']??'')));if($o==='')continue;$fields=array_values(array_filter(array_map(static fn($f):string=>sanitize_text_field((string)$f),is_array($item['fields']??null)?$item['fields']:[])));$r[]=['fields'=>$fields,'observation'=>$o];}return $r; }
    /** @param mixed $items @return array<int,array<string,string>> */
    private function suggestions($items): array { $allowed=['needs','transformation','differential','scope_included','scope_excluded'];$r=[];foreach(is_array($items)?$items:[] as $item){if(!is_array($item))continue;$f=sanitize_key((string)($item['field']??''));$s=trim(sanitize_textarea_field((string)($item['suggested_text']??'')));if(!in_array($f,$allowed,true)||$s==='')continue;$r[]=['field'=>$f,'currentText'=>trim(sanitize_textarea_field((string)($item['current_text']??''))),'suggestedText'=>$s,'reason'=>trim(sanitize_textarea_field((string)($item['reason']??'')))];}return $r; }
}
