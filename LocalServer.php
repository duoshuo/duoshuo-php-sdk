<?php
/**
 * DuoshuoSDK 本地服务类定义
 *
 * @version		$Id: LocalServer.php 0 10:17 2012-7-23
 * @author 		shen2
 * @copyright	Copyright (c) 2012 - , Duoshuo, Inc.
 * @link		http://dev.duoshuo.com
 */
class Duoshuo_LocalServer{
	
	protected $response = array();
	
	protected $plugin;
	
	public function __construct($plugin){
		$this->plugin = $plugin;
	}
	
	/**
	 * 从服务器pull评论到本地
	 * 
	 * @param array $input
	 */
	public function sync_log($input = array()){
		$syncLock = $this->plugin->getOption('sync_lock');//检查是否正在同步评论 同步完成后该值会置0
		if(!isset($syncLock) || $syncLock > time()- 900){//正在或15分钟内发生过写回但没置0
			$response = array(
					'code'	=>	Duoshuo_Exception::SUCCESS,
					'response'=> '同步中，请稍候',
			);
			return;
		}
		
		$limit = 50;
		
		try{
			$this->plugin->updateOption('sync_lock',  time());
			
			$last_sync = $this->plugin->getOption('last_sync');
			
			$params = array(
				'since' => $last_sync,
				'limit' => $limit,
				'order' => 'asc',
			);
			
			$client = $this->plugin->getClient();
			
			$posts = array();
			$affectedThreads = array();
			$max_sync_date = 0;
			
			do{
				$response = $client->getLogList($params);
			
				$count = count($response['response']);
				
				foreach($response['response'] as $log){
					switch($log['action']){
						case 'create':
							$affected = $this->plugin->createPost($log['meta']);
							break;
						case 'approve':
						case 'spam':
						case 'delete':
							$affected = $this->plugin->moderatePost($log['action'], $log['meta']);
							break;
						case 'delete-forever':
							$affected = $this->plugin->deleteForeverPost($log['meta']);
							break;
						case 'update'://现在并没有update操作的逻辑
						default:
							$affected = array();
					}
					//合并
					
					$affectedThreads = array_merge($affectedThreads, $affected);
				
					if ($log['date'] > $max_sync_date)
						$max_sync_date = $log['date'];
				}
				
				$params['since'] = $max_sync_date;
					
			} while ($count == $limit);//如果返回和最大请求条数一致，则再取一次
			
			//唯一化
			$aidList = array_unique($affectedThreads);
					
			if ($max_sync_date > $last_sync)
				$this->plugin->updateOption('last_sync', $max_sync_date);
			
			$this->plugin->updateOption('sync_lock',  0);
			
			//更新静态文件
			if ($this->plugin->getOption('sync_to_local') && $this->plugin->getOption('seo_enabled'))
				$this->plugin->refreshThreads($aidList);
			
			$this->plugin->updateOption('sync_lock', 1);
		}
		catch(Exception $ex){
			$this->plugin->updateOption('sync_lock', $ex->getLine());
			//Showmsg($e->getMessage());
		}
		
		//$this->response['response']
		$this->response['code'] = Duoshuo_Exception::SUCCESS;
	}
	
	public function update_option($input = array()){
		//duoshuo_short_name
		//duoshuo_secret
		//duoshuo_notice
		foreach($input as $optionName => $optionValue)
			if (substr($optionName, 0, 8) === 'duoshuo_'){
				$this->plugin->updateOption(substr($optionName, 8), $optionValue);
			}
		$this->response['code'] = 0;
	}
	
	public function sendResponse(){
		echo json_encode($this->response);
	}
	
	public function dispatch($input){
		if (!isset($input['signature']))
			throw new Duoshuo_Exception('Invalid signature.', Duoshuo_Exception::INVALID_SIGNATURE);
		
		$signature = $input['signature'];
		unset($input['signature']);
		
		ksort($input);
		$baseString = http_build_query($input, null, '&');
		
		$expectSignature = base64_encode(hash_hmac('sha1', $baseString, $this->plugin->getOption('secret'), true));
		if ($signature !== $expectSignature)
			throw new Duoshuo_Exception('Invalid signature, expect: ' . $expectSignature . '. (' . $baseString . ')', Duoshuo_Exception::INVALID_SIGNATURE);
		
		$method = $input['action'];
		
		if (!method_exists($this, $method))
			throw new Duoshuo_Exception('Unknown action.', Duoshuo_Exception::OPERATION_NOT_SUPPORTED);
		
		$this->$method($input);
		$this->sendResponse();
	}
	
	static function sendException($e){
		$response = array(
			'code'	=>	$e->getCode(),
			'errorMessage'=>$e->getMessage(),
		);
		echo json_encode($response);
	}
}
