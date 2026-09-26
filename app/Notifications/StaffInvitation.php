<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitation extends Notification
{
    public function __construct(public string $businessName, protected string $token, public string $expiresAt) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are invited to '.$this->businessName.' on DukaanPOS')
            ->greeting('Join your team')
            ->line('You have been invited to join '.$this->businessName.'. Sign in or create an account with this email address and verify it to accept.')
            ->action('Review invitation', route('invitations.show', ['token' => $this->token]))
            ->line('This invitation expires on '.$this->expiresAt.'. If you were not expecting this invitation, you can ignore this email.');
    }
}
