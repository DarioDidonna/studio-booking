<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelled extends Notification implements ShouldQueue
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
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['service', 'operator']);

        return (new MailMessage)
            ->subject(__('Booking cancelled: :service', ['service' => $this->booking->service->name]))
            ->greeting(__('Your booking has been cancelled.'))
            ->line(__(':service with :operator on :date', [
                'service' => $this->booking->service->name,
                'operator' => $this->booking->operator->name,
                'date' => $this->booking->localStartsAt()->format('d/m/Y H:i'),
            ]))
            ->action(__('Book again'), route('dashboard'));
    }
}
