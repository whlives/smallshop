<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Seller\Market;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Goods\Goods;
use App\Models\Market\PromoGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class GroupController extends BaseController
{
    public int $seller_id;

    public function __construct()
    {
        $this->seller_id = $this->getUserId();
    }

    /**
     * 列表获取
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        //搜索
        $where = [
            'seller_id' => $this->seller_id
        ];
        $title = $request->input('title');
        $goods_title = $request->input('goods_title');
        $goods_id = (int)$request->input('goods_id');
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        if ($goods_title) {
            $goods_id = Goods::where('title', $goods_title)->value('id');
            if ($goods_id) {
                $where[] = ['goods_id', $goods_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if ($goods_id) $where[] = ['goods_id', $goods_id];
        $query = PromoGroup::select('id', 'title', 'goods_id', 'group_num', 'hour', 'status', 'start_at', 'end_at', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $goods_ids = array_column($res_list->toArray(), 'goods_id');
        if ($goods_ids) {
            $goods_data = Goods::whereIn('id', array_unique($goods_ids))->pluck('title', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['goods_title'] = $goods_data[$value['goods_id']] ?? '';
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 根据id获取信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $data = PromoGroup::where('seller_id', $this->seller_id)->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        return $this->success($data);
    }

    /**
     * 添加编辑
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function save(Request $request)
    {
        $id = (int)$request->input('id');
        //验证规则
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'goods_id' => 'required|numeric',
            'group_num' => 'required|numeric|min:2',
            'hour' => 'required|numeric',
            'start_at' => 'nullable|date_format:"Y-m-d H:i:s"',
            'end_at' => 'nullable|date_format:"Y-m-d H:i:s"',
        ], [
            'title.required' => '标题不能为空',
            'goods_id.required' => '商品不能为空',
            'goods_id.numeric' => '商品只能是数字',
            'group_num.required' => '成团人数不能为空',
            'group_num.numeric' => '成团人数只能是数字',
            'group_num.min' => '成团人数最少2',
            'hour.required' => '成功时间不能为空',
            'hour.numeric' => '成功时间只能是数字',
            'start_at.date_format' => '开始时间格式错误',
            'end_at.date_format' => '结束时间格式错误',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $goods_id = (int)$request->input('goods_id');
        $detail = '';
        if ($id) {
            //只有不是原来的商品的时候才需要验证商品
            $detail = PromoGroup::find($id);
            if ($detail['goods_id'] != $goods_id) {
                $goods = Goods::where(['seller_id' => $this->seller_id, 'id' => $goods_id])->first();
                if (!$goods) {
                    api_error(__('admin.goods_not_exists'));
                } elseif ($goods['promo_type'] != Goods::PROMO_TYPE_DEFAULT) {
                    api_error(__('admin.goods_is_bind_promotion'));
                }
            }
        }

        $save_data = [];
        foreach ($request->only(['title', 'goods_id', 'group_num', 'hour', 'start_at', 'end_at']) as $key => $value) {
            $save_data[$key] = $value;
        }
        try {
            $res = DB::transaction(function () use ($save_data, $id, $detail) {
                if ($id) {
                    PromoGroup::where(['id' => $id, 'seller_id' => $this->seller_id])->update($save_data);
                    if ($detail['goods_id'] != $save_data['goods_id']) {
                        Goods::where('id', $detail['goods_id'])->update(['promo_type' => Goods::PROMO_TYPE_DEFAULT, 'shelves_status' => Goods::SHELVES_STATUS_OFF]);//修改活动的时候商品变化需取消商品优惠类型并下架商品
                    }
                } else {
                    $save_data['seller_id'] = $this->seller_id;
                    PromoGroup::create($save_data);
                }
                Goods::where('id', $save_data['goods_id'])->update(['promo_type' => Goods::PROMO_TYPE_GROUP]);
                return true;
            });
            Goods::syncRedisStock($save_data['goods_id']);//同步redis库存
            if ($detail['goods_id'] != $save_data['goods_id']) {
                Goods::delGoodsCache($detail['goods_id']);//删除商品缓存
            }
        } catch (\Exception $e) {
            $res = false;
        }
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }

    /**
     * 修改状态
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function status(Request $request)
    {
        $ids = $this->checkBatchId();
        $status = (int)$request->input('status');
        if (!isset(PromoGroup::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = PromoGroup::whereIn('id', $ids)->where('seller_id', $this->seller_id)->update(['status' => $status]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.fail'));
        }
    }

    /**
     * 删除数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function delete(Request $request)
    {
        $ids = $this->checkBatchId();
        $goods_ids = PromoGroup::whereIn('id', $ids)->where('seller_id', $this->seller_id)->pluck('goods_id')->toArray();
        try {
            $res = DB::transaction(function () use ($ids, $goods_ids) {
                Goods::whereIn('id', $goods_ids)->update(['promo_type' => Goods::PROMO_TYPE_DEFAULT, 'shelves_status' => Goods::SHELVES_STATUS_OFF]);//删除活动的时候需取消商品优惠类型并下架商品
                PromoGroup::whereIn('id', $ids)->where('seller_id', $this->seller_id)->delete();
                return true;
            });
            Goods::delGoodsCache($goods_ids);//删除商品缓存
        } catch (\Exception $e) {
            $res = false;
        }
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 商品搜素
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function search(Request $request)
    {
        $where = [
            ['seller_id', $this->seller_id],
        ];
        $res_list = Goods::select('id', 'title')
            ->where($where)
            ->whereIn('promo_type', [Goods::PROMO_TYPE_DEFAULT, Goods::PROMO_TYPE_GROUP])
            ->orderBy('id', 'desc')
            ->orderBy('shelves_status', 'desc')
            ->get();
        return $this->success($res_list);
    }

}
