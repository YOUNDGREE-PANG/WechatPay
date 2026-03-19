<?php
namespace app\api\controller;
session_set_cookie_params(7200); // 设置cookie有效期为1800秒（30分钟）
session_start();


class Index
{
      /**
     * 小程序获取手机号码
     *
     * @ApiTitle    (小程序获取手机号码)
     * @ApiSummary  (接受前端提交的code信息并通过微信官方API获取手机号)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/index/get_phonenumber/code/{code}/departmentid/{departmentid}/personid/{personid})
     * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
     * @ApiParams   (name="code", type="string", required=true, description="小程序code")
     * @ApiParams  (name="departmentid", type="integer", required=true, description="部门ID")
     * @ApiParams  (name="personid", type="integer", required=true, description="员工ID")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="获取手机号码成功!",description="返回的状态信息")
     * @ApiReturn   ({'code':'1','msg':'获取手机号码成功!'})
     */
    public function get_phonenumber(){
        
        $appid = "your miniprogram's appid";
    	$appsecret = your miniprogram's secret";
    	$url="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
        $code=$this->request->post("code");
        $open_id=$this->request->post("open_id");
        //获取access_token并赋值给变量
        $res = json_decode(file_get_contents($url),true);
        $access_token = $res["access_token"];
        //以POST形式发送请求到微信获取电话号码接口
        $url2="https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=".$access_token;
        $data=array("code"=>$code);
        //构造AJAX格式的请求头
        $options = array(
                'http' => array(
                    'method' => 'POST',    
                    'header' => 'Content-type:application/x-www-form-urlencoded',    
                    'content' => json_encode($data),    
                    'timeout' => 15 * 60 // 超时时间（单位:s）    
                )    
            );    
            $context = stream_context_create($options);   
            //访问接口（路径为$url）并接收返回值 
            $result = file_get_contents($url2, false, $context);
            $cellphonenumber=substr($result,56,11);
            $userinfodata=array('mobile'=>$cellphonenumber,'origin'=>1,'date_time'=>date("Y-m-d H:i:s"),'open_id'=>$open_id);
            $userinfodata1=array('mobile'=>'暂无手机号码','origin'=>1,'date_time'=>date("Y-m-d H:i:s"),'open_id'=>$open_id);
            
            $find=db('miniprogram_user')->where('mobile',$cellphonenumber)->find();
            $find1=db('miniprogram_user')->where('open_id',$open_id)->find();
            
             if(!preg_match("/^1[3456789]{1}\d{9}$/",$cellphonenumber)&&empty($find1)){
                 //没有成功获取手机号
                 
              $insertuserinfo=db('miniprogram_user')->insert($userinfodata1);   
                 
             }else if(empty($find)&&empty($find1)){//没授权过手机号也没登录过

              $insertuserinfo=db('miniprogram_user')->insert($userinfodata);

            }else if(empty($find)&&!empty($find1)){//已经登录过
                                
              $update=db('miniprogram_user')->where("open_id",$open_id)->update(["mobile"=>$cellphonenumber,'date_time'=>date("Y-m-d H:i:s")]);             
                            }
 
            return $result;
    }
    
    //小程序下单接口
    public function submit_order(){
     
    $order_id=$this->request->post("order_id");//企微H5生成的下单号
    $open_id=$this->request->post("open_id");//提交的学员openid
    $amount=$this->request->post("price");
    $rand= random_bytes(5);
    $business_name=$this->request->post("business_name");
    $IDnumber=$this->request->post("IDnumber");//提交的学员身份证号
    $user_name=$this->request->post("user_name");//提交的学员姓名
    $mobile=$this->request->post("mobile");//提交的学员姓名
    $auth_code = bin2hex($rand);//生成的5个随机字符 同时也是验证码

    $out_trade_no=date("YmdHis").$auth_code.'ORDER';//商户订单号，6-32个字符内，只能是数字、大小写字母且在同一个服务商商户号下唯一
    $params = [
    // 'amount'=>0.01,//下单金额
    'amount'=>$amount,//下单金额
    'orderid'=>$out_trade_no,//商户订单号
    'type'=>"wechat",
    'title'=>"提交报名",//支付标题
    'method'=>"miniapp",
    'openid'=>$open_id,
    'auth_code'=>$auth_code
    ];
    
    $response = \addons\epay\library\Service::submitOrder($params);
  
    $prepay_id=$response['package'];
    $prepay_id = substr($prepay_id,10);
    $date_time=date("Y-m-d H:i:s");
    $find=db('mini_orders')->where('out_trade_no',$out_trade_no)->find();
       
         
    if(empty($find)){
    
    $insert=db('mini_orders')->insert(['out_trade_no'=>$out_trade_no,'good_id'=>'666','good_name'=>'提交报名','open_id'=>$open_id,'prepay_id'=>$prepay_id,'date_time'=>$date_time,"order_id"=>$order_id,"IDnumber"=>$IDnumber,"mobile"=>$mobile,"user_name"=>$user_name]);
 
    }
    
  
    return $response;
    
    }
    
    
    
    
    
    public function  pay_success_info(){//获取微信支付结果通知
    
    $xmlData = file_get_contents("php://input");//获取微信支付结果通知
    $xml = simplexml_load_string($xmlData);//解析XML内容
    
    if ($xml) {//解析成功
        
    $key=0;
    foreach ($xml->children() as $child) {//遍历XML内容
    
    $notify_key[$key]=$child->getName();
    $notify_value[$key]=''.$child.'';
    $key++;
                }

  
    $notify_data=array_combine($notify_key,$notify_value); 
    $sign=$notify_data["sign"];
    $out_trade_no=$notify_data["out_trade_no"];
    $appid=$notify_data["appid"];
    $bank_type=$notify_data["bank_type"];
    $cash_fee=$notify_data["cash_fee"];
    $fee_type=$notify_data["fee_type"];
    $is_subscribe=$notify_data["is_subscribe"];
    $mch_id=$notify_data["mch_id"];
    $nonce_str=$notify_data["nonce_str"];
    $openid=$notify_data["openid"];
    $time_end=$notify_data["time_end"];
    $total_fee=$notify_data["total_fee"];
    $trade_type=$notify_data["trade_type"];
    $transaction_id=$notify_data["transaction_id"];
    $return_code=$notify_data["return_code"];
    
 $find=db('notify_data')->where("out_trade_no",$out_trade_no)->where("transaction_id",$transaction_id)->find();
  if(empty($find)){
     
      $result_code=$notify_data["result_code"];
     
      if($result_code=="SUCCESS"){
      $insert_data=array(
        "appid"=>$appid,
        "bank_type"=>$bank_type,
        "cash_fee"=>$cash_fee,
        "fee_type"=>$fee_type,
        "is_subscribe"=>$is_subscribe,
        "mch_id"=>$mch_id,
        "nonce_str"=>$nonce_str,
        "openid"=>$openid,
        "time_end"=>$time_end,
        "total_fee"=>$total_fee,
        "trade_type"=>$trade_type,
         "transaction_id"=>$transaction_id,
        "sign"=>$sign, 
        "out_trade_no"=>$out_trade_no,
        "result_code"=>$result_code,
        "return_code"=>$return_code
        );   
    $insert=db('notify_data')->insert($insert_data);
    $order_id=implode(db('mini_orders')->where("out_trade_no",$notify_data["out_trade_no"])->column("order_id"));
    $update=db('scrm_reg_info')->where("order_id",$order_id)->update(["has_pay"=>1]);
    $amount=$notify_data["total_fee"]/100;
    $ww_department=implode(db('scrm_reg_info')->where("order_id",$order_id)->column("ww_department"));
    $ww_user_name=implode(db('scrm_reg_info')->where("order_id",$order_id)->column("ww_user_name"));
    $department_id=json_decode($ww_department)[0];
    $IDnumber=implode(db('mini_orders')->where("out_trade_no",$notify_data["out_trade_no"])->column("IDnumber"));
    $student_name=implode(db('mini_orders')->where("out_trade_no",$notify_data["out_trade_no"])->column("user_name"));
    $mobile=implode(db('mini_orders')->where("out_trade_no",$notify_data["out_trade_no"])->column("mobile"));
    $bussines_type=implode(db('scrm_reg_info')->where("order_id",$order_id)->column("bussines_type"));
    $subject=implode(db('scrm_reg_info')->where("order_id",$order_id)->column("subject"));
    $type=implode(db('scrm_reg_info')->where("order_id",$order_id)->column("type"));
    $open_id=implode(db('mini_orders')->where("out_trade_no",$notify_data["out_trade_no"])->column("open_id"));
    //$data=array("dept_no"=>$department_id,"has_child"=>true);
    $data=array("dept_no"=>$department_id,"has_child"=>true);
        //构造AJAX格式的请求头
             $jsonData = json_encode($data); // 将数组转换为JSON字符串
        // return $jsonData;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.jiandaoyun.com/api/v5/corp/department/user/list");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 设置多个 Header
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Authorization: Bearer ADxWjmletyhfwmKgckQ1zg2SKJElJmv3'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // 发送JSON数据
        $res = curl_exec($ch);
        curl_close($ch);
            $res=json_decode($res,true);
            $res_data=$res["users"];

        foreach ($res_data as $key => $value) {
            if ($res_data[$key]["name"]==$ww_user_name) {
            $jdy_name=$res_data[$key]["username"];
                }
            }
        
 
       
    
    
    public function get_sign(){
        
        
        
        $string="appid=your miniprogram's appid&mch_id=your mch_id2&nonce_str=eIVks5uPnC6zJwZz&out_trade_no=2026031816252529cdb08091ORDER";
        
    
   
        //进行MD5加密
        $sign=md5($string);

        //将全部字母转换为大写得到签名
        $sign1 = strtoupper($sign);
       return $sign1;
        
        
    }
    
    
        public function  pay_refund(){//微信支付退款接口
        $appid="your miniprogram's appid";
        $out_trade_no=$this->request->post("out_trade_no");//商户订单号
        $noncestr=implode(db("notify_data")->where("out_trade_no",$out_trade_no)->where("result_code","SUCCESS")->limit(1)->column("nonce_str"));
      
        // $chars ='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $key="Dasd4884hdjjjdhk35j3j398j7eid84j";
            // $noncestr = '';
        // for ( $i = 0; $i <6; $i++ ){
        // // 这里提供两种字符获取方式 
        // // 第一种是使用 substr 截取$chars中的任意一位字符； 
        // // 第二种是取字符数组 $chars 的任意元素 
        // // $password .= substr($chars, mt_rand(0, strlen($chars) – 1), 1); 
        // $noncestr .= $chars[ mt_rand(0, strlen($chars) - 1) ]; 
        //     }
       $refund_fee=1;
       $total_fee=3;
       $mch_id="your mch_id2";
       $out_refund_no=$out_trade_no;
       $string="appid=".$appid."&mch_id=".$mch_id."&nonce_str=".$noncestr."&out_refund_no=".$out_refund_no."&out_trade_no=".$out_trade_no."&refund_fee=".$refund_fee."&total_fee=".$total_fee."&key=".$key;
        
    
   
        //进行MD5加密
        $sign=md5($string);

        //将全部字母转换为大写得到签名
        $sign1 = strtoupper($sign);
        $url="https://api.mch.weixin.qq.com/secapi/pay/refund";
        //以POST形式提交XML到微信关闭订单接口




// 创建cURL资源
$ch = curl_init();
// 设置cURL选项
curl_setopt($ch, CURLOPT_URL, "https://api.mch.weixin.qq.com/secapi/pay/refund"); // 设置目标URL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// 将结果作为字符串返回，而不是直接输出
curl_setopt($ch, CURLOPT_POST, true);
// 设置为POST请求
curl_setopt($ch, CURLOPT_SSLCERT,'/www/wwwroot/ddz.zhuxinedu.com/certs/apiclient_cert.pem');
// 设置证书文件路径
curl_setopt($ch, CURLOPT_SSLKEY, '/www/wwwroot/ddz.zhuxinedu.com/certs/apiclient_key.pem');
// 设置私钥文件路径
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
// 设置请求头为XML类型
$xmlData=
"<xml>
<appid>".$appid."</appid>
<mch_id>".$mch_id."</mch_id>
<nonce_str>".$noncestr."</nonce_str>
<out_refund_no>".$out_trade_no."</out_refund_no>
<out_trade_no>".$out_trade_no."</out_trade_no>
<refund_fee>".$refund_fee."</refund_fee>
<total_fee>".$total_fee."</total_fee>
 <sign>".$sign1."</sign>
</xml>";
//准备XML数据


curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
//设置POST字段为XML数据

// 执行cURL请求
$response = curl_exec($ch);

// 检查是否有错误发生
if(curl_errno($ch)){
    echo 'Curl error: ' . curl_error($ch);
} else {
    // 返回退款信息
    // echo $response;
    
     $xml = simplexml_load_string($response);//解析XML内容
    
    if ($xml) {//解析成功
        
    $key=0;
    foreach ($xml->children() as $child) {//遍历XML内容
    
    $notify_key[$key]=$child->getName();
    $notify_value[$key]=''.$child.'';
    $key++;
                }

  }
    $notify_data=array_combine($notify_key,$notify_value); 
    return json($notify_data);
    
}

// 关闭cURL资源
curl_close($ch);

    
        
     
            
        }
        
       
    
    
    
}
