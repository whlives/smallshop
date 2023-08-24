<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/7/26
 * Time: 14:21 PM
 */

namespace App\Http\Controllers\Admin\Goods;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Goods\Goods;
use App\Models\Goods\GoodsPackage;
use Illuminate\Http\Request;
use Validator;

class PackageGoodsController extends BaseController
{
    /**
     * 列表获取
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $package_id = (int)$request->input('package_id');
        if (!$package_id) {
            api_error(__('admin.content_is_empty'));
        }
        $package = self::getPackage($package_id);
        if (!$package['goods_data']) {
            api_error(__('admin.content_is_empty'));
        }
        $goods_ids = array_keys($package['goods_data']);
        $query = Goods::query()->select('id', 'title', 'image', 'sell_price')
            ->whereIn('id', $goods_ids)
            ->where(['seller_id' => $package['seller_id']]);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['num'] = $package['goods_data'][$value['id']] ?? 0;
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 添加编辑
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function save(Request $request)
    {
        $package_id = (int)$request->input('package_id');
        $goods_id = (int)$request->input('goods_id');
        $num = (int)$request->input('num');
        if (!$package_id || !$goods_id || !$num) {
            api_error(__('admin.missing_params'));
        }
        $package = self::getPackage($package_id);
        $goods_data = $package['goods_data'];
        $goods_data[$goods_id] = $num;
        $res = GoodsPackage::query()->where('id', $package_id)->update(['goods_data' => json_encode($goods_data)]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
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
        $package_id = (int)$request->input('package_id');
        $goods_id = (int)$request->input('id');
        if (!$package_id || !$goods_id) {
            api_error(__('admin.missing_params'));
        }
        $package = self::getPackage($package_id);
        $goods_data = $package['goods_data'];
        unset($goods_data[$goods_id]);
        $res = GoodsPackage::query()->where('id', $package_id)->update(['goods_data' => json_encode($goods_data)]);
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
            ['type', '<>', Goods::TYPE_PACKAGE],
        ];
        $title = $request->input('title');
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        $res_list = Goods::query()->select('id as value', 'title as name')
            ->where($where)
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();
        return $this->success($res_list);
    }

    /**
     * 获取套餐包信息
     * @param int $package_id
     * @return mixed
     * @throws \App\Exceptions\ApiError
     */
    private function getPackage(int $package_id)
    {
        $package = GoodsPackage::query()->where('id', $package_id)->first();
        if (!$package) {
            api_error(__('admin.content_is_empty'));
        }
        $goods_data = json_decode($package['goods_data'], true);
        $package['goods_data'] = $goods_data;
        return $package;
    }

}
