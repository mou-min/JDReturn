<?php
/*京东自定义配置相关信息开始*/
define("APP_KEY", "");   # JD appkey
define("APP_SECRET_KEY", ""); # JD secretkey
define("API_SITE_ID", "");   # JD 推广网站 ID
/*京东自定义配置相关信息结束*/

define("API_GOODS", "jd.union.open.goods.promotiongoodsinfo.query");
define("API_VERSION", "1.0");
define("API_FORMAT", "json");
define("API_SIG_METHOD", "md5");
define("API_UNION", "jd.union.open.promotion.common.get");
define("SEARCH_UUID", "1122e6f73219434dbd7320a9049a1cfb");   # JD Search UUID


function getUnionGoodsForSearchSite($keywords)   //通过网站搜索获取商品ID,只获取第一个结果
{
    $searchAPI = 'https://search.jd.com/Search';
    $keywords = urlencode(mb_convert_encoding($keywords, "utf8"));
    $searchParsURL = '?keyword=' . $keywords . '&enc=utf-8&wq=' . $keywords . '&pvid=';
    $searchURL = $searchAPI . $searchParsURL;
    $searchContent = getUrl($url = $searchURL, $diyHeaders = true); # 模仿浏览器
    if (!empty($searchContent)) {
        if (!preg_match('/(?<=data-sku=")\d+/', $searchContent, $matches)) {
            return null;
        }
        $skuID = intval($matches[0]);
        return array(array(array('skuId' => $skuID)));    # 只是偷懒,为了保持一致
    }
    return null;
}

function getUnionGoods($keywords)  // 通过查询关键字进行商品信息获取使用API
{
    $send_field = array();
    $send_field["pageNo"] = 1;
    $send_field["pageSize"] = 3;
    $send_field["searchUUID"] = SEARCH_UUID;

    $data = array();
    $data["categoryId"] = null;
    $data["cat2Id"] = null;
    $data["cat2Id"] = null;
    $data["deliveryType"] = 0;
    $data["fromCommissionRatio"] = null;
    $data["toCommissionRatio"] = null;
    $data["fromPrice"] = null;
    $data["toPrice"] = null;
    $data["hasCoupona"] = 0;
    $data["isHot"] = null;
    $data["isPinGou"] = 0;
    $data["isZY"] = 0;
    $data["isCare"] = 0;
    $data["lock"] = 0;
    $data["orientationFlag"] = 0;
    $data["sort"] = null;
    $data["sortName"] = null;
    $data["key"] = mb_convert_encoding($keywords, "utf8");
    $data["searchType"] = "st1";
    $data["keywordType"] = "kt1";

    $send_field["data"] = $data;

    $send_json = json_encode($send_field, JSON_UNESCAPED_UNICODE);
    $unionSearchAPI = "https://union.jd.com/api/goods/search";
    $ch = curl_init($unionSearchAPI);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $send_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:application/json"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    if (!empty($result)) {
        $arr = json_decode($result, true);
        if (!empty($arr["data"]["unionGoods"])) {
            var_dump($arr["data"]["unionGoods"]);
            return $arr["data"]["unionGoods"];
        }
    }
    return null;
}

function getUrl($url, $diyHeaders = false)   # 请求URL
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    if ($diyHeaders == true) {
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER, 'https://www.jd.com/');
        $headerValue = array('Upgrade-Insecure-Requests: 1', 'Sec-Fetch-User: ?1', 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9', 'Sec-Fetch-Site: same-site', 'Sec-Fetch-Mode: navigate', 'Accept-Language: zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerValue);
    }
    $output = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    return $output;
}

function getPromotionGoodInfoSign($request_time, $bs_param, $api)   # 请求前签名
{
    $param = array();
    $param["method"] = $api;
    $param["timestamp"] = $request_time;
    $param["format"] = API_FORMAT;
    $param["v"] = API_VERSION;
    $param["sign_method"] = API_SIG_METHOD;
    $param["param_json"] = $bs_param;
    $param["app_key"] = APP_KEY;
    ksort($param);

    $request_param = APP_SECRET_KEY;
    foreach ($param as $k => $v) {
        $request_param .= "$k$v";
    }

    $request_param .= APP_SECRET_KEY;
    $md5 = md5($request_param);
    $sign = strtoupper($md5);
    return $sign;
}

function requestGoodsInfo($sku) # 通过商品ID获取商品信息组装URL
{
    $request_time = date("Y-m-d H:i:s", time());
    $bs_param = json_encode(array("skuIds" => $sku));
    $sig = getPromotionGoodInfoSign($request_time, $bs_param, API_GOODS);
    $requesturl = sprintf("https://router.jd.com/api?v=%s&method=%s&access_token=&app_key=%s&sign_method=md5&format=json&timestamp=%s&sign=%s&param_json=%s", API_VERSION, API_GOODS, APP_KEY, urlencode($request_time), $sig, $bs_param);
    return $requesturl;
}

function requestGet($sku)  # 通过商品ID组装URL
{
    //$materialId =  sprintf("https://item.m.jd.com/product/%s.html", $sku);
    $materialId = sprintf("http://item.jd.com/%s.html", $sku);
    $request_time = date("Y-m-d H:i:s", time());
    $arr_tmp = array();
    $arr_tmp["siteId"] = API_SITE_ID;
    $arr_tmp["materialId"] = $materialId;
    $bs_param = json_encode(array("promotionCodeReq" => $arr_tmp));
    $sig = getPromotionGoodInfoSign($request_time, $bs_param, API_UNION);
    $requesturl = sprintf("https://router.jd.com/api?v=%s&method=%s&access_token=&app_key=%s&sign_method=md5&format=json&timestamp=%s&sign=%s&param_json=%s", API_VERSION, API_UNION, APP_KEY, urlencode($request_time), $sig, $bs_param);
    return $requesturl;
}

function getPrice($sku)    # 通过商品ID获取商品价格
{
    $goods_url = requestGoodsInfo($sku);
    $goods_content = getUrl($goods_url);
    $offsale = 0;
    $ret = array();
    if (!empty($goods_content)) {
        $goods_arr = json_decode($goods_content, true);
        if (!empty($goods_arr["jd_union_open_goods_promotiongoodsinfo_query_response"]["result"])) {
            $result = $goods_arr["jd_union_open_goods_promotiongoodsinfo_query_response"]["result"];
            $res_arr = json_decode($result, true);
            $code = isset($res_arr["code"]) ? $res_arr["code"] : 0;
            if ($code == 200) {
                $unitPrice = $res_arr["data"][0]["unitPrice"];
                $commisionRatioWl = $res_arr["data"][0]["commisionRatioWl"];
                $offsale = round($unitPrice * $commisionRatioWl / 100, 2);
                $ret["offsale"] = $offsale;
                $ret["imgUrl"] = $res_arr["data"][0]["imgUrl"];
                $ret["goodsName"] = $res_arr["data"][0]["goodsName"];
            }
        }
    }
    return $ret;
}

function getClickURL($sku)  # 获取最后返现的推广URL
{
    $promp_url = requestGet($sku);
    $promp_content = getUrl($promp_url);
    $returnURL = "";
    if (!empty($promp_content)) {
        $p_arr = json_decode($promp_content, true);
        if (!empty($p_arr["jd_union_open_promotion_common_get_response"]["result"])) {
            $result = $p_arr["jd_union_open_promotion_common_get_response"]["result"];
            $res_arr = json_decode($result, true);
            $code = isset($res_arr["code"]) ? $res_arr["code"] : 0;
            if ($code == 200) {
                $returnURL = $res_arr["data"]["clickURL"];
            }
        }
    }
    return $returnURL;
}

function getSkuId($url)   # 正则获取商品ID，增加了一条规则
{
    $skuID = "";
    if (preg_match("/(\d+)\.html/", $url, $res)) {
        $skuID = $res[1];
    } elseif (preg_match("/^\d{4,}$/", $url, $res)) {
        $skuID = $res[0];
    }
    return $skuID;
}

function writeLog($logData, $saeTag)    # 记录日志,新浪SAE不记录
{
    if ($saeTag === true) {
        return "";
    }
    file_put_contents("/tmp/wx.log", $logData, FILE_APPEND);
}