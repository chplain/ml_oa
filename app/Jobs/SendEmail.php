<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Mail\Mailer;
class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $inputs;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    // public function __construct(User $user)
    public function __construct($inputs)
    {
        //
        // $this->user = $user;
        $this->inputs = $inputs;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Mailer $mailer)
    {
        //$job_id = $this->job->getJobId(); // 当前队列的id值   可做存储
        //Log::error('我是来自队列,发送了一个邮件', ['job_id' => $job_id, 'id' => $this->user->id, 'name' => $this->user->username, 'attempts' => $this->attempts()]);
        $inputs = $this->inputs;
        //只发送文字消息
        $mailer->raw('这是一封来自redis队列测试邮件', function($message) use ($inputs){
            $message->to($inputs['email'])->subject('测试邮件');
        });
        //使用模板
        // $mailer->send('emails.reminder',['user'=>$user],function($message) use ($user){
        //     $message->to('ml_725@163.com')->subject('测试邮件');
        // });
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        // 发送失败通知, etc...
        if ($this->attempts() > 3) {
            $this->delete();   // 如果超过一定尝试数，则删除该队列
        }
    }
}
