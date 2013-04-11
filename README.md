###本sdk主要用于将多说数据同步到本地数据库等功能的开发  

相应api文档请参照：  
同步评论回本地数据库：http://dev.duoshuo.com/docs/50037b11b66af78d0c000009

请在下载后执行：  
1. 在SDK.php的getOption函数中，填入正确的short_name和secret  
2. 配置SDK.php的setOption函数，以保存sync_lock和last_sync  
3. 配置SDK.php的createPost，moderatePost，deleteForeverPost，处理评论保存和状态改变  