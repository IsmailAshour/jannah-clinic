<?php

use Illuminate\Support\Facades\Route;

it('exposes canonical single-prefixed P1 route names', function () {
    $names = [
        'admin.dashboard', 'portal.home', 'portal.profile.avatar',
        'admin.catalog.categories', 'admin.catalog.categories.store', 'admin.catalog.categories.update', 'admin.catalog.categories.destroy',
        'admin.catalog.services', 'admin.catalog.services.store', 'admin.catalog.services.update', 'admin.catalog.services.destroy',
        'admin.doctors.index', 'admin.doctors.store', 'admin.doctors.update', 'admin.doctors.destroy',
        'admin.doctors.schedule', 'admin.doctors.schedule.save', 'admin.doctors.exceptions.add', 'admin.doctors.exceptions.delete',
        'admin.coverage.index', 'admin.coverage.store', 'admin.coverage.update', 'admin.coverage.destroy',
        'admin.settings.index', 'admin.settings.surcharge',
        'portal.services.index',
        'admin.availability', 'portal.availability',
        'admin.availability.days', 'portal.availability.days',
        'portal.booking.create', 'portal.booking.store',
        'admin.booking.create', 'admin.booking.store',
        'admin.appointments.index', 'admin.appointments.transition',
        'admin.customers.index', 'admin.customers.show', 'admin.customers.store', 'admin.customers.update', 'admin.customers.toggle-active',
        'portal.appointments.index', 'portal.appointments.cancel', 'portal.appointments.reschedule',
        'portal.appointments.payment', 'portal.appointments.payment.upload',
    ];
    foreach ($names as $n) {
        expect(Route::has($n))->toBeTrue("missing route name: {$n}");
    }
    // no doubled-prefix names exist
    $all = collect(Route::getRoutes()->getRoutesByName())->keys();
    expect($all->filter(fn ($x) => str_contains($x, 'admin.admin.') || str_contains($x, 'portal.portal.'))->all())->toBe([]);
});
