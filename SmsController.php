<?php

namespace buddysoft\sms;

use buddysoft\sms\SmsModel;

class SmsController{
	CONST apiurl = "http://yunpian.com/v1/sms/send.json";

	private $apiKey;
	private $validTime;

	/**
	 *
	 * 构造函数，必须通过该构造函数创建对象
	 * @param string $apkKey 从云片网申请的 apikey
	 * @param integer $validTime 验证码记录有效时间，用来区分失效的验证码
	 *
	 */
	
	public function __construct($apiKey, $validTime){
		$this->apiKey = $apikey;
		$this->validTime = $validTime;
	}

	/**
	 *
	 * 返回十分钟前的时间
	 * 
	 * 用于查找十分钟（600秒）内是否发送过验证码
	 */
	
	private function validBeginTime(){
		$now = time();
		$now -= $this->validTime;
		$timeString = date('Y-m-d H:i:s', $now);
		// $timeString .= ".000Z";

		return $timeString;
	}

	/*
     * 调用云片 API 发送短信
     * 
     * url 为服务的url地址
     * query 为请求串
     */
	private	function sock_post($url,$query){
	    $data = "";
	    $info=parse_url($url);
	    $fp=fsockopen($info["host"],80,$errno,$errstr,30);
	    if(!$fp){
		    return $data;
		}

	    $head="POST ".$info['path']." HTTP/1.0\r\n";
	    $head.="Host: ".$info['host']."\r\n";
	    $head.="Referer: http://".$info['host'].$info['path']."\r\n";
	    $head.="Content-type: application/x-www-form-urlencoded\r\n";
		$head.="Content-Length: ".strlen(trim($query))."\r\n";
		$head.="\r\n";
		$head.=trim($query);
		$write=fputs($fp,$head);
		$header = "";
		while ($str = trim(fgets($fp,4096))) {
		    $header.=$str;
		}

		while (!feof($fp)) {
			$data .= fgets($fp,4096);
	    }

	    return $data;
	}

	/**
	 *
	 * 组合短信内容和发送参数，发送验证码
	 *
	 */
	
	private function directSendSms($mobile, $code){
		$text="【曦光科技】您的验证码是".$code;
		$encodedText = urlencode($text);
		$mobile=urlencode("$mobile");
		$postString = "apikey=".$this->apikey;
		$postString .= "&text=".$encodedText;
		$postString .= "&mobile=".$mobile;

		return $this->sock_post(self::apiurl, $postString);
	}

	/**
	 *
	 * 发送验证码外部服务接口
	 *
	 */
	
	public function sendCode($mobile){

		if (! is_numeric($mobile) || strlen($mobile) < 11) {
			return fasle;
		}

		# 【#company#】您的验证码是#code#
		$code = $this->newCode($mobile);
		$result = $this->directSendSms($mobile, $code);

		$data['code'] = $code;
		$data['yunpian'] = $result;

		return $data;
	}

	/**
	 *
	 * 针对某个手机号生成新的验证码
	 *
	 * 首先检查该手机最近（10分钟内）是否有验证码记录，有的话，更新记录中的验证码，否则创建新的记录。
	 */
	
	private function newCode($mobile){
		$timeString = $this->validBeginTime();

		$objects = SmsModel::find()
			->where(['mobile' => $mobile])
			->andWhere(['>', 'createdAt', $timeString])
			->orderBy(['createdAt' => SORT_DESC])
			->all();

		$code = rand(10, 10000);
		$code = sprintf("%06d", $code);

		if (empty($objects)) {
			// 生成 code，添加记录
			$model = new SmsModel();
			$model->mobile = $mobile;
			
		}else{
			// 更新 code，保存记录
			$model = $objects[0];
		}

		$model->code = $code;
		$model->save();

		return $code;
	}

	/**
	 *
	 * 验证『验证码』是否正确
	 *
	 * 只针对最近（10分钟内）的记录进行验证
	 */
	
	public function verify($mobile, $code){
		$timeString = $this->validBeginTime();

		$model = SmsModel::find()
			->where(['mobile' => $mobile, 'code' => $code])
			->andWhere(['>', 'createdAt', $timeString])
			->one();

		if (empty($model)) {
			return false;
		}else{
			return true;
		}
	}
	
}

?>