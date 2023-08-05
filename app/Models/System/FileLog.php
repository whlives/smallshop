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
 * 文件记录
 */
class FileLog extends BaseModel
{
    protected $table = 'file_log';
    protected $guarded = ['id'];
    
    //状态
    const TYPE_FILE = 0;
    const TYPE_ALIYUN = 1;
    const TYPE_DESC = [
        self::TYPE_FILE => '本地',
        self::TYPE_ALIYUN => '阿里云',
    ];

}
