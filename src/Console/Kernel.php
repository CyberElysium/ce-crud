<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use App\Console\Commands\SendPaymentIntent;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
//        Commands\SendPaymentIntent::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        $schedule->call(function () {
            // call procedure
            DB::select("CALL GenerateInvoices()");
        })
        ->days([0, 1, 2, 3, 4, 5, 6])
        ->at('1:00');

        $schedule->command('paymentintents:send')
                ->days([0, 1, 2, 3, 4, 5, 6])
                ->at('4:00')
                ->withoutOverlapping();
        
        $schedule->command('checkrecharge:status')
                ->everyMinute()
                ->withoutOverlapping();

        $schedule->command('checkstatus:cellpay')
                ->everyMinute()
                ->withoutOverlapping();

        $schedule->command('database:backup')
                    ->daily()
                    ->withoutOverlapping();

        $schedule->command('update:token')
                    ->everyFourHours()
                    ->withoutOverlapping();

        // $schedule->command('invoice:send')
        //             ->everyMinute()
        //             ->withoutOverlapping();

        $schedule->command('invoice:send')
                ->days([0, 1, 2, 3, 4, 5, 6])
                ->at('5:00')
                ->withoutOverlapping();


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
