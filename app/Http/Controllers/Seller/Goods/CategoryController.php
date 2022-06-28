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
use Validator;

class CategoryController extends BaseController
{
    /**
     * 获取包含下级的下拉列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectAll(Request $request)
    {
        $data = Category::getSelect(0, true);
        return $this->success($data);
    }
}