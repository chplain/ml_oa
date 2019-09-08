<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyType extends Model
{
    protected $table = 'daily_types';

    protected $fillable = ['daily_setting_id', 'daily_type_name', 'if_relation', 'status'];

}
