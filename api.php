<?php
require_once('Duoshuo.php');

if (!extension_loaded('json'))
	include_once 'compat_json.php';

if (!headers_sent()) {
	//nocache_headers();//max age TODO:
	header('Content-Type: text/javascript; charset=utf-8');
}

if (!class_exists('Duoshuo')){
	$response = array(
		'code'			=>	30,
		'errorMessage'	=>	'Duoshuo plugin hasn\'t been activated.'
	);
	echo json_encode($response);
	exit;
}else{
	if(!Duoshuo::$initialized){
		Duoshuo::init();
	}
}
class DuoshuoLocalServer{
	
	protected $response = array();
	
	/**
	 * 
	 * @param array $input
	 */
	public function sync_posts($input = array()){
		$this->response['response'] = Duoshuo::syncCommentsToLocal();
		$this->response['code'] = 0;
	}
	
	public function update_option($input = array()){
		//duoshuo_short_name
		//duoshuo_secret
		//duoshuo_notice
		foreach($input as $optionName => $optionValue)
			if (substr($optionName, 0, 8) === 'duoshuo_'){
				Duoshuo::saveConfig(substr($optionName, 8), $optionValue);
			}
		$this->response['code'] = 0;
	}
	
	public function sendResponse(){
		echo json_encode($this->response);		
	}
}

try{
	if ($_SERVER['REQUEST_METHOD'] == 'POST'){
		$input = $_POST;
		if (!isset($input['signature']))
			throw new Duoshuo_Exception('Invalid signature.', Duoshuo_Exception::INVALID_SIGNATURE);
		
		$signature = $input['signature'];
		unset($input['signature']);
		
		if (isset($input['spam_confirmed']))	//D-Z Theme 会给POST设置这个参数
			unset($input['spam_confirmed']);
		
		ksort($input);
		$baseString = http_build_query($input, null, '&');
		
		$secret = Duoshuo::$secret;
		$expectSignature = base64_encode(hash_hmac('sha1', $baseString, $secret, true));
		if ($signature !== $expectSignature)
			throw new Duoshuo_Exception('Invalid signature, expect: ' . $expectSignature . '. (' . $baseString . ')', Duoshuo_Exception::INVALID_SIGNATURE);
		
		$server = new DuoshuoLocalServer();
		$method = $input['action'];
		
		if (!method_exists($server, $method))
			throw new Duoshuo_Exception('Unknown action.', Duoshuo_Exception::OPERATION_NOT_SUPPORTED);
		
		$server->$method($input);
		$server->sendResponse();
	}
}
catch (Exception $e){
	Duoshuo::sendException($e);
}
