<?php

namespace buddysoft\sms\controllers;

use Yii;
use buddysoft\sms\Module;

class SmsController extends \buddysoft\widget\controllers\ApiController{

	public $smsKey;
	public $smsTemplate;
	public $validTime = null;

	/**
	 *
	 * 为了兼容之前给 controller 传参数的方式，让配置参数支持 module 参数传入，
	 * 在此做预处理。
	 *
	 */
	
	private function prepareModuleSetting(){
		if (empty($this->smsKey) || empty($this->smsTemplate)) {
			// 从模块配置中加载
			$module = Module::getInstance();
			$settings = $module->defaultSettings;
			if (isset($settings['smsKey'])) {
				$this->smsKey = $settings['smsKey'];
			}

			if (isset($settings['smsTemplate'])) {
				$this->smsTemplate = $settings['smsTemplate'];
			}
		}
	}

	public function actionIndex(){
		return $this->render('index');
	}

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

		if (empty($this->smsKey) || empty($this->smsTemplate)) {
			$this->exitWithCode('短信模板配置错误');
		}

		$this->prepareModuleSetting();

		$key = $this->smsKey;
		$template = $this->smsTemplate;
		$validTime = $this->validTime;

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
