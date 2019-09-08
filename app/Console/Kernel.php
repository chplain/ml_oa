<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 同步V3系统数据存储到本机数据库
        //$schedule->command('transfer:v3-project-task-stat')->withoutOverlapping()->cron('55 1,5,9,13,18,23 * * *');
        // 调整前四天的统计数据
        //$schedule->command('transfer:adjust-project-stat')->withoutOverlapping()->cron('0 4 * * *');
        // 自动启用暂停项目投递状态； 每天自动生成项目反馈和链接反馈，统计上一天的cpc、cpd量
        //$schedule->command('command:create-project-link-daily')->withoutOverlapping()->dailyAt('01:00');
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
