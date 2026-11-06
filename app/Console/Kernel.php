<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\PersonGeneratorCommand::class,
        Commands\RollBackImportPerson::class,
        Commands\AutoImportPerson::class,
        Commands\NotifyCheckDomain::class,
        Commands\NotifyCheckOutLinkOne::class,
        Commands\NotifyCheckOutLinkTwo::class,
        Commands\NotifyCheckOutLinkThree::class,
        Commands\NotifyCheckOutLinkFour::class,
        Commands\NotifyCheckOutLinkFive::class
    ];
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('notify:domain')->everyFiveMinutes();
        $schedule->command('notify:outlinkone')->everyFiveMinutes();
        $schedule->command('notify:outlinktwo')->everyFiveMinutes();
        $schedule->command('notify:outlinkthree')->everyFiveMinutes();
        $schedule->command('notify:outlinkfour')->everyFiveMinutes();
        $schedule->command('notify:outlinkfive')->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
