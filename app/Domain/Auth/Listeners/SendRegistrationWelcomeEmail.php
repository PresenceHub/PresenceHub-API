<?php

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Events\UserRegistered;
use App\Mail\RegistrationWelcomeMail;
use Illuminate\Support\Facades\Mail;

class SendRegistrationWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user)->queue(new RegistrationWelcomeMail(
            $event->user,
            (string) config('mail.from.address'),
            (string) config('mail.from.name'),
        ));
    }
}
