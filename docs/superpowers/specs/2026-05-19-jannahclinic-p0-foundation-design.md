# تصميم: jannahclinic — المرحلة P0 (الأساس)

> **الحالة:** DRAFT — بانتظار مراجعة المستخدم
> **التاريخ:** 2026-05-19
> **الموضوع:** نظام عيادة «جنّة» — غرينفيلد على Laravel+Inertia+Vue+shadcn-vue+Postgres، مُقلَع بحزمة المنهجية، بهوية تصميم مشتقّة من building.app. هذه الوثيقة تغطّي **P0 (الأساس)** كأول مشروع فرعي ضمن خريطة مراحل أوسع.
> **مرجع متطلبات صفحة العميل:** مجلد `C:\~projects\clinic` (تطبيق «عيادة جنة» الحالي، Next.js — يُستخدم كمرجع نطاق مميزات لا كمصدر كود).
> **مرجع التصميم البصري:** `C:\~projects\building.app-main\building.app-main\docs` (Design System V4 / Visual DNA / ADR-012 / docs/ui/*).
> **حزمة الحوكمة:** `C:\~projects\methodology-kit` v1.0.1.

---

## 1. الهدف ومعيار النجاح (المنتج ككل)

**الهدف:** بناء نظام عيادة «جنّة» — عيادة واحدة متعددة التخصصات (single-tenant) تقدّم خدمات مسبقة التسعير (حجامة، تدليك، عناية بالبشرة…) — بسطحين: **لوحة تحكم** للطاقم/الإدارة و**بوابة عميل** تُعيد إنتاج كامل مميزات صفحة العميل في مجلد `clinic`، محكومًا بحزمة المنهجية وبهوية building.app البصرية مُعاد التعبير عنها أصلًا في Vue/shadcn-vue.

**القرارات المعتمدة من العصف الذهني:**
- غرينفيلد، مجلد `C:\~projects\jannahclinic`.
- المجال: عيادة واحدة متعددة التخصصات، single-tenant، أدوار: `manager` / `doctor` / `receptionist` / `customer`.
- التقنية: Laravel 13 · PHP 8.4 · Postgres · Inertia.js · **Vue 3** · Tailwind · **shadcn-vue** (reka-ui/Radix-Vue).
- اللغة: عربي فقط RTL (لا i18n/تعادل لغوي).
- الأمان: أساسي (مصادقة + أدوار) — قرار MVP واعٍ موثّق كـ ADR مع بند إعادة نظر.
- الدفع: إرفاق وصل تحويل بنكي + تأكيد الطاقم (بلا بوابة دفع).
- الحجز: ذاتي أونلاين + عبر الاستقبال؛ يشمل زيارة منزلية (+30% رسم).
- التصميم: مشتقّ من building.app DNA، مُعاد التعبير في Vue/shadcn-vue (لا نسخ Blade/`bl-*`).
- المقاربة المعمارية: **A** — الأساس أولًا ثم شرائح رأسية (B/C مرفوضتان: خطر تشتّت بصري + اصطدام بقواعد الحزمة).

**معيار نجاح P0:** مستودع `jannahclinic` محكوم بحزمة المنهجية (حوكمة + ADR-001 + Definition of Done موصولة)، يعمل محليًا، فيه نظام تصميم مشتقّ من building.app، مصادقة بإيميل/جوال + 4 أدوار، وسطحان (لوحة تحكم + بوابة عميل) كهيكلين فارغين بالحالات الأربع — أساسٌ تَرِثه كل مرحلة لاحقة دون إعادة عمل.

---

## 2. تفكيك المنتج إلى مراحل (خريطة الطريق)

النظام أكبر من spec/خطة واحدة، فيُفكَّك إلى مشاريع فرعية متتابعة، كلٌّ بدورة spec → plan → بناء. كل مرحلة تشمل جانبها في لوحة التحكم وجانبها في بوابة العميل.

| المرحلة | المحتوى | الحالة |
|---|---|---|
| **P0 — الأساس** | إقلاع الحزمة · هيكل التطبيق · نظام التصميم · مصادقة/أدوار · سطحان فارغان · بوابة جودة | **هذه الوثيقة** |
| P1 — الخدمات والحجز | كتالوج خدمات مسبق التسعير + فئات · أطباء + جداول/استثناءات · معالج حجز (مركز/منزلي + رسم +30% + مناطق تغطية) · المواعيد (حالات/إلغاء/إعادة جدولة) | لاحقًا |
| P2 — المدفوعات | فاتورة تلقائية awaiting_receipt · رفع وصل تحويل · مراجعة الطاقم (paid/under_review/rejected) · معلومات بنك من الإعدادات | لاحقًا |
| P3 — السجل الطبي | ملخص (حساسية/مزمن) · مدخلات زيارة (تشخيص/علاج) · وصفات · عرض للقراءة للعميل | لاحقًا |
| P4 — الولاء والعضويات | نقاط (كسب/استبدال 1500→جلسة) · خطط عضوية + شراء + تتبّع جلسات | لاحقًا |
| P5 — الإشعارات والبوابة | مركز إشعارات (فئات/فلاتر/قراءة) · لوحة منزلية للعميل · إعدادات · صفحات دعم · نصائح جمال | لاحقًا |

> **مرجع نطاق بوابة العميل (للمراحل P1–P5):** مجموعة مميزات `clinic` المستخرجة — مصادقة/ملف+صورة، معالج حجز 3 خطوات (وضع التوصيل → عنوان منزلي+منطقة تغطية → طبيب→خدمة→تقويم→فترات؛ حساب سعر +30% منزلي؛ استهلاك جلسة عضوية)، مواعيد (حالات/إلغاء/إعادة جدولة/رفع إيصال مضمّن)، مدفوعات+إيصالات، ولاء (1500→جلسة)، عضويات، إشعارات مصنّفة، سجل طبي للقراءة، لوحة منزلية (تحية/قادم/حجز سريع/فئات/طبيب مميّز/نصيحة)، إعدادات، دعم. هذه ليست نطاق P0 — مسجَّلة هنا كمرجع روادمابي فقط.

---

## 3. تصميم P0 — المعمارية والمكوّنات

### 3.1 المستودع وإقلاع حزمة المنهجية
- `C:\~projects\jannahclinic` (موجود + `git init`). **تُنسَخ** الحزمة إلى `jannahclinic/methodology-kit/` (نسخة مكتفية ذاتيًا تحملها المشروع، مطابقة لتوصية QUICK-START «copy it in») v1.0.1، ثم تشغيل `methodology-kit/00-BOOTSTRAP.md` (8 خطوات، idempotent).
- **إجابات مقابلة الاشتقاق** (محسومة من العصف الذهني — تُكتب في ADR-001):

```yaml
domain:
  handles_money: yes
  handles_pii: no            # قرار MVP "أساسي" — انظر §3.6 وADR-002
  multi_tenant: no
  immutable_records: no
  audit_required: no
  has_allocation_or_split: no
  has_payments: yes          # رفع وصل تحويل + تأكيد طاقم، بلا بوابة
  concurrent_financial_writes: no
stack:
  language: PHP
  framework: Laravel + Inertia
  database: relational       # Postgres
  test_runner: pest
  linter: pint
  static_analysis: larastan
ui:
  has_ui: yes
  server_rendered_templates: no   # Inertia/Vue — قاعدة XSS-Blade غير منطبقة
  i18n: no
  bidi_rtl: yes
  has_design_system: yes
  has_forms: yes
  produces_documents: no
  client_screen_state: yes
api:
  public_api: no
sends_external_comms: no
quality:
  coverage_threshold: 60
  compliance: none
  autodoc_targets: ["docs/ARCHITECTURE.md","docs/DOMAIN-MODEL.md"]
runtime_paths: ["app/**","resources/js/**","routes/**","tests/**"]
```

- **المخرج:** `docs/` فيه GOLDEN-RULES (طبقة عامة + قواعد مجال/تقنية مولّدة عبر مكتبة القواعد)، DEFINITION-OF-DONE، DOCS-AUTHORITY + CANONICAL-DECISION-REGISTRY، ADR-001 (تبنّي الحزمة)، onboarding (START-HERE + DOCUMENTATION-INDEX)، قالب PR + مثال CI. لا توكنات `{{...}}` متبقّية.

### 3.2 هيكل التطبيق (حدود واضحة لكل وحدة)
- Laravel 13 / PHP 8.4 / Postgres / Inertia / Vue 3 / Tailwind / shadcn-vue.
- منطق الأعمال في `app/Domain/{Module}/Services/` (قاعدة R7: لا منطق في Controllers/Views). في P0 لا توجد وحدات مجال بعد — تُؤسَّس البنية فقط.
- مسارات مفصولة: `routes/auth.php` (مصادقة)، `routes/admin.php` (لوحة التحكم)، `routes/portal.php` (العميل). كل ملف مسؤولية واحدة.
- `resources/js/` منظَّم: `Layouts/` (AdminShell, ClientShell)، `Components/ui/` (طبقة shadcn-vue الأساسية)، `Components/foundation/` (مكوّنات building.app المُعاد تعبيرها)، `Pages/Admin/*`, `Pages/Portal/*`, `Pages/Auth/*`.
- Vite + إعداد Inertia-Vue + SSR غير مطلوب في P0.

### 3.3 نظام التصميم (building.app DNA → Tailwind/shadcn-vue)
وحدة قائمة بذاتها، واجهتها = توكنات + مكوّنات foundational؛ تعتمد على Tailwind + shadcn-vue فقط.
- **التوكنات** كـ CSS variables + ثيم Tailwind، مشتقّة من تخليق building.app: الألوان (brand، accent، surface/text/border، success/warning/danger/info) **مُعاد تسميتها بدلالات عيادة** (لا «income/expense/مبنى»)، مع دور مالي محايد واحد للفواتير؛ مقاييس spacing(شبكة 4px)، radius(sm6/md8/lg12/xl16/full)، shadow(xs..2xl)، motion(fast100/normal200/slow300 + spring)، z-index(dropdown10/sticky20/shell30/overlay40/modal50/toast60).
- **الطباعة:** خط **Cairo** مستضاف ذاتيًا (woff2)، سلّم النوع (xs..3xl) وأوزانه كما في التخليق.
- **RTL-first:** `<html dir="rtl">`، خصائص منطقية فقط (`ps/pe/ms/me`, `text-start/end`, `inset-inline-*`)، أيقونات اتجاهية تنعكس. فحص CI: لا `pl-|pr-|ml-|mr-` في `resources/js/`.
- **طبقة foundational فوق shadcn-vue** (سلوك building.app، حدود واضحة، قابلة لإعادة الاستخدام والاختبار مستقلة):
  - `AdminShell` — شريط جانبي (256px/64px مطوي) + topbar (64px).
  - `ClientShell` — موبايل-أوّل + تنقّل سفلي (الرئيسية/الحجز/الإشعارات/الملف) مطابق لتجربة `clinic`.
  - `PageHeader` (عنوان + سياق + إجراء واحد + eyebrow اختياري).
  - `PageStates` — غلاف الحالات الأربع (تحميل/فارغ/خطأ/نجاح) — إلزامي R10.
  - `DataTable` — كثافة صفوف، فرز، ثبات رأس، أعمدة ثانوية تُخفى < md.
  - `FormGroup` / `FormSection` / `FormActions` — تباعد قياسي + تعبئة ذكية (R35) + ربط أخطاء aria.
  - `Modal` / `Drawer` / `ConfirmModal` — عبر Radix-Vue Portal (مكافئ teleport؛ يحقّق R34 بلا كسر stacking).
  - `Badge`، `StatCard`، `EmptyState` / `Skeleton` / `ErrorState`.
- قوالب الصفحات A–H موثّقة في `docs/ui/` (Dashboard / Workspace / List / Form-Aside / Detail / Settings / Catalog / Print).

### 3.4 المصادقة والأدوار
- **الأساس التقني للمصادقة:** Laravel Breeze بحزمة Inertia + Vue 3 (يوفّر سقالة المصادقة + Inertia/Vite الجاهزة)، ثم يُخصَّص: واجهته الافتراضية تُستبدَل بطبقة تصميم shadcn-vue، ويُعدَّل الدخول ليقبل **إيميل أو جوال** + كلمة مرور (جلسة). تسجيل عميل ينشئ `User` + `CustomerProfile` ودخول تلقائي. تعديل ملف + رفع صورة (تخزين محلي `storage/`).
- 4 أدوار على `User.role`: `manager` (إدارة كاملة) / `doctor` / `receptionist` / `customer`.
- إنفاذ خادمي (R3):
  - `routes/admin.php` ← middleware يتطلب `role ∈ {manager,doctor,receptionist}`.
  - `routes/portal.php` ← middleware يتطلب `role = customer`.
  - عزل بيانات العميل: لا يرى إلا سجلّاته (يُفرَض في طبقة الخدمة/الاستعلام لاحقًا لكل وحدة؛ في P0 يُوضع الـ middleware والـ Policy الأساسية).

### 3.5 السطحان (هياكل فارغة في P0)
- **لوحة التحكم:** `AdminShell` + لوحة فارغة (placeholder KPIs) + عناصر تنقّل للوحدات القادمة معطّلة.
- **بوابة العميل:** `ClientShell` + رئيسية placeholder + تنقّل سفلي. لا منطق أعمال — قشرة + تنقّل + حالات أربع فقط.
- صفحات خطأ Inertia موحّدة (403/404/500) بهوية التصميم.

### 3.6 المخاطرة الموثّقة (شفافية)
`audit_required=no` و`handles_pii=no` في مقابلة الاشتقاق قراران لتبسيط MVP لنظام يحمل بيانات طبية فعلية. يُسجَّل **ADR-002: «Basic security posture for MVP»** في `docs/adr/` (حالة `ACTIVE`، مُدرَج في CANONICAL-DECISION-REGISTRY) مع بند صريح: *«يُعاد النظر — سجل تدقيق وصول/تعديل السجل الطبي + تشفير الحقول الحسّاسة عند التخزين — إلزاميًا قبل تشغيل بيانات مرضى حقيقية في الإنتاج.»* القرار متتبَّع عبر حوكمة الحزمة لا مدفون.

---

## 4. تدفّق البيانات (P0)
- الطلب → مسار (`auth/admin/portal`) → middleware دور → Controller رفيع → (في P0: لا خدمة مجال؛ يردّ Inertia صفحة) → مكوّن Vue داخل القشرة المناسبة → `PageStates` يحكم العرض.
- المصادقة: نموذج دخول/تسجيل → Controller → خدمة `Auth` في `app/Domain/Auth/Services` (إنشاء User+CustomerProfile، تحقّق إيميل/جوال) → جلسة → إعادة توجيه حسب الدور (طاقم→لوحة، عميل→بوابة).
- رفع الصورة الرمزية: نموذج → تحقّق نوع/حجم → `storage/app/public/avatars` → مرجع في `CustomerProfile`.

## 5. معالجة الأخطاء
- الحالات الأربع عبر `PageStates` على كل صفحة بيانات من اليوم الأول (R10).
- أخطاء التحقّق تُسترجَع بقيمها (R35) عبر `old()`/Inertia errors bag.
- صفحات 403/404/500 موحّدة. لا تسريب رسائل داخلية للمستخدم (R: أخطاء لا تكشف داخليًا).

## 6. الاختبار وبوابة الإكمال
- **Pest**: وحدة لخدمة `Auth`؛ ميزة لمسارات: دخول بإيميل، دخول بجوال، تسجيل عميل، حماية السطحين (طاقم↛بوابة العميل، عميل↛لوحة)، إعادة توجيه حسب الدور.
- اختبار مكوّن خفيف: `PageStates` يعرض الحالة الصحيحة؛ `DataTable` فارغ/مملوء.
- بوابة الجودة (Definition of Done من الحزمة) موصولة عبر قالب PR ومثال CI: `pint` + `larastan` + `pest` + فحص RTL grep + بناء Vite. حدّ التغطية 60% للملفّات المتغيّرة. لا دمج قبل الاجتياز.

## 7. غير المشمول في P0 (YAGNI صريح)
- لا خدمات/كتالوج، لا حجز/مواعيد، لا مدفوعات/إيصالات، لا سجل طبي، لا ولاء/عضويات، لا إشعارات، لا لوحة منزلية للعميل بمحتوى فعلي، لا صفحات دعم بمحتوى. كلها P1–P5.
- لا بوابة دفع، لا قنوات اتصال خارجية (SMS/واتساب)، لا i18n، لا SSR، لا تطبيق جوّال.
- لا تدقيق وصول مفصّل ولا تشفير حقلي (مؤجَّل صراحةً عبر ADR-002).

## 8. ملاحظة بيئية
`jannahclinic` مستودع Git خاص (تمّ `git init`). وثيقتا الـ spec/plan تعيشان في `docs/superpowers/` (مساحة أدوات العملية)، منفصلة عن `docs/` التي تملؤها حوكمة الحزمة عند الإقلاع. الـ commit للوثيقة جزء من هذه المرحلة.

ملاحظة: اعتُمد Laravel 13 (الأحدث المستقر في البيئة) بدل «Laravel 12» الأصلي بقرار المستخدم؛ لا فرق وظيفي للأساس.
