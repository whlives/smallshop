<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 3:31 PM
 */

namespace App\Http\Controllers\Seller\Goods;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Goods\Category;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    /**
     * 获取包含下级的下拉列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectAll(Request $request)
    {
        $parent_id = (int)$request->input('parent_id', 0);
        $data = Category::getSelect($parent_id, true);
        return $this->success($data);
    }
}
