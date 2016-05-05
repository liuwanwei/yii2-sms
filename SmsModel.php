<?php

namespace buddysoft\sms;

use Yii;

/*
 * @property string mobile
 * @property string code
 * @property string $createdAt
 * @property string $updatedAt
 */

class SmsModel extends \yii\db\ActiveRecord{

	public static function tableName(){
		return 'sms';
	}

	public function rules(){
		return [
			[['mobile'], 'string', 'max' => 16],
			[['code'], 'string', 'max' => 12],
			[['createdAt', 'updatedAt'], 'safe']
		];
	}
}