<?php
/**
 * DuoshuoSDK Client类定义
 *
 * @version		$Id: Client.php 0 10:17 2012-7-23
 * @author 		shen2
 * @copyright	Copyright (c) 2012 - , Duoshuo, Inc.
 * @link		http://dev.duoshuo.com
 */
class Duoshuo_Client{
	var $end_point = 'http://api.duoshuo.com/';
	/**
	 * 返回值格式
	 * @var string
	 */
	var $format = 'json';
	
	var $userAgent = 'DuoshuoPhpSdk/0.2.0';
	
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
		return $this->request('GET', 'log/list', $params);
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
	
	function http($url, $postfields, $method = 'POST'){
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
	
	/**
	 * 
	 * @param array $user_data
	 */
	function remoteAuth($user_data){
	    $message = base64_encode(json_encode($user_data));
	    $time = time();
	    return $message . ' ' . self::hmacsha1($message . ' ' . $time, $this->secret) . ' ' . $time;
	}
	
	// from: http://www.php.net/manual/en/function.sha1.php#39492
	// Calculate HMAC-SHA1 according to RFC2104
	// http://www.ietf.org/rfc/rfc2104.txt
	static function hmacsha1($data, $key) {
		if (function_exists('hash_hmac'))
			return hash_hmac('sha1', $data, $key);
		
	    $blocksize=64;
	    $hashfunc='sha1';
	    if (strlen($key)>$blocksize)
	        $key=pack('H*', $hashfunc($key));
	    $key=str_pad($key,$blocksize,chr(0x00));
	    $ipad=str_repeat(chr(0x36),$blocksize);
	    $opad=str_repeat(chr(0x5c),$blocksize);
	    $hmac = pack(
	                'H*',$hashfunc(
	                    ($key^$opad).pack(
	                        'H*',$hashfunc(
	                            ($key^$ipad).$data
	                        )
	                    )
	                )
	            );
	    return bin2hex($hmac);
	}
}
