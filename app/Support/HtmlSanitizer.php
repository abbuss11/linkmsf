<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans rich text written in the back-office before it is stored, because it
 * is later shown on public pages (offer description). Allow-list only: no
 * scripts, styles, event handlers, images or embeds.
 */
class HtmlSanitizer
{
    private ?SymfonySanitizer $sanitizer = null;

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim($this->sanitizer()->sanitize($html));

        return $clean === '' || trim(strip_tags($clean)) === '' ? null : $clean;
    }

    private function sanitizer(): SymfonySanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->withMaxInputLength(100_000);

        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote'] as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config->allowElement('a', ['href']);

        return $this->sanitizer = new SymfonySanitizer($config);
    }
}
