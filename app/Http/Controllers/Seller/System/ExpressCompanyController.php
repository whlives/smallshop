<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Seller\System;

use App\Http\Controllers\Seller\BaseController;
use App\Models\System\ExpressCompany;
use Illuminate\Http\Request;
use Validator;

class ExpressCompanyController extends BaseController
{
    /**
     * 选择列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function select(Request $request)
    {
        $where = [
            'status' => ExpressCompany::STATUS_ON
        ];
        $res_list = ExpressCompany::select('id', 'title')->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'desc')
            ->get();
        return $this->success($res_list);
    }
}
