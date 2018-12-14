<?php

/*

*    接收微信官方推送的ticket值以及取消授权等操作

*/

namespace app\index\controller;

use think\Db;

class Openoauth

{

    private $appid = 'wx63606cded90a9568';            //第三方平台应用appid

    private $appsecret = '13e**********d039';     //第三方平台应用appsecret

    private $token = 'spFf7o50Y515o5MyYMz5f5m0JhtffyFs';           //第三方平台应用token（消息校验Token）

    private $encodingAesKey = 'ZVhcoeJsHSczS31GchhkcgeOHhr3Jjj1Jb72hGk1mrH';      //第三方平台应用Key（消息加解密Key）

    private $component_ticket= 'ticket@**xv-g';   //微信后台推送的ticket,用于获取第三方平台接口调用凭据

    /*

    *    接收微信官方推送的消息（每10分钟1次）

    *    这里需要引入微信官方提供的加解密码示例包

    *    官方文档：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419318479&token=&lang=zh_CN

    *    示例包下载：https://wximg.gtimg.com/shake_tv/mpwiki/cryptoDemo.zip

    */

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



    /*

     * 更新component_access_token

     * @params string $component_verify_ticket

     * */

    private function updateAccessToken($component_verify_ticket)

    {

        $weixin_account = Db::name('weixin_account')->where(['type'=>1])->field('id,appId,appSecret,component_access_token,token_expires')->find();

        if($weixin_account['token_expires'] <= time() ) {

            $apiUrl = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';

            $data = '{"component_appid":"'.$weixin_account['appId'].'" ,"component_appsecret": "'.$weixin_account['appSecret'].'","component_verify_ticket": "'.$component_verify_ticket.'"}';

            $json = json_decode(_request($apiUrl,$data));

            if(isset($json->component_access_token)) {

                Db::name('weixin_account')->where(['id'=>$weixin_account['id']])->update(['component_access_token'=>$json->component_access_token,'token_expires'=>time()+7200]);

            }

        }

    }

}