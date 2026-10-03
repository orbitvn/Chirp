<?php

namespace App\Support;

use Closure;
use InvalidArgumentException;

/**
 * The council (RAMM database) the current request or command is working on.
 *
 * Web requests take it from the session (set by the council picker); artisan
 * commands set it explicitly with use() / --council.
 */
class Council
{
    private static ?string $override = null;

    /** @return array<string, array<string, mixed>> slug => config */
    public static function all(): array
    {
        return config('councils.list', []);
    }

    public static function exists(?string $slug): bool
    {
        return $slug !== null && array_key_exists($slug, self::all());
    }

    public static function default(): string
    {
        return config('councils.default', 'hastings');
    }

    public static function current(): string
    {
        if (self::$override !== null) {
            return self::$override;
        }
        $slug = app()->runningInConsole() ? null : session('council');
        return self::exists($slug) ? $slug : self::default();
    }

    /** @return array<string, mixed> */
    public static function config(?string $slug = null): array
    {
        return self::all()[$slug ?? self::current()] ?? [];
    }

    /** Pin the council for the rest of this process (artisan commands). */
    public static function use(string $slug): void
    {
        if (! self::exists($slug)) {
            throw new InvalidArgumentException("Unknown council '{$slug}'. Known: " . implode(', ', array_keys(self::all())));
        }
        self::$override = $slug;
    }

    /** Run $fn with $slug as the current council, then restore. */
    public static function using(string $slug, Closure $fn): mixed
    {
        $prev = self::$override;
        self::use($slug);
        try {
            return $fn();
        } finally {
            self::$override = $prev;
        }
    }

    /** Remember the user's choice for later web requests. */
    public static function choose(string $slug): void
    {
        if (! self::exists($slug)) {
            throw new InvalidArgumentException("Unknown council '{$slug}'.");
        }
        session(['council' => $slug]);
    }
}
