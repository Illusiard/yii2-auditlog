# Yii2 Audit Log

Модуль логирования и аудита для Yii2-приложений.

Позволяет логировать:
- создание, изменение и удаление ActiveRecord-моделей;
- diff изменённых полей;
- произвольный контекст (route, IP, user-agent и т.д.);
- бизнес-события (через прямой вызов логгера).

Поддерживает подключение через **Behavior** и не требует наследования моделей.

---

## Возможности

- ✅ Логирование `create / update / delete`
- ✅ Diff только изменённых полей
- ✅ Контекст выполнения (route, IP, user agent и т.д.)
- ✅ Автоматическое создание `entity_type` и `action`, если их нет
- ✅ Кэширование справочников
- ✅ Bootstrap-автоподключение
- ✅ Не зависит от имени компонента в конфиге
- ✅ Готов к использованию как Composer-пакет

---

## Установка

```bash
composer require illusiard/yii2-auditlog
```

После установки модуль автоматически подключается через Bootstrap.

Однако для проверки и подтягивания пользователей, нужно добавить в конфиг с User-классом:
```php
use illusiard\auditlog\components\AuditLogger;

[
    'components' => [
        'auditlog' => [
            'class' => AuditLogger::class,
            'userClass' => app\models\User::class,
        ]
    ],
]
```

---

## Миграции

```bash
php yii migrate --migrationPath=@illusiard/auditlog/migrations
```

---

## Подключение AuditBehavior

```php
use illusiard\auditlog\components\AuditBehavior;

class Order extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'audit' => [
                'class' => AuditBehavior::class,
                'entityTypeCode' => 'order',
            ],
        ];
    }
}
```

---

## Контекст

```php
'context' => function () {
    return [
        'route' => Yii::$app->requestedRoute,
        'ip' => Yii::$app->request->userIP,
        'userAgent' => Yii::$app->request->userAgent,
    ];
},
```

---

## Ручное логирование

```php
use illusiard\auditlog\components\AuditLogger;

AuditLogger::getInstance()->log(
    entityTypeCode: 'order',
    entityId: 42,
    actionCode: 'publish',
    diff: null,
    context: ['reason' => 'manual publish']
);
```

---

## Лицензия

MIT
