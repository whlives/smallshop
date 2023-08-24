<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Seller\Member;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Member\MemberGroup;
use Illuminate\Http\Request;
use Validator;

class GroupController extends BaseController
{
    /**
     * 选择列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function select(Request $request)
    {
        $where = [
            'status' => MemberGroup::STATUS_ON
        ];
        $res_list = MemberGroup::query()->select('id', 'title')->where($where)
            ->orderBy('id', 'desc')
            ->get();
        return $this->success($res_list);
    }

}
