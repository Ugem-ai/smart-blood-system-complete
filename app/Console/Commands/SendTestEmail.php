<?php

namespace App\Console\Commands;

use App\Mail\GenericNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'notifications:send-test-email {--to=}';

    protected $description = 'Send a test email using the configured mail transport (Resend)';

    public function handle(): int
    {
        $to = $this->option('to') ?: env('MAIL_TEST_RECIPIENT', env('MAIL_FROM_ADDRESS'));

        if (! $to) {
            $this->error('No recipient specified; set MAIL_TEST_RECIPIENT or use --to');
            return 1;
        }

        $subject = 'SmartBlood Test Email';
        $payload = [
            'title' => 'SmartBlood Test Email',
            'message' => 'This is a test email to verify the configured mail transport.',
            'link' => env('APP_URL') ?: '',
        ];

        try {
            Mail::to($to)->send(new GenericNotification($subject, $payload));
            $this->info('Test email queued/sent to '.$to);
            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to send test email: '.$e->getMessage());
            return 1;
        }
    }
}
