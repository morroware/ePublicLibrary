<?php
/**
 * Minimal PHP-template renderer with layouts.
 *
 *   render('library/index', ['books' => $books]);
 *   render('book/show', $vars, 'reader');   // explicit layout
 *
 * The view file may call `layout('reader')` to set its layout from inside,
 * or `layout(null)` to render without a layout (e.g. error pages).
 *
 * Inside templates: use e(), eurl(), url(), asset() helpers directly.
 */

defined('APP_BOOTED') or exit;

/** Set the layout for the current render. NULL = no layout. */
function layout(?string $name): void
{
    $GLOBALS['__view_layout'] = $name;
}

/** Render a view with optional layout. Echoes HTML. */
function render(string $view, array $vars = [], ?string $defaultLayout = 'app'): void
{
    $GLOBALS['__view_layout'] = $defaultLayout;
    $template = project_path('views/' . ltrim($view, '/') . '.php');
    if (!is_file($template)) {
        throw new RuntimeException("View not found: {$view}");
    }
    extract($vars, EXTR_SKIP);

    ob_start();
    require $template;
    $contents = ob_get_clean();

    $layoutName = $GLOBALS['__view_layout'] ?? null;
    if ($layoutName === null) {
        echo $contents;
        return;
    }
    $layoutPath = project_path('views/layouts/' . $layoutName . '.php');
    if (!is_file($layoutPath)) {
        echo $contents;
        return;
    }
    $vars['__contents'] = $contents;
    extract($vars, EXTR_SKIP);
    require $layoutPath;
}

/** Render a small partial inside a layout/view (no layout chaining). */
function partial(string $name, array $vars = []): void
{
    $path = project_path('views/partials/' . ltrim($name, '/') . '.php');
    if (!is_file($path)) {
        throw new RuntimeException("Partial not found: {$name}");
    }
    extract($vars, EXTR_SKIP);
    require $path;
}
