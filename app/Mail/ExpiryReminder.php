<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExpiryReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $serviceName,
        public string $serviceType,
        public string $dueDate,
        public int $daysBefore,
        public string $price,
        public string $currency,
        public bool $isTest = false,
    ) {}

    public function build(): static
    {
        $subject = $this->isTest ? '[My Idlers] Test reminder' : "[My Idlers] Service due in {$this->daysBefore} ".($this->daysBefore === 1 ? 'day' : 'days');
        return $this->subject($subject)
            ->view('emails.expiry-reminder')
            ->text('emails.expiry-reminder-text');
    }
}
