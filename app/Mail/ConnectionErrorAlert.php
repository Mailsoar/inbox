<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConnectionErrorAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $alertData;

    public function __construct($alertData)
    {
        $this->alertData = $alertData;
    }

    public function build()
    {
        return $this->subject('🚨 MailSoar: Email Connection Failures Detected')
                    ->view('emails.connection-error-alert')
                    ->with([
                        'failedAccounts' => $this->alertData['failedAccounts'],
                        'checkedAt' => $this->alertData['checkedAt'],
                        'totalFailed' => $this->alertData['totalFailed']
                    ]);
    }
}