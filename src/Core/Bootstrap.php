<?php
declare(strict_types=1);

namespace VerbumStudio\Core;

use VerbumStudio\Auth\Capabilities;

final class Bootstrap
{
    private static ?Plugin $plugin = null;

    public static function boot(): Plugin
    {
        if (! self::$plugin) {
            self::$plugin = new Plugin(new Container(), new Config());
            self::$plugin->register();
        }
        return self::$plugin;
    }

    public static function activate(): void
    {
        (new Capabilities())->add();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
