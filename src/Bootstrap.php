<?php

namespace illusiard\auditlog;

use illusiard\auditlog\components\AuditLogger;
use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;

final class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        Yii::setAlias('@illusiard/auditlog', dirname(__DIR__) . '/src');

        if (!$app->has('auditLogger')) {
            $app->set('auditLogger', [
                'class' => AuditLogger::class,
            ]);
        }
    }
}
