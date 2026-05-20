<?php

it('app locale is Arabic and validation messages resolve in Arabic', function () {
    expect(app()->getLocale())->toBe('ar');

    $msg = trans('validation.required', ['attribute' => trans('validation.attributes.email')]);
    expect($msg)->toBe('الحقل البريد الإلكتروني مطلوب.');
});

it('fallback locale is English so missing keys are visible', function () {
    expect(config('app.fallback_locale'))->toBe('en');
});

it('validation error responses return Arabic messages on a real route', function () {
    $this->postJson('/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'identifier' => 'الحقل البريد أو الهاتف مطلوب.',
            'password' => 'الحقل كلمة المرور مطلوب.',
        ]);
});

it('common project attributes resolve to Arabic', function () {
    $attrs = trans('validation.attributes');
    expect($attrs)->toBeArray()
        ->and($attrs['name'] ?? null)->toBe('الاسم')
        ->and($attrs['phone'] ?? null)->toBe('الهاتف')
        ->and($attrs['visible_summary'] ?? null)->toBe('الخلاصة')
        ->and($attrs['payment_method'] ?? null)->toBe('طريقة الدفع');
});
