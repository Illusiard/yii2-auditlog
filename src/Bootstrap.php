<?php

namespace illusiard\auditlog;

use illusiard\auditlog\components\AuditLogger;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;

final class Bootstrap implements BootstrapInterface
{
    /**
     * @param $app
     * @return void
     * @throws InvalidConfigException
     */
    public function bootstrap($app): void
    {
        Yii::setAlias('@illusiard/auditlog', dirname(__DIR__) . '/src');

        if (!$this->hasAuditLogger($app)) {
            $app->set('auditLogger', [
                'class' => AuditLogger::class,
            ]);
        }
    }

    private function hasAuditLogger($app): bool
    {
        if ($app->has('auditLogger')) {
            return true;
        }

        foreach ($app->getComponents() as $definition) {
            if ($this->isAuditLoggerDefinition($definition)) {
                return true;
            }
        }

        return false;
    }

    private function isAuditLoggerDefinition(mixed $definition): bool
    {
        if ($definition instanceof AuditLogger) {
            return true;
        }

        if (is_string($definition)) {
            return is_a($definition, AuditLogger::class, true);
        }

        return is_array($definition)
            && isset($definition['class'])
            && is_string($definition['class'])
            && is_a($definition['class'], AuditLogger::class, true);
    }
}
