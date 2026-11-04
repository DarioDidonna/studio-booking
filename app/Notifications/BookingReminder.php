<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

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
     * The queued job may run after the booking was cancelled; the queue
     * reloads the booking, so its status is current here.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->booking->status === BookingStatus::Confirmed;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['service', 'operator']);

        return (new MailMessage)
            ->subject(__('Reminder: :service tomorrow', ['service' => $this->booking->service->name]))
            ->greeting(__('See you soon!'))
            ->line(__(':service with :operator', [
                'service' => $this->booking->service->name,
                'operator' => $this->booking->operator->name,
            ]))
            ->line(__('When: :date', ['date' => $this->booking->localStartsAt()->format('d/m/Y H:i')]));
    }
}
