<?php

use App\Support\HtmlSanitizer;

it('removes scripts, event handlers and dangerous links from descriptions', function () {
    $clean = app(HtmlSanitizer::class)->clean(
        '<h2>Poste</h2><p onclick="steal()">Texte <strong>gras</strong></p>'
        .'<script>alert(1)</script><img src=x onerror=alert(1)>'
        .'<a href="javascript:alert(1)">piège</a><a href="https://link.ne">lien</a>'
        .'<iframe src="https://evil.test"></iframe><style>body{}</style>'
    );

    expect($clean)
        ->toContain('<h2>Poste</h2>')
        ->toContain('<strong>gras</strong>')
        ->toContain('href="https://link.ne"')
        ->toContain('rel="noopener noreferrer nofollow"')
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('<img')
        ->not->toContain('javascript:')
        ->not->toContain('iframe')
        ->not->toContain('<style');
});

it('returns null for empty content', function () {
    expect(app(HtmlSanitizer::class)->clean('<p> </p>'))->toBeNull()
        ->and(app(HtmlSanitizer::class)->clean(null))->toBeNull();
});
