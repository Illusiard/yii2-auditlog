<?php

namespace illusiard\auditlog\components;

use illusiard\auditlog\models\AuditAction;
use illusiard\auditlog\models\AuditEntityType;
use illusiard\auditlog\models\AuditLog;
use Yii;
use yii\base\Component;
use yii\caching\CacheInterface;
use yii\db\Connection;
use yii\helpers\ArrayHelper;

class AuditLogger extends Component
{
    private static ?self $_instance = null;

    public ?string $userClass = null;

    public string $cacheKeyEntityTypes = 'audit:entityTypeMap';
    public string $cacheKeyActions     = 'audit:actionMap';

    /** @var array<string,int> code => id */
    private array $entityTypeMap = [];

    /** @var array<string,int> code => id */
    private array $actionMap = [];

    public static function getInstance(): ?static
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function init(): void
    {
        parent::init();
        self::$_instance = $this;
        $this->loadCache();
    }

    private function loadCache(): void
    {
        $entityTypes = Yii::$app->cache->get($this->cacheKeyEntityTypes);
        $actions     = Yii::$app->cache->get($this->cacheKeyActions);

        if (!is_array($entityTypes)) {
            $entityTypes = ArrayHelper::map(AuditEntityType::find()->select(['id', 'code'])->all(), 'code', 'id');
            Yii::$app->cache->set($this->cacheKeyEntityTypes, $entityTypes);
        }
        if (!is_array($actions)) {
            $actions = ArrayHelper::map(AuditAction::find()->select(['id', 'code'])->all(), 'code', 'id');
            Yii::$app->cache->set($this->cacheKeyActions, $actions);
        }

        $this->entityTypeMap = $entityTypes;
        $this->actionMap     = $actions;
    }


    public function resetCache(): void
    {
        Yii::$app->cache->delete($this->cacheKeyEntityTypes);
        Yii::$app->cache->delete($this->cacheKeyActions);

        $this->entityTypeMap = [];
        $this->actionMap     = [];

        $this->loadCache();
    }

    public function log(
        string $entityTypeCode,
        int $entityId,
        string $actionCode,
        ?array $diff = null,
        ?array $context = null,
        ?int $userId = null
    ): void {
        if ($userId === null && !Yii::$app->user->isGuest) {
            $userId = (int)Yii::$app->user->id;
        }

        $entityTypeId = $this->ensureEntityType($entityTypeCode);
        $actionId     = $this->ensureAction($actionCode);

        $log = new AuditLog([
            'entity_type_id' => $entityTypeId,
            'entity_id'      => $entityId,
            'action_id'      => $actionId,
            'user_id'        => $userId,
            'diff'           => $diff === null ? null : json_encode($diff, JSON_UNESCAPED_UNICODE),
            'context'        => $context === null ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
        ]);
        if (!$log->save()) {
            Yii::error(['auditLogSaveError' => $log->errors], __METHOD__);
        }
    }

    private function ensureEntityType(string $code): int
    {
        if (isset($this->entityTypeMap[$code])) {
            return $this->entityTypeMap[$code];
        }

        $id = AuditEntityType::find()->where(['code' => $code])->scalar();

        if (!$id) {
            $type = new AuditEntityType([
                'code' => $code,
                'name' => $code,
            ]);
            $type->save();
            $id = $type->id;
        }

        $this->entityTypeMap[$code] = $id;
        Yii::$app->cache->set($this->cacheKeyEntityTypes, $this->entityTypeMap);

        return $id;
    }

    private function ensureAction(string $code): int
    {
        if (isset($this->actionMap[$code])) {
            return $this->actionMap[$code];
        }

        $id = AuditAction::find()->where(['code' => $code])->scalar();

        if (!$id) {
            $action = new AuditAction([
                'code' => $code,
                'name' => $code,
            ]);
            $action->save();
            $id = $action->id;
        }

        $this->actionMap[$code] = $id;
        Yii::$app->cache->set($this->cacheKeyActions, $this->actionMap);

        return $id;
    }
}
