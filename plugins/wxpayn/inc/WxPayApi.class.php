<?php

use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Util\PemUtil;
use WeChatPay\Formatter;

require SYSTEM_ROOT."vendor/autoload.php";

class WxPayApi
{
    private $config;
    private $instance;
    private $merchantPrivateKeyInstance;
    private $platformPublicKeyInstance;
    private $platformCertificateSerial;

    public function __construct($config)
    {
        $this->config = $config;
        $this->instance = $this->getInstance();
        if(!$this->platformPublicKeyInstance){
            $this->downloadCertificate();
            $this->instance = $this->getInstance();
        }
    }

    //Native支付
    public function nativePay($param){
        $path = 'v3/pay/transactions/native';
        $publicParam = [
            'appid' => $this->config['appid'],
            'mchid' => $this->config['mchid'],
        ];
        $param = array_merge($publicParam, $param);
        return $this->sendRequest('POST', $path, $param);
    }

    //JSAPI支付
    public function jsapiPay($param){
        $path = 'v3/pay/transactions/jsapi';
        $publicParam = [
            'appid' => $this->config['appid'],
            'mchid' => $this->config['mchid'],
        ];
        $param = array_merge($publicParam, $param);
        return $this->sendRequest('POST', $path, $param);
    }

    //获取JSAPI支付的参数
    public function getJsApiParameters($prepay_id, $returnArr = false)
    {
        $params = [
            'appId' => $this->config['appid'],
            'timeStamp' => (string)Formatter::timestamp(),
            'nonceStr' => Formatter::nonce(),
            'package' => 'prepay_id='.$prepay_id,
        ];
        $params += ['paySign' => Rsa::sign(
            Formatter::joinedByLineFeed(...array_values($params)),
            $this->merchantPrivateKeyInstance
        ), 'signType' => 'RSA'];
        return $returnArr ? $params : json_encode($params);
    }

    //H5支付
    public function h5Pay($param){
        $path = 'v3/pay/transactions/h5';
        $publicParam = [
            'appid' => $this->config['appid'],
            'mchid' => $this->config['mchid'],
        ];
        $param = array_merge($publicParam, $param);
        return $this->sendRequest('POST', $path, $param);
    }

    //APP下单
    public function appPay($param){
        $path = 'v3/pay/transactions/app';
        $publicParam = [
            'appid' => $this->config['appid'],
            'mchid' => $this->config['mchid'],
        ];
        $param = array_merge($publicParam, $param);
        return $this->sendRequest('POST', $path, $param);
    }

    //查询订单
    public function queryOrder($transaction_id = null, $out_trade_no = null){
        if(!empty($transaction_id)){
            $path = 'v3/pay/transactions/id/'.$transaction_id;
        }elseif(!empty($out_trade_no)){
            $path = 'v3/pay/transactions/out-trade-no/'.$out_trade_no;
        }else{
            throw new Exception('微信支付订单号和商户订单号不能同时为空');
        }
        
        $param = [
            'mchid' => $this->config['mchid'],
        ];
        return $this->sendRequest('GET', $path, $param);
    }

    //关闭订单
    public function closeOrder($out_trade_no){
        $path = 'v3/pay/transactions/out-trade-no/'.$out_trade_no.'/close';
        $param = [
            'mchid' => $this->config['mchid'],
        ];
        return $this->sendRequest('POST', $path, $param);
    }

    //申请退款
    public function refund($param){
        $path = 'v3/refund/domestic/refunds';
        return $this->sendRequest('POST', $path, $param);
    }

    //查询单笔退款
    public function queryRefund($out_refund_no){
        $path = 'v3/refund/domestic/refunds/'.$out_refund_no;
        return $this->sendRequest('GET', $path, []);
    }

    //申请交易账单
    public function tradeBill($param){
        $path = 'v3/bill/tradebill';
        return $this->sendRequest('GET', $path, $param);
    }

    //申请资金账单
    public function fundflowBill($param){
        $path = 'v3/bill/fundflowbill';
        return $this->sendRequest('GET', $path, $param);
    }

    //发起商家转账
    public function transfer($param){
        $path = 'v3/transfer/batches';
        $publicParam = [
            'appid' => $this->config['appid'],
        ];
        $param = array_merge($publicParam, $param);
        $headers = [
            'Wechatpay-Serial' => $this->platformCertificateSerial,
        ];
        return $this->sendRequest('POST', $path, $param, $headers);
    }

    //微信批次单号查询转账批次单
    public function transferbatch($batch_id, $param){
        $path = 'v3/transfer/batches/batch-id/'.$batch_id;
        return $this->sendRequest('GET', $path, $param);
    }

    //微信明细单号查询转账明细单
    public function transferdetail($batch_id, $detail_id){
        $path = 'v3/transfer/batches/batch-id/'.$batch_id.'/details/detail-id/'.$detail_id;
        return $this->sendRequest('GET', $path, []);
    }

    //商家批次单号查询转账批次单
    public function transferoutbatch($out_batch_no, $param){
        $path = 'v3/transfer/batches/out-batch-no/'.$out_batch_no;
        return $this->sendRequest('GET', $path, $param);
    }

    //商家明细单号查询转账明细单
    public function transferoutdetail($out_batch_no, $out_detail_no){
        $path = 'v3/transfer/batches/out-batch-no/'.$out_batch_no.'/details/out-detail-no/'.$out_detail_no;
        return $this->sendRequest('GET', $path, []);
    }

    //回调通知
    public function notify(){
        $inWechatpaySignature = $_SERVER['HTTP_WECHATPAY_SIGNATURE'];
        $inWechatpayTimestamp = $_SERVER['HTTP_WECHATPAY_TIMESTAMP'];
        $inWechatpaySerial = $_SERVER['HTTP_WECHATPAY_SERIAL'];
        $inWechatpayNonce = $_SERVER['HTTP_WECHATPAY_NONCE'];
        $inBody = file_get_contents('php://input');

        if(!$inBody){
            throw new Exception('no data');
        }
        if($this->platformCertificateSerial != $inWechatpaySerial){
            throw new Exception('平台证书序列号不匹配');
        }

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        if(!$timeOffsetStatus){
            throw new Exception('订单超时');
        }

        $verifiedStatus = Rsa::verify(
            // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $this->platformPublicKeyInstance
        );
        if(!$verifiedStatus){
            throw new Exception('签名校验失败');
        }

        // 转换通知的JSON文本消息为PHP Array数组
        $inBodyArray = (array)json_decode($inBody, true);
        // 使用PHP7的数据解构语法，从Array中解构并赋值变量
        ['resource' => [
            'ciphertext'      => $ciphertext,
            'nonce'           => $nonce,
            'associated_data' => $associated_data
        ]] = $inBodyArray;
        // 加密文本消息解密
        $inBodyResource = AesGcm::decrypt($ciphertext, $this->config['apiv3Key'], $nonce, $associated_data);
        // 把解密后的文本转换为PHP Array数组
        $inBodyResourceArray = (array)json_decode($inBodyResource, true);
        // print_r($inBodyResourceArray);// 打印解密后的结果
        return $inBodyResourceArray;
    }

    //敏感信息加密
    public function textEncrypt($str){
        if (openssl_public_encrypt($str, $encrypted, $this->platformPublicKeyInstance, OPENSSL_PKCS1_OAEP_PADDING)) {
            $text = base64_encode($encrypted);
            return $text;
        }
        return false;
    }

    private function sendRequest($method, $path, $param, $headers = []){
        try {
            if($method == 'POST'){
                $resp = $this->instance->chain($path)->post(['json' => $param, 'headers' => $headers, 'verify'=>false]);
            }elseif($method == 'GET'){
                $resp = $this->instance->chain($path)->get(['query' => $param, 'headers' => $headers, 'verify'=>false]);
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                $resp = $e->getResponse();
            }else{
                throw $e;
            }
        }

        $code = $resp->getStatusCode();
        $body = $resp->getBody();
        $arr = json_decode($body, true);
        if($code == 200 && $arr){
            return $arr;
        }elseif($arr){
            throw new Exception('['.$arr['code'].']'.$arr['message'].(isset($arr['detail']['issue'])?'('.$arr['detail']['issue'].')':''));
        }else{
            throw new Exception('返回数据解析失败(http_code='.$code.')'.$body);
        }
    }

    private function downloadCertificate(){
        $path = 'v3/certificates';
        $result = $this->sendRequest('GET', $path, []);
        $encert = $result['data'][0]['encrypt_certificate'];
        $cert = AesGcm::decrypt($encert['ciphertext'], $this->config['apiv3Key'], $encert['nonce'], $encert['associated_data']);
        if(!file_put_contents($this->config['platformCertificateFilePath'], $cert)){
            throw new Exception('微信支付平台证书保存失败，可能无文件写入权限');
        }
    }

    private function getInstance(){
        // 商户号
        $merchantId = $this->config['mchid'];
        if(empty($merchantId)){
            throw new Exception('商户号不能为空');
        }

        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = $this->config['merchantPrivateKeyFilePath'];
        if(!file_exists($merchantPrivateKeyFilePath)){
            throw new Exception('商户API私钥文件不存在');
        }
        $merchantPrivateKeyInstance = Rsa::from(file_get_contents($merchantPrivateKeyFilePath), Rsa::KEY_TYPE_PRIVATE);
        $this->merchantPrivateKeyInstance = $merchantPrivateKeyInstance;

        // 「商户API证书」的「证书序列号」
        $merchantCertificateSerial = $this->config['merchantCertificateSerial'];
        if(empty($merchantCertificateSerial)){
            throw new Exception('商户API证书序列号不能为空');
        }

        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformCertificateFilePath = $this->config['platformCertificateFilePath'];
        if(!file_exists($platformCertificateFilePath)){
            // 构造一个 APIv3 客户端实例
            $instance = Builder::factory([
                'mchid'      => $merchantId,
                'serial'     => $merchantCertificateSerial,
                'privateKey' => $merchantPrivateKeyInstance,
                'certs'      => ['any' => null],
            ]);
            return $instance;
        }
        
        $platformPublicKeyInstance = Rsa::from(file_get_contents($platformCertificateFilePath), Rsa::KEY_TYPE_PUBLIC);
        $this->platformPublicKeyInstance = $platformPublicKeyInstance;

        // 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo(file_get_contents($platformCertificateFilePath));
        $this->platformCertificateSerial = $platformCertificateSerial;

        // 构造一个 APIv3 客户端实例
        $instance = Builder::factory([
            'mchid'      => $merchantId,
            'serial'     => $merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs'      => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);
        return $instance;
    }

}