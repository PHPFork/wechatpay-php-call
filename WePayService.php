<?php

namespace app\service;

use app\model\TransferM;
use GuzzleHttp\Exception\RequestException;
use think\Env;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Transformer;
use WeChatPay\Util\PemUtil;

/** 微信的 支付服务
 * Class WePayTransfer
 * @package app\service
 */
class WePayService
{
    /** 申请微信支付的公众号 appid
     * @var
     */
    private static $app_id;
    private static $mch_id;
    /**   从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
     * @var
     */
    private static $merchantPrivateKeyFilePath;
    private static $merchantPrivateKeyInstance;
    /** 商户 证书序列号
     * @var bool|mixed|string|null
     */
    private static $merchantCertificateSerial;
    /** 商户证书路径
     * @var string
     */
    private static $merchantPublicKeyFilePath;
    /**平台证书的序列号
     * @var
     *
     */
    private static $platformCertificateSerial;
    /** 证书实例
     * @var mixed|\OpenSSLAsymmetricKey|resource
     */
    private static $platformPublicKeyInstance;

    /**
     * @var string  平台的！证书文件路径
     */
    private static $platformCertificateFilePath;
    private static $apikeyV3;
    private static $apikeyV2;
    private static $config;
    private static $app;
    private static $instanceV2;
    private static $instanceV3;

    private static function init()
    {

        self::$app_id = Env::get("wepay.app_id", "");
        self::$mch_id = Env::get("wepay.mch_id", "");


        $certPath = ROOT_PATH . 'public/wepay/';
        self::$platformCertificateFilePath = $certPath . 'platform_cert.pem';//平台！ API 证书
        self::$merchantPrivateKeyFilePath = $certPath . 'apiclient_key.pem';//商户 API 私钥
        self::$merchantPublicKeyFilePath = $certPath . 'apiclient_key.pem';//商户 API 公钥
        self::$platformCertificateSerial = PemUtil::parseCertificateSerialNo(file_get_contents(self::$platformCertificateFilePath));
//        以下  是 easyWeChat的初始化 
        /*self::$config = [
            // 必要配置
            'app_id' => Env::get("wepay.app_id", ""),
            'mch_id' => Env::get("wepay.mch_id", ""),
            'key' => Env::get("wepay.key", ""),
            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path' => self::$merchantPublicKeyFilePath, // 商户 API 证书证书XXX: 绝对路径！！！！
            'key_path' => self::$merchantPrivateKeyFilePath,      //商户 API 私钥  XXX: 绝对路径！！！！
            'sandbox' => false, // 设置为 false 或注释则关闭沙箱模式
        ];

        self::$app = Factory::payment(self::$config);*/
    }

    /**
     *  微信支付官方sdk 实例初始化
     */
    static private function instanceInit()
    {
        self::init();
        // 工厂方法构造一个实例
        self::$instanceV2 = Builder::factory([
            'mchid' => Env::get("wepay.mch_id", ""),
            'serial' => 'nop',
            'privateKey' => 'any',
            'certs' => ['any' => null],
            'secret' => Env::get("wepay.key", ""),
            'merchant' => [
//                'cert' => self::$xx,
                'key' => self::$merchantPrivateKeyFilePath,
            ],
        ]);

//        v3初始化
        self::$apikeyV3 = Env::get("wepay.v3key", "");
        self::$merchantCertificateSerial = Env::get("wepay.CertificateSerial", "");
        self::$platformPublicKeyInstance = Rsa::from(file_get_contents(self::$platformCertificateFilePath), Rsa::KEY_TYPE_PUBLIC);
        self::$merchantPrivateKeyInstance = Rsa::from(file_get_contents(self::$merchantPrivateKeyFilePath), Rsa::KEY_TYPE_PRIVATE);
        // 构造一个 APIv3 客户端实例
        self::$instanceV3 = Builder::factory([
            'mchid' => self::$mch_id,
            'serial' => self::$merchantCertificateSerial,
            'privateKey' => self::$merchantPrivateKeyInstance,
            'certs' => [
                self::$platformCertificateSerial => self::$platformPublicKeyInstance,
            ],
        ]);
    }

    /**
     * 商家转账
     * 调用方法
     *$out_batch_no :按文档要求传入自行生成的批次编号
     * $amount 单位为 分的 整型int 数值  例如 100
     * https://blog.csdn.net/lfbin5566/article/details/125516953
     * https://pay.weixin.qq.com/docs/merchant/apis/batch-transfer-to-balance/transfer-batch/initiate-batch-transfer.html
     */
    static public function transfer($out_batch_no = "", $amount = 0, $openid = "")
    {
        self::instanceInit();
        $nowTime = time();

        if (empty($out_batch_no)) {
            return "批次编号为空";
        }
        $url = 'https://api.mch.weixin.qq.com/v3/transfer/batches';
        $detailList = [];
        $detail["out_detail_no"] = $nowTime . ""; //string 类型 需要带引号
        $detail["transfer_amount"] = $amount;
        $detail["transfer_remark"] = "技术测试中";
        $detail["openid"] = $openid;
//        $detail["user_name"] = "user_name";
        $detailList[] = $detail;
        $dataType = "application/json";


//        body  需要加密的部分
        $body = ["appid" => self::$app_id
            , "out_batch_no" => $out_batch_no
            , "batch_name" => "batch_n"
            , "batch_remark" => "batch_mark"
            , "total_amount" => $amount
            , "total_num" => 1 // 不能带引号
            , "transfer_detail_list" => $detailList
//                ,"transfer_scene_id"=>
        ];
        $http_method = 'POST';//请求方法（GET,POST,PUT）
        $timestamp = $nowTime;//请求时间戳
        $url_parts = parse_url($url);//获取请求的绝对URL
        $nonce = $timestamp . rand('10000', '99999');//请求随机串
        $visiableBody = json_encode((object)$body);//请求报文主体
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));
        $visiableMessage = $http_method . "\n" .
            $canonical_url . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            json_encode($visiableBody) . "\n";
//        对业务参数进行加密
        $signature = Rsa::sign($visiableMessage, self::$merchantPrivateKeyInstance);
        $mch_id = self::$mch_id;
        $merchantCertificateSerial = self::$merchantCertificateSerial;
        $authorization = "WECHATPAY2-SHA256-RSA2048 mchid=$mch_id,nonce_str=$nonce,signature=$signature,timestamp=$nowTime,serial_no=$merchantCertificateSerial";
        $params = [
            "debug" => false,
//            临时测试  不传header竟然也行。 。
            'headers' => [
                'Content-Type' => $dataType
                , "Wechatpay-Serial" => self::$platformCertificateSerial
                , "Authorization" => $authorization
                , "Accept" => $dataType
            ],
            "json" => $body,];
//        self::$error('在此检查参数具体内容', $params);
//        Log::notice($params);
        try {
            $resp = self::$instanceV3->chain('/v3/transfer/batches')->post($params);
//            echo $resp->getStatusCode(), PHP_EOL;
//            echo $resp->getBody(), PHP_EOL;
            $respStr = $resp->getBody()->__toString();
            //         存数据库
            TransferM::save2db($params, $respStr);
            return $respStr; //  将响应数据的字符串  返回给控制器
        } catch (\Exception $e) {
// 进行错误处理
//            echo $e->getMessage(), PHP_EOL;
            $bodyResp = "";
            if ($e instanceof RequestException && $e->hasResponse()) {
                $r = $e->getResponse();
                $msg = $r->getReasonPhrase();
                $responseCode = $r->getStatusCode();
//                echo $r->getStatusCode(), PHP_EOL;
//                echo $r->getReasonPhrase(), PHP_EOL;
//                echo $r->getBody(); // 这个比较有用
                $bodyResp = $r->getBody()->__toString();
            }
//             这里按需保存对你有用的数据到数据库  ，我选择大部分数据都存了
            TransferM::save2db($params, $bodyResp);
//            echo $e->getTraceAsString(), PHP_EOL; 没啥用
            return $bodyResp;
        }
        // 调试模式，https://docs.guzzlephp.org/en/stable/request-options.html#debug


    }

    /**
     *   1、付款到零钱(apiv2)  无产品权限 https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=14_2
     *   2、商家转账 （apiv3）  https://pay.weixin.qq.com/docs/merchant/apis/batch-transfer-to-balance/transfer-batch/initiate-batch-transfer.html
     */
    static function fukuan()
    {

        $res = self::$instanceV2
            ->v2->mmpaymkttransfers->promotion->transfers
            ->postAsync([
                'xml' => [
                    'mch_appid' => Env::get("wepay.app_id", ""),
                    'mchid' => Env::get("wepay.mch_id", ""),// 注意这个商户号，key是`mchid`非`mch_id`
                    'partner_trade_no' => '' . time(),
                    'openid' => 'o6KIX6gpox0O_Fj9ZzY7zz4z9tlc',
                    'check_name' => 'FORCE_CHECK',
                    're_user_name' => '李建飞',
                    'amount' => '100',
                    'desc' => '理赔',
//                    'spbill_create_ip' => '192.168.0.1',
                ],
                'security' => true, //请求需要双向证书
                'debug' => true //开启调试模式
            ])
            ->then(static function ($response) {
//                $this->error('1', $response);
                return Transformer::toArray((string)$response->getBody());
            })
            ->otherwise(static function ($e) {
                // 更多`$e`异常类型判断是必须的，这里仅列出一种可能情况，请根据实际对接过程调整并增加
//                $this->error('2', $e);
                if ($e instanceof \GuzzleHttp\Promise\RejectionException) {
                    return Transformer::toArray((string)$e->getReason()->getBody());
                }
                return [];
            })
            ->wait();
        return $res;
    }


}