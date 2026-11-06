<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\CalendarInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Queued only after the booking transaction commits, so a rolled-back
     * booking never sends a confirmation.
     */
    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification, with a calendar event attached.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['service', 'operator']);
        $calendar = app(CalendarInvite::class);

        return (new MailMessage)
            ->subject(__('Booking confirmed: :service', ['service' => $this->booking->service->name]))
            ->greeting(__('Your booking is confirmed.'))
            ->line(__(':service with :operator', [
                'service' => $this->booking->service->name,
                'operator' => $this->booking->operator->name,
            ]))
            ->line(__('When: :date', ['date' => $this->booking->localStartsAt()->format('d/m/Y H:i')]))
            ->line(__('You can cancel for free until :deadline.', [
                'deadline' => $this->booking->freeCancellationEndsAt()->setTimezone(config('app.business_timezone'))->format('d/m/Y H:i'),
            ]))
            ->action(__('View your bookings'), route('dashboard'))
            ->attachData($calendar->forBooking($this->booking), $calendar->filename($this->booking), ['mime' => 'text/calendar; charset=utf-8']);
    }
}
