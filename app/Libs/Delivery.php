<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/20
 * Time: 2:34 PM
 */

namespace App\Libs;

use App\Models\Order\DeliveryTraces;
use App\Services\LogService;

/**
 * 物流
 */
class Delivery
{
    public string $key;
    public string $secret;
    public string $salt;
    public string $callback_url;

    public function __construct()
    {
        $custom_config = get_custom_config_all();
        $this->key = $custom_config['kuaidi_100_key'];//客户授权key
        $this->secret = $custom_config['kuaidi_100_secret'];//客户授权secret
        $this->salt = $custom_config['kuaidi_100_salt'];
        $this->callback_url = url('/v1/out_push/delivery_notify');
    }

    /**
     * 订阅物流
     * @param string $company_code
     * @param string $code
     * @return bool
     */
    public function subscribe(string $company_code, string $code)
    {
        //参数设置
        $param = [
            'company' => $company_code,//快递公司编码
            'number' => $code,//快递单号
            'key' => $this->key,//客户授权key
            'parameters' => [
                'callbackurl' => $this->callback_url,//回调地址
                'salt' => $this->salt,//加密串
                'resultv2' => '1',//行政区域解析
                'autoCom' => '0',//单号智能识别
            ]
        ];
        $post_data = [
            'schema' => 'json',
            'param' => json_encode($param)
        ];
        $url = 'https://poll.kuaidi100.com/poll';
        $res = $this->curl($url, $post_data);
        //记录日志
        $log = [
            'company_code' => $company_code,
            'code' => $code,
            'return_code' => $res['returnCode'],
            'message' => $res['message'] ?? ''
        ];
        LogService::putLog('delivery_subscribe', $log);
        if ($res['result'] == true && $res['returnCode'] == 200) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 快递回调推送
     * @return array
     */
    public function notify()
    {
        $return = [
            'result' => false,
            'returnCode' => 500,
            'message' => '服务器错误'
        ];
        $post_data = request()->post();
        $sign = strtoupper(md5($post_data['param'] . $this->salt));
        if ($sign == $post_data['sign']) {
            $param = json_decode($post_data['param'], true);
            $traces = $param['lastResult'] ?? [];
            if ($traces) {
                DeliveryTraces::where(['company_code' => $traces['com'], 'code' => $traces['nu']])->delete();
                $insert_data = [];
                foreach ($traces['data'] as $value) {
                    $insert_data[] = [
                        'company_code' => $traces['com'],
                        'code' => $traces['nu'],
                        'accept_time' => $value['ftime'],
                        'info' => $value['context'],
                        'status' => $traces['state'],
                        'created_at' => get_date(),
                        'updated_at' => get_date()
                    ];
                }
                if ($insert_data) {
                    DeliveryTraces::insert($insert_data);
                }
                //记录日志
                $log = [
                    'company' => $traces['com'],
                    'code' => $traces['nu'],
                    'message' => $traces['message']
                ];
                LogService::putLog('delivery_notify', $log);
            }
            $return = [
                'result' => true,
                'returnCode' => 200,
                'message' => '成功'
            ];
        }
        return $return;
    }

    /**
     * 面子面单html
     * @param array $express_company
     * @param array $order
     * @param array $address
     * @return array|mixed
     */
    public function htmlOrder(array $express_company, array $order, array $address)
    {
        $express_param = json_decode($express_company['param'], true);
        [$msec, $sec] = explode(' ', microtime());
        $t = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $param = [
            'partnerId' => $express_param['partnerId'] ?? '',//电子面单客户账户或月结账号
            'partnerKey' => $express_param['partnerKey'] ?? '',//电子面单密码
            'partnerSecret' => $express_param['partnerSecret'] ?? '',//电子面单密钥
            'partnerName' => $express_param['partnerName'] ?? '',//电子面单客户账户名称
            'net' => $express_param['net'] ?? '',//收件网点名称
            'code' => $express_param['code'] ?? '',//电子面单承载编号
            'checkMan' => $express_param['checkMan'] ?? '',//电子面单承载快递员名
            'tbNet' => $express_param['tbNet'] ?? '',//在使用菜鸟/淘宝/拼多多授权电子面单时，若月结账号下存在多个网点，则tbNet="网点名称,网点编号" ，注意此处为英文逗号
            'kuaidicom' => $express_company['code'],//快递公司的编码
            'recMan' => [
                'name' => $order['full_name'],//收件人姓名
                'mobile' => $order['tel'],//收件人手机
                'printAddr' => $order['prov'] . $order['city'] . $order['area'] . $order['address'],//收件人地址
                'company' => ''//收件人公司名
            ],
            'sendMan' => [
                'name' => $address['full_name'],//寄件人姓名
                'mobile' => $address['tel'],//寄件人手机
                'printAddr' => $address['prov_name'] . $address['city_name'] . $address['area_name'] . $address['address'],//寄件人地址
                'company' => ''//寄件人公司名
            ],
            'cargo' => '商品',//物品名称
            'thirdOrderId' => $order['order_no'],//平台导入返回的订单id
            'count' => $order['product_num'],//物品总数量
            'needTemplate' => 1,//是否返回面单： 0：不需要(默认) 1：需要 如果需要，则返回要打印的模版的HTML代码
            'pollCallBackUrl' => $this->callback_url,//回调地址
            'salt' => $this->salt,//加密串
        ];
        $param = json_encode($param);
        $post_data = [
            'key' => $this->key,
            'sign' => md5($param . $t . $this->key . $this->secret),
            't' => $t,
            'param' => $param
        ];
        $url = 'http://poll.kuaidi100.com/eorderapi.do?method=getElecOrder';
        $res = $this->curl($url, $post_data);
        $delivery_data = [];
        if (isset($res['data'][0])) {
            $delivery_data = $res['data'][0];
        }
        //记录日志
        $log = [
            'company_code' => $express_company['code'],
            'code' => $delivery_data['kuaidinum'] ?? '',
            'return_code' => $res['status'] ?? '',
            'message' => $res['message'] ?? ''
        ];
        LogService::putLog('delivery_e_order', $log);
        if ($res['result'] == true) {
            return [
                'code' => $delivery_data['kuaidinum'] ?? '',
                'template_url' => $delivery_data['templateurl'][0] ?? '',
                'template' => $delivery_data['template'][0] ?? '',
            ];
        } else {
            return $res['message'];
        }
    }

    /**
     * 发送请求
     * @param string $url
     * @param array $post_data
     * @return mixed
     */
    public function curl(string $url, array $post_data)
    {
        /*$params = '';
        foreach ($post_data as $k => $v) {
            $params .= $k . '=' . urlencode($v) . '&';     //默认UTF-8编码格式
        }
        $post_data = substr($params, 0, -1);*/
        //发送post请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        return json_decode($result, true);
    }
}