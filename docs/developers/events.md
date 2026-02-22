# Events

Events can be used to extend the functionality of Redirect Manager. All redirect events use the `RedirectEvent` class, which provides access to the redirect data and allows you to cancel save/delete operations.

## RedirectEvent Properties

| Property | Type | Description |
|----------|------|-------------|
| `$event->redirect` | `array` | The redirect data |
| `$event->isValid` | `bool` | Set to `false` to cancel the operation (before events only) |

## RedirectsService Events

### The `beforeSaveRedirect` event

Event triggered before a redirect is saved. Set `$event->isValid = false` to cancel the save.

```php
use lindemannrock\redirectmanager\events\RedirectEvent;
use lindemannrock\redirectmanager\services\RedirectsService;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_BEFORE_SAVE_REDIRECT,
    function(RedirectEvent $event) {
        // Access the redirect data
        $redirect = $event->redirect;

        // Optionally cancel the save
        $event->isValid = false;
    }
);
```

### The `afterSaveRedirect` event

Event triggered after a redirect is saved.

```php
use lindemannrock\redirectmanager\events\RedirectEvent;
use lindemannrock\redirectmanager\services\RedirectsService;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_AFTER_SAVE_REDIRECT,
    function(RedirectEvent $event) {
        $redirect = $event->redirect;
        // Your code here
    }
);
```

### The `beforeDeleteRedirect` event

Event triggered before a redirect is deleted. Set `$event->isValid = false` to cancel the deletion.

```php
use lindemannrock\redirectmanager\events\RedirectEvent;
use lindemannrock\redirectmanager\services\RedirectsService;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_BEFORE_DELETE_REDIRECT,
    function(RedirectEvent $event) {
        $redirect = $event->redirect;

        // Optionally cancel the deletion
        $event->isValid = false;
    }
);
```

### The `afterDeleteRedirect` event

Event triggered after a redirect is deleted.

```php
use lindemannrock\redirectmanager\events\RedirectEvent;
use lindemannrock\redirectmanager\services\RedirectsService;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_AFTER_DELETE_REDIRECT,
    function(RedirectEvent $event) {
        $redirect = $event->redirect;
        // Your code here
    }
);
```
