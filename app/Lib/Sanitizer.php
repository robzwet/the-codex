<?php

declare(strict_types=1);

namespace App\Lib;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes user-authored rich-text HTML before it is stored, so a malicious
 * note can never inject script/style/handlers. Wiki links are kept as literal
 * [[Title]] tokens in the text and resolved at render time (see WikiLinks),
 * so no special attributes need to survive sanitization.
 */
final class Sanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(string $html): string
    {
        // Graceful fallback if the vendor dir isn't present (e.g. bare CLI).
        if (!class_exists(HTMLPurifier::class)) {
            return strip_tags($html, '<p><br><strong><em><u><s><h1><h2><h3><h4><ul><ol><li><blockquote><code><pre><hr><a>');
        }

        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            // We don't customise the HTML definition, so disable the on-disk
            // definition cache — vendor/ is read-only in the container.
            $config->set('Cache.DefinitionImpl', null);
            $config->set('HTML.Allowed', implode(',', [
                'p', 'br', 'strong', 'em', 'u', 's',
                'h1', 'h2', 'h3', 'h4',
                'ul', 'ol', 'li', 'blockquote',
                'code', 'pre', 'hr',
                'a[href|title]',
            ]));
            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('HTML.TargetBlank', true);
            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier->purify($html);
    }
}
