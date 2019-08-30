<?php

namespace buddysoft\sms\controllers;

use Yii;
use buddysoft\sms\Module;
use buddysoft\sms\utils\SmsSender;
use buddysoft\sms\utils\PseudoConverter;

class SmsController extends \buddysoft\widget\controllers\ApiController
{

  public $smsKey;
  public $smsTemplate;
  public $validTime = null;

  /**
   * 20190830 新加入的配置方式，通过模块配置参数传入：
   * 'controllerMap' => [
   *    'sms' => [
   *      'class' => 'buddysoft\sms\controllers\SmsController',
   *      'smsKey' => '6f32ddfa70b3dd14a12e42f42e37100d',
   *      'smsTemplate' => '【篮球助手】您的验证码为：#code#，请在10分钟内完成验证。如非本人操作，请忽略。',
   *      'pseudos = [
   *        [
   *          'fakeNumber' => '13501010101',  // 用来作为账号的假号码
   *          'sendNumber' => '18037963805',  // 假号码的实际发送目标，一般是测试者手机号
   *        ],
   *        [
   *          'fakeNumber' => '13821212121',  // 用来作为账号的假号码
   *          'sendNumber' => '18037994714',  // 假号码的实际发送目标，一般是测试者手机号
   *        ]
   *      ],
   *    ] 
   * ],
   * 
   * 测试时，手机号填写 fakeNumber，Sms 模块经过比对后，会将短信发往 sendNumber，但 sms 表内保存的还是 fakeNumber.
   * 总的来说，除了最终发送的号码会被替换之外，其它流程都不变。
   *
   * @var array
   */
  public $pseudos = [];

  /**
	 *
	 * 为了兼容之前给 controller 传参数的方式，让配置参数支持 module 参数传入，
	 * 在此做预处理。
	 *
	 */

  private function prepareModuleSetting()
  {
    if (empty($this->smsKey) || empty($this->smsTemplate)) {
      // 从模块配置中加载
      $module = Module::getInstance();
      $settings = $module->smsSettings;
      if (isset($settings['smsKey'])) {
        $this->smsKey = $settings['smsKey'];
      }

      if (isset($settings['smsTemplate'])) {
        $this->smsTemplate = $settings['smsTemplate'];
      }
    }
  }

  public function actionIndex()
  {
    return $this->render('index');
  }

  public function actionSend()
  {
    $this->prepareModuleSetting();

    $params = $_POST;

    if (!isset($params['mobile'])) {
      $this->exitWithInvalidParam();
    }
    $mobile = $params['mobile'];

    if (empty($this->smsKey) || empty($this->smsTemplate)) {
      $this->exitWithCode('短信模板配置错误');
    }

    $key = $this->smsKey;
    $template = $this->smsTemplate;
    $validTime = $this->validTime;

    $sender = new SmsSender($key, $validTime);
    if (false === $sender->setTemplate($template)) {
      $this->exitWithInvalidParam('短信模板错误');
    }
    
    // 引入 pseudoNumber，默认就是用户传来的手机号
    $sendNumber = $mobile;

    // 测试模式时，并不真的发短信，但要创建发送记录和验证码
    if (isset($params['pseudo'])) {
      if (isset(Yii::$app->params['pseudoSms']) && Yii::$app->params['pseudoSms'] === true) {
        $sender->pretendSend = true;	
			}else{
        $this->exitWithInvalidParam('pseudoSms 开关未打开');
			}      
    }else if (! empty($this->pseudos)){
      $sendNumber = PseudoConverter::convert($mobile, $this->pseudos);
    }

    $result = $sender->sendCode($sendNumber, $mobile);

    $this->exitWithSuccess($result);
  }

  public function actionVerify()
  {
    $params = $_POST;

    if (!isset($params['mobile']) or !isset($params['code'])) {
      $this->exitWithInvalidParam();
    }

    $mobile = $params['mobile'];
    $code = $params['code'];

    $sender = new SmsSender();
    $result = $sender->verify($mobile, $code);
    if ($result === true) {
      $this->exitWithSuccess();
    } else {
      $this->exitWithInvalidParam('验证码错误');
    }
  }
}
 