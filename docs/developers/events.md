# Events

Events can be used to extend the functionality of Redirect Manager.

## RedirectsService Events

### The `beforeSaveRedirect` event

```php
use lindemannrock\redirectmanager\services\RedirectsService;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_BEFORE_SAVE_REDIRECT,
    function(Event $event) {
        // Your code here
    }
);
```

### The `afterSaveRedirect` event

Event triggered after a redirect is saved

```php
use lindemannrock\redirectmanager\services\RedirectsService;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_AFTER_SAVE_REDIRECT,
    function(Event $event) {
        // Your code here
    }
);
```

### The `beforeDeleteRedirect` event

Event triggered before a redirect is deleted

```php
use lindemannrock\redirectmanager\services\RedirectsService;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_BEFORE_DELETE_REDIRECT,
    function(Event $event) {
        // Your code here
    }
);
```

### The `afterDeleteRedirect` event

Event triggered after a redirect is deleted

```php
use lindemannrock\redirectmanager\services\RedirectsService;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_AFTER_DELETE_REDIRECT,
    function(Event $event) {
        // Your code here
    }
);
```

