<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\ReminderKind;
use App\Models\Appointment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification
{
    public function __construct(
        private readonly Appointment $appointment,
        private readonly ReminderKind $kind,
    ) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->kind === ReminderKind::Before24h
            ? 'تذكير: موعدك في عيادة جنّة غدًا'
            : 'موعدك بعد ساعتين — عيادة جنّة';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.appointment-reminder', [
                'appointment' => $this->appointment,
                'kind' => $this->kind,
                'recipientName' => $this->appointment->customer->name,
            ]);
    }

    /** @return array<string,mixed> */
    public function toDatabase(object $notifiable): array
    {
        $when = $this->appointment->start_at->isoFormat('D MMM HH:mm');
        $body = $this->kind === ReminderKind::Before24h
            ? "موعدك غدًا الساعة {$this->appointment->start_at->format('H:i')}."
            : "موعدك بعد ساعتين — {$when}.";

        return [
            'category' => NotificationCategory::Appointment->value,
            'title' => $this->kind === ReminderKind::Before24h
                ? 'تذكير: موعدك غدًا'
                : 'موعدك بعد ساعتين',
            'body' => $body,
            'action_url' => '/portal/appointments',
            'subject_type' => Appointment::class,
            'subject_id' => $this->appointment->id,
        ];
    }
}
