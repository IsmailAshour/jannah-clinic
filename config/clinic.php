<?php

return [
    // Home-visit surcharge as a percentage of the (override-or-base) price.
    'home_surcharge_pct' => 30,
    // Minimum minutes between "now" and a bookable slot start.
    'booking_lead_minutes' => 0,
    // Slot grid configuration.
    'slot_minutes' => 30,
    'day_start' => '08:00',
    'day_end' => '22:00',
    'band_split' => '15:00',
    // Bank account info (P2). Defaults are empty; the live values are set at
    // runtime via SettingService (DB-backed override) from the admin Settings page.
    'bank_name' => env('CLINIC_BANK_NAME', ''),
    'bank_account_holder' => env('CLINIC_BANK_ACCOUNT_HOLDER', ''),
    'bank_iban' => env('CLINIC_BANK_IBAN', ''),
    'bank_account_number' => env('CLINIC_BANK_ACCOUNT_NUMBER', ''),

    // Clinic contact info shown on the public support page.
    'contact' => [
        'phone' => env('CLINIC_PHONE', ''),
        'whatsapp' => env('CLINIC_WHATSAPP', ''),
        'address' => env('CLINIC_ADDRESS', ''),
    ],

    // P5b — beauty/health tips rotated on the public home page. Manager edits in deployment.
    'tips' => [
        'اشرب 8 أكواب ماء يوميًّا للحفاظ على نضارة بشرتك.',
        'النوم 7–9 ساعات ليلًا يحسّن المزاج والمناعة.',
        'حركة خفيفة 30 دقيقة يوميًّا تقي من معظم الأمراض المزمنة.',
        'استخدم واقي الشمس حتى في الأيام الغائمة.',
        'تنفّس بعمق 5 دقائق يوميًّا لخفض التوتّر.',
    ],

    // P5b — public FAQ entries.
    'faqs' => [
        ['q' => 'كيف أحجز موعدًا؟', 'a' => 'من قائمة الخدمات اختر الخدمة المناسبة ثم اضغط «احجز» — سيُطلب منك تسجيل الدخول أو إنشاء حساب.'],
        ['q' => 'ما طريقة الدفع؟', 'a' => 'يتمّ الدفع عبر تحويل بنكي ثم رفع صورة الإيصال من صفحة الموعد.'],
        ['q' => 'هل توجد خدمة منزلية؟', 'a' => 'نعم لبعض الخدمات — تظهر الخدمات القابلة للتقديم منزليًّا في قائمة الحجز.'],
        ['q' => 'هل يمكنني إلغاء الموعد؟', 'a' => 'نعم، من صفحة «مواعيدي» قبل وقت محدّد من الموعد.'],
        ['q' => 'كيف أستخدم نقاط الولاء؟', 'a' => 'تكسب نقطة عن كل شيكل تدفعه. تستبدلها لاحقًا بالخدمات المُفعَّل عليها برنامج الولاء أثناء الحجز.'],
    ],
];
