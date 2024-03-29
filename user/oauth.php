<?php
/**
 * 登录
**/
$nosession=true;
include("../includes/common.php");
if($conf['login_alipay']==0 && $conf['cert_open']!=3)sysmsg("未开启支付宝快捷登录");

if(isset($_GET['sid'])){
	$sid = trim(daddslashes($_GET['sid']));
	if(!preg_match('/^(.[a-zA-Z0-9]+)$/',$sid))exit("Access Denied");
	session_id($sid);
}
session_start();

if(isset($_GET['act']) && $_GET['act']=='login'){
	if(isset($_SESSION['alipay_uid']) && !empty($_SESSION['alipay_uid'])){
		$alipay_uid = daddslashes($_SESSION['alipay_uid']);
		$userrow=$DB->getRow("SELECT * FROM pre_user WHERE alipay_uid='{$alipay_uid}' limit 1");
		if($userrow){
			$uid=$userrow['uid'];
			$key=$userrow['key'];
			if($islogin2==1){
				exit('{"code":-1,"msg":"当前支付宝已绑定商户ID:'.$uid.'，请勿重复绑定！"}');
			}
			$session=md5($uid.$key.$password_hash);
			$expiretime=time()+604800;
			$token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
			setcookie("user_token", $token, time() + 604800);
			$DB->exec("update `pre_user` set `lasttime` ='$date' where `uid`='$uid'");
			$result=array("code"=>0,"msg"=>"登录成功！正在跳转到用户中心","url"=>"./");
		}elseif($islogin2==1){
			$sds=$DB->exec("update `pre_user` set `alipay_uid`='$alipay_uid' where `uid`='$uid'");
			$result=array("code"=>0,"msg"=>"已成功绑定支付宝账号！","url"=>"./editinfo.php");
		}else{
			$_SESSION['Oauth_alipay_uid']=$alipay_uid;
			$result=array("code"=>0,"msg"=>"请输入商户ID和密钥完成绑定和登录","url"=>"./login.php?connect=true");
		}
		unset($_SESSION['alipay_uid']);
	}else{
		$result=array("code"=>1);
	}
	exit(json_encode($result));
}

if(isset($_GET['auth_code']) && isset($_GET['cert_verify_id'])){

$channel = \lib\Channel::get($conf['cert_channel']);
if(!$channel)sysmsg('当前支付通道信息不存在');
define("PAY_ROOT", PLUGIN_ROOT.'alipay/');
require_once(PAY_ROOT."inc/AlipayCertdocService.php");

$certdoc = new AlipayCertdocService($config);
$tokenArr = $certdoc->getToken($_GET['auth_code']);
if($tokenArr['user_id']){
	$result = $certdoc->consult($_GET['cert_verify_id'], $tokenArr['access_token']);
	if($result['passed']){
		$uid = authcode(str_replace(' ', '+', $_GET['state']), 'DECODE', SYS_KEY);
		if(!$uid || $uid<=0)sysmsg('state校验失败');
		if($result['passed'] == 'T'){
			$DB->exec("update `pre_user` set `cert`=1,`certtime`=NOW(),`alipay_uid`=:user_id where `uid`=:uid", [':user_id'=>$tokenArr['user_id'], ':uid'=>$uid]);
			@header('Content-Type: text/html; charset=UTF-8');
			if($islogin2==1){
				exit("<script language='javascript'>alert('实名认证成功！');window.location.href='./certificate.php';</script>");
			}else{
				include PAYPAGE_ROOT.'certok.php';
				exit;
			}
		}else{
			$msg = '实名认证失败，原因：'.$result['fail_reason'].'';
			if($islogin2==1)$msg .= '<br/><br/><a href="./certificate.php">返回重新认证</a>';
			sysmsg('<center>'.$msg.'</center>');
		}
	}
	else {
		sysmsg('实名证件信息比对验证失败！['.$result['sub_code'].']'.$result['sub_msg']);
	}
}
else {
	sysmsg('支付宝快捷登录失败！['.$tokenArr['sub_code'].']'.$tokenArr['sub_msg']);
}

}elseif(isset($_GET['auth_code'])){

$channel = \lib\Channel::get($conf['login_alipay']);
if(!$channel)sysmsg('当前支付通道信息不存在');
define("PAY_ROOT", PLUGIN_ROOT.'alipay/');
require_once(PAY_ROOT."inc/AlipayOauthService.php");

$oauth = new AlipayOauthService($config);
$result = $oauth->getToken($_GET['auth_code']);

if($result['user_id']){

	//支付宝用户号
	$user_id = daddslashes($result['user_id']);
	if(isset($_GET['state'])){
		$redirect_uri = authcode(str_replace(' ', '+', $_GET['state']), 'DECODE', SYS_KEY);
		if($redirect_uri){
			exit("<script language='javascript'>window.location.replace('{$redirect_uri}?userid={$user_id}');</script>");
		}
	}
	$_SESSION['alipay_uid'] = $user_id;

	$userrow=$DB->getRow("SELECT * FROM pre_user WHERE alipay_uid='{$user_id}' limit 1");
	if($userrow){
		$uid=$userrow['uid'];
		$key=$userrow['key'];
		if($islogin2==1){
			@header('Content-Type: text/html; charset=UTF-8');
			exit("<script language='javascript'>alert('当前支付宝已绑定商户ID:{$uid}，请勿重复绑定！');window.location.href='./editinfo.php';</script>");
		}
		$DB->exec("insert into `pre_log` (`uid`,`type`,`date`,`ip`,`city`) values ('".$uid."','支付宝快捷登录','".$date."','".$clientip."','".$city."')");
		$session=md5($uid.$key.$password_hash);
		$expiretime=time()+604800;
		$token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		setcookie("user_token", $token, time() + 604800);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>window.location.href='./';</script>");
	}elseif($islogin2==1){
		$sds=$DB->exec("update `pre_user` set `alipay_uid` ='$user_id' where `uid`='$uid'");
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('已成功绑定支付宝账号！');window.location.href='./editinfo.php';</script>");
	}else{
		$_SESSION['Oauth_alipay_uid']=$user_id;
		exit("<script language='javascript'>alert('请输入商户ID和密钥完成绑定和登录');window.location.href='./login.php?connect=true';</script>");
	}
}
else {
	@header('Content-Type: text/html; charset=UTF-8');
	sysmsg('支付宝快捷登录失败！['.$result['sub_code'].']'.$result['sub_msg']);
}

}elseif(isset($_GET['logout'])){
	setcookie("user_token", "", time() - 604800);
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登陆！');window.location.href='./login.php';</script>");
}elseif($islogin2==1 && isset($_GET['unbind'])){
	$DB->exec("update `pre_user` set `alipay_uid` =NULL where `uid`='$uid'");
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功解绑支付宝账号！');window.location.href='./editinfo.php';</script>");
}elseif($islogin2==1 && !isset($_GET['bind']) && !isset($_GET['state'])){
	exit("<script language='javascript'>alert('您已登陆！');window.location.href='./';</script>");
}elseif($conf['login_alipay']==-1){
	if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey'])exit('未配置好聚合登录信息');
	$Oauth_config['apiurl']=$conf['login_apiurl'];
	$Oauth_config['appid']=$conf['login_appid'];
	$Oauth_config['appkey']=$conf['login_appkey'];
	$Oauth_config['callback']=$siteurl.'user/connect.php';

	$Oauth=new \lib\Oauth($Oauth_config);
	$arr = $Oauth->login('alipay');
	if(isset($arr['code']) && $arr['code']==0){
		exit("<script language='javascript'>window.location.href='{$arr['url']}';</script>");
	}elseif(isset($arr['code'])){
		sysmsg('<h3>error:</h3>'.$arr['errcode'].'<h3>msg  :</h3>'.$arr['msg']);
	}else{
		sysmsg('获取登录数据失败');
	}
}elseif(checkmobile()==false || strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient')){
	$channel = \lib\Channel::get($conf['login_alipay']);
	if(!$channel)sysmsg('当前支付通道信息不存在');
	define("PAY_ROOT", PLUGIN_ROOT.'alipay/');
	require_once(PAY_ROOT."inc/AlipayOauthService.php");
	$oauth = new AlipayOauthService($config);
	$oauth->oauth(isset($_GET['state'])?trim($_GET['state']):null);
}else{

$code_url = $siteurl.'user/oauth.php?sid='.session_id();
if(isset($_GET['bind'])){
	$code_url .= '&bind=1';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8" />
<title>支付宝快捷登录 | <?php echo $conf['sitename']?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" type="text/css" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>animate.css/3.5.2/animate.min.css" type="text/css" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>font-awesome/4.7.0/css/font-awesome.min.css" type="text/css" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>simple-line-icons/2.4.1/css/simple-line-icons.min.css" type="text/css" />
<link rel="stylesheet" href="./assets/css/font.css" type="text/css" />
<link rel="stylesheet" href="./assets/css/app.css" type="text/css" />
<style>input:-webkit-autofill{-webkit-box-shadow:0 0 0px 1000px white inset;-webkit-text-fill-color:#333;}img.logo{width:14px;height:14px;margin:0 5px 0 3px;}</style>
</head>
<body>
<div class="app app-header-fixed  ">
<div class="container w-xxl w-auto-xs" ng-controller="SigninFormController" ng-init="app.settings.container = false;">
<span class="navbar-brand block m-t" id="sitename"><?php echo $conf['sitename']?></span>
<div class="m-b-lg">
<div class="wrapper text-center">
支付宝快捷登录
</div>
<form name="form" class="form-validation">
<div class="text-center">
<button type="button" class="btn btn-lg btn-primary btn-block" onclick="jump()" ng-disabled='form.$invalid'>跳转到支付宝</button>
</div>
</div>
</form>
</div>
<div class="text-center">
<p>
<small class="text-muted"><a href="/"><?php echo $conf['sitename']?></a><br>&copy; 2016~<?php echo date("Y")?></small>
</p>
</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
function jump(){
	var url = '<?php echo $code_url?>';
	window.location.href='alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='+encodeURIComponent(url);
}
$(document).ready(function(){
	jump();
	setTimeout('checkopenid()', 2000);
});
function checkopenid(){
	$.ajax({
		type: "GET",
		dataType: "json",
		url: "oauth.php?act=login",
		success: function (data, textStatus) {
			if (data.code == 0) {
				layer.msg(data.msg, {icon: 16,time: 10000,shade:[0.3, "#000"]});
				setTimeout(function(){ window.location.href=data.url }, 1000);
			}else if (data.code == 1) {
				setTimeout('checkopenid()', 2000);
			}else{
				layer.alert(data.msg);
			}
		},
		error: function (data) {
			layer.msg('服务器错误', {icon: 2});
			return false;
		}
	});
}
</script>
</body>
</html>
<?php
}