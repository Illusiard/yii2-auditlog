<?php

use yii\db\Migration;

class m260919_000000_change_audit_log_identifiers_to_string extends Migration
{
    public function safeUp(): void
    {
        $this->alterColumn('{{%audit_log}}', 'entity_id', $this->string(64)->notNull());
        $this->alterColumn('{{%audit_log}}', 'user_id', $this->string(64)->null());
    }

    public function safeDown(): void
    {
        $this->alterColumn('{{%audit_log}}', 'entity_id', $this->integer()->notNull());
        $this->alterColumn('{{%audit_log}}', 'user_id', $this->integer()->null());
    }
}
