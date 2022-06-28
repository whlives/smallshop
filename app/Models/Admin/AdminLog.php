<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:08 PM
 */

namespace App\Models\Admin;

use App\Models\BaseModel;

/**
 * 后台用户操作记录
 */
class AdminLog extends BaseModel
{
    //保存方式
    const LOG_TYPE_MYSQL = 1;
    const LOG_TYPE_FILE = 2;
    const LOG_TYPE_DESC = [
        self::LOG_TYPE_MYSQL => 'mysql数据库',
        self::LOG_TYPE_FILE => '文件',
    ];

    protected $table = 'admin_log';
    protected $guarded = ['id'];
}
