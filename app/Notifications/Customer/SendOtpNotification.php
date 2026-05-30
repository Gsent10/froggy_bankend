<?php

namespace App\Notifications\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp,
        public string $type  // 'email_verification' | 'password_reset'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->type === 'password_reset'
            ? 'Reset Your Password'
            : 'Verify Your Email Address';

        $intro = $this->type === 'password_reset'
            ? 'You requested a password reset. Use the OTP below to proceed.'
            : 'Thank you for registering. Use the OTP below to verify your email address.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->full_name . ',')
            ->line($intro)
            ->line('Your OTP is: **' . $this->otp . '**')
            ->line('This OTP expires in 20 minutes.')
            ->line('If you did not request this, please ignore this email.');
    }
}
