<?php
/**
 * Tiny PSR-0-style autoloader for includes/classes/.
 *
 * Class FooBar → includes/classes/FooBar.php
 *
 * Limitations (deliberate): single directory, no namespaces, no Composer.
 * Keeps installation a zip-and-upload affair.
 */

defined('APP_BOOTED') or exit;

spl_autoload_register(static function (string $class): void {
    // Reject suspicious class names — only [A-Za-z0-9_].
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
        return;
    }
    $path = __DIR__ . '/classes/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
