<?php

use Illuminate\Support\Facades\Route;

it('exposes canonical single-prefixed P1 route names', function () {
    $names = [
        'admin.dashboard', 'admin.dashboard.calendar', 'admin.reports.index', 'portal.home', 'portal.profile.avatar',
        'admin.catalog.categories', 'admin.catalog.categories.store', 'admin.catalog.categories.update', 'admin.catalog.categories.destroy',
        'admin.catalog.services', 'admin.catalog.services.store', 'admin.catalog.services.update', 'admin.catalog.services.destroy',
        'admin.doctors.index', 'admin.doctors.store', 'admin.doctors.update', 'admin.doctors.destroy',
        'admin.doctors.schedule', 'admin.doctors.day', 'admin.doctors.schedule.save', 'admin.doctors.exceptions.add', 'admin.doctors.exceptions.delete',
        'admin.appointments.photos.store', 'admin.appointments.photos.file', 'admin.appointments.photos.destroy',
        'admin.coverage.index', 'admin.coverage.store', 'admin.coverage.update', 'admin.coverage.destroy',
        'admin.settings.index', 'admin.settings.clinic', 'admin.settings.clinic.logo', 'admin.settings.surcharge', 'admin.settings.bank',
        'portal.services.index',
        'admin.availability', 'portal.availability',
        'admin.availability.days', 'portal.availability.days',
        'portal.booking.create', 'portal.booking.store',
        'admin.booking.create', 'admin.booking.store',
        'admin.appointments.index', 'admin.appointments.transition',
        'admin.customers.index', 'admin.customers.show', 'admin.customers.store', 'admin.customers.update', 'admin.customers.toggle-active', 'admin.customers.reset-password', 'admin.customers.search',
        'portal.appointments.index', 'portal.appointments.cancel', 'portal.appointments.reschedule',
        'portal.appointments.payment', 'portal.appointments.payment.upload', 'portal.appointments.payment.receipt-file',
        'admin.payments.index', 'admin.payments.show', 'admin.payments.receipt-file',
        'admin.payments.verify', 'admin.payments.reject', 'admin.payments.mark-refund-pending', 'admin.payments.mark-refunded',
        'admin.appointments.medical-entry.store', 'admin.appointments.medical-entry.create',
        'admin.medical-entries.edit', 'admin.medical-entries.update',
        'admin.customers.profile.medical.update',
        'portal.medical-record.index', 'portal.medical-record.show',
        'admin.notifications.index', 'admin.notifications.read', 'admin.notifications.mark-all-read',
        'portal.notifications.index', 'portal.notifications.read', 'portal.notifications.mark-all-read',
        'admin.customers.loyalty.show', 'admin.customers.loyalty.adjust',
        'portal.loyalty.index',
        'public.home', 'public.services', 'public.services.show', 'public.doctors', 'public.support', 'public.contact.store', 'pwa.manifest',
        'admin.messages.index', 'admin.messages.show', 'admin.messages.status', 'admin.messages.destroy',
        'portal.profile.edit', 'portal.profile.update',
        'portal.settings.index', 'portal.settings.password',
    ];
    foreach ($names as $n) {
        expect(Route::has($n))->toBeTrue("missing route name: {$n}");
    }
    // no doubled-prefix names exist
    $all = collect(Route::getRoutes()->getRoutesByName())->keys();
    expect($all->filter(fn ($x) => str_contains($x, 'admin.admin.') || str_contains($x, 'portal.portal.'))->all())->toBe([]);
});
