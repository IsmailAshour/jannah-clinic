<?php

return [

    /*
    |--------------------------------------------------------------------------
    | رسائل التحقّق من الإدخال (Validation)
    |--------------------------------------------------------------------------
    |
    | كل رسالة تستخدم :attribute للاسم الحقلي و:value/:min/:max/:other/:date/...
    | لتعويض القيم. أبقِ هذه العلامات كما هي.
    |
    */

    'accepted' => 'يجب قبول الحقل :attribute.',
    'accepted_if' => 'يجب قبول الحقل :attribute عندما تكون قيمة :other هي :value.',
    'active_url' => 'الحقل :attribute يجب أن يكون رابطًا صحيحًا.',
    'after' => 'الحقل :attribute يجب أن يكون تاريخًا بعد :date.',
    'after_or_equal' => 'الحقل :attribute يجب أن يكون تاريخًا يساوي أو يلي :date.',
    'alpha' => 'الحقل :attribute يجب أن يحتوي على أحرف فقط.',
    'alpha_dash' => 'الحقل :attribute يجب أن يحتوي على أحرف وأرقام وشرطات فقط.',
    'alpha_num' => 'الحقل :attribute يجب أن يحتوي على أحرف وأرقام فقط.',
    'any_of' => 'قيمة الحقل :attribute غير صالحة.',
    'array' => 'الحقل :attribute يجب أن يكون مصفوفة.',
    'ascii' => 'الحقل :attribute يجب أن يحتوي على أحرف ورموز أحاديّة البايت فقط.',
    'before' => 'الحقل :attribute يجب أن يكون تاريخًا قبل :date.',
    'before_or_equal' => 'الحقل :attribute يجب أن يكون تاريخًا يساوي أو يسبق :date.',
    'between' => [
        'array' => 'الحقل :attribute يجب أن يحتوي بين :min و :max عناصر.',
        'file' => 'حجم الملف :attribute يجب أن يكون بين :min و :max كيلوبايت.',
        'numeric' => 'القيمة في :attribute يجب أن تكون بين :min و :max.',
        'string' => 'عدد أحرف :attribute يجب أن يكون بين :min و :max.',
    ],
    'boolean' => 'الحقل :attribute يجب أن يكون نعم أو لا.',
    'can' => 'الحقل :attribute يحتوي على قيمة غير مسموح بها.',
    'confirmed' => 'تأكيد الحقل :attribute غير مطابق.',
    'contains' => 'الحقل :attribute لا يحتوي على القيمة المطلوبة.',
    'current_password' => 'كلمة المرور الحالية غير صحيحة.',
    'date' => 'الحقل :attribute يجب أن يكون تاريخًا صحيحًا.',
    'date_equals' => 'الحقل :attribute يجب أن يساوي التاريخ :date.',
    'date_format' => 'الحقل :attribute يجب أن يطابق الصيغة :format.',
    'decimal' => 'الحقل :attribute يجب أن يحتوي :decimal خانة عشريّة.',
    'declined' => 'يجب رفض الحقل :attribute.',
    'declined_if' => 'يجب رفض الحقل :attribute عندما تكون قيمة :other هي :value.',
    'different' => 'الحقل :attribute و:other يجب أن يكونا مختلفين.',
    'digits' => 'الحقل :attribute يجب أن يتكوّن من :digits رقمًا.',
    'digits_between' => 'عدد أرقام :attribute يجب أن يكون بين :min و :max.',
    'dimensions' => 'أبعاد الصورة في :attribute غير صحيحة.',
    'distinct' => 'الحقل :attribute يحتوي قيمة مكرّرة.',
    'doesnt_contain' => 'الحقل :attribute يجب ألّا يحتوي على أيٍّ من: :values.',
    'doesnt_end_with' => 'الحقل :attribute يجب ألّا ينتهي بأيٍّ من: :values.',
    'doesnt_start_with' => 'الحقل :attribute يجب ألّا يبدأ بأيٍّ من: :values.',
    'email' => 'الحقل :attribute يجب أن يكون بريدًا إلكترونيًّا صحيحًا.',
    'encoding' => 'الحقل :attribute يجب أن يكون بترميز :encoding.',
    'ends_with' => 'الحقل :attribute يجب أن ينتهي بأحد القيم: :values.',
    'enum' => 'القيمة المختارة في :attribute غير صالحة.',
    'exists' => 'القيمة المختارة في :attribute غير موجودة.',
    'extensions' => 'الحقل :attribute يجب أن يحوي امتدادًا من: :values.',
    'file' => 'الحقل :attribute يجب أن يكون ملفًّا.',
    'filled' => 'الحقل :attribute يجب أن يحوي قيمة.',
    'gt' => [
        'array' => 'عدد عناصر :attribute يجب أن يكون أكبر من :value.',
        'file' => 'حجم الملف :attribute يجب أن يكون أكبر من :value كيلوبايت.',
        'numeric' => 'القيمة في :attribute يجب أن تكون أكبر من :value.',
        'string' => 'عدد أحرف :attribute يجب أن يكون أكبر من :value.',
    ],
    'gte' => [
        'array' => 'عدد عناصر :attribute يجب أن يكون :value أو أكثر.',
        'file' => 'حجم الملف :attribute يجب أن يساوي أو يتجاوز :value كيلوبايت.',
        'numeric' => 'القيمة في :attribute يجب أن تساوي أو تتجاوز :value.',
        'string' => 'عدد أحرف :attribute يجب أن يساوي أو يتجاوز :value.',
    ],
    'hex_color' => 'الحقل :attribute يجب أن يكون لونًا سداسيًّا صحيحًا.',
    'image' => 'الحقل :attribute يجب أن يكون صورة.',
    'in' => 'القيمة المختارة في :attribute غير صالحة.',
    'in_array' => 'الحقل :attribute يجب أن يكون موجودًا في :other.',
    'in_array_keys' => 'الحقل :attribute يجب أن يحوي مفتاحًا من: :values.',
    'integer' => 'الحقل :attribute يجب أن يكون عددًا صحيحًا.',
    'ip' => 'الحقل :attribute يجب أن يكون عنوان IP صحيحًا.',
    'ipv4' => 'الحقل :attribute يجب أن يكون عنوان IPv4 صحيحًا.',
    'ipv6' => 'الحقل :attribute يجب أن يكون عنوان IPv6 صحيحًا.',
    'json' => 'الحقل :attribute يجب أن يكون نصّ JSON صحيح.',
    'list' => 'الحقل :attribute يجب أن يكون قائمة.',
    'lowercase' => 'الحقل :attribute يجب أن يكون بأحرف صغيرة.',
    'lt' => [
        'array' => 'عدد عناصر :attribute يجب أن يكون أقلّ من :value.',
        'file' => 'حجم الملف :attribute يجب أن يكون أقلّ من :value كيلوبايت.',
        'numeric' => 'القيمة في :attribute يجب أن تكون أقلّ من :value.',
        'string' => 'عدد أحرف :attribute يجب أن يكون أقلّ من :value.',
    ],
    'lte' => [
        'array' => 'عدد عناصر :attribute يجب ألّا يتجاوز :value.',
        'file' => 'حجم الملف :attribute يجب ألّا يتجاوز :value كيلوبايت.',
        'numeric' => 'القيمة في :attribute يجب ألّا تتجاوز :value.',
        'string' => 'عدد أحرف :attribute يجب ألّا يتجاوز :value.',
    ],
    'mac_address' => 'الحقل :attribute يجب أن يكون عنوان MAC صحيحًا.',
    'max' => [
        'array' => 'عدد عناصر :attribute يجب ألّا يتجاوز :max.',
        'file' => 'حجم الملف :attribute يجب ألّا يتجاوز :max كيلوبايت.',
        'numeric' => 'القيمة في :attribute يجب ألّا تتجاوز :max.',
        'string' => 'عدد أحرف :attribute يجب ألّا يتجاوز :max.',
    ],
    'max_digits' => 'عدد أرقام :attribute يجب ألّا يتجاوز :max.',
    'mimes' => 'الحقل :attribute يجب أن يكون ملفًّا من نوع: :values.',
    'mimetypes' => 'الحقل :attribute يجب أن يكون ملفًّا من نوع: :values.',
    'min' => [
        'array' => 'عدد عناصر :attribute يجب أن يكون :min أو أكثر.',
        'file' => 'حجم الملف :attribute يجب أن يكون :min كيلوبايت أو أكثر.',
        'numeric' => 'القيمة في :attribute يجب أن تكون :min أو أكبر.',
        'string' => 'عدد أحرف :attribute يجب أن يكون :min أو أكثر.',
    ],
    'min_digits' => 'عدد أرقام :attribute يجب أن يكون :min أو أكثر.',
    'missing' => 'الحقل :attribute يجب ألّا يُرسَل.',
    'missing_if' => 'الحقل :attribute يجب ألّا يُرسَل عندما تكون قيمة :other هي :value.',
    'missing_unless' => 'الحقل :attribute يجب ألّا يُرسَل إلّا عندما تكون قيمة :other هي :value.',
    'missing_with' => 'الحقل :attribute يجب ألّا يُرسَل عند وجود :values.',
    'missing_with_all' => 'الحقل :attribute يجب ألّا يُرسَل عند وجود :values جميعًا.',
    'multiple_of' => 'الحقل :attribute يجب أن يكون من مضاعفات :value.',
    'not_in' => 'القيمة المختارة في :attribute غير صالحة.',
    'not_regex' => 'صيغة الحقل :attribute غير صحيحة.',
    'numeric' => 'الحقل :attribute يجب أن يكون رقمًا.',
    'password' => [
        'letters' => 'كلمة المرور :attribute يجب أن تحتوي حرفًا واحدًا على الأقل.',
        'mixed' => 'كلمة المرور :attribute يجب أن تحتوي حرفًا كبيرًا وآخر صغيرًا.',
        'numbers' => 'كلمة المرور :attribute يجب أن تحتوي رقمًا واحدًا على الأقل.',
        'symbols' => 'كلمة المرور :attribute يجب أن تحتوي رمزًا واحدًا على الأقل.',
        'uncompromised' => 'كلمة المرور :attribute ظهرت ضمن تسريبات بيانات. اختر كلمة مرور مختلفة.',
    ],
    'present' => 'الحقل :attribute يجب أن يكون موجودًا.',
    'present_if' => 'الحقل :attribute يجب أن يكون موجودًا عندما تكون قيمة :other هي :value.',
    'present_unless' => 'الحقل :attribute يجب أن يكون موجودًا إلّا عندما تكون قيمة :other هي :value.',
    'present_with' => 'الحقل :attribute يجب أن يكون موجودًا عند وجود :values.',
    'present_with_all' => 'الحقل :attribute يجب أن يكون موجودًا عند وجود :values جميعًا.',
    'prohibited' => 'الحقل :attribute غير مسموح به.',
    'prohibited_if' => 'الحقل :attribute غير مسموح به عندما تكون قيمة :other هي :value.',
    'prohibited_if_accepted' => 'الحقل :attribute غير مسموح به عند قبول :other.',
    'prohibited_if_declined' => 'الحقل :attribute غير مسموح به عند رفض :other.',
    'prohibited_unless' => 'الحقل :attribute غير مسموح به ما لم تكن قيمة :other ضمن :values.',
    'prohibits' => 'الحقل :attribute يمنع وجود :other.',
    'regex' => 'صيغة الحقل :attribute غير صحيحة.',
    'required' => 'الحقل :attribute مطلوب.',
    'required_array_keys' => 'الحقل :attribute يجب أن يحتوي مدخلات لـ: :values.',
    'required_if' => 'الحقل :attribute مطلوب عندما تكون قيمة :other هي :value.',
    'required_if_accepted' => 'الحقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'الحقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'الحقل :attribute مطلوب ما لم تكن قيمة :other ضمن :values.',
    'required_with' => 'الحقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'الحقل :attribute مطلوب عند وجود :values جميعًا.',
    'required_without' => 'الحقل :attribute مطلوب عند غياب :values.',
    'required_without_all' => 'الحقل :attribute مطلوب عند غياب :values جميعًا.',
    'same' => 'الحقل :attribute يجب أن يطابق :other.',
    'size' => [
        'array' => 'الحقل :attribute يجب أن يحوي :size عناصر.',
        'file' => 'حجم الملف :attribute يجب أن يكون :size كيلوبايت.',
        'numeric' => 'القيمة في :attribute يجب أن تكون :size.',
        'string' => 'عدد أحرف :attribute يجب أن يكون :size.',
    ],
    'starts_with' => 'الحقل :attribute يجب أن يبدأ بإحدى القيم: :values.',
    'string' => 'الحقل :attribute يجب أن يكون نصًّا.',
    'timezone' => 'الحقل :attribute يجب أن يكون منطقة زمنيّة صحيحة.',
    'unique' => 'القيمة في :attribute مستخدمة مسبقًا.',
    'uploaded' => 'فشل رفع :attribute.',
    'uppercase' => 'الحقل :attribute يجب أن يكون بأحرف كبيرة.',
    'url' => 'الحقل :attribute يجب أن يكون رابطًا صحيحًا.',
    'ulid' => 'الحقل :attribute يجب أن يكون ULID صحيحًا.',
    'uuid' => 'الحقل :attribute يجب أن يكون UUID صحيحًا.',

    /*
    |--------------------------------------------------------------------------
    | رسائل مخصّصة (Custom)
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول العربيّة (Attributes)
    |--------------------------------------------------------------------------
    |
    | استبدال :attribute باسم الحقل العربيّ بدل اسمه التقنيّ الإنجليزي.
    |
    */

    'attributes' => [
        // عام
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'is_active' => 'الحالة',
        'date_of_birth' => 'تاريخ الميلاد',
        'gender' => 'الجنس',
        'notes' => 'الملاحظات',
        'note' => 'الملاحظة',
        'reason' => 'السبب',
        'status' => 'الحالة',
        'token' => 'الرمز',
        'identifier' => 'البريد أو الهاتف',

        // الخدمات
        'category_id' => 'الفئة',
        'service_id' => 'الخدمة',
        'base_price' => 'السعر',
        'duration_minutes' => 'مدّة الجلسة',
        'home_service_enabled' => 'تفعيل الخدمة المنزلية',
        'description' => 'الوصف',
        'display_order' => 'الترتيب',
        'icon_key' => 'الأيقونة',
        'slug' => 'المُعرّف',
        'color_variant' => 'اللون',
        'loyalty_enabled' => 'تفعيل الولاء',
        'loyalty_redemption_points' => 'نقاط الاستبدال',

        // الأطبّاء
        'doctor_id' => 'الطبيب',
        'doctor_profile_id' => 'الطبيب',
        'specialty' => 'التخصّص',
        'bio' => 'النبذة',
        'is_bookable' => 'متاح للحجز',
        'rating_average' => 'متوسّط التقييم',

        // الحجوزات والمواعيد
        'customer_id' => 'العميل',
        'start_at' => 'وقت البدء',
        'end_at' => 'وقت الانتهاء',
        'start' => 'وقت البدء',
        'delivery_mode' => 'طريقة التقديم',
        'coverage_area_id' => 'منطقة التغطية',
        'address_text' => 'العنوان',
        'location_note' => 'ملاحظة الموقع',
        'price_at_booking' => 'سعر الحجز',
        'home_surcharge_amount' => 'رسم الزيارة المنزلية',
        'cancellation_reason' => 'سبب الإلغاء',
        'payment_method' => 'طريقة الدفع',

        // الدفع
        'amount' => 'المبلغ',
        'file' => 'الملف',
        'receipt' => 'الإيصال',
        'refund_reference' => 'مرجع الاسترداد',
        'bank_name' => 'اسم البنك',
        'bank_account_holder' => 'صاحب الحساب',
        'bank_iban' => 'الـ IBAN',
        'bank_account_number' => 'رقم الحساب',

        // السجل الطبي
        'visible_summary' => 'الخلاصة',
        'staff_notes' => 'ملاحظات الطاقم',
        'chronic_conditions' => 'الأمراض المزمنة',
        'allergies' => 'الحساسية',
        'medication_name' => 'اسم الدواء',
        'dosage' => 'الجرعة',
        'frequency' => 'تواتر الجرعة',
        'duration' => 'المدّة',
        'prescriptions' => 'الوصفات',

        // مناطق التغطية
        'min_radius_km' => 'أقلّ نصف قطر',
        'max_radius_km' => 'أكبر نصف قطر',
        'surcharge_percent' => 'نسبة الرسم',

        // الإعدادات
        'home_surcharge_percent' => 'نسبة رسم الزيارة المنزلية',
        'slot_minutes' => 'مدّة الفترة',

        // الولاء
        'delta' => 'التغيير',
    ],

];
