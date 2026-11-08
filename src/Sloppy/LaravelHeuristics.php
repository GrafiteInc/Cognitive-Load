<?php

namespace Grafite\CognitiveLoad\Sloppy;

/**
 * Shared vocabulary for the Laravel/Eloquent rules.
 */
class LaravelHeuristics
{
    public const TERMINATORS = [
        'get', 'first', 'firstorfail', 'find', 'findorfail', 'findmany', 'sole', 'count', 'exists',
        'doesntexist', 'pluck', 'sum', 'avg', 'max', 'min', 'value', 'paginate', 'simplepaginate',
        'cursor', 'chunk', 'create', 'insert', 'update', 'delete', 'upsert', 'updateorcreate',
        'firstorcreate', 'firstornew', 'destroy', 'all',
    ];

    // Terminators that only exist on query builders, never on collections.
    public const QUERY_ONLY_TERMINATORS = [
        'get', 'firstorfail', 'findorfail', 'sole', 'exists', 'doesntexist', 'paginate', 'simplepaginate',
        'cursor', 'chunk', 'create', 'insert', 'update', 'delete', 'upsert', 'updateorcreate', 'firstorcreate',
    ];

    // Calls whose result is a set of Eloquent models.
    public const FETCHES = ['get', 'all', 'paginate', 'simplepaginate', 'cursorpaginate', 'cursor', 'lazy'];

    public const BUILDERS = [
        'query', 'with', 'withcount', 'latest', 'oldest', 'join', 'leftjoin', 'select', 'groupby',
        'having', 'has', 'doesnthave', 'limit', 'offset', 'table', 'orderby', 'orderbydesc',
    ];

    // Facades and helpers whose `::get()`, `::first()` etc. are not database queries.
    private const NON_QUERY_CLASSES = [
        'arr', 'str', 'collection', 'carbon', 'cache', 'config', 'log', 'session', 'validator',
        'storage', 'http', 'event', 'queue', 'mail', 'gate', 'auth', 'hash', 'crypt', 'url', 'route',
        'view', 'lang', 'request', 'response', 'redirect', 'file', 'bus', 'notification', 'process',
        'number', 'date', 'context', 'cookie', 'app', 'artisan', 'blade', 'broadcast', 'password',
        'ratelimiter', 'redis', 'schema', 'vite', 'input', 'self', 'static', 'parent',
    ];

    public static function isBuilder(string $method): bool
    {
        return in_array($method, self::BUILDERS, true)
            || str_starts_with($method, 'where')
            || str_starts_with($method, 'orwhere');
    }

    public static function isTerminator(string $method): bool
    {
        return in_array($method, self::TERMINATORS, true);
    }

    public static function isQueryOnlyTerminator(string $method): bool
    {
        return in_array($method, self::QUERY_ONLY_TERMINATORS, true);
    }

    /**
     * True for chains that hand back models: `Post::all()`, `Post::where(...)->get()`,
     * `$user->posts()->paginate()`.
     */
    public static function fetchesModels(CallChain $chain): bool
    {
        $names = $chain->names();

        if (! in_array(end($names), self::FETCHES, true)) {
            return false;
        }

        if ($chain->isStatic()) {
            return self::couldBeQueryable($chain->staticClass());
        }

        return count($names) > 1 || array_filter($names, self::isBuilder(...)) !== [];
    }

    public static function isDbFacade(?string $class): bool
    {
        return $class !== null && in_array(strtolower($class), ['db', 'illuminate\support\facades\db'], true);
    }

    public static function looksLikeModel(?string $class): bool
    {
        return $class !== null && str_contains($class, '\\Models\\');
    }

    public static function couldBeQueryable(?string $class): bool
    {
        if ($class === null) {
            return false;
        }

        $last = strtolower((string) substr((string) strrchr('\\'.$class, '\\'), 1));

        return ! in_array($last, self::NON_QUERY_CLASSES, true);
    }
}
