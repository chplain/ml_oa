<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'title', 'message','operator_id'];

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id', 'id');
    }

    /*
     * 获取未读的提醒信息
     */
    public function unreadNotified($inputs = array())
    {
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $querys = Notification::where('user_id', auth()->id())
            ->orderBy('id', 'DESC');
        $records_filtered = $querys->count();
        if (!empty($inputs['my_unread_notified'])) {
            return $records_filtered;
        }
        $unread_notifieds = $querys->skip($start)
            ->take($length)
            ->get();
        return ['code' => 1, 'message' => '获取未读的提醒信息成功', 'data' => ['records_filtered' => $records_filtered, 'unread_notifieds' => $unread_notifieds]];
    }
}
