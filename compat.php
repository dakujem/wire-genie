<?php

/**
 * This file makes for smoother migration by aliasing classes that have been renamed or replaced.
 *
 * class_alias( new_class, old_class )
 */

if (interface_exists(Dakujem\Wire\Constructor::class) && !interface_exists(Dakujem\Constructor::class)) {
    class_alias(Dakujem\Wire\Constructor::class, Dakujem\Constructor::class);
}
if (interface_exists(Dakujem\Wire\Invoker::class) && !interface_exists(Dakujem\Invoker::class)) {
    class_alias(Dakujem\Wire\Invoker::class, Dakujem\Invoker::class);
}

if (trait_exists(Dakujem\Wire\PredictableAccess::class) && !trait_exists(Dakujem\PredictableAccess::class)) {
    class_alias(Dakujem\Wire\PredictableAccess::class, Dakujem\PredictableAccess::class);
}

if (class_exists(Dakujem\Wire\Genie::class) && !class_exists(Dakujem\WireGenie::class)) {
    class_alias(Dakujem\Wire\Genie::class, Dakujem\WireGenie::class);
}
if (class_exists(Dakujem\Wire\Genie::class) && !class_exists(Dakujem\WireInvoker::class)) {
    class_alias(Dakujem\Wire\Genie::class, Dakujem\WireInvoker::class);
}
if (class_exists(Dakujem\Wire\TagBasedStrategy::class) && !class_exists(Dakujem\ArgInspector::class)) {
    class_alias(Dakujem\Wire\TagBasedStrategy::class, Dakujem\ArgInspector::class);
}

if (class_exists(Dakujem\Wire\Simpleton::class) && !class_exists(Dakujem\InvokableProvider::class)) {
    class_alias(Dakujem\Wire\Simpleton::class, Dakujem\InvokableProvider::class);
}
if (class_exists(Dakujem\Wire\Limiter::class) && !class_exists(Dakujem\WireLimiter::class)) {
    class_alias(Dakujem\Wire\Limiter::class, Dakujem\WireLimiter::class);
}
if (class_exists(Dakujem\Wire\Exceptions\ServiceNotWhitelisted::class) && !class_exists(Dakujem\WireLimiterException::class)) {
    class_alias(Dakujem\Wire\Exceptions\ServiceNotWhitelisted::class, Dakujem\WireLimiterException::class);
}
