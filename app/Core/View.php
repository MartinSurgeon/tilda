<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /** Render views/pages/{$view}.php inside views/layouts/{$layout}.php. */
    public static function render(string $view, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::partial('pages/' . $view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial('layouts/' . $layout, $data + ['content' => $content]);
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = BASE_PATH . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function show(string $view, array $data = [], ?string $layout = 'app', int $status = 200): never
    {
        Response::html(self::render($view, $data, $layout), $status);
    }
}
