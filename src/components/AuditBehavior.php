<?php

namespace illusiard\auditlog\components;

use Closure;
use ReflectionClass;
use yii\base\Behavior;
use yii\db\ActiveRecord;

/**
 * Поведение для логирования изменений ActiveRecord.
 */
class AuditBehavior extends Behavior
{
    /**
     * Код сущности (например: 'order', 'user', 'chapter').
     * Если не задан — попытаемся вывести из tableName()
     */
    public ?string $entityTypeCode = null;

    public string $createActionCode = 'create';
    public string $updateActionCode = 'update';
    public string $deleteActionCode = 'delete';

    public array $ignoreAttributes = ['created_at', 'updated_at'];

    public bool $onlyDirty = true;

    /**
     * Контекст, который будет попадать в лог.
     * Можно передавать callable: fn(ActiveRecord $model, string $eventName): array
     *
     * @var array<string,mixed>|Closure|null
     */
    public array|Closure|null $context = null;

    /**
     * Пользователь, от имени которого пишем.
     * Можно передать callable: fn(): ?int
     * Если null — AuditLogger сам попробует взять Yii::$app->user->id
     */
    public int|Closure|null $userId = null;

    /**
     * Писать ли лог внутри транзакции изменения модели.
     * По умолчанию true — лог пишется в after* событиях, уже в той же транзакции, если она есть.
     */
    public bool $logAfterEvents = true;

    /**
     * Снимок старых атрибутов до обновления, чтобы корректно посчитать diff.
     */
    private array $oldAttributesSnapshot = [];

    /**
     * Имена изменённых атрибутов до обновления.
     */
    private array $dirtyAttributeNames = [];

    public function events(): array
    {
        return [
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            ActiveRecord::EVENT_AFTER_INSERT  => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE  => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_DELETE  => 'afterDelete',
        ];
    }

    public function beforeUpdate(): void
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;

        // Снимок старых атрибутов перед изменением (на случай, если owner->oldAttributes поменяются)
        $this->oldAttributesSnapshot = $model->oldAttributes ?? [];
        $this->dirtyAttributeNames    = array_keys($model->getDirtyAttributes());
    }

    public function afterInsert(): void
    {
        if (!$this->logAfterEvents) {
            return;
        }

        /** @var ActiveRecord $model */
        $model = $this->owner;

        $diff = $this->buildCreateDiff($model);
        $this->writeLog($model, $this->createActionCode, $diff);
    }

    public function afterUpdate(): void
    {
        if (!$this->logAfterEvents) {
            return;
        }

        /** @var ActiveRecord $model */
        $model = $this->owner;

        $diff = $this->buildUpdateDiff($model);
        if ($diff === null || $diff === []) {
            return;
        }

        $this->writeLog($model, $this->updateActionCode, $diff);
    }

    public function afterDelete(): void
    {
        if (!$this->logAfterEvents) {
            return;
        }

        /** @var ActiveRecord $model */
        $model = $this->owner;

        $this->writeLog($model, $this->deleteActionCode, null);
    }

    private function writeLog(ActiveRecord $model, string $actionCode, ?array $diff): void
    {
        $logger = $this->getLogger();

        $entityTypeCode = $this->resolveEntityTypeCode($model);
        $entityId       = $this->resolveEntityId($model);

        $context = $this->resolveContext($model, $actionCode);
        $userId  = $this->resolveUserId();

        $logger->log(
            entityTypeCode: $entityTypeCode,
            entityId      : $entityId,
            actionCode    : $actionCode,
            diff          : $diff,
            context       : $context,
            userId        : $userId
        );
    }

    private function getLogger(): AuditLogger
    {
        return AuditLogger::getInstance();
    }

    private function resolveEntityTypeCode(ActiveRecord $model): string
    {
        if ($this->entityTypeCode !== null && $this->entityTypeCode !== '') {
            return $this->entityTypeCode;
        }

        $t = $model::tableName();
        $t = preg_replace('~^\{\{%?(.+?)}}$~', '$1', $t);
        $t = ltrim((string)$t, '%');

        return $t ?: new ReflectionClass($model)->getShortName();
    }

    private function resolveEntityId(ActiveRecord $model): int
    {
        $pk = $model->getPrimaryKey();

        if (is_array($pk)) {
            $first = reset($pk);

            return (int)$first;
        }

        return (int)$pk;
    }

    private function resolveContext(ActiveRecord $model, string $actionCode): ?array
    {
        if ($this->context === null) {
            return null;
        }

        if ($this->context instanceof Closure) {
            return (array)($this->context)($model, $actionCode);
        }

        return $this->context;
    }

    private function resolveUserId(): ?int
    {
        if ($this->userId === null) {
            return null;
        }

        if ($this->userId instanceof Closure) {
            $v = ($this->userId)();

            return $v === null ? null : (int)$v;
        }

        return (int)$this->userId;
    }

    private function buildCreateDiff(ActiveRecord $model): array
    {
        $attrs = $model->getAttributes();

        foreach ($this->ignoreAttributes as $ignore) {
            unset($attrs[$ignore]);
        }

        return array_map(static fn ($v) => ['old' => null, 'new' => $v], $attrs);
    }

    private function buildUpdateDiff(ActiveRecord $model): ?array
    {
        $newAttrs = $model->getAttributes();
        $oldAttrs = $this->oldAttributesSnapshot ?: ($model->oldAttributes ?? []);

        $keys = $this->onlyDirty ? $this->dirtyAttributeNames : array_keys($newAttrs);

        $diff = [];
        foreach ($keys as $key) {
            if (in_array($key, $this->ignoreAttributes, true)) {
                continue;
            }

            $old = $oldAttrs[$key] ?? null;
            $new = $newAttrs[$key] ?? null;

            if ($old === $new) {
                continue;
            }

            $diff[$key] = ['old' => $old, 'new' => $new];
        }

        return $diff;
    }
}
