<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericNotification extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public array $payload;

    public function __construct(string $subjectLine, array $payload = [])
    {
        $this->subjectLine = $subjectLine;
        $this->payload = $payload;
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('mail.generic_notification')
            ->with('payload', $this->payload);
    }
}
