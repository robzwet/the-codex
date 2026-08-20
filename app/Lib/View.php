<?php

declare(strict_types=1);

namespace App\Lib;

final class View
{
    /**
     * Render a view template, optionally wrapped in a layout. The template's
     * output is captured into $content and made available to the layout.
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require __DIR__ . '/../Views/' . $template . '.php';
        $content = ob_get_clean();

        if ($layout !== null) {
            require __DIR__ . '/../Views/' . $layout . '.php';
        } else {
            echo $content;
        }
    }
}
