<?php
/**
 * 验证短信发送控制器
 * 
 * 使用时，直接在应用的配置文件中，通过 controllerMap 参数将发送短信的路由（名字自定义）
 * 重定向到这里。主要功能有：
 * 1. 发送短信验证码接口
 * 2. 对“验证码”进行有效性验证接口
 * 
 * 除了基本发送功能之外，为了测试方便，还提供了两种辅助测试功能：
 * 一. 假发送功能（pretend send）
 * 一般用在测试初期的 API 调试时，client 设定了 'pseudo' 参数后，服务器并不真的发送短信，而是将生成的验证码（code 字段）直接返回给 client。
 * client 可以将验证码填充到有效性验证接口上，继续进行测试。
 * 
 * 二. 发送转移功能（transfer）
 * 一般用在模拟创建账号时，找不到可以接收验证码的手机号的情况。
 * 可以将任意手机号（号码 A）和能接收验证码的手机号（号码 B，注没注册过都行）配置在当前控制器的 transfers 参数（详情参考参数说明）中。
 * 在注册时可以填写号码 A，真正的短信会发送到号码 B，这样就能基本模拟最“真实”的用户注册场景。
 * 
 * **这两个参数都通过 testSms 开关打开，打开后同时生效。**
 */
namespace buddysoft\sms\controllers;

use Yii;
use buddysoft\sms\Module;
use buddysoft\sms\utils\SmsSender;
use bytefly\yii2api\controllers\AntiqueApiController;

class SmsController extends AntiqueApiController
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
   *      'transfers = [
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
   * 或者将 transfers 配置加入 common\config\params.php，这样可以在其它模块（如 console ）中控制对假用户的操作。
   * 测试发送总开关放在应用全局配置文件中（-local.php），可以随时修改。
   * 
   * 测试时，手机号填写 fakeNumber，Sms 模块经过比对后，会将短信发往 sendNumber，但 sms 表内保存的还是 fakeNumber.
   * 总的来说，除了最终发送的号码会被替换之外，其它流程都不变。
   *
   * @var array
   */
  public $transfers = [];

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
    
    // 初始化发送对象手机号
    $sendNumber = $mobile;

    /**
     * 检查手机号是否匹配中伪发送手机号
     * 测试模式时，并不真的发短信，但要创建发送记录和验证码
     */
    if (isset(Yii::$app->params['testSms']) && Yii::$app->params['testSms'] === true) {      
      if (isset($params['pseudo'])) {
        // 假发送功能优先级较高
        $sender->pretendSend = true;

      }else {
        // 没有假发送时，检查是否有命中发送转移条件
        foreach ($this->transfers as $transfer) {
          if ($transfer['fakeNumber'] == $mobile) {
            $sendNumber = $transfer['sendNumber'];
            break;
          }
        }
      }      
    }

    $result = $sender->sendCode($sendNumber, $mobile);

    if ($result['result'] === true) {
      $this->exitWithSuccess($result);
    }else{
      $this->exitWithErrorData("发送失败", $result);
    }
  }

  /**
   * 判断验证码是否正确
   * 
   * @param string $_POST['mobile']
   * @param string $_POST['code']
   *
   * @return void
   */
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
 