<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:08 PM
 */

namespace App\Models\Member;

use App\Models\BaseModel;
use App\Services\TokenService;

/**
 * 用户登录记录
 */
class MemberLoginLog extends BaseModel
{
    protected $table = 'member_login_log';
    protected $guarded = ['id'];
    
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '下线',
        self::STATUS_ON => '在线',
    ];

    /**
     * 锁定用户的时候清除用户登录状态
     * @param int|array $id
     * @return bool
     */
    public static function removeLoginStatus(int|array $id): bool
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

    /**
     * 踢出同一个平台的登录账号
     * @param int $m_id
     * @param string $platform
     * @return void
     */
    public static function removeLoginUser(int $m_id, string $platform)
    {
        $token_service = new TokenService();
        $login_log = self::select('id', 'token')->where(['m_id' => $m_id, 'platform' => $platform, 'status' => self::STATUS_ON])->get();
        if (!$login_log->isEmpty()) {
            $log_ids = [];
            foreach ($login_log as $value) {
                $token_service->delToken($value['token']);
                $log_ids[] = $value['id'];
            }
            if ($log_ids) {
                self::whereIn('id', $log_ids)->update(['status' => self::STATUS_OFF]);
            }
        }
    }
}
