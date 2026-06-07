<?php

namespace illusiard\auditlog\components;

use illusiard\auditlog\models\AuditAction;
use illusiard\auditlog\models\AuditEntityType;
use illusiard\auditlog\models\AuditLog;
use JsonException;
use RuntimeException;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\caching\CacheInterface;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\helpers\ArrayHelper;
use yii\web\User;

class AuditLogger extends Component
{
    private static ?self $_instance = null;

    /** @var class-string<ActiveRecord>|null */
    public ?string $userClass = null;

    public string $cacheKeyEntityTypes = 'audit:entityTypeMap';
    public string $cacheKeyActions     = 'audit:actionMap';

    /** @var array<string,int> code => id */
    private array $entityTypeMap = [];

    /** @var array<string,int> code => id */
    private array $actionMap = [];

    /**
     * @return self
     * @throws InvalidConfigException
     */
    public static function getInstance(): self
    {
        if (self::$_instance === null) {
            self::$_instance = self::findConfiguredInstance() ?? Yii::createObject(self::class);
        }

        return self::$_instance;
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        self::$_instance = $this;
        $this->loadCache();
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    private function loadCache(): void
    {
        if (Yii::$app === null) {
            return;
        }

        $cache = $this->getCache();

        $entityTypes = $cache?->get($this->cacheKeyEntityTypes);
        $actions     = $cache?->get($this->cacheKeyActions);

        if (!is_array($entityTypes)) {
            $entityTypes = ArrayHelper::map(AuditEntityType::find()->select(['id', 'code'])->all(), 'code', 'id');
            $cache?->set($this->cacheKeyEntityTypes, $entityTypes);
        }
        if (!is_array($actions)) {
            $actions = ArrayHelper::map(AuditAction::find()->select(['id', 'code'])->all(), 'code', 'id');
            $cache?->set($this->cacheKeyActions, $actions);
        }

        $this->entityTypeMap = $this->normalizeIdMap($entityTypes);
        $this->actionMap     = $this->normalizeIdMap($actions);
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function resetCache(): void
    {
        $cache = $this->getCache();

        $cache?->delete($this->cacheKeyEntityTypes);
        $cache?->delete($this->cacheKeyActions);

        $this->entityTypeMap = [];
        $this->actionMap     = [];

        $this->loadCache();
    }

    /**
     * @param string $entityTypeCode
     * @param int $entityId
     * @param string $actionCode
     * @param ?array $diff
     * @param ?array $context
     * @param ?int $userId
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function log(
        string $entityTypeCode,
        int $entityId,
        string $actionCode,
        ?array $diff = null,
        ?array $context = null,
        ?int $userId = null
    ): void {
        if ($userId === null && ($user = $this->getUserComponent()) !== null && !$user->isGuest) {
            $userId = (int)$user->id;
        }

        $entityTypeId = $this->ensureEntityType($entityTypeCode);
        $actionId     = $this->ensureAction($actionCode);

        $log = new AuditLog([
            'entity_type_id' => $entityTypeId,
            'entity_id'      => $entityId,
            'action_id'      => $actionId,
            'user_id'        => $userId,
            'diff'           => $this->encodeJson($diff),
            'context'        => $this->encodeJson($context),
        ]);
        if (!$log->save()) {
            Yii::error(['auditLogSaveError' => $log->errors], __METHOD__);
        }
    }

    /**
     * @param string $code
     * @return int
     * @throws Exception
     * @throws InvalidConfigException
     */
    private function ensureEntityType(string $code): int
    {
        if (isset($this->entityTypeMap[$code])) {
            return $this->entityTypeMap[$code];
        }

        $id = AuditEntityType::find()->where(['code' => $code])->scalar();

        if ($id === false || $id === null) {
            $type = new AuditEntityType([
                'code' => $code,
                'name' => $code,
            ]);
            if (!$type->save(false)) {
                throw new RuntimeException('Unable to create audit entity type.');
            }

            $id = $type->id;
        }

        $id = (int)$id;

        $this->entityTypeMap[$code] = $id;
        $this->getCache()?->set($this->cacheKeyEntityTypes, $this->entityTypeMap);

        return $id;
    }

    /**
     * @param string $code
     * @return int
     * @throws Exception
     * @throws InvalidConfigException
     */
    private function ensureAction(string $code): int
    {
        if (isset($this->actionMap[$code])) {
            return $this->actionMap[$code];
        }

        $id = AuditAction::find()->where(['code' => $code])->scalar();

        if ($id === false || $id === null) {
            $action = new AuditAction([
                'code' => $code,
                'name' => $code,
            ]);
            if (!$action->save(false)) {
                throw new RuntimeException('Unable to create audit action.');
            }

            $id = $action->id;
        }

        $id = (int)$id;

        $this->actionMap[$code] = $id;
        $this->getCache()?->set($this->cacheKeyActions, $this->actionMap);

        return $id;
    }

    /**
     * @return ?self
     * @throws InvalidConfigException
     */
    private static function findConfiguredInstance(): ?self
    {
        if (Yii::$app === null) {
            return null;
        }

        foreach (Yii::$app->getComponents(false) as $component) {
            if ($component instanceof self) {
                return $component;
            }
        }

        foreach (Yii::$app->getComponents() as $id => $definition) {
            if (!self::isAuditLoggerDefinition($definition)) {
                continue;
            }

            $component = Yii::$app->get($id);

            if ($component instanceof self) {
                return $component;
            }
        }

        return null;
    }

    private static function isAuditLoggerDefinition(mixed $definition): bool
    {
        if ($definition instanceof self) {
            return true;
        }

        if (is_string($definition)) {
            return is_a($definition, self::class, true);
        }

        return is_array($definition)
            && isset($definition['class'])
            && is_string($definition['class'])
            && is_a($definition['class'], self::class, true);
    }

    /**
     * @return ?CacheInterface
     * @throws InvalidConfigException
     */
    private function getCache(): ?CacheInterface
    {
        if (Yii::$app === null) {
            return null;
        }

        $cache = Yii::$app->get('cache', false);

        return $cache instanceof CacheInterface ? $cache : null;
    }

    /**
     * @return User|null
     * @throws InvalidConfigException
     */
    private function getUserComponent(): ?User
    {
        if (Yii::$app === null || !Yii::$app->has('user')) {
            return null;
        }

        $user = Yii::$app->get('user', false);

        return $user instanceof User ? $user : null;
    }

    /**
     * @param array<string,mixed>|null $value
     */
    private function encodeJson(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Yii::error(['auditLogJsonEncodeError' => $exception->getMessage()], __METHOD__);

            return null;
        }
    }

    /**
     * @param array<string,int|string> $map
     *
     * @return array<string,int>
     */
    private function normalizeIdMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $code => $id) {
            $normalized[(string)$code] = (int)$id;
        }

        return $normalized;
    }
}
