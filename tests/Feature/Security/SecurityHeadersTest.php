<?php

it('sends security headers on public pages and back-offices', function (string $path) {
    $response = $this->get($path);

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain("object-src 'none'");
})->with(['/', '/connexion', '/app/login', '/admin/login']);

it('keeps public pages light for slow connections', function (string $path) {
    expect(strlen($this->get($path)->getContent()))->toBeLessThan(20_000);
})->with(['/', '/connexion', '/inscription', '/demander-un-espace']);
