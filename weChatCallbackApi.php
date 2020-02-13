<?php
define("weChatToken", "");  // 微信Token
define("weChatSignCheck", false);   // 每次请求都验证签名开关

class weChatCallbackApi
{
    public function valid()
    {
        //valid signature , option
        if ($_SERVER['REQUEST_METHOD'] == "GET") {
            header("content-type:text");
            $echoStr = $_GET["echostr"];
            if (isset($_GET['openid']) || empty($echoStr)) {    // 没事瞎请求
                exit("GET METHOD EXISTS OPENID OR ECHOSTR EMPTY.");
            } else {    // 第一次接入微信验证
                if ($this->checkSignature()) {
                    exit($echoStr);
                } else {
                    exit('SIGN CHECK ERROR-FRIST.');
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] == "POST") {    // 正常业务请求使用POST
            if (empty($_GET["openid"])) {    // 微信过来都带了openid
                exit("POST METHOD NOT EXISTS OPENID.");
            } else {    // 有时候不改代码，验证时而能通过，时而不能，很奇怪(SAE)
                if (weChatSignCheck) {  // 检查开关
                    if (!$this->checkSignature()) { // 签名验证不通过,通过即可使用正常业务
                        exit('SIGN CHECK ERROR.');
                    }
                }
            }
        } else {
            exit('REQUEST METHOD ERROR.');  // 没事别瞎请求
        }
    }

    private function checkSignature()
    {
        $token = weChatToken;
        if (empty($token)) {
            exit("WECHAT TOKEN IS EMPTY!");
        }

        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }
}