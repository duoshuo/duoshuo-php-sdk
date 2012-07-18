<?php
/**
 * 
 * @link http://duoshuo.com/
 * @author shen2
 *
 */
class DuoshuoClient{
	var $end_point = 'http://api.duoshuo.com/';
	/**
	 * 返回值格式
	 * @var string
	 */
	var $format = 'json';
	
	var $userAgent = 'DuoshuoSDK/0.2.0';
	
	var $connecttimeout = 30;
	var $timeout = 30;
	var $shortName;
	var $secret;
	var $accessToken;
	var $http;
	
	function __construct($shortName = null, $secret = null, $remoteAuth = null, $accessToken = null){
		$this->shortName = $shortName;
		$this->secret = $secret;
		$this->remoteAuth = $remoteAuth;
		$this->accessToken = $accessToken;
	}
	
	function getLogList($params){
		$this->request('GET', 'log/list', $params);
	}
	
	/**
	 * 
	 * @param $method
	 * @param $path
	 * @param $params
	 * @throws Duoshuo_Exception
	 * @return array
	 */
	function request($method, $path, $params = array()){
		$params['short_name'] = $this->shortName;
		$params['secret'] = $this->secret;
		$params['remote_auth'] = $this->remoteAuth;
		
		if ($this->accessToken)
			$params['access_token'] = $this->accessToken;
		
		$url = $this->end_point . $path. '.' . $this->format;
		
		return $this->httpRequest($url, $method, $params);
	}
	
	function httpRequest($url, $method, $params){
		$body = NULL;
		switch($method){
			case 'GET':
				$url .= '?' . http_build_query($params, null, '&');	// overwrite arg_separator.output
				break;
			case 'POST':
				$headers = array();
				$body = http_build_query($params);	//未支持multi
				break;
			default:
		}
		$response = $this->http($url,$body, $method);
			
		if (isset($response->curlErr)){
			throw new Duoshuo_Exception('Curl错误：'.$response->curlErr, Duoshuo_Exception::CANNOT_ACCESS);
		}

		$json = json_decode($response, true);
		
		return $json === null ? $response : $json;
	}
	
	/**
	 * 
	 * @param string $type
	 * @param array $keys
	 */
	function getAccessToken( $type, $keys ) {
		$params = array(
			'client_id'	=>	$this->shortName,
			'client_secret' => $this->secret,
		);
		
		switch($type){
		case 'token':
			$params['grant_type'] = 'refresh_token';
			$params['refresh_token'] = $keys['refresh_token'];
			break;
		case 'code':
			$params['grant_type'] = 'authorization_code';
			$params['code'] = $keys['code'];
			$params['redirect_uri'] = $keys['redirect_uri'];
			break;
		case 'password':
			$params['grant_type'] = 'password';
			$params['username'] = $keys['username'];
			$params['password'] = $keys['password'];
			break;
		default:
			throw new Duoshuo_Exception("wrong auth type");
		}

		$accessTokenUrl = 'http://api.duoshuo.com/oauth2/access_token';
		$response = $this->httpRequest($accessTokenUrl, 'POST', $params);
		
		$token = $response;
		if ( is_array($token) && !isset($token['error']) ) {
			$this->access_token = $token['access_token'];
			if (isset($token['refresh_token'])) //	可能没有refresh_token
				$this->refresh_token = $token['refresh_token'];
		} else {
			throw new Duoshuo_Exception("get access token failed." . $token['error']);
		}
		
		return $token;
	}
	
	protected function http($url, $postfields, $method = 'POST'){
		$ci = curl_init();
		/* Curl settings */
		curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($ci, CURLOPT_USERAGENT,$this->userAgent);
		curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
		curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ci, CURLOPT_ENCODING, "");
		curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ci, CURLOPT_HEADER, FALSE);
	
		switch ($method) {
			case 'POST':
				curl_setopt($ci, CURLOPT_POST, TRUE);
				if (!empty($postfields)) {
					curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
					$this->postdata = $postfields;
				}
				break;
			case 'DELETE':
				curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
				if (!empty($postfields)) {
					$url = "{$url}?{$postfields}";
				}
		}
		curl_setopt($ci, CURLOPT_URL, $url );
	
		curl_setopt($ci, CURLINFO_HEADER_OUT, FALSE );
	
		$response = curl_exec($ci);
		
		if($response === false){
			$response->curlErr = curl_error($ci);
		}
		
		return $response;
	}
}