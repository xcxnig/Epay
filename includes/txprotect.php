<?php
//屏蔽各种蜘蛛与非正常浏览器
if(strpos($_SERVER['HTTP_USER_AGENT'], 'Baiduspider')!==false || strpos($_SERVER['HTTP_USER_AGENT'], '360Spider')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'YisouSpider')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'Sogou web spider')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'Sogou inst spider')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'python')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'MJ12bot')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'SemrushBot')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'AhrefsBot')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'DotBot')!==false || strpos($_SERVER['HTTP_REFERER'], '.tr.com')!==false||strpos($_SERVER['HTTP_REFERER'], '.wsd.com')!==false || strpos($_SERVER['HTTP_REFERER'], '.oa.com')!==false || strpos($_SERVER['HTTP_REFERER'], '.cm.com')!==false || strpos($_SERVER['HTTP_REFERER'], '/membercomprehensive/')!==false || strpos($_SERVER['HTTP_REFERER'], 'www.internalrequests.org')!==false || !isset($_SERVER['HTTP_ACCEPT']) || preg_match("/manager/", strtolower($_SERVER['HTTP_USER_AGENT'])) || strpos($_SERVER['HTTP_USER_AGENT'], 'ozilla')!==false && strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla')===false || preg_match("/Windows NT 6.1/", $_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_ACCEPT']=='*/*' || preg_match("/Windows NT 5.1/", $_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_ACCEPT']=='*/*' || preg_match("/vnd.wap.wml/", $_SERVER['HTTP_ACCEPT']) && preg_match("/Windows NT 5.1/", $_SERVER['HTTP_USER_AGENT']) || isset($_COOKIE['ASPSESSIONIDQASBQDRC']) || empty($_SERVER['HTTP_USER_AGENT']) || preg_match("/Alibaba.Security.Heimdall/", $_SERVER['HTTP_USER_AGENT']) || strpos($_SERVER['HTTP_USER_AGENT'], 'wechatdevtools/')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'libcurl/')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'Go-http-client')!==false || strpos($_SERVER['HTTP_USER_AGENT'], 'HeadlessChrome')!==false) {
	header("HTTP/1.1 404 Not Found");
	exit;
}