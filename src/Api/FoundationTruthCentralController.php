<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Exceptions\ValidationError;
use VerbumStudio\Library\FoundationIntentionRepository;
use VerbumStudio\Library\FoundationLetterSoulRepository;
use VerbumStudio\Library\FoundationReaderResultRepository;
use VerbumStudio\Library\FoundationTruthCentralRepository;
use VerbumStudio\Library\LibraryRepository;

final class FoundationTruthCentralController
{
    private Config $config;
    private ResponseFactory $responses;
    private Capabilities $capabilities;
    private LibraryRepository $library;
    private FoundationTruthCentralRepository $truth;
    private FoundationLetterSoulRepository $letter;
    private FoundationIntentionRepository $intention;
    private FoundationReaderResultRepository $reader;

    public function __construct(Config $config, ResponseFactory $responses, Capabilities $capabilities, LibraryRepository $library, FoundationTruthCentralRepository $truth, FoundationLetterSoulRepository $letter, FoundationIntentionRepository $intention, FoundationReaderResultRepository $reader)
    {
        $this->config=$config;$this->responses=$responses;$this->capabilities=$capabilities;$this->library=$library;
        $this->truth=$truth;$this->letter=$letter;$this->intention=$intention;$this->reader=$reader;
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            $namespace=$this->config->get('api_namespace');$permission=[$this,'canAccess'];$base='/books/(?P<id>\d+)/foundation/truth-central';
            register_rest_route($namespace,$base,[['methods'=>'GET','callback'=>[$this,'show'],'permission_callback'=>$permission],['methods'=>'PATCH','callback'=>[$this,'save'],'permission_callback'=>$permission]]);
            register_rest_route($namespace,$base.'/complete',['methods'=>'POST','callback'=>[$this,'complete'],'permission_callback'=>$permission]);
            register_rest_route($namespace,$base.'/generate-thesis',['methods'=>'POST','callback'=>[$this,'generateThesis'],'permission_callback'=>$permission]);
            register_rest_route($namespace,$base.'/generate-phrases',['methods'=>'POST','callback'=>[$this,'generatePhrases'],'permission_callback'=>$permission]);
            register_rest_route($namespace,$base.'/coherence',['methods'=>'POST','callback'=>[$this,'coherence'],'permission_callback'=>$permission]);
        });
    }

    public function canAccess(): bool { return $this->capabilities->currentUserCanAccess(); }
    public function show(\WP_REST_Request $r):\WP_REST_Response{try{$id=(int)$r['id'];$this->owned($id);return $this->responses->success($this->truth->data($id));}catch(\Throwable $e){return $this->responses->error($e);}}
    public function save(\WP_REST_Request $r):\WP_REST_Response{try{$id=(int)$r['id'];$this->owned($id);return $this->responses->success($this->truth->save($id,$this->payload($r)));}catch(\Throwable $e){return $this->responses->error($e);}}
    public function complete(\WP_REST_Request $r):\WP_REST_Response{try{$id=(int)$r['id'];$this->owned($id);return $this->responses->success($this->truth->complete($id));}catch(\Throwable $e){return $this->responses->error($e);}}

    public function generateThesis(\WP_REST_Request $r):\WP_REST_Response
    {
        try{$id=(int)$r['id'];$this->owned($id);$context=$this->context($id,$this->payload($r));$json=$this->callAi('Formule de uma a três sugestões de tese para uma obra. Cada tese deve ser uma afirmação clara, demonstrável e fiel aos textos fornecidos; não pode ser pergunta, slogan nem prometer algo fora dos limites. Não invente fatos. Responda somente JSON válido: {"suggestions":[{"text":"...","reason":"..."}]}.',$context,1500);$out=[];foreach((array)($json['suggestions']??[]) as $x){if(!is_array($x))continue;$text=trim(sanitize_textarea_field((string)($x['text']??'')));if($text!=='')$out[]=['text'=>$text,'reason'=>trim(sanitize_textarea_field((string)($x['reason']??'')))];}if($out===[])throw new ValidationError('A inteligência artificial não apresentou uma tese válida. Tente novamente.');return $this->responses->success(['suggestions'=>array_slice($out,0,3)]);}catch(\Throwable $e){return $this->responses->error($e);}
    }

    public function generatePhrases(\WP_REST_Request $r):\WP_REST_Response
    {
        try{$id=(int)$r['id'];$this->owned($id);$p=$this->payload($r);if(trim(wp_strip_all_tags((string)($p['thesis_html']??'')))==='')throw new ValidationError('Escreva a Tese principal antes de gerar opções de frase.');$json=$this->callAi('Gere de três a cinco frases-síntese, cada uma com até 180 caracteres, que comuniquem com sobriedade e fidelidade a tese da obra. Evite exagero publicitário e não invente fatos. Responda somente JSON válido: {"options":["..."]}.',$this->context($id,$p),1500);$out=[];foreach((array)($json['options']??[]) as $x){$v=trim(sanitize_textarea_field((string)$x));if($v!==''&&$this->length($v)<=180&&!in_array($v,$out,true))$out[]=$v;}if($out===[])throw new ValidationError('A inteligência artificial não apresentou frases válidas. Tente novamente.');return $this->responses->success(['options'=>array_slice($out,0,5)]);}catch(\Throwable $e){return $this->responses->error($e);}
    }

    public function coherence(\WP_REST_Request $r):\WP_REST_Response
    {
        try{$id=(int)$r['id'];$this->owned($id);$json=$this->callAi('Faça uma conferência completa da Fundação. Avalie continuidade, público, problema e transformação, propósito e objetivos, diferencial, limites, consistência da tese e fidelidade da frase-síntese. Não invente dados. Responda somente JSON válido com general_assessment (texto) e arrays coherent_points, attention_points, contradictions, incomplete_fields e suggestions. Observações usam fields e observation. Sugestões usam field (thesis_html ou synthesis_phrase), current_text, suggested_text e reason.',$this->context($id,$this->payload($r)),2600);return $this->responses->success(['generalAssessment'=>trim(sanitize_textarea_field((string)($json['general_assessment']??''))),'coherentPoints'=>$this->observations($json['coherent_points']??[]),'attentionPoints'=>$this->observations($json['attention_points']??[]),'contradictions'=>$this->observations($json['contradictions']??[]),'incompleteFields'=>array_values(array_filter(array_map(static fn($x):string=>sanitize_text_field((string)$x),(array)($json['incomplete_fields']??[])))),'suggestions'=>$this->suggestions($json['suggestions']??[])]);}catch(\Throwable $e){return $this->responses->error($e);}
    }

    private function owned(int $id):void{$this->library->workspaceForBook(get_current_user_id(),$id);}
    /** @return array<string,mixed> */
    private function payload(\WP_REST_Request $r):array{$p=$r->get_json_params();$p=is_array($p)?$p:[];$out=[];if(array_key_exists('thesis_html',$p))$out['thesis_html']=wp_kses_post((string)$p['thesis_html']);if(array_key_exists('synthesis_phrase',$p))$out['synthesis_phrase']=sanitize_textarea_field((string)$p['synthesis_phrase']);if(array_key_exists('base_revision',$p))$out['base_revision']=max(0,(int)$p['base_revision']);return $out;}
    private function context(int $id,array $current):string
    {
        $l=$this->letter->data($id);$i=$this->intention->data($id);$rr=$this->reader->data($id);$t=$this->truth->data($id);$objectives=array_map(static fn($o):string=>(string)($o['text']??''),(array)($i['specificObjectives']??[]));
        return "TEMA\n".get_post_meta($id,'_verbum_work_project_theme',true)."\n\nGÊNERO\n".get_post_meta($id,'_verbum_genre',true)."\n\nABORDAGEM\n".get_post_meta($id,'_verbum_planning_approach',true)."\n\nPÚBLICO\n".$rr['audience']."\n\nCARTA\n".wp_strip_all_tags((string)$l['letterHtml'])."\n\nALMA\n".$l['soul']."\n\nPROBLEMA\n".$i['problem']."\n\nPROPÓSITO\n".$i['purpose']."\n\nOBJETIVO GERAL\n".$i['generalObjective']."\n\nOBJETIVOS ESPECÍFICOS\n".implode("\n",$objectives)."\n\nNECESSIDADES\n".$rr['needs']."\n\nTRANSFORMAÇÃO\n".$rr['transformation']."\n\nDIFERENCIAL\n".$rr['differential']."\n\nABORDARÁ\n".$rr['scopeIncluded']."\n\nNÃO ABORDARÁ\n".$rr['scopeExcluded']."\n\nTESE ATUAL\n".wp_strip_all_tags((string)($current['thesis_html']??$t['thesisHtml']))."\n\nFRASE-SÍNTESE\n".($current['synthesis_phrase']??$t['synthesisPhrase']);
    }
    /** @return array<string,mixed> */
    private function callAi(string $instructions,string $input,int $max):array{$key=trim((string)$this->config->get('openai_api_key',''));if($key==='')throw new ValidationError('A inteligência artificial está indisponível porque VERBUM_OPENAI_API_KEY ainda não foi configurada no servidor.');$res=wp_remote_post('https://api.openai.com/v1/responses',['timeout'=>60,'headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],'body'=>wp_json_encode(['model'=>'gpt-5.6-luna','instructions'=>$instructions,'input'=>$input,'max_output_tokens'=>$max])]);if(is_wp_error($res))throw new ValidationError('Não foi possível acessar a inteligência artificial neste momento.');$status=(int)wp_remote_retrieve_response_code($res);$body=json_decode((string)wp_remote_retrieve_body($res),true);if($status<200||$status>=300||!is_array($body))throw new ValidationError('A inteligência artificial não conseguiu concluir a solicitação. Tente novamente.');$text='';foreach((array)($body['output']??[])as$item){if(!is_array($item)||($item['type']??'')!=='message')continue;foreach((array)($item['content']??[])as$c)if(is_array($c)&&($c['type']??'')==='output_text')$text.=(string)($c['text']??'');}if(preg_match('/^```(?:json)?\s*(.*?)\s*```$/s',trim($text),$m))$text=trim((string)($m[1]??''));$json=json_decode(trim($text),true);if(!is_array($json))throw new ValidationError('A inteligência artificial retornou uma resposta inválida. Tente novamente.');return$json;}
    /** @param mixed $items @return array<int,array<string,mixed>> */
    private function observations($items):array{$r=[];foreach(is_array($items)?$items:[]as$x){if(!is_array($x))continue;$o=trim(sanitize_textarea_field((string)($x['observation']??'')));if($o==='')continue;$r[]=['fields'=>array_values(array_filter(array_map(static fn($f):string=>sanitize_text_field((string)$f),(array)($x['fields']??[])))),'observation'=>$o];}return$r;}
    /** @param mixed $items @return array<int,array<string,string>> */
    private function suggestions($items):array{$r=[];foreach(is_array($items)?$items:[]as$x){if(!is_array($x))continue;$f=sanitize_key((string)($x['field']??''));$text=trim(sanitize_textarea_field((string)($x['suggested_text']??'')));if(!in_array($f,['thesis_html','synthesis_phrase'],true)||$text==='')continue;if($f==='synthesis_phrase'&&$this->length($text)>180)continue;$r[]=['field'=>$f,'currentText'=>trim(sanitize_textarea_field((string)($x['current_text']??''))),'suggestedText'=>$text,'reason'=>trim(sanitize_textarea_field((string)($x['reason']??'')))];}return$r;}
    private function length(string $v):int{return function_exists('mb_strlen')?mb_strlen($v):strlen($v);}
}
