<?php

namespace illusiard\auditlog\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ?string $description
 * @property string $created_at
 *
 * @property AuditLog[] $auditLogs
 */
class AuditEntityType extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%audit_entity_type}}';
    }

    public function rules(): array
    {
        return [
            [['description'], 'default', 'value' => null],
            [['code', 'name'], 'required'],
            [['description'], 'string'],
            [['created_at'], 'safe'],
            [['code'], 'string', 'max' => 64],
            [['name'], 'string', 'max' => 255],
            [['code'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'code' => 'Code',
            'name' => 'Name',
            'description' => 'Description',
            'created_at' => 'Created At',
        ];
    }

    public function getAuditLogs(): ActiveQuery
    {
        return $this->hasMany(AuditLog::class, ['entity_type_id' => 'id']);
    }
}
