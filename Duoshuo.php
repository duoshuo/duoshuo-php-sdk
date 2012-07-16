<?php
/**
 * 多说插件 类定义
 *
 * @version		$Id: duoshuo.php 0 10:17 2012-4-27 
 * @author 		allen 
 * @package		DedeCMS.DUOSHUO
 * @copyright	Copyright (c) 2012 - , Duoshuo, Inc.
 * @link		http://www.duoshuo.com
 */
class Duoshuo_Exception extends Exception{
	const SUCCESS		= 0;
	const ENDPOINT_NOT_VALID = 1;
	const MISSING_OR_INVALID_ARGUMENT = 2;
	const ENDPOINT_RESOURCE_NOT_VALID = 3;
	const NO_AUTHENTICATED = 4;
	const INVALID_API_KEY = 5;
	const INVALID_API_VERSION = 6;
	const CANNOT_ACCESS = 7;
	const OBJECT_NOT_FOUND = 8;
	const API_NO_PRIVILEGE = 9;
	const OPERATION_NOT_SUPPORTED = 10;
	const API_KEY_INVALID = 11;
	const NO_PRIVILEGE = 12;
	const RESOURCE_RATE_LIMIT_EXCEEDED = 13;
	const ACCOUNT_RATE_LIMIT_EXCEEDED = 14;
	const INTERNAL_SERVER_ERROR = 15;
	const REQUEST_TIMED_OUT = 16;
	const NO_ACCESS_TO_THIS_FEATURE = 17;
	const INVALID_SIGNATURE = 18;

	const USER_DENIED_YOUR_REQUEST = 21;
	const EXPIRED_TOKEN = 22;
	const REDIRECT_URI_MISMATCH = 23;
	const DUPLICATE_CONNECTED_ACCOUNT = 24;

	const PLUGIN_DEACTIVATED = 30;
}

include_once DEDEROOT.'/plus/duoshuo/duoshuo_client.php';

function rfc3339_to_mysql($string){
	global $cfg_cli_time;
	if (method_exists('DateTime', 'createFromFormat')){	//	php 5.3.0
		return DateTime::createFromFormat(DateTime::RFC3339, $string)->format('Y-m-d H:i:s');
	}
	else{
		$timestamp = strtotime($string);
		return gmdate('Y-m-d H:i:s', $timestamp  + $cfg_cli_time * 3600);
	}
}

function rfc3339_to_mysql_gmt($string){
	if (method_exists('DateTime', 'createFromFormat')){	//	php 5.3.0
		return DateTime::createFromFormat(DateTime::RFC3339, $string)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}
	else{
		$timestamp = strtotime($string);
		return gmdate('Y-m-d H:i:s', $timestamp);
	}
}


function current_url()
{
	$sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
	$php_self     = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
	$path_info    = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
	$relate_url   = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
	return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
}


class Duoshuo{
	
	const DOMAIN = 'duoshuo.com';
	const STATIC_DOMAIN = 'static.duoshuo.com';
	const VERSION = '0.2.0';
	
	/**
	 *
	 * @var string
	 */
	static $prefix = 'duoshuo_';
	
	/**
	 *
	 * @var string
	 */
	static $shortName;
	
	/**
	 *
	 * @var string
	 */
	static $secret;
	
	/**
	 * 是否开启评论实时反向同步回本地
	 * @var bool
	 */
	static $syncToLocal = true;
	
	/**
	 * 是否开启SEO优化
	 * @var bool
	 */
	static $seoEnabled = false;
	
	/**
	 * 每篇文章seo显示的最大行数
	 * @var bool
	 */
	static $seoMaxRow = 100;
	
	/**
	 * 
	 */	
	static $initialized = false;
	/**
	 *
	 * @var array
	 */
	static $errorMessages = array();
	
	static $EMBED = false;
	
	static function init()
	{
		//从数据库获取结果
		self::$shortName = self::getConfig('short_name');
		self::$secret = self::getConfig('secret');
		self::$adminPath = self::getConfig('admin_path');
		self::$seoEnabled = self::getConfig('seo_enabled') !== NULL ? self::getConfig('seo_enabled') : self::$seoEnabled;
		self::$initialized = true;
	}
	
	/**
	 * 保存多说设置
	 * @param 键 $key
	 * @param 值 $value
	 * @param 键名 $info
	 * @param 类型 $type
	 * @param 组别 $groupid
	 */
	static function saveConfig($key, $value, $info = NULL,$type = NULL,$groupid = NULL){
		//本地处理
		$config = null;
		return $config;
	}
	
	static function getConfig($key){
		//self::duoshuoKey($key)
		//本地处理
		$value = null;
		if(is_array($value)){
			return $value['value'];
		}else{
			return NULL;
		}
	}
	
	static function duoshuoKey($key){
		return self::$prefix.$key;
	}
	
	/**
	 *
	 * @return DuoshuoClient
	 */
	static function getClient($userId = 0){	//如果不输入参数，就是游客
		$remoteAuth = null;
		return new DuoshuoClient(self::$shortName, self::$secret, $remoteAuth);
	}
	
	static function checkDefaultSettings($adminPath){
		$duoshuoDefaultSettings = array(
				'short_name'	=>	array(
						'value' =>	'',
						'info'	=>	'多说二级域名',
						'type'	=>	'string',
				),
				'secret'	=>	array(
						'value' =>	'',
						'info'	=>	'多说站点密钥',
						'type'	=>	'string',
				),
				'sync_lock'		=>	array(
						'value'	=>	0,
						'info'	=>	'多说正在同步时间(0表示同步正常完成)',
						'type'	=>	'int',
				),
				'last_sync'	=>	array(
						'value'	=>	0,
						'info'	=>	'已完成的最后同步时间戳',
						'type'	=>	'int',
				),
				'seo_enabled'	=>	array(
						'value'	=>	0,
						'info'	=>	'开启SEO优化',
						'type'	=>	'int',
				),
		);
		
		foreach ($duoshuoDefaultSettings as $key => $defaultSetting){
			$setting = self::getConfig($key);
			if(!isset($setting) || $setting === NULL){
				self::saveConfig($key, $defaultSetting['value'],
				$defaultSetting['info'], $defaultSetting['type']);
			}
		}
		
	}
	
	/**
	 * 从服务器pull评论到本地
	 *
	 * @param array $posts
	 */
	static function syncCommentsToLocal(){
		$syncLock = self::getConfig('sync_lock');//检查是否正在同步评论 同步完成后该值会置0
		if(!isset($syncLock) || $syncLock > time()- 900){//正在或15分钟内发生过写回但没置0
			return;
		}
		try{
			self::saveConfig('sync_lock',  time());
			
			$last_sync = self::getConfig('last_sync');
			
			$limit = 50;
			
			$params = array(
				'since' => $last_sync,
				'limit' => $limit,
				'order' => 'asc',
				'sources'=>'duoshuo,anonymous'
			);
			
			$client = self::getClient();
			
			$posts = array();
			$aidList = array();
			$max_sync_date = 0;
			
			do{
				$response = $client->getLogList($params);
			
				$count = count($response['response']);
				
				if ($count){
					//合并
					$aidList = array_merge(self::_syncCommentsToLocal($response['response']),$aidList);
					//唯一化
					$aidList = array_unique($aidList);
					foreach($response['response'] as $log){
						if ($log['date'] > $max_sync_date){
							$max_sync_date = $log['date'];
						}
					}
					$params['since'] = $max_sync_date;
				}
			} while ($count == $limit);//如果返回和最大请求条数一致，则再取一次
			
			if ($max_sync_date > $last_sync)
				self::saveConfig('last_sync', $max_sync_date);
			
			self::saveConfig('sync_lock',  0);
			
			//更新静态文件
			//本地处理
			
			self::saveConfig('sync_lock', 1);
		}
		catch(Exception $ex){
			self::saveConfig('sync_lock', $ex->getLine());
			//Showmsg($e->getMessage());
		}
	}
	
	static function sendException($e){
		$response = array(
				'code'	=>	$e->getCode(),
				'errorMessage'=>$e->getMessage(),
		);
		echo json_encode($response);
		exit;
	}

	/**
	 * 将pull回的数据写入本地数据库
	 *
	 * @param array $posts
	 */
	static function _syncCommentsToLocal($logs){
		$approvedMap = array(
				'pending'	=>	'0',
				'approved'	=>	'1',
				'deleted'	=>	'2',
				'spam'		=>	'3',
				'thread-deleted'=>'4',
		);
		$actionMap = array(
			'create' => '0',
			'update' => '0',
			'approve' => '1',
			'delete' => '2',
			'spam' => '3',
			'delete-forever' => '4',
		);
		//文章名列表
		$aidList = array();
		foreach($logs as $log){
			switch($log['action']){
				case 'create':
					//本地处理
					break;
				case 'approve':
				case 'spam':
				case 'delete':
					foreach($log['meta'] as $postId){
						//本地处理
					}
					break;
				case 'delete-forever':
					$log['meta'] = json_decode($log['meta'],false);
					foreach($log['meta'] as $postId){
						//本地处理
					}
					break;
				case 'update'://现在并没有update操作的逻辑
				default:
					break;
			}
		}
		return $aidList;
	}
}