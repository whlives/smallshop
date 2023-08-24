<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/2
 * Time: 3:23 PM
 */

namespace App\Models\Seller;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 商家
 */
class Seller extends BaseModel
{
    use SoftDeletes;

    protected $table = 'seller';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'deleted_at'];
    protected $dates = ['deleted_at'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_PENDING = 2;
    const STATUS_REFUSED = 3;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
        self::STATUS_PENDING => '待审',
        self::STATUS_REFUSED => '拒绝'
    ];

    //开发票
    const INVOICE_OFF = 0;
    const INVOICE_ON = 1;
    const INVOICE_DESC = [
        self::INVOICE_OFF => '否',
        self::INVOICE_ON => '是',
    ];

    /**
     * 获取商家资料
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function profile()
    {
        return $this->hasOne('App\Models\Seller\SellerProfile');
    }

    /**
     * 保存数据
     * @param array $seller_data 主表数据
     * @param array $profile_data 附属表数据
     * @param int $id
     * @return bool|mixed
     */
    public static function saveData(array $seller_data, array $profile_data, int $id = 0)
    {
        if (!$seller_data) return false;
        try {
            DB::transaction(function () use ($id, $seller_data, $profile_data) {
                if (isset($seller_data['password'])) {
                    $seller_data['password'] = Hash::make($seller_data['password']);
                }
                if ($id) {
                    self::query()->where('id', $id)->update($seller_data);
                    SellerProfile::query()->where('seller_id', $id)->update($profile_data);
                } else {
                    $result = self::query()->create($seller_data);
                    $seller_id = $result->id;
                    $profile_data['seller_id'] = $seller_id;
                    SellerProfile::query()->create($profile_data);
                }
            });
            $res = true;
        } catch (\Exception $e) {
            $res = false;
        }
        return $res;
    }
}
