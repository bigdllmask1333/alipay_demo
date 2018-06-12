<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/4 0004
 * Time: 11:02
 */

namespace Vendor\Wechat;

class WechatCommon
{
    public $appid;
    public $mch_id;
    public $key;



    public function test()
    {
        echo 1234455;
    }


    /**
     * 拼装请求的数据
     * @return  String 拼装完成的数据
     */
    public function setSendData($data) {

        $this->sTpl = "<xml>
                        <appid><![CDATA[%s]]></appid>
                        <body><![CDATA[%s]]></body>
                        <mch_id><![CDATA[%s]]></mch_id>
                        <nonce_str><![CDATA[%s]]></nonce_str>
                        <notify_url><![CDATA[%s]]></notify_url>
                        <out_trade_no><![CDATA[%s]]></out_trade_no>
                        <spbill_create_ip><![CDATA[%s]]></spbill_create_ip>
                        <total_fee><![CDATA[%d]]></total_fee>
                        <trade_type><![CDATA[%s]]></trade_type>
                        <sign><![CDATA[%s]]></sign>
                    </xml>";                          //xml数据模板

        $nonce_str = $this->getNonceStr();        //调用随机字符串生成方法获取随机字符串
        $data['appid'] = $this->appid;
        $data['mch_id'] = $this->mch_id;
        $data['nonce_str'] = $nonce_str;
        $data['notify_url'] = $this->notify_url;
        $data['trade_type'] = $this->trade_type;      //将参与签名的数据保存到数组



        // 注意：以上几个参数是追加到$data中的，$data中应该同时包含开发文档中要求必填的剔除sign以外的所有数据
        $sign = $this->getSign($data);        //获取签名  第一次签名

        $data = sprintf($this->sTpl, $this->appid, $data['body'], $this->mch_id, $nonce_str, $this->notify_url, $data['out_trade_no'], $data['spbill_create_ip'], $data['total_fee'], $this->trade_type, $sign);


        //生成xml数据格式
        return $data;
    }


    /**
     * 发送下单请求；
     * @param  Curl   $curl 请求资源句柄
     * @return mixed       请求返回数据
     */
    public function sendRequest(Curl $curl, $data) {
        $data = $this->setSendData($data);            //获取要发送的数据
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $curl->setUrl($url);          //设置请求地址
        $content = $curl->execute('POST', $data);       //执行该请求
        return $content;      //返回请求到的数据
    }

    /**
     * 解析xml文档，转化为对象
     * @author 栗荣发 2016-09-20
     * @param  String $xmlStr xml文档
     * @return Object         返回Obj对象
     */
    public function xmlToObject($xmlStr) {
        if (!is_string($xmlStr) || empty($xmlStr)) {
            return false;
        }
        // 由于解析xml的时候，即使被解析的变量为空，依然不会报错，会返回一个空的对象，所以，我们这里做了处理，当被解析的变量不是字符串，或者该变量为空，直接返回false
        $postObj = simplexml_load_string($xmlStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $postObj = json_decode(json_encode($postObj));
        //将xml数据转换成对象返回
        return $postObj;
    }


    /**
     * 接收支付结果通知参数
     * @return Object 返回结果对象；
     */
    public function getNotifyData() {
        $postXml = $GLOBALS["HTTP_RAW_POST_DATA"];    // 接受通知参数；
        if (empty($postXml)) {
            return false;
        }
        $postObj = $this->xmlToObject($postXml);      // 调用解析方法，将xml数据解析成对象
        if ($postObj === false) {
            return false;
        }
        if (!empty($postObj->return_code)) {
            if ($postObj->return_code == 'FAIL') {
                return false;
            }
        }
        return $postObj;          // 返回结果对象；
    }


    /**
     * 获取客户端支付信息
     * @author 栗荣发 2016-09-18
     * @param  Array $data 参与签名的信息数组
     * @return String       签名字符串
     */
    public function getClientPay($data) {
        $sign = $this->getSign($data);        // 生成签名并返回
        return $sign;
    }


    /**
     * 查询订单状态
     * @param  Curl   $curl         工具类
     * @param  string $out_trade_no 订单号
     * @return xml               订单查询结果
     */
    public function queryOrder(Curl $curl, $out_trade_no) {
        $nonce_str = $this->getNonceStr();
        $data = array(
            'appid'        =>    $this->appid,
            'mch_id'    =>    $this->mch_id,
            'out_trade_no'    =>    $out_trade_no,
            'nonce_str'            =>    $nonce_str
        );
        $sign = $this->getSign($data);
        $xml_data = '<xml>
                   <appid>%s</appid>
                   <mch_id>%s</mch_id>
                   <nonce_str>%s</nonce_str>
                   <out_trade_no>%s</out_trade_no>
                   <sign>%s</sign>
                </xml>';
        $xml_data = sprintf($xml_data, $this->appid, $this->mch_id, $nonce_str, $out_trade_no, $sign);
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        $curl->setUrl($url);
        $content = $curl->execute(true, 'POST', $xml_data);
        return $content;
    }


    /**
     * 设置通知地址
     * @param  String $url 通知地址；
     */
    public function setNotifyUrl($url) {
        if (is_string($url)) {
            $this->notify_url = $url;
        }
    }



    /**
     * 获取参数签名；
     * @param  Array  要传递的参数数组
     * @return String 通过计算得到的签名；
     */
    public function getSign($params) {
        ksort($params);        //将参数数组按照参数名ASCII码从小到大排序
        foreach ($params as $key => $item) {
            if (!empty($item)) {         //剔除参数值为空的参数
                $newArr[] = $key.'='.$item;     // 整合新的参数数组
            }
        }
        $stringA = implode("&", $newArr);         //使用 & 符号连接参数

        $stringSignTemp = $stringA."&key=".$this->key;        //拼接key

        // key是在商户平台API安全里自己设置的
        $stringSignTemp = MD5($stringSignTemp);       //将字符串进行MD5加密

        $sign = strtoupper($stringSignTemp);      //将所有字符转换为大写

        return $sign;


    }



    /**
     * 生成随机数并返回
     */
    public function getNonceStr() {
        $code = "";
        for ($i=0; $i > 10; $i++) {
            $code .= mt_rand(1000);        //获取随机数
        }
        $nonceStrTemp = md5($code);
        $nonce_str = mb_substr($nonceStrTemp, 5,37);      //MD5加密后截取32位字符
        return $nonce_str;
    }
}