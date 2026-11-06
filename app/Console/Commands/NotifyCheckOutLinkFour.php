<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NotifyCheckOutLinkFour extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:outlinkfour';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify if domain get a issue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //check domain
        $listDomains = config('constants.domainOutlink4');
        foreach ( $listDomains as $domain ) {
            $wait = 10; // wait Timeout In Seconds
            $fp = @fsockopen($domain, 80, $errCode, $errStr, $wait);
            if (!$fp) {
                if ( $errCode == '10060' ) {
                    $text = "Ping $domain ==> Timeout over 10s";
                } else {
                    $text = "Ping $domain ==> ERROR: $errCode - $errStr";
                }
                Telegram::sendMessage([
                    'chat_id' => env('TELEGRAM_CHANNEL_ID', '-4061154988'),
                    'parse_mode' => 'HTML',
                    'text' => $text
                ]);

            }
        }
        $this->info('Ok!');
    }
}
