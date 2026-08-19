<?php

declare(strict_types=1);

namespace VerbumStudio\Api;

use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Library\DashboardRepository;

final class DashboardController
{
    private Config $config; private ResponseFactory $responses; private Capabilities $capabilities; private DashboardRepository $dashboard;
    public function __construct(Config$config,ResponseFactory$responses,Capabilities$capabilities,DashboardRepository$dashboard){$this->config=$config;$this->responses=$responses;$this->capabilities=$capabilities;$this->dashboard=$dashboard;}
    public function register():void{add_action('rest_api_init',function():void{$permission=[$this,'canAccess'];register_rest_route($this->config->get('api_namespace'),'/dashboard',[['methods'=>'GET','callback'=>[$this,'show'],'permission_callback'=>$permission],['methods'=>'POST','callback'=>[$this,'action'],'permission_callback'=>$permission]]);});}
    public function canAccess():bool{return$this->capabilities->currentUserCanAccess();}
    public function show():\WP_REST_Response{try{return$this->responses->success($this->dashboard->data(get_current_user_id()));}catch(\Throwable$e){return$this->responses->error($e);}}
    public function action(\WP_REST_Request$request):\WP_REST_Response{try{$payload=$request->get_json_params();return$this->responses->success($this->dashboard->action(get_current_user_id(),is_array($payload)?$payload:[]));}catch(\Throwable$e){return$this->responses->error($e);}}
}
