<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/7
 * Time: 4:12 PM
 */

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;

class IndexController extends BaseController
{

    /**
     * 首页
     * @return string
     */
    public function index(Request $request)
    {
        return 'ok';
    }
}
