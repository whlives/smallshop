<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/1
 * Time: 4:30 PM
 */

namespace App\Models\Member;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 会员
 */
class Member extends BaseModel
{
    use SoftDeletes;

    protected $table = 'member';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'pay_password', 'deleted_at'];
    protected $dates = ['deleted_at'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    /**
     * 获取会员资料
     * @return HasOne
     */
    public function profile()
    {
        return $this->hasOne('App\Models\Member\MemberProfile');
    }

    /**
     * 获取会员组
     * @param int $group_id
     * @return string
     */
    public static function group(int $group_id)
    {
        $group_title = MemberGroup::query()->where('id', $group_id)->value('title');
        return $group_title ?: '';
    }

    /**
     * 保存数据
     * @param array $member_data 主表数据
     * @param array $profile_data 附属表数据
     * @param int $id
     * @return bool|mixed
     */
    public static function saveData(array $member_data, array $profile_data, int $id = 0)
    {
        if (!$member_data) return false;
        try {
            DB::transaction(function () use ($id, $member_data, $profile_data) {
                if (isset($member_data['password'])) {
                    $member_data['password'] = Hash::make($member_data['password']);
                }
                if ($id) {
                    self::query()->where('id', $id)->update($member_data);
                    MemberProfile::query()->where('member_id', $id)->update($profile_data);
                } else {
                    $result = self::query()->create($member_data);
                    $member_id = $result->id;
                    $profile_data['member_id'] = $member_id;
                    MemberProfile::query()->create($profile_data);
                }
            });
            $res = true;
        } catch (\Exception $e) {
            $res = false;
        }
        return $res;
    }

    /**
     * 获取推荐人信息
     * @param int $m_id
     * @return array
     */
    public static function getLevelParentId(int $m_id)
    {
        $level_one_m_id = 0;
        $level_two_m_id = Member::query()->where('id', $m_id)->value('parent_id');
        if ($level_two_m_id) {
            $level_one_m_id = Member::query()->where('id', $level_two_m_id)->value('parent_id');
        }
        return [(int)$level_one_m_id, (int)$level_two_m_id];
    }

    /**
     * 验证支付密码
     * @param int $m_id
     * @param string|null $pay_password
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function checkPayPassword(int $m_id, string|null $pay_password = '')
    {
        if (!$pay_password) {
            $pay_password = request()->post('pay_password');
        }
        if (!$pay_password) {
            api_error(__('api.pay_password_error'));
        }
        $member_data = Member::query()->find($m_id);
        if (empty($member_data['pay_password'])) {
            api_error(__('api.pay_password_notset'));
        }
        if (!Hash::check($pay_password, $member_data['pay_password'])) {
            api_error(__('api.pay_password_error'));
        }
    }
}
