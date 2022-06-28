<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/7
 * Time: 4:12 PM
 */

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

class IndexController extends Controller
{

    /**
     * 首页
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return 'ok';
    }
}
