<?php
namespace app\index\controller;

class Index
{

    private $appid = 'wx63606cded90a9568';            //第三方平台应用appid

    private $appsecret = '13e**********d039';     //第三方平台应用appsecret

    private $token = 'ePF58******Q2Ae';           //第三方平台应用token（消息校验Token）

    private $encodingAesKey = 'bzH***FCamD';      //第三方平台应用Key（消息加解密Key）

    private $component_ticket= 'ticket@**xv-g';   //微信后台推送的ticket,用于获取第三方平台接口调用凭据



    public function index()
    {

        $encryptMsg = file_get_contents("php://input");

        $xml_tree = new \DOMDocument();

        $xml_tree->loadXML($encryptMsg);

        $xml_array = $xml_tree->getElementsByTagName("Encrypt");

        $encrypt = $xml_array->item(0)->nodeValue;

        require_once('wxBizMsgCrypt.php');

        $Prpcrypt = new \Prpcrypt($this->encodingAesKey);

        $postData = $Prpcrypt->decrypt($encrypt, $this->appid);

        if ($postData[0] != 0) {

            return $postData[0];

        } else {

            $msg = $postData[1];

            $xml = new \DOMDocument();

            $xml->loadXML($msg);

            $array_a = $xml->getElementsByTagName("InfoType");

            $infoType = $array_a->item(0)->nodeValue;

            if ($infoType == "unauthorized") {

                //取消公众号/小程序授权

                $array_b = $xml->getElementsByTagName("AuthorizerAppid");

                $AuthorizerAppid = $array_b->item(0)->nodeValue;    //公众号/小程序appid

                $where = array("type" => 1, "appid" => $AuthorizerAppid);

                $save = array("authorizer_access_token" => "", "authorizer_refresh_token" => "", "authorizer_expires" => 0);

                Db::name("wxuser")->where($where)->update($save);   //公众号取消授权

                Db::name("wxminiprograms")->where('authorizer_appid',$AuthorizerAppid)->update($save);   //小程序取消授权

            } else if ($infoType == "component_verify_ticket") {

                //微信官方推送的ticket值

                $array_e = $xml->getElementsByTagName("ComponentVerifyTicket");

                $component_verify_ticket = $array_e->item(0)->nodeValue;

                if (Db::name("weixin_account")->where(array("type" => 1))->update(array("component_verify_ticket" => $component_verify_ticket, "date_time" => time()))) {

                    $this->updateAccessToken($component_verify_ticket);

                    echo "success";

                }

            }

        }

    }

    public function hello($name = 'ThinkPHP5')
    {
        return 'hello,' . $name;
    }


    /*

    * 扫码授权，注意此URL必须放置在页面当中用户点击进行跳转，不能通过程序跳转，否则将出现“请确认授权入口页所在域名，与授权后回调页所在域名相同....”错误

    * @params string $redirect_uri : 扫码成功后的回调地址

    * @params int $auth_type : 授权类型，1公众号，2小程序，3公众号/小程序同时展现。不传参数默认都展示

    */

    public function startAuth($redirect_uri,$auth_type = 3)

{

    $url = "https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid=".$this->appid."&pre_auth_code=".$this->get_pre_auth_code()."&redirect_uri=".urlencode($redirect_uri)."&auth_type=".$auth_type;

    return $url;

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