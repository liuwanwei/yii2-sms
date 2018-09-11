<?php

use yii\db\Migration;

/**
 * Handles the creation of table `sms`.
 */
class m180911_025036_create_sms_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('sms', [
            'id' => $this->primaryKey(),
            'mobile' => $this->string(16)->notNull(),
            'code' => $this->string(12)->notNull()->comment('验证码'),
            'createdAt' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
            'updatedAt' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP')
        ]);

        $this->createIndex('idx_mobile', 'sms', 'mobile');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('sms');
    }
}
