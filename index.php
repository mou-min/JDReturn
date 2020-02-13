<?php
date_default_timezone_set("PRC");
include_once "./weChatCallbackApi.php";
include_once "./jd.php";
$sinaSae = false;    # 默认新浪SAE环境,不记录日志到文件，如果你是自己的服务器并且有写入日志的需求，可以设置为false

$weChatApiObj = new weChatCallbackApi();
$weChatApiObj->valid(); # 验证微信签名

$postData = file_get_contents("php://input");
libxml_disable_entity_loader(true); # Fix XXE
$postObj = simplexml_load_string($postData);
writeLog($logData = $postData, $sinaSae);

if (strtolower($postObj->MsgType) == "event") {
    if (strtolower($postObj->Event) == "subscribe") {
        $content = "<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[%s]]></Content></xml>";
        $click_content = "复制您要购买的京东商品连接，发送给公众号，可获得返利，提现后直接发红包";
        $echo_content = sprintf($content, $postObj->FromUserName, $postObj->ToUserName, time(), $click_content);
        writeLog($logData = $postData, $sinaSae);
        echo $echo_content;
    }
} elseif (strtolower($postObj->MsgType) == "text") {
    $click_content = "";
    $sku = getSkuId(trim($postObj->Content));   // 去除两边的空格
    $searchGoods = array();
    if (empty($sku)) {
        $searchGoodsName = $postObj->Content; // 如果关键字中有空格，使用另外一个自定义接口获取推广链接
        $searchGoods = getUnionGoodsForSearchSite($searchGoodsName);    // 准确性最高，如果访问频率高，不确定会不会封
        if ($searchGoods === null) {
            $searchGoods = getUnionGoods($searchGoodsName);   // 使用联盟API接口查询
        }
    }
    if (!empty($sku)) {
        $click_url = getClickURL($sku);
        $goods_info = getPrice($sku);
        $content = "<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[news]]></MsgType>";
        $content .= "<ArticleCount>1</ArticleCount><Articles><item><Title><![CDATA[%s]]></Title><Description><![CDATA[%s]]>";
        $content .= "</Description><PicUrl><![CDATA[%s]]></PicUrl><Url><![CDATA[%s]]></Url></item></Articles></xml>";
        if (empty($click_url) && empty($goods_info["imgUrl"]) && empty($goods_info["goodsName"])) {
            $desc = sprintf("精确商品ID查找，此商品不在推广中，商品ID:%s ", $sku);
            $send_content = sprintf($content, $postObj->FromUserName, $postObj->ToUserName, time(), $desc, "", "", ""); // 懒得改
        } else {
            $desc = sprintf("精确商品ID查找，蚊子腿再小也是肉，您可以节省:%s 元", $goods_info["offsale"]);
            $send_content = sprintf($content, $postObj->FromUserName, $postObj->ToUserName, time(), $desc, $goods_info["goodsName"], $goods_info["imgUrl"], $click_url);
        }
        writeLog($logData = $postData, $sinaSae);
        echo $send_content;
    } elseif (!empty($searchGoods)) {
        $content = sprintf("<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[news]]></MsgType>", $postObj->FromUserName, $postObj->ToUserName, time());
        $content .= sprintf("<ArticleCount>%s</ArticleCount><Articles>", count($searchGoods));
        //$content .= sprintf("<item><Title><![CDATA[%s]]></Title><Description><![CDATA[%s]]></Description><PicUrl><![CDATA[%s]]></PicUrl><Url><![CDATA[%s]]></Url></item>", "省多多", "",  "https://www.imoney.sg/articles/assets/buy-property.jpg", "https://www.jd.com");
        foreach ($searchGoods as $k => $o) {
            $skuID = trim($o[0]["skuId"]);
            $click_url = getClickURL($skuID);
            $goods_info = getPrice($skuID);
            $desc = sprintf("关键字查找，您可以节省:%s 元", $goods_info["offsale"]);
            $content .= sprintf("<item><Title><![CDATA[%s]]></Title><Description><![CDATA[%s]]></Description><PicUrl><![CDATA[%s]]></PicUrl><Url><![CDATA[%s]]></Url></item>", $desc, $goods_info["goodsName"], $goods_info["imgUrl"], $click_url);
        }
        $content .= "</Articles></xml>";
        writeLog($logData = $postData, $sinaSae);
        echo $content;
    } else {
        $content = "<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[%s]]></Content></xml>";
        $click_content = "复制您要购买的京东商品连接或者商品标题，发送给公众号，可以获得返利, 提现直接发红包";
        $echo_content = sprintf($content, $postObj->FromUserName, $postObj->ToUserName, time(), $click_content);
        writeLog($logData = $postData, $sinaSae);
        echo $echo_content;
    }
}