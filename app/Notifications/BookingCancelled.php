<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\CalendarInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $reason  Why the barbershop cancelled, shown to the customer.
     */
    public function __construct(public Booking $booking, public ?string $reason = null)
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
     * Get the mail representation of the notification. The attached event cancels
     * the one sent with the confirmation, so it leaves the customer's calendar.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['service', 'operator']);
        $calendar = app(CalendarInvite::class);

        $message = (new MailMessage)
            ->subject(__('Booking cancelled: :service', ['service' => $this->booking->service->name]))
            ->greeting(__('Your booking has been cancelled.'))
            ->line(__(':service with :operator on :date', [
                'service' => $this->booking->service->name,
                'operator' => $this->booking->operator->name,
                'date' => $this->booking->localStartsAt()->format('d/m/Y H:i'),
            ]));

        if ($this->reason !== null) {
            $message->line($this->reason)->line(__('We are sorry for the inconvenience: you can pick another time or another barber.'));
        }

        return $message
            ->action(__('Book again'), route('bookings.create'))
            ->attachData($calendar->forBooking($this->booking), $calendar->filename($this->booking), ['mime' => 'text/calendar; charset=utf-8']);
    }
}
