# wechatpay-php-call
基于微信支付开放SDK封装的轮子，旨在快速传参完成某个API的调用

## 更新
2023年4月26日 ：目前  仅有商家转账和付款到零钱两个调用代码

## 使用步骤
1、引入微信支付sdk（https://github.com/wechatpay-apiv3/wechatpay-php）
2、商户注册、产品开通等一系列工作均已完成
3、将service文件复制到你的工程中  按需修改！


### 具体方法传参介绍 
1、`transfer` 
参考文档 https://pay.weixin.qq.com/docs/merchant/apis/batch-transfer-to-balance/transfer-batch/initiate-batch-transfer.html 
```

    function transferCall()
    {
        $out_batch_no = $this->request->post("out_batch_no");
        if (empty($out_batch_no)) {
            $out_batch_no = "batch" . time();
        }

        $res = WePayService::transfer($out_batch_no, 100, $this->openIdTest);

        $this->success("仅供调试！！", json_decode($res));

    }
```