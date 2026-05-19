# تصميم: jannahclinic — المرحلة P1 (الخدمات والحجز)

> **الحالة:** DRAFT — بانتظار مراجعة المستخدم
> **التاريخ:** 2026-05-19
> **الموضوع:** المرحلة P1 من نظام عيادة «جنّة» — كتالوج خدمات مسبق التسعير + أطباء/جداول + معالج حجز (ذاتي + نيابة، مركز/منزلي) + دورة حياة المواعيد. مشروع فرعي ثانٍ ضمن خريطة مراحل P0–P5.
> **يبني على:** P0 (الأساس) — الوسم `p0-foundation`. أساس قائم: مصادقة إيميل/جوال + 4 أدوار، `AdminShell`/`ClientShell`، طبقة foundation (PageStates/DataTable/FormGroup/Modal…)، توكنات Tailwind v4، Postgres، حوكمة methodology-kit، ADR-001/002.
> **مرجع المتطلبات:** جرد مميزات `C:\~projects\clinic` (تطبيق «عيادة جنة» Next.js — مرجع نطاق لا مصدر كود) + روادماب P0 spec §2.
> **الحوكمة:** methodology-kit v1.0.1 (R7 خدمات Domain، R12 config-driven، R16 4 حالات، R20 RTL منطقي، R6 autodoc، نقود decimal/bcmath، DoD/CI).

---

## 1. الهدف ومعيار النجاح

**الهدف:** تمكين عيادة «جنّة» من نشر كتالوج خدمات مسبق التسعير وإدارة الأطباء وجداولهم، وتمكين العملاء من الحجز الذاتي (مركز/منزلي) والاستقبال من الحجز نيابةً، مع دورة حياة مواعيد كاملة — كل ذلك فوق أساس P0 ومحكومًا بحزمة المنهجية.

**معيار نجاح P1:** مشغّل/استقبال يدير الفئات/الخدمات/الأطباء/الجداول/مناطق التغطية ونسبة الرسم من لوحة التحكم؛ عميل يتصفّح الخدمات ويحجز ذاتيًا (مركز أو منزلي) عبر معالج 3 خطوات بسعر محسوب صحيح (+نسبة منزلية من الإعداد)؛ الاستقبال يحجز نيابةً عن عميل (منتقى أو منشأ سريعًا)؛ المواعيد تمرّ بدورة حياتها الكاملة (تأكيد/رفض/إكمال/تخلّف/إلغاء/إعادة جدولة) بإنفاذ انتقالات خادمي؛ منع الحجز المزدوج للفترة؛ كل ذلك مع بوابة الجودة خضراء وDOMAIN-MODEL/ARCHITECTURE محدّثَين. **P1 ينتهي عند الموعد فقط — لا كيان دفع (المدفوعات P2).**

**القرارات المعتمدة (عصف ذهني P1):**
1. حدّ P1↔P2: P1 يُنشئ `Appointment` (requested/confirmed) و`price_at_booking` (+رسم منزلي)؛ **لا كيان Payment**؛ الفوترة/الإيصالات كلها P2.
2. طبيب↔خدمة: pivot `doctor_service` يحدّد من يقدّم ماذا + `price_override` اختياري لكل طبيب؛ السعر = `price_override ?? service.base_price`؛ المعالج يفلتر الخدمات حسب الطبيب.
3. دورة الموعد: 7 حالات (`requested`→`confirmed`/`rejected`، `completed`، `cancelled`، `no_show`، `rescheduled`). العميل: حجز/إلغاء/إعادة جدولة. الطاقم: تأكيد/رفض/إكمال/تعليم تخلّف/إلغاء.
4. القنوات: العميل ذاتيًا (البوابة) + الاستقبال/الإدارة نيابةً (اللوحة، منتقي عميل + إنشاء سريع، `created_by_role` مسجَّل). معالج حجز واحد مشترك.
5. التوفّر: لكل (طبيب، يوم أسبوعي) نافذة صباح مفعّلة + مدى، ونافذة مساء مفعّلة + مدى، + `slot_interval_minutes`؛ + استثناءات تاريخ (`closed`/`custom_hours`) + استبعاد الفترات المحجوزة + لا فترات ماضية. عبر `AvailabilityService`.
6. الرسم المنزلي: نسبة عبر `Setting` (افتراضي 30% من config، تُعدَّل من اللوحة — R12) + `HomeServiceCoverageArea` مدارة من الإدارة + `Service.home_service_enabled`.

**المقاربة المعمارية:** **A** — بناء رأسي بترتيب التبعيات (B/C مرفوضتان: B تخاطر بإعادة عمل أعقد قطعة على stubs، C تؤخّر القلب المشترك). تسلسل: الكتالوج → الأطباء/الجداول → التغطية/الإعداد → AvailabilityService → معالج الحجز → دورة المواعيد.

---

## 2. نموذج المجال (الكيانات الجديدة)

كل الكيانات تتبع أنماط P0: Postgres، قيود CHECK على pgsql فقط (تُتخطّى على SQLite الاختباري؛ CI Postgres السلطوي — كـ P0)، `#[Fillable]`، casts، فهارس، `DOMAIN-MODEL.md` يُحدَّث في نفس change set (R6). نقود = `decimal` (لا float — فحص CI)، حسابات bcmath.

| الكيان / الجدول | الأعمدة | علاقات/قيود |
|---|---|---|
| **ServiceCategory** `service_categories` | id، name، slug (unique)، color_variant (`brand`\|`gold`)، display_order (int)، is_active (bool، افتراضي true)، timestamps | hasMany Service؛ CHECK color_variant enum |
| **Service** `services` | id، category_id FK→service_categories (cascade-on-delete أو restrict — restrict)، name، description (text nullable)، base_price `decimal(10,2)`، duration_minutes (int)، home_service_enabled (bool، افتراضي false)، icon_key (varchar nullable)، is_active (bool true)، display_order (int)، timestamps | belongsTo Category؛ belongsToMany DoctorProfile (pivot doctor_service)؛ CHECK `base_price >= 0`، `duration_minutes > 0` |
| **DoctorProfile** `doctor_profiles` | id، user_id FK→users (unique؛ المستخدم role=doctor)، specialty (varchar)، bio (text nullable)، rating_average `decimal(2,1)` nullable (عرض فقط — لا واجهة إدخال في P1)، is_bookable (bool true)، display_order (int)، timestamps | belongsTo User؛ belongsToMany Service (pivot)؛ hasMany DoctorSchedule/ScheduleException |
| **doctor_service** (pivot) | id، doctor_profile_id FK، service_id FK، price_override `decimal(10,2)` nullable، timestamps | unique(doctor_profile_id، service_id)؛ CHECK `price_override >= 0` (عند عدم NULL) |
| **DoctorSchedule** `doctor_schedules` | id، doctor_profile_id FK، weekday (smallint 0–6)، morning_enabled (bool false)، morning_start (time nullable)، morning_end (time nullable)، evening_enabled (bool false)، evening_start (time nullable)، evening_end (time nullable)، slot_interval_minutes (int، افتراضي 30)، timestamps | unique(doctor_profile_id، weekday)؛ CHECK weekday 0–6، `slot_interval_minutes > 0` |
| **ScheduleException** `schedule_exceptions` | id، doctor_profile_id FK، date (date)، type (`closed`\|`custom_hours`)، custom_start (time nullable)، custom_end (time nullable)، note (varchar nullable)، timestamps | unique(doctor_profile_id، date)؛ CHECK type enum |
| **HomeServiceCoverageArea** `home_service_coverage_areas` | id، name، is_active (bool true)، display_order (int)، timestamps | hasMany ServiceAddress |
| **Appointment** `appointments` | id، customer_id FK→users، doctor_profile_id FK، service_id FK، start_at (datetime)، end_at (datetime)، status (varchar enum)، price_at_booking `decimal(10,2)`، delivery_mode (`center`\|`home`)، home_surcharge_amount `decimal(10,2)` default 0، created_by_role (varchar — UserRole)، cancellation_reason (varchar nullable)، rescheduled_from_id (FK→appointments nullable)، timestamps | belongsTo customer(User)/doctor(DoctorProfile)/service؛ hasOne ServiceAddress؛ CHECK status enum، delivery_mode enum، `price_at_booking >= 0`، `end_at > start_at`؛ index(doctor_profile_id, start_at)، index(customer_id, status) |
| **ServiceAddress** `service_addresses` | id، appointment_id FK (unique، cascade-on-delete)، coverage_area_id FK→home_service_coverage_areas، address_text (varchar)، location_note (varchar nullable)، timestamps | belongsTo Appointment/CoverageArea؛ يوجد فقط عند delivery_mode=home |
| **Setting** `settings` | id، key (varchar unique)، value (varchar)، timestamps | جدول key/value بسيط؛ يخدم P2+ أيضًا |

**Enums (PHP، كنمط `App\Enums\UserRole` من P0):**
- `App\Enums\AppointmentStatus: string` — `Requested`/`Confirmed`/`Rejected`/`Completed`/`Cancelled`/`NoShow`/`Rescheduled`؛ `isTerminal(): bool` (rejected/completed/cancelled/no_show/rescheduled)؛ `canTransitionTo(self): bool` (مصفوفة انتقالات صريحة).
- `App\Enums\DeliveryMode: string` — `Center`/`Home`.

**الإعداد config-driven (R12):** `config/clinic.php` يحوي `home_surcharge_pct => 30`. `SettingService::get(string $key, mixed $default)` يقرأ من `settings` ثم يسقط إلى `config('clinic.'.$key)`. مفتاح P1: `home_surcharge_pct`. تُحرَّر القيمة من صفحة إعداد في اللوحة.

`docs/DOMAIN-MODEL.md` يُحدَّث: تُضاف هذه الكيانات؛ يُقلَّص قسم «OUT OF SCOPE» ليُبقي P2–P5 فقط (Payment/Receipt/MedicalRecord/MedicalEntry/Prescription/MembershipPlan/UserMembership/LoyaltyTransaction/Notification).

---

## 3. خدمات المجال والمنطق

كل المنطق في `app/Domain/{Module}/Services/` (R7) — Controllers رفيعة، لا منطق في Vue/Inertia. خدمات معزولة قابلة للاختبار وحدويًا.

### 3.1 `AvailabilityService` (`app/Domain/Booking/Services/AvailabilityService.php`)
نقية، بلا أثر جانبي. `slotsFor(DoctorProfile $doctor, Service $service, CarbonImmutable $date): array<array{start:CarbonImmutable,end:CarbonImmutable}>`:
1. اقرأ `DoctorSchedule` للطبيب و`weekday` التاريخ. إن غاب أو كلتا النافذتين معطّلتان ⇒ [].
2. ابنِ النوافذ: صباح إن `morning_enabled` (morning_start..morning_end)؛ مساء إن `evening_enabled` (evening_start..evening_end).
3. طبّق `ScheduleException` للطبيب وذلك التاريخ: `closed` ⇒ []؛ `custom_hours` ⇒ نافذة واحدة (custom_start..custom_end) تستبدل نوافذ الجدول.
4. قطّع كل نافذة بخطوة `slot_interval_minutes`، كل فترة بطول `service.duration_minutes`، بحيث لا تتجاوز نهاية النافذة.
5. استبعد الفترات المتداخلة زمنيًا مع `appointments` لذلك الطبيب بحالة **نشطة حاجزة فقط: {requested, confirmed}**. الحالات المنتهية (rejected/completed/cancelled/no_show/**rescheduled**) لا تحجز — موعد `rescheduled` أُخلِيت فترته القديمة والموعد الجديد يحجز فترته بحالته النشطة.
6. استبعد الفترات التي تبدأ في الماضي (الآن + هامش حجز قابل للضبط عبر config، افتراضي 0).
مختبَرة وحدويًا بكثافة (صباح فقط/مساء فقط/كلاهما، استثناء closed/custom، تداخل حجز، حدّ نهاية النافذة، ماضٍ).

### 3.2 `PricingService`
`quote(DoctorProfile $d, Service $s, DeliveryMode $mode): array{base:string,surcharge:string,total:string}`:
- `base = doctor_service.price_override ?? service.base_price` (للزوج d,s).
- `surcharge = mode===Home ? bcmul(base, SettingService::get('home_surcharge_pct', config('clinic.home_surcharge_pct'))/100, 2) : '0.00'`.
- `total = bcadd(base, surcharge, 2)`. كل القيم نصوص decimal، حساب bcmath (لا float).

### 3.3 `BookingService`
`book(BookingData $data): Appointment` ضمن `DB::transaction` مع قفل تشاؤمي (`lockForUpdate`) على مواعيد الطبيب المتداخلة (يمنع الحجز المزدوج للفترة — TOCTOU):
- تحقّق: الخدمة مرتبطة بالطبيب (pivot)؛ إن `Home`: `service.home_service_enabled` صحيح ومنطقة تغطية مفعّلة موجودة؛ الفترة ما زالت ضمن `AvailabilityService::slotsFor` (إعادة فحص داخل المعاملة بعد القفل).
- أنشئ `Appointment` (status=`Requested`، start/end، `price_at_booking`/`home_surcharge_amount` من `PricingService`، `created_by_role` من الفاعل، `service_id`/`doctor_profile_id`/`customer_id`). إن `Home` أنشئ `ServiceAddress`.
- القنوات بنفس الخدمة: الذاتي (الفاعل=العميل المصادَق، created_by_role=customer)؛ النيابة (الفاعل=طاقم؛ customer=منتقى؛ «انتقِ أو أنشئ سريعًا» يعيد استخدام `AuthService::registerCustomer` من P0؛ created_by_role=receptionist|manager).
- فشل التحقّق/التعارض ⇒ استثناء مجالي (`SlotUnavailableException` إلخ) — يُترجَم لرسالة عربية + HTTP مناسب (409 للتعارض)، لا تسريب داخلي.

### 3.4 `AppointmentTransitionService`
آلة حالات تستخدم `AppointmentStatus::canTransitionTo()`:
- العميل: `Requested|Confirmed → Cancelled` (سبب إلزامي)؛ `Requested|Confirmed → Rescheduled` (وقت جديد ⇒ تحقّق توفّر عبر AvailabilityService + إنشاء **موعد جديد بحالة `Requested`** يحمل `rescheduled_from_id`=القديم ويُعاد اقتباس سعره، والقديم يُنقل إلى `Rescheduled` (منتهٍ)). إعادة الجدولة عملية ذرّية ضمن `DB::transaction`.
- الطاقم: `Requested → Confirmed|Rejected`؛ `Confirmed → Completed|NoShow`؛ أي حالة غير منتهية → `Cancelled`.
- انتقال غير مسموح ⇒ استثناء مجالي (لا 500 مكشوف). يُسجَّل الفاعل/الزمن في أعمدة الموعد (سجل تدقيق كامل مؤجَّل لـ P3+ حسب ADR-002).
- صلاحيات: Policies — العميل لا يتصرّف إلا على مواعيده؛ الطاقم حسب الدور (R3 إنفاذ خادمي).

### 3.5 معالج الحجز (Vue مشترك)
`resources/js/Components/booking/BookingWizard.vue` فوق طبقة foundation P0 (PageStates/FormGroup/Modal، توكنات، RTL منطقي). 3 خطوات (مطابقة لتجربة `clinic`):
1. وضع التوصيل: `center` | `home`. إن `home`: اختيار `HomeServiceCoverageArea` (المفعّلة) + `address_text` + `location_note`.
2. اختيار الطبيب (is_bookable) ← ثم الخدمة (مفلترة بالـ pivot؛ إن home: الخدمات `home_service_enabled` فقط).
3. تقويم → فترات (تُجلب من `GET …/availability?doctor&service&date` ترجع JSON من `AvailabilityService`) + تذييل يعرض `PricingService::quote` (base/surcharge/total).
مكوّن واحد يُستخدم في البوابة (ذاتي) ولوحة التحكم (نيابة، يُضاف منتقي/إنشاء عميل قبل الخطوة 1). تأكيد → POST → Controller رفيع → `BookingService`.

---

## 4. الأسطح والصفحات

داخل `AdminShell`/`ClientShell` (P0)، مكوّنات foundation، RTL، توكنات. مسارات داخل `routes/admin.php`/`routes/portal.php` (P0) + Policies حسب الدور (R3 خادمي).

| السطح | الصفحات |
|---|---|
| **لوحة التحكم** (`admin.*`) | فئات الخدمات (قائمة/إنشاء/تعديل/تعطيل) · الخدمات (CRUD + إسناد أطباء + priceOverride) · الأطباء (CRUD + `DoctorSchedule` نوافذ صباح/مساء لكل يوم + `ScheduleException`) · مناطق التغطية (CRUD) · إعداد نسبة الرسم المنزلي · حجز نيابة (BookingWizard + منتقي/إنشاء عميل) · جدول المواعيد (`DataTable` + فلاتر حالة/طبيب/تاريخ + إجراءات: تأكيد/رفض/إكمال/تخلّف/إلغاء) |
| **بوابة العميل** (`portal.*`) | تصفّح الخدمات (فئات + بطاقات) · معالج الحجز الذاتي · مواعيدي (قائمة بالحالات + إلغاء + إعادة جدولة) |

**تدفّق البيانات:** صفحة Inertia → Controller رفيع → خدمة Domain → نموذج → إعادة توجيه/JSON. التوفّر عبر مسار `GET` يرجع فترات JSON. الأسعار من `PricingService` (لا حساب في الواجهة — قيمة معروضة فقط، يُعاد التحقّق خادميًا عند الحجز).

---

## 5. معالجة الأخطاء

- الحالات الأربع (تحميل/فارغ/خطأ/نجاح) عبر `PageStates` على كل صفحة بيانات (R16).
- أخطاء التحقّق تُسترجَع بقيمها (تعبئة ذكية، رسائل عربية).
- استثناءات المجال (توفّر/انتقال/تعارض) تُترجَم لرسائل عربية واضحة + HTTP مناسب؛ تعارض الحجز المزدوج (القفل) ⇒ 409 «الفترة لم تعد متاحة، اختر فترة أخرى». لا `$e->getMessage()` داخلي مكشوف.
- صفحات خطأ Inertia الموحّدة من P0 (403/404/409/500/503).

## 6. الاختبار (Pest + Vitest؛ عتبة DoD ≥60%)

- **وحدة:** `AvailabilityService` (صباح/مساء/كلاهما، استثناء closed/custom_hours، تداخل حجز، حدّ نهاية النافذة، استبعاد الماضي)؛ `PricingService` (price_override، رسم منزلي من Setting، تقريب bcmath)؛ `AppointmentStatus::canTransitionTo` (مصفوفة الانتقالات المسموح/الممنوع)؛ `SettingService` (قراءة/سقوط إلى config).
- **ميزة:** حجز ذاتي center؛ حجز ذاتي home (يُنشئ Appointment+ServiceAddress، السعر = base + نسبة)؛ حجز نيابة من الطاقم (created_by_role، عميل منتقى/منشأ سريعًا)؛ منع الحجز المزدوج لنفس الفترة (سباق متزامن)؛ انتقالات الدورة لكل دور (سماح/رفض)؛ عزل الصلاحيات (عميل لا يلغي موعد غيره؛ الطاقم↛البوابة والعكس — يمتدّ من P0)؛ فلترة الخدمات بالطبيب.
- **مكوّن (Vitest):** `BookingWizard` خطواته الثلاث + حالة «لا فترات».

## 7. حدود P1 (YAGNI — مؤجَّل لمرحلته، لا ملغى)

ما يلي **لا يُنفَّذ في P1** لكنه **مجدوَل لمرحلة لاحقة محدّدة** حسب الروادماب (P0 spec §2) — ليس إلغاءً بل ترتيب أولويات؛ كل بند يُبنى لاحقًا بدورة spec→plan→تنفيذ خاصة به فوق ما تنتجه P1:

| المؤجَّل | المرحلة |
|---|---|
| كيان `Payment`/`Receipt`، الفوترة، رفع/مراجعة إيصال التحويل البنكي | **P2** |
| السجل الطبي (تشخيص/علاج)، الوصفات، عرض السجل للعميل | **P3** |
| الولاء (نقاط/استبدال)، خطط العضوية + استهلاك جلسات، العروض/الخصومات/الكوبونات | **P4** |
| الإشعارات والتذكيرات (SMS/بريد/واتساب)، واجهة إدخال تقييم الأطباء (`rating_average` عرض فقط في P1)، صفحات الدعم، نصائح الجمال، تلميع لوحة العميل | **P5** |

في P1: الموعد ينتهي عند `requested/confirmed` بسعر محسوب فقط. هذه الحدود تحافظ على شريحة P1 رفيعة ومختبَرة دون إعادة عمل، وكل مرحلة تالية ترث أساس P1 (الكتالوج/الأطباء/الجداول/المواعيد) وتبني عليه.

## 8. تكامل الحوكمة

كل مهمة تُنفَّذ عبر دورة subagent (منفّذ + مراجعة مطابقة + مراجعة جودة + إصلاح حتى الاعتماد) كما في P0. كل PR يمرّ DoD (Gate Q + بوابات المجال/الـUI المولّدة). قواعد ملزِمة: R7 (خدمات Domain، لا منطق في Controller/Vue)، R12 (نسبة الرسم config-driven عبر Setting)، R16 (4 حالات)، R20 (RTL منطقي — فحص CI على الكود المؤلَّف؛ `Components/ui/` المورَّد مستثنى)، R6 (DOMAIN-MODEL.md + ARCHITECTURE.md يُحدَّثان في نفس change set)، نقود `decimal`/bcmath (فحص CI float)، CHANGELOG مدخل لكل PR، ADR لأي قرار معماري جديد. ADR-002 يبقى ساريًا — **لا بيانات مرضى حقيقية في الإنتاج** قبل تجاوزه (تدقيق + تشفير). بنية الاختبار: SQLite محليًا للسرعة، Postgres في CI هو السلطة (كـ P0).

## 9. ملاحظة بيئية

`jannahclinic` مستودع Git مستقل (master، موسوم `p0-foundation`). وثيقتا spec/plan في `docs/superpowers/`. التنفيذ على Postgres محلي (`postgres`/`123123`، psql في `C:\Program Files\PostgreSQL\18\bin`). الـ commit جزء من هذه المرحلة. التقنية: Laravel 13 · Inertia · Vue 3 · Tailwind v4 · shadcn-vue · Postgres · Pest/Pint/Larastan (كما بُنيت في P0).
