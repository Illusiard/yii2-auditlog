<?php

use yii\db\Expression;
use yii\db\Migration;

class m251123_101600_audit extends Migration
{
    public function safeUp(): void
    {
        /**
         * Справочник типов сущностей для аудита
         */
        $this->createTable('{{%audit_entity_type}}', [
            'id'          => $this->primaryKey(),
            'code'        => $this->string(64)->notNull()->unique(),
            'name'        => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'created_at'  => $this->dateTime()->notNull()->defaultExpression(new Expression('CURRENT_TIMESTAMP')),
        ]);

        /**
         * Справочник действий
         */
        $this->createTable('{{%audit_action}}', [
            'id'          => $this->primaryKey(),
            'code'        => $this->string(64)->notNull()->unique(),
            'name'        => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'created_at'  => $this->dateTime()->notNull()->defaultExpression(new Expression('CURRENT_TIMESTAMP')),
        ]);

        /**
         * Основная таблица логов
         */
        $this->createTable('{{%audit_log}}', [
            'id'              => $this->primaryKey(),
            'entity_type_id'  => $this->integer()->notNull(),
            'entity_id'       => $this->integer()->notNull(),
            'action_id'       => $this->integer()->notNull(),
            'user_id'         => $this->integer()->null(),
            'diff'            => $this->json()->null(),
            'context'         => $this->json()->null(),
            'created_at'      => $this->dateTime()->notNull()->defaultExpression(new Expression('CURRENT_TIMESTAMP')),
        ]);

        $this->createIndex('idx-audit_log-entity', '{{%audit_log}}', ['entity_type_id', 'entity_id']);
        $this->createIndex('idx-audit_log-action', '{{%audit_log}}', ['action_id']);
        $this->createIndex('idx-audit_log-user_id', '{{%audit_log}}', ['user_id']);

        if ($this->supportsForeignKeys()) {
            $this->addForeignKey('fk-audit_log-entity_type', '{{%audit_log}}', 'entity_type_id', '{{%audit_entity_type}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey('fk-audit_log-action', '{{%audit_log}}', 'action_id', '{{%audit_action}}', 'id', 'CASCADE', 'CASCADE');
        }
    }

    public function safeDown(): void
    {
        if ($this->supportsForeignKeys()) {
            $this->dropForeignKey('fk-audit_log-action', '{{%audit_log}}');
            $this->dropForeignKey('fk-audit_log-entity_type', '{{%audit_log}}');
        }

        $this->dropTable('{{%audit_log}}');
        $this->dropTable('{{%audit_action}}');
        $this->dropTable('{{%audit_entity_type}}');
    }

    private function supportsForeignKeys(): bool
    {
        return $this->getDb()->driverName !== 'sqlite';
    }
}
