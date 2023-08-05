<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 2:14 PM
 */

namespace App\Models\Tool;

use App\Models\BaseModel;

/**
 * 广告
 */
class Adv extends BaseModel
{
    protected $table = 'adv';
    protected $guarded = ['id'];
    
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    //跳转连接类型
    const TARGET_TYPE_URL = 1;
    const TARGET_TYPE_ARTICLE = 2;
    const TARGET_TYPE_THEME = 3;
    const TARGET_TYPE_GOODS = 4;
    const TARGET_TYPE_MINI_PROGRAM_PATH = 5;
    const TARGET_TYPE_OTHER_MINI_PROGRAM_PATH = 6;
    const TARGET_TYPE_DESC = [
        self::TARGET_TYPE_URL => '链接',
        self::TARGET_TYPE_ARTICLE => '文章',
        self::TARGET_TYPE_THEME => '专题',
        self::TARGET_TYPE_GOODS => '商品',
        self::TARGET_TYPE_MINI_PROGRAM_PATH => '小程序地址',
        self::TARGET_TYPE_OTHER_MINI_PROGRAM_PATH => '外部小程序地址',
    ];

    /**
     * 获取广告
     * @param int $code
     * @return array
     */
    public static function getAdv(int $code)
    {
        $return = [];
        $group_where = [
            ['code', $code],
            ['status', AdvGroup::STATUS_ON]
        ];
        $group_id = AdvGroup::where($group_where)->value('id');
        if (!$group_id) {
            return $return;
        }
        $adv_where = [
            ['group_id', $group_id],
            ['status', Adv::STATUS_ON],
            ['start_at', '<=', get_date()],
            ['end_at', '>=', get_date()]
        ];
        $res_list = Adv::select('title', 'image', 'target_type', 'target_value', 'app_id')
            ->where($adv_where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            return $return;
        }
        return $res_list->toArray();
    }

}
