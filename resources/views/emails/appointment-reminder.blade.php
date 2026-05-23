@php
    /**
     * @var \App\Models\Appointment $appointment
     * @var \App\Enums\ReminderKind $kind
     * @var string $recipientName
     */
    use App\Enums\DeliveryMode;
    use App\Enums\PaymentStatus;
    use App\Enums\ReminderKind;

    $deliveryMode = $appointment->delivery_mode;
    $payment = $appointment->payment ?? null;
    $isUnpaid = $payment !== null && $payment->status === PaymentStatus::Pending;
    $startAr = $appointment->start_at->isoFormat('dddd D MMMM YYYY');
    $timeStr = $appointment->start_at->format('H:i');
    $heading = $kind === ReminderKind::Before24h
        ? 'تذكير بموعدك غدًا'
        : 'موعدك بعد ساعتين';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f7fa;padding:24px 0;">
  <tr>
    <td align="center">
      <table width="560" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.04);">
        <tr>
          <td style="background:#0d9488;padding:20px 24px;text-align:center;color:#ffffff;">
            <h1 style="margin:0;font-size:20px;font-weight:800;">عيادة جنّة</h1>
          </td>
        </tr>
        <tr>
          <td style="padding:24px;">
            <h2 style="margin:0 0 8px 0;font-size:18px;color:#0d9488;font-weight:700;">{{ $heading }}</h2>
            <p style="margin:0 0 16px 0;font-size:14px;line-height:1.6;">
              مرحبًا {{ $recipientName }}، نُذكّرك بموعدك القادم:
            </p>

            @php
                $serviceNames = $appointment->services->pluck('name')->implode(' + ');
                if ($serviceNames === '') {
                    $serviceNames = $appointment->service?->name ?? '—';
                }
                $serviceLabel = $appointment->services->count() > 1 ? 'الخدمات' : 'الخدمة';
            @endphp
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;font-size:14px;">
              <tr>
                <td style="padding:6px 0;color:#6b7280;width:120px;">{{ $serviceLabel }}</td>
                <td style="padding:6px 0;font-weight:600;">{{ $serviceNames }}</td>
              </tr>
              <tr>
                <td style="padding:6px 0;color:#6b7280;">الطبيب</td>
                <td style="padding:6px 0;font-weight:600;">{{ $appointment->doctor->user->name }}</td>
              </tr>
              <tr>
                <td style="padding:6px 0;color:#6b7280;">التاريخ</td>
                <td style="padding:6px 0;font-weight:600;">{{ $startAr }}</td>
              </tr>
              <tr>
                <td style="padding:6px 0;color:#6b7280;">الوقت</td>
                <td style="padding:6px 0;font-weight:600;" dir="ltr">{{ $timeStr }}</td>
              </tr>
              <tr>
                <td style="padding:6px 0;color:#6b7280;">طريقة التقديم</td>
                <td style="padding:6px 0;font-weight:600;">
                  @if($deliveryMode === DeliveryMode::Center)
                    في المركز
                  @elseif($deliveryMode === DeliveryMode::Home)
                    زيارة منزليّة
                  @else
                    أونلاين (واتساب)
                  @endif
                </td>
              </tr>
              @if($deliveryMode === DeliveryMode::Home && $appointment->serviceAddress)
              <tr>
                <td style="padding:6px 0;color:#6b7280;vertical-align:top;">العنوان</td>
                <td style="padding:6px 0;font-weight:600;">{{ $appointment->serviceAddress->address_text }}</td>
              </tr>
              @endif
              @if($deliveryMode === DeliveryMode::Online && $appointment->whatsapp_phone)
              <tr>
                <td style="padding:6px 0;color:#6b7280;">رقم واتساب</td>
                <td style="padding:6px 0;font-weight:600;" dir="ltr">{{ $appointment->whatsapp_phone }}</td>
              </tr>
              @endif
            </table>

            @if($isUnpaid)
              <div style="background:#fef3c7;border-right:4px solid #f59e0b;padding:12px 16px;border-radius:8px;margin:0 0 16px 0;font-size:13px;color:#92400e;">
                <strong>تذكير:</strong> الموعد لم يُسدَّد بعد. يُمكنك إتمام الدفع من بوّابة العملاء.
              </div>
            @endif

            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:16px auto 0 auto;">
              <tr>
                <td style="background:#0d9488;border-radius:8px;">
                  <a href="{{ url('/portal/appointments') }}" style="display:inline-block;padding:10px 24px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">
                    عرض الموعد
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;font-size:12px;color:#9ca3af;">
            إن لم تكن صاحب هذا الموعد، تجاهل هذه الرسالة.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
