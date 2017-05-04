<?php

namespace buddysoft\sms;

use buddysoft\sms\SmsModel;

class SmsSender{
	CONST apiurl = "http://yunpian.com/v1/sms/send.json";

	// 从云片网申请的 apikey，必须设置
	public $apiKey;

	// 在云片网络设置的短信发送模板，必须以【服务名称】开头，包含 #code# 字符串，必须设置
	public $yunpianTemplate;

	// 验证码记录有效时间，用来区分失效的验证码，单位秒
	public $validTime = 600;

	public function __construct($key = null, $validTime = null){
		$this->apiKey = $key;
		if (! empty($validTime)) {
			$this->validTime = $validTime;
		}
	}
	
	/**
	 *
	 * 设置短信发送模板
	 * @param string $template 必以【服务名称】开头，包含 #code# 的字符串
	 * @return boolean true 设置成功，false 设置失败
	 */
	
	public function setTemplate($template){
		// if (false === strstr($template, '#code#')) {
		// 	return false;
		// }

		// if (empty(preg_match('【.*】', $template))) {
		// 	return false;
		// }

		$this->yunpianTemplate = $template;

		return true;
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

	private function makeSms($code){
		$text = str_replace('#code#', $code, $this->yunpianTemplate);
		return $text;
	}

	private function sendText($text, $mobile){
		$text = urlencode($text);
		$mobile = urlencode($mobile);

		$postString = "apikey=".$this->apiKey;
		$postString .= "&text=".$text;
		$postString .= "&mobile=".$mobile;

		return $this->sock_post(self::apiurl, $postString);
	}

	/**
	 *
	 * 组合短信内容和发送参数，发送验证码
	 *
	 */
	
	// private function directSendSms($mobile, $code){

		// $text="【曦光科技】您的验证码是".$code;
		// $encodedText = urlencode($text);
		// $mobile=urlencode("$mobile");
		// $postString = "apikey=".$this->apiKey;
		// $postString .= "&text=".$encodedText;
		// $postString .= "&mobile=".$mobile;
		
		// return $this->sock_post(self::apiurl, $postString);
	// }

	/**
	 *
	 * 发送验证码外部服务接口
	 *
	 */
	
	public function sendCode($mobile){

		if (! is_numeric($mobile) || strlen($mobile) < 11) {
			return false;
		}

		if (empty($this->yunpianTemplate)) {
			return false;
		}

		# 【#company#】您的验证码是#code#
		$code = $this->newCode($mobile);
		$text = $this->makeSms($code);
		$result = $this->sendText($text, $mobile);

		$data['code'] = $code;
		$data['yunpian'] = $result;

		return $data;
	}
	
	/**
	 *
	 * 向某个用户发送一条短信，短信模板的变量被 params 数组代替
	 *
	 * @param Array $params 参数数组，跟短信模板对应
	 * @param String $mobile 接收短信手机号
	 *
	 */
	
	public function sendWithParams($params, $mobile){
		$text = $this->yunpianTemplate;
		foreach ($params as $key => $value) {
			$text = str_replace("#{$key}#", $value, $text);
		}

		return $this->sendText($text, $mobile);
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