<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 2:14 PM
 */

namespace App\Models\System;

use App\Models\BaseModel;

/**
 * 短信记录
 */
class SmsLog extends BaseModel
{

    protected $table = 'sms_log';
    protected $guarded = ['id'];
}