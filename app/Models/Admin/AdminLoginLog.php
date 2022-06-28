<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:45 PM
 */

namespace App\Models\Admin;

use App\Models\BaseModel;
use App\Services\TokenService;

/**
 * 管理员登录记录
 */
class AdminLoginLog extends BaseModel
{
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '下线',
        self::STATUS_ON => '在线',
    ];

    protected $table = 'admin_login_log';
    protected $guarded = ['id'];

    /**
     * 锁定用户的时候清除用户登录状态
     * @param int|array $id
     * @return bool
     */
    static function removeLoginStatus(int|array $id): bool
    {
        $token_service = new TokenService();
        if (!is_array($id)) {
            $id = [$id];
        }
        $token_data = self::whereIn('m_id', $id)->pluck('token');
        if ($token_data) {
            foreach ($token_data as $value) {
                $token_service->delToken($value);
            }
            self::whereIn('m_id', $id)->update(['status' => self::STATUS_OFF]);
        }
        return true;
    }
}
