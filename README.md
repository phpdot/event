# phpdot/event

PSR-14 event dispatcher with attribute-based listener discovery, async dispatch support, ordering/priority, and persistence abstraction. Zero framework dependencies — PSR interfaces only.

---

## Table of Contents

- [Install](#install)
- [Quick Start](#quick-start)
- [Why This Package](#why-this-package)
- [Architecture](#architecture)
  - [Boot Time vs Runtime](#boot-time-vs-runtime)
  - [Dispatch Pipeline](#dispatch-pipeline)
  - [Package Structure](#package-structure)
- [Events](#events)
  - [Notification Events (Immutable)](#notification-events-immutable)
  - [Enhancement Events (Mutable)](#enhancement-events-mutable)
  - [Stoppable Events](#stoppable-events)
- [Listeners](#listeners)
  - [The #[Listener] Attribute](#the-listener-attribute)
  - [Single Event Listener](#single-event-listener)
  - [Multi-Event Listener](#multi-event-listener)
  - [Async Listener](#async-listener)
  - [Ordering](#ordering)
- [Dispatching Events](#dispatching-events)
  - [Basic Dispatch](#basic-dispatch)
  - [Dispatch with Stop Propagation](#dispatch-with-stop-propagation)
  - [Dispatch with Async Handlers](#dispatch-with-async-handlers)
  - [Dispatch with Mixed Sync/Async](#dispatch-with-mixed-syncasync)
- [ListenerProvider](#listenerprovider)
  - [Manual Registration](#manual-registration)
  - [Bulk Loading](#bulk-loading)
  - [Loading from Repository](#loading-from-repository)
  - [Event Class Hierarchy](#event-class-hierarchy)
  - [Querying Listeners](#querying-listeners)
- [Contracts (Interfaces)](#contracts-interfaces)
  - [ListenerDiscoveryInterface](#listenerdiscoveryinterface)
  - [ListenerRepositoryInterface](#listenerrepositoryinterface)
  - [AsyncDispatcherInterface](#asyncdispatcherinterface)
- [Default Implementations](#default-implementations)
  - [InMemoryListenerRepository](#inmemorylistenerrepository)
  - [SyncOnlyDispatcher](#synconlydispatcher)
- [Admin GUI Management](#admin-gui-management)
  - [Enable/Disable Listeners](#enabledisable-listeners)
  - [Reorder Listeners](#reorder-listeners)
  - [Sync After Discovery](#sync-after-discovery)
- [Exception Handling](#exception-handling)
  - [Exception Hierarchy](#exception-hierarchy)
  - [Catching Exceptions](#catching-exceptions)
- [Framework Wiring](#framework-wiring)
- [Comparison](#comparison)
- [API Reference](#api-reference)
  - [Listener Attribute](#listener-attribute-api)
  - [ListenerEntry DTO](#listenerentry-dto-api)
  - [EventDispatcher](#eventdispatcher-api)
  - [ListenerProvider](#listenerprovider-api)
  - [StoppableEvent](#stoppableevent-api)
  - [ListenerDiscoveryInterface](#listenerdiscoveryinterface-api)
  - [ListenerRepositoryInterface](#listenerrepositoryinterface-api)
  - [AsyncDispatcherInterface](#asyncdispatcherinterface-api)
  - [InMemoryListenerRepository](#inmemorylistenerrepository-api)
  - [SyncOnlyDispatcher](#synconlydispatcher-api)
  - [Exceptions](#exceptions-api)
- [License](#license)

---

## Install

```bash
composer require phpdot/event
```

| Requirement | Version |
|-------------|---------|
| PHP | >= 8.3 |
| psr/event-dispatcher | ^1.0 |
| psr/container | ^2.0 |
| psr/log | ^3.0 |

Zero phpdot dependencies. Zero framework coupling.

---

## Quick Start

```php
// 1. Define an event — any PHP class, no base class needed
final readonly class UserRegistered
{
    public function __construct(
        public int $userId,
        public string $email,
    ) {}
}

// 2. Create a handler — the attribute IS the registration
#[Listener(UserRegistered::class, order: 1)]
final class SendWelcomeEmail
{
    public function __construct(private MailerInterface $mailer) {}

    public function __invoke(UserRegistered $event): void
    {
        $this->mailer->send($event->email, 'Welcome!');
    }
}

// 3. Wire and dispatch
$provider = new ListenerProvider();
$provider->addListener(UserRegistered::class, SendWelcomeEmail::class, order: 1);

$dispatcher = new EventDispatcher($provider, $container, $asyncDispatcher, $logger);
$dispatcher->dispatch(new UserRegistered(userId: 1, email: 'omar@example.com'));
```

No central configuration file. No service provider. No YAML. The handler declares what it handles.

---

## Why This Package

Every PHP event dispatcher forces centralized listener registration:

| Framework | Registration |
|-----------|-------------|
| **Laravel** | `EventServiceProvider::$listen` array — every team edits one file |
| **Symfony** | YAML tags, compiler passes, or `getSubscribedEvents()` |
| **PHPdot** | `#[Listener]` attribute on the handler class — no central file |

PHPdot inverts the registration. Each handler declares what it handles. Discovery finds all listeners at boot time. The runtime dispatcher uses zero-cost in-memory lookups.

**Additionally:**
- Async support built-in via `AsyncDispatcherInterface` (Laravel has ShouldQueue, but tied to Illuminate)
- Order + Priority correctly separated (order = execution sequence, priority = queue urgency)
- Persistence abstraction for admin GUI management (enable/disable without deploy)
- PSR-14 compliant and replaceable by any PSR-14 implementation

---

## Architecture

### Boot Time vs Runtime

```
Boot time (once, cached):
    ListenerDiscoveryInterface scans #[Listener] attributes
        → ListenerProvider stores event→handlers mapping in memory
        → Optionally loads overrides from ListenerRepositoryInterface (DB)

Runtime (every dispatch, zero I/O):
    dispatch(object $event)
        → ListenerProvider→getListenersForEvent($event)  ← in-memory lookup
        → sorted by order
        → for each listener:
            if sync:  resolve from PSR-11 container → call __invoke($event)
            if async: AsyncDispatcherInterface→publishAsync(event, handler, priority)
            if StoppableEvent and stopped: break
        → log via PSR-3 LoggerInterface
        → return event object
```

### Dispatch Pipeline

```
EventDispatcher::dispatch(object $event)
    │
    ├── Is StoppableEvent and already stopped? → return immediately
    │
    ├── ListenerProvider::getListenersForEvent($event)
    │   ├── Match exact class
    │   ├── Match parent classes
    │   ├── Match interfaces
    │   └── Sort by order (ascending)
    │
    └── For each ListenerEntry:
        ├── Skip if disabled (enabled: false)
        ├── Check StoppableEvent::isPropagationStopped() → break if true
        │
        ├── If sync:
        │   ├── Container::get($handlerClass)
        │   ├── Validate callable
        │   ├── Call $handler($event)
        │   └── Log via PSR-3 (debug on success, error on failure)
        │
        └── If async:
            ├── AsyncDispatcherInterface::publishAsync($event, $handlerClass, $priority)
            └── Log via PSR-3 (debug on success, error on failure)
```

### Package Structure

```
src/
├── Attribute/
│   └── Listener.php                    # #[Listener] attribute — repeatable, class-target
│
├── EventDispatcher.php                 # PSR-14 dispatcher — sync/async, stop propagation, logging
├── ListenerProvider.php                # PSR-14 provider — in-memory map, class hierarchy matching
│
├── DTO/
│   └── ListenerEntry.php              # Immutable descriptor — event, handler, order, async, priority, enabled
│
├── Contract/
│   ├── ListenerDiscoveryInterface.php  # Scanning abstraction — framework implements
│   ├── ListenerRepositoryInterface.php # Persistence abstraction — framework implements
│   └── AsyncDispatcherInterface.php    # Queue abstraction — framework implements
│
├── Event/
│   └── StoppableEvent.php             # Base class for PSR-14 StoppableEventInterface
│
├── Provider/
│   ├── InMemoryListenerRepository.php  # Default — no DB needed
│   └── SyncOnlyDispatcher.php          # Default — runs async handlers synchronously
│
└── Exception/
    ├── EventException.php              # Base (extends RuntimeException)
    ├── ListenerException.php           # Handler resolution/execution failure
    └── AsyncDispatchException.php      # Queue publishing failure
```

13 source files. 812 lines.

---

## Events

Events are plain PHP objects. No base class required. No interface. No trait.

### Notification Events (Immutable)

One-way signal: "something happened." Listeners react but don't modify the event.

```php
final readonly class UserRegistered
{
    public function __construct(
        public int $userId,
        public string $email,
        public DateTimeImmutable $registeredAt,
    ) {}
}

final readonly class OrderPlaced
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public float $total,
    ) {}
}
```

### Enhancement Events (Mutable)

Two-way signal: "modify this before I use it." Listeners enrich the event.

```php
final class ResponseCreated
{
    /** @var list<string> */
    public array $headers = [];

    public function __construct(
        public readonly Response $response,
    ) {}

    public function addHeader(string $header): void
    {
        $this->headers[] = $header;
    }
}
```

### Stoppable Events

First handler that can handle it wins. Extend `StoppableEvent` and call `stopPropagation()`.

```php
use PHPdot\Event\Event\StoppableEvent;

final class RouteMatched extends StoppableEvent
{
    public ?Route $route = null;

    public function __construct(
        public readonly string $path,
    ) {}
}

// First listener that matches stops propagation
#[Listener(RouteMatched::class, order: 1)]
final class ApiRouteResolver
{
    public function __invoke(RouteMatched $event): void
    {
        if (str_starts_with($event->path, '/api/')) {
            $event->route = $this->resolveApiRoute($event->path);
            $event->stopPropagation();
        }
    }
}

#[Listener(RouteMatched::class, order: 2)]
final class WebRouteResolver
{
    public function __invoke(RouteMatched $event): void
    {
        // Only reached if API resolver didn't match
        $event->route = $this->resolveWebRoute($event->path);
    }
}
```

Events that don't need stopping are plain objects — no StoppableEvent inheritance needed.

---

## Listeners

### The #[Listener] Attribute

```php
use PHPdot\Event\Attribute\Listener;

#[Listener(
    event: UserRegistered::class,  // required — event class to listen for
    order: 1,                       // execution sequence (lower = first, default 0)
    async: false,                   // sync (default) or queue
    priority: 0,                    // queue priority for async (0-10, higher = urgent)
)]
```

The attribute is `IS_REPEATABLE` — one handler can listen to multiple events.

### Single Event Listener

```php
#[Listener(UserRegistered::class)]
final class SendWelcomeEmail
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(UserRegistered $event): void
    {
        $this->mailer->send($event->email, 'Welcome!');
    }
}
```

Handlers must be callable — implement `__invoke()`. Resolved from the PSR-11 container (constructor injection works).

### Multi-Event Listener

```php
#[Listener(UserRegistered::class, order: 1)]
#[Listener(UserUpdated::class, order: 1)]
final class UpdateSearchIndex
{
    public function __invoke(UserRegistered|UserUpdated $event): void
    {
        $this->search->index('users', $event->userId);
    }
}
```

### Async Listener

```php
#[Listener(OrderPlaced::class, order: 3, async: true, priority: 5)]
final class SendOrderConfirmation
{
    public function __invoke(OrderPlaced $event): void
    {
        $this->mailer->send($event->userId, 'order.confirmation');
    }
}
```

Async listeners are published to the queue via `AsyncDispatcherInterface`. They don't block the dispatch call. The handler runs later when a queue worker consumes the message.

### Ordering

Order controls **execution sequence** within a single event. Lower numbers run first.

```php
#[Listener(OrderPlaced::class, order: 1)]  // runs 1st
final class ValidateOrder { ... }

#[Listener(OrderPlaced::class, order: 2)]  // runs 2nd
final class ChargePayment { ... }

#[Listener(OrderPlaced::class, order: 3)]  // runs 3rd
final class ReserveInventory { ... }

#[Listener(OrderPlaced::class, order: 4, async: true, priority: 5)]  // queued 4th
final class SendConfirmation { ... }

#[Listener(OrderPlaced::class, order: 5, async: true, priority: 1)]  // queued 5th, lower queue priority
final class TrackAnalytics { ... }
```

**Order** and **priority** are separate concerns:
- `order` — when this listener runs relative to others for the same event (sync and async)
- `priority` — how urgently the queue should process this async listener (async only)

---

## Dispatching Events

### Basic Dispatch

```php
use Psr\EventDispatcher\EventDispatcherInterface;

final class OrderService
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function place(int $userId, Cart $cart): Order
    {
        $order = $this->createOrder($userId, $cart);

        $this->dispatcher->dispatch(new OrderPlaced(
            orderId: $order->id,
            userId: $userId,
            total: $cart->total(),
        ));

        return $order;
    }
}
```

The emitter depends on PSR-14 `EventDispatcherInterface` — knows nothing about handlers.

### Dispatch with Stop Propagation

```php
$event = new RouteMatched('/api/users');
$dispatcher->dispatch($event);

// $event->route is set by the first resolver that matched
if ($event->route !== null) {
    $this->executeRoute($event->route);
}
```

If the event implements `StoppableEventInterface` and `isPropagationStopped()` returns true, remaining listeners are skipped — including async ones.

### Dispatch with Async Handlers

```php
// Async handlers are published to the queue — dispatch returns immediately
$dispatcher->dispatch(new OrderPlaced(42, 1, 99.99));
// ChargePayment (sync) ran inline
// ReserveInventory (sync) ran inline
// SendConfirmation (async) → published to queue → returns immediately
// TrackAnalytics (async) → published to queue → returns immediately
```

### Dispatch with Mixed Sync/Async

Sync handlers block. Async handlers return immediately. Order is respected across both.

```
dispatch(OrderPlaced)
    → order 1: ValidateOrder    (sync)  → container.get() → __invoke() → done
    → order 2: ChargePayment    (sync)  → container.get() → __invoke() → done
    → order 3: ReserveInventory (sync)  → container.get() → __invoke() → done
    → order 4: SendConfirmation (async) → queue.publish(priority: 5) → returns immediately
    → order 5: TrackAnalytics   (async) → queue.publish(priority: 1) → returns immediately
    → return event
```

---

## ListenerProvider

The provider manages the in-memory event→handlers mapping. Implements PSR-14 `ListenerProviderInterface`.

### Manual Registration

```php
$provider = new ListenerProvider();

$provider->addListener(
    eventClass: UserRegistered::class,
    handlerClass: SendWelcomeEmail::class,
    order: 1,
    async: false,
    priority: 0,
);

$provider->addListener(
    eventClass: UserRegistered::class,
    handlerClass: SyncToMailchimp::class,
    order: 2,
    async: true,
    priority: 5,
);
```

### Bulk Loading

```php
use PHPdot\Event\DTO\ListenerEntry;

$provider->load([
    new ListenerEntry(UserRegistered::class, SendWelcomeEmail::class, order: 1),
    new ListenerEntry(UserRegistered::class, SyncToMailchimp::class, order: 2, async: true, priority: 5),
    new ListenerEntry(OrderPlaced::class, ChargePayment::class, order: 1),
]);
```

### Loading from Repository

```php
// Load DB overrides — merges with existing entries
$provider->loadFromRepository($repository);
```

Repository entries override existing entries with the same event+handler pair. New entries are added. Used for admin GUI management.

### Event Class Hierarchy

Listeners registered on a parent class or interface are triggered by subclass events:

```php
// Listener on parent class
$provider->addListener(BaseUserEvent::class, AuditLogger::class);

// These events all trigger AuditLogger:
$dispatcher->dispatch(new UserRegistered(...));  // extends BaseUserEvent
$dispatcher->dispatch(new UserUpdated(...));     // extends BaseUserEvent
$dispatcher->dispatch(new UserDeleted(...));     // extends BaseUserEvent

// Listener on interface
$provider->addListener(AuditableInterface::class, AuditLogger::class);

// Any event implementing AuditableInterface triggers AuditLogger
```

Matching order: exact class → parent classes → interfaces. All sorted by order.

### Querying Listeners

```php
$provider->hasListeners(UserRegistered::class);  // bool
$provider->getAll();                               // array<string, list<ListenerEntry>>
$provider->removeListeners(UserRegistered::class); // remove all for this event
$provider->clear();                                // remove everything
```

---

## Contracts (Interfaces)

Three interfaces that the framework implements. The event package ships with default in-memory implementations.

### ListenerDiscoveryInterface

Scans the codebase for `#[Listener]` attributes. Framework implements using its attribute scanner.

```php
interface ListenerDiscoveryInterface
{
    /** @return list<ListenerEntry> */
    public function discover(): array;
}
```

### ListenerRepositoryInterface

Persists listener mappings for admin GUI management. Framework implements using its database layer.

```php
interface ListenerRepositoryInterface
{
    /** @return list<ListenerEntry> */
    public function getAll(): array;

    /** @return list<ListenerEntry> */
    public function getByEvent(string $eventClass): array;

    public function save(ListenerEntry $entry): void;
    public function setEnabled(string $eventClass, string $handlerClass, bool $enabled): void;
    public function setOrder(string $eventClass, string $handlerClass, int $order): void;
    public function delete(string $eventClass, string $handlerClass): void;

    /** @param list<ListenerEntry> $discovered */
    public function sync(array $discovered): void;
}
```

### AsyncDispatcherInterface

Publishes events to a message queue. Framework implements using its queue layer.

```php
interface AsyncDispatcherInterface
{
    public function publishAsync(object $event, string $handlerClass, int $priority = 0): void;
}
```

---

## Default Implementations

### InMemoryListenerRepository

No database needed. Full CRUD. Preserves admin overrides on sync.

```php
use PHPdot\Event\Provider\InMemoryListenerRepository;

$repo = new InMemoryListenerRepository();

$repo->save(new ListenerEntry(UserRegistered::class, SendEmail::class, order: 1));
$repo->setEnabled(UserRegistered::class, SendEmail::class, false);
$repo->setOrder(UserRegistered::class, SendEmail::class, 5);
$repo->delete(UserRegistered::class, SendEmail::class);
$repo->getAll();
$repo->getByEvent(UserRegistered::class);
$repo->sync($discoveredEntries);  // merge, preserve overrides, remove stale
```

### SyncOnlyDispatcher

Runs async handlers synchronously. Useful for development, testing, and simple deployments where no message queue is configured.

```php
use PHPdot\Event\Provider\SyncOnlyDispatcher;

$async = new SyncOnlyDispatcher($container);

// "Async" handlers just run inline
$async->publishAsync($event, SendEmail::class, priority: 5);
// SendEmail::__invoke($event) called synchronously
```

---

## Admin GUI Management

The `ListenerRepositoryInterface` enables runtime listener management without code deploy.

### Enable/Disable Listeners

```php
// Disable a listener — it will be skipped during dispatch
$repository->setEnabled(UserRegistered::class, SendWelcomeEmail::class, false);

// Re-enable
$repository->setEnabled(UserRegistered::class, SendWelcomeEmail::class, true);
```

### Reorder Listeners

```php
// Change execution order without code change
$repository->setOrder(UserRegistered::class, SendWelcomeEmail::class, 10);
$repository->setOrder(UserRegistered::class, SyncToMailchimp::class, 1);  // now runs first
```

### Sync After Discovery

When the application boots, newly discovered listeners are merged with stored ones. Admin overrides (enabled/disabled, reordered) are preserved. Handlers removed from code are cleaned up.

```php
$discovered = $discovery->discover();
$repository->sync($discovered);

$provider = new ListenerProvider();
$provider->load($discovered);
$provider->loadFromRepository($repository);  // applies DB overrides
```

---

## Exception Handling

### Exception Hierarchy

```
EventException (extends RuntimeException)
├── ListenerException            — handler resolution or execution failure
│   ├── getHandlerClass(): string
│   └── getEventClass(): string
└── AsyncDispatchException       — queue publishing failure
    ├── getHandlerClass(): string
    └── getEventClass(): string
```

All exceptions carry the original cause as `getPrevious()`.

### Catching Exceptions

```php
use PHPdot\Event\Exception\ListenerException;
use PHPdot\Event\Exception\AsyncDispatchException;
use PHPdot\Event\Exception\EventException;

try {
    $dispatcher->dispatch(new OrderPlaced(...));
} catch (ListenerException $e) {
    // Sync handler failed
    $e->getHandlerClass();  // 'App\Listener\ChargePayment'
    $e->getEventClass();    // 'App\Event\OrderPlaced'
    $e->getPrevious();      // original exception
} catch (AsyncDispatchException $e) {
    // Queue publishing failed
    $e->getHandlerClass();  // 'App\Listener\SendConfirmation'
    $e->getEventClass();    // 'App\Event\OrderPlaced'
} catch (EventException $e) {
    // Catch-all
}
```

A `ListenerException` is also thrown when a handler resolved from the container is not callable.

---

## Framework Wiring

How phpdot/dot (or any framework) wires this package at boot time:

```php
// 1. Discover #[Listener] attributes
$discovery = new AttributeListenerDiscovery($attributeScanner, $paths);
$entries = $discovery->discover();

// 2. Build the provider
$provider = new ListenerProvider();
$provider->load($entries);

// 3. Optionally load DB overrides
$repository = new DatabaseListenerRepository($db);
$provider->loadFromRepository($repository);

// 4. Wire the async dispatcher
$asyncDispatcher = new QueueAsyncDispatcher($queue, $serializer);

// 5. Create the event dispatcher
$dispatcher = new EventDispatcher(
    provider: $provider,
    container: $container,
    async: $asyncDispatcher,
    logger: $logger,
);

// 6. Register as PSR-14
$container->set(EventDispatcherInterface::class, $dispatcher);
```

The framework implementations (`AttributeListenerDiscovery`, `DatabaseListenerRepository`, `QueueAsyncDispatcher`) live in the framework, not in this package.

---

## Comparison

| Feature | PHPdot | Symfony | Laravel |
|---------|--------|--------|---------|
| **Registration** | `#[Listener]` attribute | YAML/tags/subscriber | EventServiceProvider array |
| **Central config file** | None | services.yaml | EventServiceProvider |
| **Auto-discovery** | Via ListenerDiscoveryInterface | Compiler pass | handle() type-hint |
| **Admin GUI manageable** | ListenerRepositoryInterface | No | No |
| **Enable/disable without deploy** | Yes | No | No |
| **Events** | Any PHP object | Extends Event (optional) | Any class |
| **Type safety** | Class-based identity | String or FQCN | FQCN |
| **Stop propagation** | StoppableEvent | Event::stopPropagation() | return false |
| **Async dispatch** | AsyncDispatcherInterface | Messenger (separate) | ShouldQueue |
| **Order control** | `order` param | Priority (numeric) | Registration order |
| **Queue priority** | `priority` param | N/A | $queue property |
| **PSR-14 compliant** | Yes | Yes | No |
| **Standalone** | Yes | Yes | No (illuminate/*) |
| **Replaceable** | Any PSR-14 impl | Any PSR-14 impl | No |

---

## API Reference

### Listener Attribute API

```
#[Attribute(TARGET_CLASS | IS_REPEATABLE)]
final readonly class Listener

__construct(
    public string $event,          // event class name
    public int    $order    = 0,   // execution order (lower = first)
    public bool   $async    = false, // run via queue
    public int    $priority = 0,   // queue priority (0-10)
)
```

### ListenerEntry DTO API

```
final readonly class ListenerEntry

__construct(
    public string $eventClass,
    public string $handlerClass,
    public int    $order    = 0,
    public bool   $async    = false,
    public int    $priority = 0,
    public bool   $enabled  = true,
)
```

### EventDispatcher API

```
final class EventDispatcher implements EventDispatcherInterface

__construct(
    ListenerProvider         $provider,
    ContainerInterface       $container,
    AsyncDispatcherInterface $async,
    LoggerInterface          $logger,
)

dispatch(object $event): object
```

### ListenerProvider API

```
final class ListenerProvider implements ListenerProviderInterface

getListenersForEvent(object $event): iterable<ListenerEntry>
addListener(string $eventClass, string $handlerClass, int $order = 0, bool $async = false, int $priority = 0): void
load(list<ListenerEntry> $entries): void
loadFromRepository(ListenerRepositoryInterface $repository): void
getAll(): array<string, list<ListenerEntry>>
hasListeners(string $eventClass): bool
removeListeners(string $eventClass): void
clear(): void
```

### StoppableEvent API

```
abstract class StoppableEvent implements StoppableEventInterface

isPropagationStopped(): bool
stopPropagation(): void
```

### ListenerDiscoveryInterface API

```
interface ListenerDiscoveryInterface

discover(): list<ListenerEntry>
```

### ListenerRepositoryInterface API

```
interface ListenerRepositoryInterface

getAll(): list<ListenerEntry>
getByEvent(string $eventClass): list<ListenerEntry>
save(ListenerEntry $entry): void
setEnabled(string $eventClass, string $handlerClass, bool $enabled): void
setOrder(string $eventClass, string $handlerClass, int $order): void
delete(string $eventClass, string $handlerClass): void
sync(list<ListenerEntry> $discovered): void
```

### AsyncDispatcherInterface API

```
interface AsyncDispatcherInterface

publishAsync(object $event, string $handlerClass, int $priority = 0): void
```

### InMemoryListenerRepository API

```
final class InMemoryListenerRepository implements ListenerRepositoryInterface

getAll(): list<ListenerEntry>
getByEvent(string $eventClass): list<ListenerEntry>
save(ListenerEntry $entry): void
setEnabled(string $eventClass, string $handlerClass, bool $enabled): void
setOrder(string $eventClass, string $handlerClass, int $order): void
delete(string $eventClass, string $handlerClass): void
sync(list<ListenerEntry> $discovered): void
```

### SyncOnlyDispatcher API

```
final class SyncOnlyDispatcher implements AsyncDispatcherInterface

__construct(ContainerInterface $container)
publishAsync(object $event, string $handlerClass, int $priority = 0): void
```

### Exceptions API

```
EventException (extends RuntimeException)

ListenerException (extends EventException)
    __construct(string $message, string $handlerClass, string $eventClass, int $code = 0, ?Throwable $previous = null)
    getHandlerClass(): string
    getEventClass(): string

AsyncDispatchException (extends EventException)
    __construct(string $message, string $handlerClass, string $eventClass, int $code = 0, ?Throwable $previous = null)
    getHandlerClass(): string
    getEventClass(): string
```

---

## License

MIT
