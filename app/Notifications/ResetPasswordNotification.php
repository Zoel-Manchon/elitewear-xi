<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Restablece tu contraseña · Elitewear XI')
            ->greeting('Restablecer contraseña')
            ->line('Hemos recibido una solicitud para cambiar la contraseña de tu cuenta.')
            ->action('Crear una nueva contraseña', $url)
            ->line('Este enlace caduca en '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.')
            ->line('Si no solicitaste el cambio, no tienes que hacer nada.')
            ->salutation('Elitewear XI');
    }
}
