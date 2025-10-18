<?php

// app/Mail/ResetCodeMail.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $code) {}

    public function build()
    {
        return $this->subject('Seu código para redefinir a senha')
            ->markdown('emails.reset_code', [
                'name' => $this->name,
                'code' => $this->code,
            ]);
    }
}
