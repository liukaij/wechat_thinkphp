<?php
/**
 * Created by PhpStorm.
 * User: chengbo
 * Date: 2018/12/14
 * Time: 11:50
 */

namespace app\authorization\controller;


class Weixin
{
    private $appid = 'wx3e******165c';            //第三方平台应用appid

    private $appsecret = '13e**********d039';     //第三方平台应用appsecret

    private $token = 'ePF58******Q2Ae';           //第三方平台应用token（消息校验Token）

    private $encodingAesKey = 'bzH***FCamD';      //第三方平台应用Key（消息加解密Key）

    private $component_ticket = 'ticket@**xv-g';   //微信后台推送的ticket,用于获取第三方平台接口调用凭据


    public function ticket(){
        require_once(dirname(__FILE__).'/wxBizMsgCrypt.php');//该文件在官方demo里面，下载后引入进来就可以了
        $encodingAesKey = '';//创建平台时填写的公众号消息加解密Key
        $token = '';//创建平台时填写的公众号消息校验Token
        $appId = '';//公众号第三方平台AppID
        $timeStamp = empty ( $_GET ['timestamp']) ? "" : trim ( $_GET ['timestamp'] );
        $nonce = empty ( $_GET ['nonce'] ) ?"" : trim ( $_GET ['nonce'] );
        $msg_sign = empty ( $_GET['msg_signature'] ) ? "" : trim ( $_GET ['msg_signature'] );
        $encryptMsg = file_get_contents ('php://input' );
        $pc = new \WXBizMsgCrypt ( $token,$encodingAesKey, $appId );
        // 第三方收到公众号平台发送的消息
        $msg = '';
        $errCode = $pc->decryptMsg ($msg_sign, $timeStamp, $nonce, $encryptMsg, $msg );
        if ($errCode == 0) {
            $data = $this->_xmlToArr ( $msg);
            if (isset ( $data['ComponentVerifyTicket'] )) {
                $config['componentverifyticket'] = $data ['ComponentVerifyTicket'];
                $config['create_time'] =date("Y-m-d H:i:s");
                $where['id']= '1';
                M('Public')->where($where)->setField($config);
            } elseif ($data ['InfoType'] =='unauthorized') {
                // 在公众号后台取消授权后，同步把系统里的公众号删除掉，并更新相关用户缓存
                $map ['appid'] = $data['AuthorizerAppid'];
                $map2 ['id'] = M ('WechatPublic' )->where ( $map )->getField ( 'id' );
                if ($map2 ['id']) {
                    M ( 'WechatPublic')->where ( $map2 )->delete();
                }
            }
            echo 'success';
        } else {
            echo '解密失败'.$errCode;
        }
    }
    public function _xmlToArr($xml) {
        $res = @simplexml_load_string ( $xml,NULL, LIBXML_NOCDATA );
        $res = json_decode ( json_encode ( $res), true );
        return $res;
    }


/*
    * 获取第三方平台access_token

    * 注意，此值应保存，代码这里没保存
*/
    private function get_component_access_token()

    {

        $url = "https://api.weixin.qq.com/cgi-bin/component/api_component_token";

        $data = '{

            "component_appid":"'.$this->appid.'" ,

            "component_appsecret": "'.$this->appsecret.'",

            "component_verify_ticket": "'.$this->component_ticket.'"

        }';

        $ret = json_decode($this->https_post($url,$data));

        if($ret->errcode == 0) {

            return $ret->component_access_token;

        } else {

            return $ret->errcode;

        }

    }


    /*

    *  第三方平台方获取预授权码pre_auth_code

    */

    public function get_pre_auth_code()

    {

        $url = "https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token=".$this->get_component_access_token();

        $data = '{"component_appid":"'.$this->appid.'"}';

        $ret = json_decode($this->https_post($url,$data));

        if($ret->errcode == 0) {

            echo $ret->pre_auth_code;

        } else {

            echo $ret->errcode;

        }

    }


    /*

   * 发起POST网络提交

   * @params string $url : 网络地址

   * @params json $data ： 发送的json格式数据

   */

    private function https_post($url,$data)

    {

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);

        if (!empty($data)){

            curl_setopt($curl, CURLOPT_POST, 1);

            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $output = curl_exec($curl);

        curl_close($curl);

        return $output;

    }

    /*

   * 发起GET网络提交

   * @params string $url : 网络地址

   */

    private function https_get($url)

    {

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        curl_setopt($curl, CURLOPT_HEADER, FALSE) ;

        curl_setopt($curl, CURLOPT_TIMEOUT,60);

        if (curl_errno($curl)) {

            return 'Errno'.curl_error($curl);

        }

        else{$result=curl_exec($curl);}

        curl_close($curl);

        return $result;

    }

}