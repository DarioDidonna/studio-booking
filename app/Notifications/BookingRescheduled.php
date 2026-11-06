<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\CalendarInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRescheduled extends Notification implements ShouldQueue
{
    use Queueable;

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
     * Get the mail representation of the notification. The attached event keeps
     * the booking's UID, so calendars move the appointment instead of copying it.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['service', 'operator']);
        $calendar = app(CalendarInvite::class);

        return (new MailMessage)
            ->subject(__('Booking moved: :service', ['service' => $this->booking->service->name]))
            ->greeting(__('Your booking has been moved.'))
            ->line(__(':service with :operator', [
                'service' => $this->booking->service->name,
                'operator' => $this->booking->operator->name,
            ]))
            ->line(__('New time: :date', ['date' => $this->booking->localStartsAt()->format('d/m/Y H:i')]))
            ->action(__('View your bookings'), route('dashboard'))
            ->attachData($calendar->forBooking($this->booking), $calendar->filename($this->booking), ['mime' => 'text/calendar; charset=utf-8']);
    }
}
