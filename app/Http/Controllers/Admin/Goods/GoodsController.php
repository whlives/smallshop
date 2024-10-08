<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:36 PM
 */

namespace App\Http\Controllers\Admin\Goods;

use App\Exceptions\ApiError;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Goods\Category;
use App\Models\Goods\Delivery;
use App\Models\Goods\Goods;
use App\Models\Goods\GoodsObject;
use App\Models\Goods\GoodsPackage;
use App\Models\Market\Coupons;
use App\Models\Seller\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsController extends BaseController
{
    /**
     * 列表获取
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        //搜索
        $where = [];
        $id = (int)$request->input('id');
        $title = $request->input('title');
        $category_id = (int)$request->input('category_id');
        $seller_id = (int)$request->input('seller_id');
        $brand_id = (int)$request->input('brand_id');
        $status = $request->input('status');
        $shelves_status = $request->input('shelves_status');
        $is_rem = $request->input('is_rem');
        $type = (int)$request->input('type');
        if ($id) $where[] = ['id', $id];
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        if ($category_id) $where[] = ['category_id', $category_id];
        if ($seller_id) $where[] = ['seller_id', $seller_id];
        if ($brand_id) $where[] = ['brand_id', $brand_id];
        if (is_numeric($status)) $where[] = ['status', $status];
        if (is_numeric($shelves_status)) $where[] = ['shelves_status', $shelves_status];
        if (is_numeric($is_rem)) $where[] = ['is_rem', $is_rem];
        if ($type) $where[] = ['type', $type];
        $query = Goods::query()->select('id', 'title', 'image', 'sell_price', 'market_price', 'is_rem', 'category_id', 'seller_id', 'type', 'shelves_status', 'status', 'position', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $res_list = $res_list->toArray();
        $category_ids = array_column($res_list, 'category_id');
        if ($category_ids) {
            $category = Category::query()->whereIn('id', array_unique($category_ids))->pluck('title', 'id');
        }
        $seller_ids = array_column($res_list, 'seller_id');
        if ($seller_ids) {
            $seller = Seller::query()->whereIn('id', array_unique($seller_ids))->pluck('title', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['type'] = Goods::TYPE_DESC[$value['type']];
            $_item['category_name'] = $category[$value['category_id']] ?? '';
            $_item['seller_name'] = $seller[$value['seller_id']] ?? '';
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
     * @return JsonResponse
     * @throws ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $data = Goods::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        $data['goods_image'] = $data->image()->pluck('url')->toArray();//查询商品图片
        $data['content'] = $data->content()->value('content');
        $data['type_title'] = Goods::TYPE_DESC[$data['type']];
        $data['category_title'] = Category::query()->where('id', $data['category_id'])->value('title');
        $data['object_id'] = GoodsObject::query()->where('goods_id', $id)->value('object_id');
        return $this->success($data);
    }

    /**
     * 添加编辑
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function save(Request $request)
    {
        $res = Goods::formatGoods($request);
        if ($res === true) {
            Goods::delGoodsCache((int)$request->input('id'));//清除商品缓存
            return $this->success();
        } elseif ($res) {
            api_error($res);
        } else {
            api_error(__('admin.save_error'));
        }
    }

    /**
     * 修改状态
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function status(Request $request)
    {
        $ids = $this->checkBatchId();
        $status = (int)$request->input('status');
        if (!isset(Goods::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Goods::query()->whereIn('id', $ids)->update(['status' => $status, 'shelves_status' => Goods::SHELVES_STATUS_OFF]);
        if ($res) {
            Goods::delGoodsCache($ids);//清除商品缓存
            return $this->success();
        } else {
            api_error(__('admin.fail'));
        }
    }

    /**
     * 修改上下架状态
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function shelvesStatus(Request $request)
    {
        $ids = $this->checkBatchId();
        $status = (int)$request->input('status');
        if (!isset(Goods::SHELVES_STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        if ($status == Goods::SHELVES_STATUS_ON) {
            $res = Goods::query()->whereIn('id', $ids)->where('status', Goods::STATUS_ON)->update(['shelves_status' => $status, 'shelves_at' => get_date()]);
            $error_msg = __('admin.goods_shelves_status_fail');
        } else {
            $res = Goods::query()->whereIn('id', $ids)->update(['shelves_status' => $status]);
            $error_msg = __('admin.fail');
        }
        if ($res) {
            Goods::delGoodsCache($ids);//清除商品缓存
            return $this->success();
        } else {
            api_error($error_msg);
        }
    }

    /**
     * 修改推荐状态
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function rem(Request $request)
    {
        $ids = $this->checkBatchId();
        $is_rem = (int)$request->input('is_rem');
        if (!isset(Goods::REM_DESC[$is_rem])) {
            api_error(__('admin.missing_params'));
        }
        $res = Goods::query()->whereIn('id', $ids)->update(['is_rem' => $is_rem]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.fail'));
        }
    }

    /**
     * 删除数据
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function delete(Request $request)
    {
        $ids = $this->checkBatchId();
        $res = Goods::query()->whereIn('id', $ids)->delete();
        if ($res) {
            Goods::delGoodsCache($ids);//清除商品缓存
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 修改单个字段值
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function fieldUpdate(Request $request)
    {
        $id = (int)$request->input('id');
        $field = $request->input('field');
        $field_value = $request->input('field_value');
        $field_arr = ['position'];//支持修改的字段
        if ($field == 'position') $field_value = (int)$field_value;
        if (!in_array($field, $field_arr) || !$id || !$field || !$field_value) {
            api_error(__('admin.invalid_params'));
        }
        $res = Goods::query()->where('id', $id)->update([$field => $field_value]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.fail'));
        }
    }

    /**
     * 获取分类下的属性并判断是否已经选择
     * @param Request $request
     * @return array|JsonResponse
     */
    public function getAttribute(Request $request)
    {
        $category_id = (int)$request->input('category_id');
        $goods_id = (int)$request->input('goods_id');
        $return = Goods::goodsAttribute($category_id, $goods_id);
        return $this->success($return);
    }

    /**
     * 获取分类下的规格并判断是否已经选择
     * @param Request $request
     * @return array|JsonResponse
     */
    public function getSpec(Request $request)
    {
        $category_id = $request->input('category_id');
        $goods_id = $request->input('goods_id');
        $return = Goods::goodsSpec($category_id, $goods_id);
        return $this->success($return);
    }

    /**
     * 类型
     * @param Request $request
     * @return JsonResponse
     */
    public function type(Request $request)
    {
        return $this->success(Goods::TYPE_DESC);
    }

    /**
     * 获取对象列表（优惠券、套餐包）
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function object(Request $request)
    {
        $type = (int)$request->input('type');
        $seller_id = (int)$request->input('seller_id');
        if (!$type || !$seller_id) {
            api_error(__('admin.missing_params'));
        }
        $res_list = [];
        if ($type == Goods::TYPE_COUPONS) {
            $where = [
                ['status', Coupons::STATUS_ON],
                ['is_buy', Coupons::IS_BUY_ON],
                ['seller_id', $seller_id],
                ['end_at', '>', get_date()]
            ];
            $res_list = Coupons::query()->select('id', 'title')->where($where)
                ->orderBy('id', 'desc')
                ->get();
        } elseif ($type == Goods::TYPE_PACKAGE) {
            $where = [
                ['status', GoodsPackage::STATUS_ON],
                ['seller_id', $seller_id],
            ];
            $res_list = GoodsPackage::query()->select('id', 'title')->where($where)
                ->orderBy('id', 'desc')
                ->get();
        }
        return $this->success($res_list);
    }

    /**
     * 配送方式选择列表
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function delivery(Request $request)
    {
        $seller_id = (int)$request->input('seller_id');
        if (!$seller_id) {
            api_error(__('admin.missing_params'));
        }
        $where = [
            'status' => Delivery::STATUS_ON,
            'seller_id' => $seller_id
        ];
        $res_list = Delivery::query()->select('id', 'title')->where($where)
            ->orderBy('id', 'desc')
            ->get();
        return $this->success($res_list);
    }

    /**
     * 小程序码
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function qrcode(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $data = Goods::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        $qrcode = $data['qrcode'];
        if (!$qrcode) {
            $qrcode = Goods::createQrcode($id);
        }
        $return = [
            'mini_qrcode' => $qrcode
        ];
        return $this->success($return);
    }

}
