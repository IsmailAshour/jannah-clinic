<?php

it('serves the PWA manifest as JSON with the required PWA keys', function () {
    $resp = $this->get('/manifest.webmanifest');

    $resp->assertOk();
    $resp->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8');

    $body = $resp->json();
    expect($body['name'])->toBeString()
        ->and($body['short_name'])->toBeString()
        ->and($body['start_url'])->toBe('/')
        ->and($body['display'])->toBe('standalone')
        ->and($body['theme_color'])->toBe('#0B4F2F')
        ->and($body['dir'])->toBe('rtl')
        ->and($body['lang'])->toBe('ar')
        ->and($body['icons'])->toHaveCount(3);

    // Required for Chrome installability: 192 and 512 sizes present.
    $sizes = array_map(fn ($i) => $i['sizes'], $body['icons']);
    expect($sizes)->toContain('192x192')
        ->and($sizes)->toContain('512x512');
});

it('manifest works for guests (no auth required)', function () {
    $this->get('/manifest.webmanifest')->assertOk();
});
