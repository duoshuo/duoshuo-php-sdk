<?php
/**
 * DuoshuoSDK 本地服务类定义
 *
 * @version		$Id: LocalServer.php 0 16:28 2013-4-11
 * @author 		xiaowu
 * @copyright	Copyright (c) 2012 - , Duoshuo, Inc.
 * @link		http://dev.duoshuo.com
 */
class Duoshuo_SDK extends Duoshuo_Abstract{
	
	const VERSION = '0.3.0';
	
	public static $approvedMap = array(
		'pending' => '0',
		'approved' => '1',
		'deleted' => '2',
		'spam' => '3',
		'thread-deleted'=>'4',
	);
	public static $actionMap = array(
		'create' => '0',
		'update' => '0',
		'approve' => '1',
		'delete' => '2',
		'spam' => '3',
		'delete-forever' => '4',
	);
	/**
	 *
	 * @var array
	 */
	public static $errorMessages = array();
	
	public static $EMBED = false;
	
	public static function getInstance(){
		if (self::$_instance === null)
			self::$_instance = new self();
		return self::$_instance;
	}
	
	public static function timezone(){
		global $cfg_cli_time;
		return $cfg_cli_time;
	}
	
	/**
	 * 保存多说设置
	 * @param 键 $key
	 * @param 值 $value
	 * @param 键名 $info
	 * @param 类型 $type
	 * @param 组别 $groupid
	 */
	public function updateOption($key, $value){//, $info = NULL,$type = NULL,$groupid = NULL){
		/*
		 * 以下为dedecms中的处理过程，仅作为示例 
		global $dsql;
		$oldvalue = $this->getOption($key);
		if($oldvalue===NULL){
			$info = isset($info) ? $info : '多说设置项'; //默认值
			$type = isset($type) ? $type : 'string';	//默认值
			$groupid = isset($groupid) ? $groupid : 8;	//默认值
			
			$sql = "INSERT into #@__sysconfig (varname, value, info, type, groupid) values ('duoshuo_$key','$value','$info','$type',$groupid)";
		}
		else{
			$sql = "UPDATE #@__sysconfig SET "
			.(" value = '$value'")
			.(isset($info) ? ",info = '$info',": "")
			.(isset($type) ? ",type = '$type',": "")
			.(isset($groupid) ? ",groupid = '$groupid' ": "")
			." WHERE varname = 'duoshuo_$key'";
		}
		$option = $dsql->ExecuteNoneQuery($sql);
		$this->options[$key] = $value;
		return $option;
		*/
	}
	
	public function getOption($key){
		//short_name,secret，sync_to_local等信息可以直接在下方填写，sync_lock和last_sync等信息建议写入数据库保存
		$this->options = array();
		$this->options['short_name'] = '';//注册了abc.duoshuo.com,short_name即abc
		$this->options['secret'] = '';//在http://abc.duoshuo.com/settings查看
		$this->options['sync_to_local'] = 1;//1表示开启同步回本地，0表示关闭
		$this->options['sync_lock'] = 0;//用于防止瞬间发起多次同步请求，请配置setOption函数进行存储
		$this->options['last_sync'] = 0;//用于记录最后同步的log_id，请配置setOption函数进行存储
		
		if(isset($this->options[$key])){
			return $this->options[$key];
		}else{
			return NULL;
		}
		/*
		 * 以下为dedecms中的处理过程，仅作为示例
		if(isset($this->options[$key])){
			return $this->options[$key];
		}else{
			global $dsql;
			$sql = "SELECT value FROM #@__sysconfig WHERE varname = 'duoshuo_$key'";
			$value = $dsql->GetOne($sql);
			if(is_array($value)){
				$this->options[$key] = $value['value'];
				return $value['value'];
			}
			else{
				return NULL;
			}
		}
		*/
	}
	
	public static function currentUrl(){
		$sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
		$php_self	 = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
		$path_info	= isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
		$relate_url   = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
		return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
	}
	
	static function sendException($e){
		$response = array(
			'code'	=>	$e->getCode(),
			'errorMessage'=>$e->getMessage(),
		);
		echo json_encode($response);
		exit;
	}
	
	public function createPost($meta){
		//注意防止sql注入 title,author_name,message
		//gbk站点注意对utf-8数据进行转换，dedecms示例中有utf-8转gbk示例。
		
		/*
		 * 以下为dedecms gbk中的处理过程，仅作为示例
		global $dsql;
		//查找同步记录
		$postId = $meta['post_id'];
		$sql = "SELECT * FROM duoshuo_commentmeta WHERE post_id = $postId";
		$synced = $dsql->GetOne($sql);
		if(is_array($synced)){//create操作的评论，没同步过才处理
			return array();
		}
		if(!empty($meta['thread_key'])){
			$aid = $meta['thread_key'];
			$sql = "SELECT typeid, title FROM #@__archives WHERE id = $aid";
			$thread = $dsql->GetOne($sql);
			if(is_array($thread)){
				//注意防止sql注入 title,author_name,message
				$title = addslashes($thread['title']);
				$threadKey = $meta['thread_key'];
				$author_name = addslashes(iconv("UTF-8","GBK",trim(strip_tags($meta['author_name']))));
				$ip = $meta['ip'];
				$ischeck = self::$approvedMap[$meta['status']];
				$dtime = strtotime($meta['created_at']);
				$message = addslashes(iconv("UTF-8","GBK",strip_tags($meta['message'])));
				$typeId = $thread['typeid'];
				$sql = "INSERT INTO #@__feedback (aid,typeid,username,arctitle,ip,ischeck,dtime,mid,bad,good,ftype,face,msg) VALUES ("
				."$threadKey,$typeId,'$author_name','$title','$ip',$ischeck,'$dtime',1,0,0,'feedback',1,'$message')";
				$dsql->ExecuteNoneQuery($sql);
				$last_id = $dsql->GetLastID();
				$sql = "INSERT INTO duoshuo_commentmeta (post_id,cid) VALUES ($postId,$last_id)";
				$dsql->ExecuteNoneQuery($sql);
				return array($aid);
			}//没有文章直接略去评论
		}
		return null;*/
	}
	
	public function moderatePost($action, $postIdArray){
		/*
		 * 以下为dedecms中的处理过程，仅作为示例
		global $dsql;
		$aidList = array();
		foreach($postIdArray as $postId){
			$sql = "SELECT * FROM duoshuo_commentmeta WHERE post_id = $postId";
			$synced = $dsql->GetOne($sql);
			if(!is_array($synced)){//非create操作的评论，同步过才处理
				continue;
			}
			$cid = $synced['cid'];
			$sql = "SELECT * FROM #@__feedback WHERE id = $cid";
			$comment = $dsql->GetOne($sql);
			if(!is_array($comment)){
				continue;
			}
			$ischeck = self::$actionMap[$action];
			$sql = "UPDATE #@__feedback SET ischeck = $ischeck WHERE id = $cid";
			$dsql->ExecuteNoneQuery($sql);
			$aidList[] = $comment['aid'];
		}
		return $aidList;
		*/
	}
	
	public function deleteForeverPost($postIdArray){
		/*
		 * 以下为dedecms中的处理过程，仅作为示例
		global $dsql;
		$aidList = array();
		foreach($postIdArray as $postId){
			$sql = "SELECT * FROM duoshuo_commentmeta WHERE post_id = ".$postId;
			$synced = $dsql->GetOne($sql);
			if(!is_array($synced)){//非create操作的评论，同步过才处理
				continue;
			}
			$cid = $synced['cid'];
			$sql = "SELECT * FROM #@__feedback WHERE id = $cid";
			$comment = $dsql->GetOne($sql);
			if(!is_array($comment)){
				continue;
			}
			$sql = "DELETE FROM #@__feedback WHERE id = $cid";
			$dsql->ExecuteNoneQuery($sql);
			$aidList[] = $comment['aid'];
		}
		return $aidList;
		*/
	}
	
	public function refreshThreads($aidList){
		/*
		 * 以下为dedecms中的处理过程，仅作为示例
		foreach($aidList as $aid){
			$arc = new Archives($aid);
			if($arc){
				$arc->MakeHtml();
			}
		}
		*/
	}
	
	/**
	 * 将文章和评论内容同步到多说，用于以前的评论显示和垃圾评论过滤
	 */
	public function export(){
		/*
		 * 以下为dedecms中的处理过程，仅作为示例
		global $dsql;
		
		@set_time_limit(0);
		@ini_set('memory_limit', '256M');
		@ini_set('display_errors', $this->getOption('debug'));
		
		$progress = $this->getOption('synchronized');
		
		if (!$progress || is_numeric($progress))//	之前已经完成了导出流程
			$progress = 'thread/0';
		
		list($type, $offset) = explode('/', $progress);
		
		try{
			switch($type){
				case 'thread':
					$limit = 10;
					$dsql->SetQuery("SELECT aid FROM `#@__feedback` where `aid` > $offset group by aid order by aid asc limit $limit");
					$dsql->Execute();
					$aidArray = array();
					while($row = $dsql->GetArray())
					{
						$aidArray[] = $row['aid'];
					}
					if(count($aidArray)>0){
						$aids = implode(',', $aidArray);
						$dsql->SetQuery("SELECT * FROM `#@__archives` where `id` in ($aids)");
						$dsql->Execute();
						$threads = array();
						while($row = $dsql->GetArray())
						{
							$arc = new Archives($row['id']);
							$arc->Fields['arcurl'] = $arc->GetTrueUrl(null);
							$threads[] = $arc->Fields;
						}
						$count = $this->exportThreads($threads);
						$maxid = $aidArray[count($aidArray)-1];
					}else{
						$count = 0;
					} 
					break;
				case 'post':
					$limit = 50;
					$dsql->SetQuery("SELECT cid FROM `duoshuo_commentmeta` group by cid");
					$dsql->Execute();
					$cidFromDuoshuo = array();
					while($row = $dsql->GetArray())
					{
						$cidFromDuoshuo[] = $row['cid'];
					}
					$dsql->SetQuery("SELECT * FROM `#@__feedback` order by id asc limit $offset,$limit ");
					$dsql->Execute();
					$comments = array();
					while($row = $dsql->GetArray())
					{
						$comments[] = $row;
					}
					$count = $this->exportPosts($comments,$cidFromDuoshuo);
					
					break;
				default:
			}
			
			if ($count == $limit){
				switch($type){
					case 'thread':
						$progress = $type . '/' . ($maxid);
						break;
					case 'post':
						$progress = $type . '/' . ($offset + $limit);
						break;
				}
			}
			elseif($type == 'thread')
				$progress = 'post/0';
			elseif($type == 'post')
				$progress = time();
			
			$this->updateOption('synchronized', $progress);
			$response = array(
				'progress'=>$progress,
				'code'	=>	0
			);
			return $response;
		}
		catch(Duoshuo_Exception $e){
			$this->updateOption('synchronized', $progress);
			$this->sendException($e);
		}
		*/
	}
	
	function exportThreads($threads){
		if (count($threads) === 0)
			return 0;
	
		$params = array(
				'threads'	=>	array(),
		);
		foreach($threads as $index => $thread){
			$params['threads'][] = $this->packageThread($thread);
		}
	
		$remoteResponse = $this->getClient()->request('POST','threads/import', $params);
	
		return count($threads);
	}
	
	function exportPosts($posts,$postIdsFromDuoshuo){
		if (count($posts) === 0)
			return 0;
	
		$params = array(
				'posts'	=>	array()
		);
	
		foreach($posts as $comment){
			if(!in_array($comment['id'],$postIdsFromDuoshuo))
				$params['posts'][] = $this->packagePost($comment);
		}
		if(count($params['posts']) > 0){
			$remoteResponse = $this->getClient()->request('POST', 'posts/import', $params);
		}
		return count($posts);
	}
	
	public function timeFormat($time) {
		return date('Y-m-d H:i:s', $time);
	}
	
	public function statusFormat($status) {
		switch($status) {
			case 1 : return 'approved';
			case 0 : return 'pending';
		}
	}
	
	public function getTables() {
		return array(
			'thread'	=>	array('archives'),
			'post'		=>	array('feedback')
		);
	}
	/*
	 * 以下为dedecms中的处理过程所用到的函数，仅作为示例
	public function packagePost($post) {
		return array(
			'post_key'	=>	$post['id'],
			'thread_key'	=>	$post['aid'],
			'author_key'	=>	$post['mid'],
			'author_name'	=>	iconv("GBK","UTF-8",$post['username']),
			'created_at'	=>	$this->timeFormat($post['dtime']),
			'ip'			=>	$post['ip'],
			'status'		=>	$this->statusFormat($post['ischeck']),
			'message'		=>	iconv("GBK","UTF-8",$post['msg']),
			'likes'			=>	$post['good'],
			'dislikes'		=>	$post['bad']
		);
	}

	public function packageThread($thread) {
		global $cfg_basehost,$cfg_cmspath;
		$data = array(
			'thread_key'	=>	$thread['id'],
			'title'			=>	iconv("GBK","UTF-8",$thread['title']),
			'created_at'	=>	$this->timeFormat($thread['pubdate']),
			'author_key'	=>	$thread['mid'],
			'updated_at'	=>	$this->timeFormat($thread['lastpost']),
			'likes'			=>	$thread['goodpost'],
			'dislikes'		=>	$thread['badpost'],
			'excerpt'		=>	iconv("GBK","UTF-8",$thread['description']),
			'ip'			=>	$thread['userip'],
			'source'		=>	'dedecms',
		);
		if(isset($thread['body']))
			$data['content'] = iconv("GBK","UTF-8",$thread['body']);
		else if(isset($thread['introduce']))
			$data['content'] = iconv("GBK","UTF-8",$thread['introduce']);
		else 
			$data['content'] = '';
		if(!empty($thread['arcurl'])){
			if(strpos($thread['arcurl'],$cfg_basehost)){
				$data['url'] = $thread['arcurl'];
			}
			else{
				$data['url'] = $cfg_basehost.$cfg_cmspath.$thread['arcurl'];
			}
		}
		if(!empty($thread['litpic'])  && !preg_match('/\/images\/defaultpic.gif/',$thread['litpic'])){
			if(preg_match('/http:\/\//',$thread['litpic'])){
				$data['images'] = json_encode(array($thread['litpic']));
			}else{
				$data['images'] = json_encode(array($cfg_basehost.$cfg_cmspath.$thread['litpic']));
			}
			
		}
		$data['meta'] = json_encode($this->myUnset($thread, array('id', 'title', 'pubdate', 'mid', 'lastpost','litpic','arcurl',
									'goodpost', 'badpost', 'description', 'userip', 'body', 'introduce')));
		return $data;
	}
	
	public function myUnset($data, $keys) {
		if(!is_array($data)) return array();
		foreach($keys as $key) {
			if(isset($data[$key]))
				unset($data[$key]);
		}
		return $data;
	}
	*/
}