<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/3
 * Time: 4:46 PM
 */

namespace App\Models\Goods;

use App\Jobs\MiniProgramQrcode;
use App\Libs\Weixin\MiniProgram;
use App\Models\BaseModel;
use App\Models\Market\Coupons;
use App\Models\Market\PromoSeckill;
use App\Models\Seller\Seller;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * 优惠券
 */
class Goods extends BaseModel
{
    use SoftDeletes;

    protected $table = 'goods';
    protected $guarded = ['id'];
    protected $hidden = ['deleted_at'];
    protected $dates = ['deleted_at'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '待审核',
        self::STATUS_ON => '已审核',
    ];

    //上架状态
    const SHELVES_STATUS_OFF = 0;
    const SHELVES_STATUS_ON = 1;
    const SHELVES_STATUS_DESC = [
        self::SHELVES_STATUS_OFF => '下架',
        self::SHELVES_STATUS_ON => '上架',
    ];

    //是否推荐
    const REM_OFF = 0;
    const REM_ON = 1;
    const REM_DESC = [
        self::REM_OFF => '不推荐',
        self::REM_ON => '推荐',

    ];

    //商品类型
    const TYPE_GOODS = 1;
    const TYPE_COUPONS = 2;
    const TYPE_POINT = 3;
    const TYPE_TICKET = 4;
    const TYPE_PACKAGE = 5;
    const TYPE_DESC = [
        self::TYPE_GOODS => '普通',
        self::TYPE_COUPONS => '优惠券',
        self::TYPE_POINT => '积分',
        self::TYPE_TICKET => '电子券',
        self::TYPE_PACKAGE => '套餐包',
    ];

    //活动类型
    const PROMO_TYPE_DEFAULT = 1;
    const PROMO_TYPE_SECKILL = 2;
    const PROMO_TYPE_GROUP = 3;
    const PROMO_TYPE_DESC = [
        self::PROMO_TYPE_DEFAULT => '普通',
        self::PROMO_TYPE_SECKILL => '秒杀',
        self::PROMO_TYPE_GROUP => '拼团',
    ];

    /**
     * 获取详情
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function content()
    {
        return $this->hasOne('App\Models\Goods\GoodsContent');
    }

    /**
     * 获取计数信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function num()
    {
        return $this->hasOne('App\Models\Goods\GoodsNum');
    }

    /**
     * 获取商品图片
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function image()
    {
        return $this->hasMany('App\Models\Goods\GoodsImage');
    }

    /**
     * 获取商品属性
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attribute()
    {
        return $this->hasMany('App\Models\Goods\GoodsAttribute');
    }

    /**
     * 获取子商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function goodsSku()
    {
        return $this->hasMany('App\Models\Goods\GoodsSku');
    }

    /**
     * 获取商家分类
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sellerCategory()
    {
        return $this->hasMany('App\Models\Goods\GoodsSellerCategory');
    }

    /**
     * 获取按钮状态
     * @param array $goods
     * @return int[]
     */
    public static function button(array $goods)
    {
        $button = [
            'now' => 0,//立即购买
            'cart' => 0,//加入购物车
            'shelves' => 0,//已经下架
        ];
        if ($goods['shelves_status'] == Goods::SHELVES_STATUS_ON) {
            $button['now'] = 1;
            if ($goods['type'] == Goods::TYPE_GOODS && $goods['promo_type'] == Goods::PROMO_TYPE_DEFAULT) {
                $button['cart'] = 1;
            }
        } else {
            $button['shelves'] = 1;
        }
        return $button;
    }

    /**
     * 商品添加/编辑
     * @param $request
     * @param int $seller_id 商家
     * @return array|false|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|mixed|string|void|null
     */
    public static function formatGoods($request, int $seller_id = 0)
    {
        $id = (int)$request->input('id');
        //验证规则
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'type' => 'required|numeric',
            'category_id' => 'required|numeric',
            'delivery_id' => 'required|numeric',
            'brand_id' => 'required|numeric',
            'image' => 'required|array',
            'sku_code' => [
                'required',
                Rule::unique('goods')->ignore($id)
            ],
            'position' => 'required|numeric',
            'level_one_pct' => 'required|numeric|min:0|max:100',
            'level_two_pct' => 'required|numeric||min:0|max:100',
            'spec_market_price' => 'required|array',
            'spec_market_price.*' => 'numeric|min:0.01',
            'spec_sell_price' => 'required|array',
            'spec_sell_price.*' => 'numeric|min:0.01',
            'spec_stock' => 'required|array',
            'spec_stock[]' => 'numeric',
            'spec_sku_code' => 'required|array',
            'spec_weight' => 'required|array',
            'spec_weight[]' => 'numeric',
            'spec_min_buy' => 'required|array',
            'spec_min_buy[]' => 'numeric',
            'spec_max_buy' => 'required|array',
            'spec_max_buy[]' => 'numeric',

        ], [
            'title.required' => '标题不能为空',
            'type.required' => '类型不能为空',
            'type.numeric' => '类型只能是数字',
            'category_id.required' => '分类id不能为空',
            'category_id.numeric' => '分类id只能是数字',
            'delivery_id.required' => '运费模板不能为空',
            'delivery_id.numeric' => '运费模板只能是数字',
            'brand_id.required' => '排序不能为空',
            'brand_id.numeric' => '排序只能是数字',
            'image.required' => '图片不能为空',
            'image.array' => '图片不能为空',
            'sku_code.required' => '货号不能为空',
            'sku_code.unique' => '货号已经存在',
            'position.required' => '排序不能为空',
            'position.numeric' => '排序只能是数字',
            'level_one_pct.required' => '一级分成不能为空',
            'level_one_pct.numeric' => '一级分成只能是数字',
            'level_one_pct.min' => '一级分成不能小于0',
            'level_one_pct.max' => '一级分成不能大于100',
            'level_two_pct.required' => '二级分成不能为空',
            'level_two_pct.numeric' => '二级分成只能是数字',
            'level_two_pct.min' => '二级分成不能小于0',
            'level_two_pct.max' => '二级分成不能大于100',
            'spec_market_price.required' => '市场价不能为空',
            'spec_market_price.array' => '市场价参数错误',
            'spec_market_price.*.min' => '市场价价格必须大于0',
            'spec_sell_price.required' => '销售价不能为空',
            'spec_sell_price.array' => '销售价参数错误',
            'spec_sell_price.*.min' => '销售价价格必须大于0',
            'spec_stock.required' => '库存不能为空',
            'spec_stock.array' => '库存参数错误',
            'spec_stock[].numeric' => '库存只能是数字',
            'spec_sku_code.required' => '货号不能为空',
            'spec_sku_code.array' => '货号参数错误',
            'spec_weight.required' => '重量不能为空',
            'spec_weight.array' => '重量参数错误',
            'spec_weight[].numeric' => '重量只能是数字',
            'spec_min_buy.required' => '起订量不能为空',
            'spec_min_buy.array' => '起订量参数错误',
            'spec_min_buy[].numeric' => '起订量只能是数字',
            'spec_max_buy.required' => '最大购买数量不能为空',
            'spec_max_buy.array' => '最大购买数量参数错误',
            'spec_max_buy[].numeric' => '最大购买数量只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            return current($error);
        }
        $image = $request->input('image');
        $spec_sku_id = $request->input('spec_sku_id');
        $spec_market_price = $request->input('spec_market_price');
        $spec_sell_price = $request->input('spec_sell_price');
        $spec_stock = $request->input('spec_stock');
        $spec_sku_code = $request->input('spec_sku_code');
        $spec_weight = $request->input('spec_weight');
        $spec_min_buy = $request->input('spec_min_buy');
        $spec_max_buy = $request->input('spec_max_buy');
        //规格信息
        $spec_id = $request->input('spec_id');
        $spec_name = $request->input('spec_name');
        $spec_value = $request->input('spec_value');
        $spec_image = $request->input('spec_image');
        $spec_alias = $request->input('spec_alias');
        $content = remove_xss($request->input('content'));
        $seller_category = $request->input('seller_category');
        $object_id = (int)$request->input('object_id');
        //主商品信息
        $goods = [
            'image' => current($image),
            'market_price' => min($spec_market_price),
            'sell_price' => min($spec_sell_price),
        ];
        foreach ($request->only(['title', 'subtitle', 'video', 'sku_code', 'category_id', 'delivery_id', 'brand_id', 'seller_id', 'type', 'position', 'level_one_pct', 'level_two_pct']) as $key => $value) {
            $goods[$key] = $value;
        }
        if ($seller_id) {
            $goods['seller_id'] = $seller_id;
        } else {
            if (!$goods['seller_id']) {
                return __('admin.seller_is_must');
            }
        }

        //组装子商品信息
        $goods_sku = [];
        foreach ($spec_market_price as $key => $value) {
            $sku_spec_value = [];
            $sku_spec_image = current($image);
            if (isset($spec_id[$key])) {
                foreach ($spec_id[$key] as $k => $v) {
                    $_sku_value = [
                        'id' => $spec_id[$key][$k],
                        'name' => $spec_name[$key][$k],
                        'value' => $spec_value[$key][$k],
                        'image' => $spec_image[$key][$k],
                        'alias' => $spec_alias[$key][$k],
                    ];
                    $sku_spec_value[] = $_sku_value;
                    if (isset($spec_image[$key][$k]) && $spec_image[$key][$k]) {
                        $sku_spec_image = $spec_image[$key][$k];
                    }
                }
            }
            $_sku_item = [
                'sku_id' => $spec_sku_id[$key] ?? '',
                'market_price' => $spec_market_price[$key],
                'sell_price' => $spec_sell_price[$key],
                'sku_code' => $spec_sku_code[$key],
                'stock' => $spec_stock[$key],
                'weight' => $spec_weight[$key],
                'min_buy' => $spec_min_buy[$key],
                'max_buy' => $spec_max_buy[$key],
                'spec_value' => json_encode($sku_spec_value, JSON_UNESCAPED_UNICODE),
                'image' => $sku_spec_image
            ];
            $goods_sku[] = $_sku_item;
        }
        //属性信息
        $goods_attribute = [];
        $attribute = $request->input('attribute');
        if ($attribute) {
            foreach ($attribute as $attribute_id => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $_item = [
                            'attribute_id' => $attribute_id,
                            'value' => $v
                        ];
                        $goods_attribute[] = $_item;
                    }
                } else {
                    $_item = [
                        'attribute_id' => $attribute_id,
                        'value' => $value
                    ];
                    $goods_attribute[] = $_item;
                }
            }
        }
        //店铺分类
        if ($seller_id && $seller_category) {
            $seller_category = format_number($seller_category, true);
        } else {
            $seller_category = [];
        }
        //优惠券、套餐包商品需要验证
        if ($goods['type'] == Goods::TYPE_COUPONS || $goods['type'] == Goods::TYPE_PACKAGE) {
            if (!$object_id) {
                return __('api.coupons_not_exists');
            }
            if ($goods['type'] == Goods::TYPE_COUPONS) {
                //验证优惠券
                $coupons = Coupons::query()->where(['seller_id' => $goods['seller_id'], 'id' => $object_id, 'status' => Coupons::STATUS_ON])->first();
                if (!$coupons) {
                    return __('admin.coupons_not_exists');
                } elseif ($coupons['end_at'] < get_date() && !$coupons['day_num']) {
                    return __('admin.coupons_overdue');
                } elseif ($coupons['status'] != Coupons::STATUS_ON) {
                    return __('admin.coupons_status_error');
                }
            } elseif ($goods['type'] == Goods::TYPE_PACKAGE) {
                //验证套餐包
                $package = GoodsPackage::query()->where(['seller_id' => $goods['seller_id'], 'id' => $object_id, 'status' => Coupons::STATUS_ON])->first();
                if (!$package) {
                    return __('admin.package_not_exists');
                } elseif ($package['status'] != GoodsPackage::STATUS_ON) {
                    return __('admin.package_status_error');
                }
            }
        } else {
            $object_id = 0;
        }
        try {
            $res = DB::transaction(function () use ($id, $seller_id, $goods, $content, $image, $goods_sku, $goods_attribute, $seller_category, $object_id) {
                //修改主商品
                if ($id) {
                    self::query()->where(['id' => $id, 'seller_id' => $goods['seller_id']])->update($goods);
                    GoodsContent::query()->where('goods_id', $id)->update(['content' => $content]);
                    GoodsImage::query()->where('goods_id', $id)->delete();
                    GoodsAttribute::query()->where('goods_id', $id)->delete();
                    GoodsObject::query()->where('goods_id', $id)->delete();
                    GoodsSku::query()->where('goods_id', $id)->update(['status' => GoodsSku::STATUS_DEL]);
                } else {
                    $result = self::query()->create($goods);
                    $id = $result->id;
                    GoodsContent::query()->create(['goods_id' => $id, 'content' => $content]);
                    GoodsNum::query()->create(['goods_id' => $id]);//商品数量相关
                    MiniProgramQrcode::dispatch('goods', ['id' => $id]);//生成小程序码
                }
                //商品图片
                if ($image) {
                    $goods_image = [];
                    foreach ($image as $value) {
                        $_item = [
                            'goods_id' => $id,
                            'url' => $value
                        ];
                        $goods_image[] = $_item;
                    }
                    GoodsImage::query()->insert($goods_image);
                }
                //sku商品
                if ($goods_sku) {
                    foreach ($goods_sku as $value) {
                        $sku_id = $value['sku_id'];
                        unset($value['sku_id']);
                        $value['goods_id'] = $id;
                        $value['status'] = GoodsSku::STATUS_ON;
                        if ($sku_id) {
                            GoodsSku::query()->where('id', $sku_id)->update($value);
                        } else {
                            GoodsSku::query()->create($value);
                        }
                    }
                }
                //商品属性
                if ($goods_attribute) {
                    foreach ($goods_attribute as $key => $value) {
                        $value['goods_id'] = $id;
                        $goods_attribute[$key] = $value;
                    }
                    GoodsAttribute::query()->insert($goods_attribute);
                }
                //商家分类
                if ($seller_id) {
                    //商家编辑的时候先删除
                    GoodsSellerCategory::query()->where('goods_id', $id)->delete();
                }
                if ($seller_category) {
                    $seller_category_data = [];
                    foreach ($seller_category as $value) {
                        $seller_category_data[] = [
                            'goods_id' => $id,
                            'category_id' => $value
                        ];
                    }
                    GoodsSellerCategory::query()->insert($seller_category_data);
                }
                //优惠券、套餐包商品
                if ($object_id && ($goods['type'] == Goods::TYPE_COUPONS || $goods['type'] == Goods::TYPE_PACKAGE)) {
                    GoodsObject::query()->create(['goods_id' => $id, 'object_id' => $object_id, 'type' => $goods['type']]);
                }
                return $id;
            });
            self::syncRedisStock($res);//同步redis库存
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 商品属性
     * @param int $category_id
     * @param int|null $goods_id
     * @return array
     */
    public static function goodsAttribute(int $category_id, int|null $goods_id = 0)
    {
        if (!$category_id) return [];
        $goods_attribute = [];
        //查询商品属性
        if ($goods_id) {
            $goods_attribute_res = GoodsAttribute::query()->select('value', 'attribute_id')->where('goods_id', $goods_id)->get();
            if (!$goods_attribute_res->isEmpty()) {
                foreach ($goods_attribute_res as $value) {
                    $goods_attribute[$value['attribute_id']][] = $value['value'];
                }
            }
        }
        //查询分类下的属性
        $attribute = [];
        $attribute_res = Attribute::query()->where('category_id', $category_id)
            ->select('id', 'title', 'input_type')
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        if (!$attribute_res->isEmpty()) {
            $attribute_ids = [];
            foreach ($attribute_res->toArray() as $value) {
                $attribute_ids[] = $value['id'];
                if ($value['input_type'] == 'text' && isset($goods_attribute[$value['id']])) {
                    $value['value'] = current($goods_attribute[$value['id']]);
                }
                $attribute[$value['id']] = $value;
            }
            //获取属性值
            if ($attribute_ids) {
                $attribute_value_res = AttributeValue::query()->whereIn('attribute_id', array_unique($attribute_ids))
                    ->select('id', 'value', 'attribute_id')
                    ->orderBy('position', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
                if (!$attribute_value_res->isEmpty()) {
                    foreach ($attribute_value_res->toArray() as $value) {
                        if (isset($attribute[$value['attribute_id']])) {
                            $value['is_checked'] = 0;
                            if (isset($goods_attribute[$value['attribute_id']])) {
                                if (in_array($value['id'], $goods_attribute[$value['attribute_id']])) {
                                    $value['is_checked'] = 1;
                                }
                            }
                            $attribute[$value['attribute_id']]['value'][] = $value;
                        }
                    }
                }
            }
        }
        return array_values($attribute);
    }

    /**
     * 商品规格
     * @param int $category_id
     * @param int|null $goods_id
     * @return array
     */
    public static function goodsSpec(int $category_id, int|null $goods_id = 0)
    {
        if (!$category_id) return [];
        $goods_spec = [];
        //查询子商品
        $goods_sku = [];
        if ($goods_id) {
            $goods_sku_res = GoodsSku::query()->where(['status' => GoodsSku::STATUS_ON, 'goods_id' => $goods_id])->get();
            if (!$goods_sku_res->isEmpty()) {
                foreach ($goods_sku_res as $value) {
                    $_key_arr = [];
                    $spec_value = json_decode($value['spec_value'], true);
                    foreach ($spec_value as $spec) {
                        $_key_arr[] = $spec['id'];
                        $goods_spec[$spec['id']] = [
                            'value' => $spec['value'],
                            'image' => $spec['image'],
                            'alias' => $spec['alias'],
                        ];
                    }
                    $_key = join('|', $_key_arr);
                    if (!$_key) $_key = 'default';
                    $goods_sku[$_key] = [
                        'spec_sku_id' => $value['id'],
                        'spec_market_price' => $value['market_price'],
                        'spec_sell_price' => $value['sell_price'],
                        'spec_sku_code' => $value['sku_code'],
                        'spec_stock' => $value['stock'],
                        'spec_weight' => $value['weight'],
                        'spec_min_buy' => $value['min_buy'],
                        'spec_max_buy' => $value['max_buy']
                    ];
                }
            }
        }
        $spec = [];
        //查询分类下的属性
        $spec_res = Spec::query()->where('category_id', $category_id)
            ->select('id', 'title', 'type')
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        if (!$spec_res->isEmpty()) {
            $spec_ids = [];
            foreach ($spec_res->toArray() as $value) {
                $spec_ids[] = $value['id'];
                $spec[$value['id']] = $value;
            }
            //判断依据选择的属性值
            if ($spec_ids) {
                $spec_value_res = SpecValue::query()->whereIn('spec_id', $spec_ids)
                    ->select('id', 'value', 'spec_id')
                    ->orderBy('position', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
                if (!$spec_value_res->isEmpty()) {
                    foreach ($spec_value_res->toArray() as $value) {
                        if (isset($spec[$value['spec_id']])) {
                            $_goods_spec = $goods_spec[$value['id']] ?? [];
                            //判断已经选择的
                            $value['is_checked'] = 0;
                            if (isset($_goods_spec['value']) && $_goods_spec['value'] == $value['value']) {
                                $value['is_checked'] = 1;
                            }
                            $value['alias'] = $_goods_spec['alias'] ?? '';
                            $value['image'] = $_goods_spec['image'] ?? '';
                            $spec[$value['spec_id']]['value'][] = $value;
                        }
                    }
                }
            }
        }
        return [
            'spec' => array_values($spec),
            'goods_sku' => $goods_sku
        ];
    }

    /**
     * 彻底删除商品
     * @param array $ids
     * @return bool|void|null
     */
    public static function completelyDelete(array $ids)
    {
        try {
            DB::transaction(function () use ($ids) {
                self::query()->whereIn('id', $ids)->forceDelete();
                GoodsAttribute::query()->whereIn('goods_id', $ids)->delete();
                GoodsContent::query()->whereIn('goods_id', $ids)->delete();
                GoodsObject::query()->whereIn('goods_id', $ids)->delete();
                GoodsImage::query()->whereIn('goods_id', $ids)->delete();
                GoodsNum::query()->whereIn('goods_id', $ids)->delete();
                GoodsSellerCategory::query()->whereIn('goods_id', $ids)->delete();
                GoodsSku::query()->whereIn('goods_id', $ids)->delete();
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 生成小程序码
     * @param int $goods_id
     * @return bool|string
     * @throws \App\Exceptions\ApiError
     */
    public static function createQrcode(int $goods_id)
    {
        $mini_program = new MiniProgram();
        $qrcode = $mini_program->createGoodsQrcode($goods_id);
        if (!$qrcode) {
            return false;
        }
        Goods::query()->where('id', $goods_id)->update(['qrcode' => $qrcode]);
        return $qrcode;
    }

    /**
     * 删除商品缓存
     * @param int|array $ids
     * @return false|void
     */
    public static function delGoodsCache(int|array $ids)
    {
        if (!$ids) return false;
        if (!is_array($ids)) $ids = [$ids];
        foreach ($ids as $_id) {
            $cache_key = 'goods:' . $_id;
            $detail_cache_key = 'goods_detail:' . $_id;
            Cache::put($cache_key, '', 0);
            Cache::put($detail_cache_key, '', 0);
        }
    }

    /**
     * 获取商品缓存信息
     * @param int $id
     * @return mixed
     */
    public static function getGoods(int $id)
    {
        $cache_key = 'goods:' . $id;
        $goods = Cache::get($cache_key);
        if (!$goods) {
            $goods = self::query()->select('id', 'title', 'subtitle', 'image', 'video', 'shelves_status', 'seller_id', 'type', 'promo_type')->find($id);
            if ($goods) $goods = $goods->toArray();
            Cache::put($cache_key, $goods, get_custom_config('cache_time'));
        }
        return $goods;
    }

    /**
     * 获取商品详情缓存信息
     * @param int $id
     * @return mixed
     */
    public static function getGoodsDetail(int $id)
    {
        $cache_key = 'goods_detail:' . $id;
        $goods = Cache::get($cache_key);
        if (!$goods) {
            $goods = self::query()->select('id', 'title', 'subtitle', 'image', 'video', 'shelves_status', 'seller_id', 'type', 'promo_type')->find($id);
            if ($goods) {
                $goods['favorite'] = $goods['stock'] = 0;
                $goods['spec'] = $goods['sku'] = $goods['attribute'] = $goods['group'] = $goods['seckill'] = [];
                $goods['num'] = $goods->num()->select('favorite', 'sale')->first();
                $goods['content'] = $goods->content()->value('content');
                $goods['image_list'] = $goods->image()->select('url')->get();
                $sku = $goods->goodsSku()->select('id', 'image', 'spec_value', 'stock', 'sell_price', 'market_price', 'min_buy', 'max_buy')->where('status', GoodsSku::STATUS_ON)->get();
                //获取子商品并组装规格
                $goods_sku = $spec = [];
                foreach ($sku->toArray() as $value) {
                    $spec_value = json_decode($value['spec_value'], true);
                    $sku_spec_id = [];
                    if ($spec_value) {
                        foreach ($spec_value as $val) {
                            $_spec = [
                                'id' => $val['id'],
                                'alias' => $val['alias'],
                                'image' => $val['image']
                            ];
                            $spec[$val['name']][$val['id']] = $_spec;
                            $sku_spec_id[] = $val['id'];
                        }
                    }
                    $_item = $value;
                    $_item['sku_spec_id'] = join('_', $sku_spec_id);
                    unset($_item['spec_value']);
                    $goods['stock'] += $value['stock'];
                    $goods_sku[] = $_item;
                }
                //组装规格
                foreach ($spec as $key => $value) {
                    $_item = [
                        'name' => $key,
                        'value' => array_values($value)
                    ];
                    $spec[$key] = $_item;
                }
                $goods['sku'] = $goods_sku;
                $goods['spec'] = array_values($spec);
                //获取商品属性
                $goods_attribute = $goods->attribute()->select('attribute_id', 'value')->get();
                $attribute_ids = array_column($goods_attribute->toArray(), 'attribute_id');
                //获取属性信息
                if ($attribute_ids) {
                    $attribute = Attribute::query()->whereIn('id', array_unique($attribute_ids))->select('id', 'title', 'input_type')->get();
                    $attribute = array_column($attribute->toArray(), null, 'id');
                }
                //获取属性id
                $attr_value_ids = [];
                foreach ($goods_attribute as $value) {
                    if (isset($attribute[$value['attribute_id']]) && $attribute[$value['attribute_id']]['input_type'] != 'text') {
                        $attr_value_ids[] = $value['value'];
                    }
                }
                //获取属性值
                if ($attr_value_ids) {
                    $attr_value = AttributeValue::query()->whereIn('id', array_unique($attr_value_ids))->pluck('value', 'id')->toArray();
                }
                //组装属性
                $goods_attr = [];
                foreach ($goods_attribute as $value) {
                    if (isset($attribute[$value['attribute_id']])) {
                        $_attribute = $attribute[$value['attribute_id']];
                        if ($_attribute['input_type'] == 'text') {
                            $_value = $value['value'];
                        } else {
                            $_value = $attr_value[$value['value']] ?? '';
                        }
                        $goods_attr[$value['attribute_id']]['name'] = $_attribute['title'];
                        $goods_attr[$value['attribute_id']]['value'][] = $_value;
                    }
                }
                $goods['attribute'] = array_values($goods_attr);
                //商家信息
                $goods['seller'] = Seller::query()->select('id', 'title', 'image')->find($goods['seller_id']);
                Cache::put($cache_key, $goods, get_custom_config('cache_time'));
            }
        }
        return $goods;
    }

    /**
     * 同步redis库存
     * @param int $goods_id
     * @return false|void
     */
    public static function syncRedisStock(int $goods_id)
    {
        $end_at = '';
        $goods = self::query()->where('id', $goods_id)->first();
        if ($goods['promo_type'] == self::PROMO_TYPE_SECKILL) {
            $end_at = PromoSeckill::query()->where('goods_id', $goods_id)->value('end_at');
        } elseif ($goods['type'] == self::TYPE_COUPONS) {
            $coupons_id = GoodsObject::query()->where('goods_id', $goods_id)->value('object_id');
            $end_at = Coupons::query()->where('id', $coupons_id)->value('end_at');
        }
        if (!$end_at) return false;
        $sku_data = GoodsSku::query()->where(['goods_id' => $goods_id])->pluck('stock', 'id')->toArray();
        $save_data = $sku_data;
        $save_data['all'] = array_sum($sku_data);
        $stock_redis_key = 'goods_redis_stock:' . $goods_id;
        Redis::del($stock_redis_key);
        Redis::hmset($stock_redis_key, $save_data);
        Redis::expire($stock_redis_key, strtotime($end_at) - time());
    }

    /**
     * 还原秒杀库存
     * @param array $goods
     * @return void
     */
    public static function stockRedisIncr(array $goods)
    {
        $stock_redis_key = 'goods_redis_stock:' . $goods['goods_id'];
        Redis::hincrby($stock_redis_key, $goods['sku_id'], $goods['buy_qty']);
    }

    /**
     * 获取秒杀redis库存
     * @param int $goods_id
     * @return array|false
     */
    public static function getRedisStock(int $goods_id)
    {
        $stock_redis_key = 'goods_redis_stock:' . $goods_id;
        $stock = Redis::hgetall($stock_redis_key);
        if (!$stock) return false;
        $remaining_stock = array_sum($stock) - $stock['all'];//剩余库存
        $all_stock = $stock['all'];//总库存
        $pct = format_price(1 - ($remaining_stock / $all_stock), 2, false) * 100;//剩余库存比例
        //$sale = $stock['all'] - $remaining_stock;//已经销售的存库
        return [$pct, $stock, $remaining_stock];//已经销售比例，库存信息，剩余库存
    }

    /**
     * 获取商品sku redis库存
     * @param int $goods_id
     * @param bool $stock_decr
     * @return array|false
     */
    public static function getRedisSkuStock(int $cart, bool $stock_decr = false)
    {
        $stock_redis_key = 'goods_redis_stock:' . $cart['goods_id'];
        $stock = Redis::hget($stock_redis_key, $cart['sku_id']);
        if ($stock < $cart['buy_qty']) {
            api_error(__('api.goods_stock_no_enough'));//秒杀库存不足
        }
        //开始减去库存
        if ($stock_decr) {
            Redis::hincrby($stock_redis_key, $cart['sku_id'], -$cart['buy_qty']);
        }
    }
}
