<?php

namespace buddysoft\sms;

use Yii;
use common\widgets\ApiController;
// use buddysoft\sms\SmsSender;

class SmsController extends ApiController{

	public function actionSend(){
		$params = $_POST;

		if (! isset($params['mobile'])) {
			$this->exitWithInvalidParam();
		}
		$mobile = $params['mobile'];

		// 测试模式时，并不真的发短信
		if (isset($params['pseudo'])) {
			$pseudo = true;
		}

		if (! isset(Yii::$app->params['yunpian'])) {
			$this->exitWithError('短信模板不存在');
		}

		$params = Yii::$app->params['yunpian'];
		if (! isset($params['key']) || 
			! isset($params['template'])) {
			$this->exitWithCode('短信模板配置错误');
		}

		$key = $params['key'];
		$template = $params['template'];

		$validTime = 0;
		if (isset($params['validTime']) && is_integer($params['validTime'])) {
			$validTime = $params['validTime'];
		}

		$sender = new SmsSender($key, $validTime);
		if(false === $sender->setTemplate($template)){
			$this->exitWithInvalidParam('短信模板错误');
		}

		if (isset($pseudo) && $pseudo === true) {
			$result = 0;
		}else{
			$result = $sender->sendCode($mobile);
		}
		
		$this->exitWithSuccess($result);
	}

	public function actionVerify(){
		$params = $_POST;

		if (!isset($params['mobile']) or
			!isset($params['code'])) {
			$this->exitWithInvalidParam();
		}

		$mobile = $params['mobile'];
		$code = $params['code'];

		$sender = new SmsSender();
		$result = $sender->verify($mobile, $code);
		if ($result === true) {
			$this->exitWithSuccess();
		}else{
			$this->exitWithInvalidParam('验证码错误');
		}
	}
}

?>
