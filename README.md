# sms
sms service originally supporting YunPian

## Install
composer require buddysoft/yii2-sms dev-master

## 导入 sms 表

./yii migrate --migrationPath=@buddysoft/sms/migrations

## Yii2 项目配置

在 main.php 数组根目录下（跟 components 同级）添加类似配置：

"""
'controllerMap' => [
    // 短信验证，需要 composer require buddysoft/yii2-sms "~1.0.4"
    'sms' => [
        'class' => 'buddysoft\sms\controllers\SmsController',
        'smsKey' => '6f32f42e37100d',
        'smsTemplate' => '【购物助手】您的验证码为：#code#，请在10分钟内完成验证。如非本人操作，请忽略。',
    ],
],
"""
