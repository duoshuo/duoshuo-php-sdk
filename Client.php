<?php
/**
 * DuoshuoSDK Client类定义
 *
 * @version		$Id: Client.php 0 16:35 2013-4-11
 * @author 		xiaowu
 * @copyright	Copyright (c) 2012 - , Duoshuo, Inc.
 * @link		http://dev.duoshuo.com
 */
require 'EasyHttp.php';
require 'EasyHttp/Curl.php';
require 'EasyHttp/Cookie.php';
require 'EasyHttp/Encoding.php';
require 'EasyHttp/Fsockopen.php';
require 'EasyHttp/Proxy.php';
require 'EasyHttp/Streams.php';
/**
 * 
 * @link http://duoshuo.com/
 * @author shen2
 *
 */
class Duoshuo_Client{
	var $end_point = 'http://api.duoshuo.com/';
	/**
	 * 返回值格式
	 * @var string
	 */
	var $format = 'json';
	
	var $userAgent = 'DuoshuoPhpSdk/0.3.0';
	
	var $connecttimeout = 30;
	var $timeout = 60;
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
		$args = array(
				'method' => $method,    //  GET/POST
				'timeout' => $this->timeout,  //  超时的秒数
				'redirection' => 5,     //  最大重定向次数
				'httpversion' => '1.0', //  1.0/1.1
				'user-agent' => $this->userAgent,
				//'blocking' => true,     //  是否阻塞
				'headers' 	=> array('Expect'=>''),   //  header信息
				//'cookies' => array(),   //  关联数组形式的cookie信息
				//'compress' => false,    //  是否压缩
				//'decompress' => true,   //  是否自动解压缩结果
				'sslverify' => true,
				//'stream' => false,
				//'filename' => null      //  如果stream = true，则必须设定一个临时文件名
		);
		switch($method){
			case 'GET':
				$url .= '?' . http_build_query($params, null, '&');	// overwrite arg_separator.output
				break;
			case 'POST':
				$headers = array();
				$args['body'] =  http_build_query($params);
				break;
			default:
		}
		$http = new EasyHttp();
		$response = $http->request($url, $args);
		if (isset($response->errors)){
			if (isset($response->errors['http_request_failed'])){
				$message = $response->errors['http_request_failed'][0];
				if ($message == 'name lookup timed out')
					$message = 'DNS解析超时，请重试或检查你的主机的域名解析(DNS)设置。';
				elseif (stripos($message, 'Could not open handle for fopen') === 0)
					$message = '无法打开fopen句柄，请重试或联系多说管理员。http://dev.duoshuo.com/';
				elseif (stripos($message, 'Couldn\'t resolve host') === 0)
					$message = '无法解析duoshuo.com域名，请重试或检查你的主机的域名解析(DNS)设置。';
				elseif (stripos($message, 'Operation timed out after ') === 0)
					$message = '操作超时，请重试或联系多说管理员。http://dev.duoshuo.com/';
				throw new Duoshuo_Exception($message, Duoshuo_Exception::REQUEST_TIMED_OUT);
			}
            else
            	throw new Duoshuo_Exception('连接服务器失败, 详细信息：' . json_encode($response->errors), Duoshuo_Exception::REQUEST_TIMED_OUT);
		}

		$json = json_decode($response['body'], true);
		return $json === null ? $response['body'] : $json;
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
			return hash_hmac('sha1', $data, $key,true);
		
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
