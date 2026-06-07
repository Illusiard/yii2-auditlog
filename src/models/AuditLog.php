<?php

namespace illusiard\auditlog\models;

use illusiard\auditlog\components\AuditLogger;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "audit_log".
 *
 * @property int               $id
 * @property int               $entity_type_id
 * @property int               $entity_id
 * @property int               $action_id
 * @property ?int              $user_id
 * @property ?string           $diff
 * @property ?string           $context
 * @property string            $created_at
 *
 * @property AuditAction        $action
 * @property AuditEntityType    $entityType
 * @property ?IdentityInterface $user
 */
class AuditLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%audit_log}}';
    }

    public function rules(): array
    {
        $rules = [
            [['user_id', 'diff', 'context'], 'default', 'value' => null],
            [['entity_type_id', 'entity_id', 'action_id'], 'required'],
            [['entity_type_id', 'entity_id', 'action_id', 'user_id'], 'default', 'value' => null],
            [['entity_type_id', 'entity_id', 'action_id', 'user_id'], 'integer'],
            [['diff', 'context', 'created_at'], 'safe'],
            [
                ['action_id'],
                'exist',
                'skipOnError'     => true,
                'targetClass'     => AuditAction::class,
                'targetAttribute' => ['action_id' => 'id'],
            ],
            [
                ['entity_type_id'],
                'exist',
                'skipOnError'     => true,
                'targetClass'     => AuditEntityType::class,
                'targetAttribute' => ['entity_type_id' => 'id'],
            ],
        ];

        if (($userClass = $this->getUserClass()) !== null) {
            $rules[] = [
                ['user_id'],
                'exist',
                'skipOnError'     => true,
                'targetClass'     => $userClass,
                'targetAttribute' => ['user_id' => 'id'],
            ];
        }

        return $rules;
    }

    public function attributeLabels(): array
    {
        return [
            'id'             => 'ID',
            'entity_type_id' => 'Entity Type',
            'entity_id'      => 'Entity',
            'action_id'      => 'Action',
            'user_id'        => 'User',
            'diff'           => 'Diff',
            'context'        => 'Context',
            'created_at'     => 'Created At',
        ];
    }

    public function getAction(): ActiveQuery
    {
        return $this->hasOne(AuditAction::class, ['id' => 'action_id']);
    }

    public function getEntityType(): ActiveQuery
    {
        return $this->hasOne(AuditEntityType::class, ['id' => 'entity_type_id']);
    }

    public function getUser(): ?ActiveQuery
    {
        if (($userClass = $this->getUserClass()) !== null) {
            return $this->hasOne($userClass, ['id' => 'user_id']);
        }

        return null;
    }

    /**
     * @return class-string<ActiveRecord>|null
     */
    private function getUserClass(): ?string
    {
        $userClass = AuditLogger::getInstance()->userClass;

        if ($userClass === null || !is_a($userClass, ActiveRecord::class, true)) {
            return null;
        }

        return $userClass;
    }
}
