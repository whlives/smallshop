<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/25
 * Time: 3:40 PM
 */

namespace App\Services;

use App\Models\Financial\SellerWithdraw;
use App\Models\Financial\Trade;
use App\Models\Financial\TradeRefund;
use App\Models\Financial\Withdraw;
use App\Models\Order\Order;
use App\Models\Order\OrderDelivery;
use App\Models\Order\Refund;
use App\Models\System\Payment;
use League\Csv\Reader;
use League\Csv\Writer;

class ExportService
{
    /**
     * 时间判断
     * @param string|null $start_at
     * @param string|null $end_at
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function timeRange(string|null $start_at, string|null $end_at)
    {
        if (!$start_at || !$end_at) {
            api_error(__('admin.export_time_must'));
        } elseif ((strtotime($end_at) - strtotime($start_at)) > (24 * 3600 * 31)) {
            api_error(__('admin.export_time_out_31'));
        }
    }


    /**
     * 订单导出
     * @param $request
     * @param array $where
     * @param string|null $start_at
     * @param string|null $end_at
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function order($request, array $where, string|null $start_at = '', string|null $end_at = '')
    {
        self::timeRange($start_at, $end_at);
        //$cols = $request->input('cols');
        $cols = [
            'full_name' => '收件人姓名',
            'tel' => '手机/电话',
            'prov' => '省',
            'city' => '市',
            'area' => '区',
            'address' => '地址',
            'goods_title' => '产品内容',
            'subtotal' => '金额',
            'order_no' => '订单号',
        ];
        $query = Order::select('order.id as order_id', 'order_no', 'full_name', 'tel', 'prov', 'city', 'area', 'address', 'subtotal', 'goods.goods_title', 'goods.buy_qty');
        if (isset($where['where']) && $where['where']) {
            $query->where($where['where']);
        }
        if (isset($where['where_in']) && $where['where_in']) {
            foreach ($where['where_in'] as $key => $val) {
                $query->wherein($key, $val);
            }
        }
        $res_list = $query->leftJoin('order_goods as goods', 'order.id', '=', 'goods.order_id')
            ->orderBy('order.id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        //导出
        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->insertOne(array_values($cols));//表头
        //表数据
        $goods_title = '';
        foreach ($res_list as $key => $value) {
            if (!isset($res_list[$key + 1]['order_id']) || $res_list[$key + 1]['order_id'] != $value['order_id']) {
                //这里需要看是否是相同订单的商品，相同的需要合并，只有下一条的订单id不一样的时候才开始写入
                $value['goods_title'] = $goods_title . $value['goods_title'] . '×' . $value['buy_qty'];
                $_cols_val = [];
                foreach ($cols as $_name => $_title) {
                    $_cols_val[] = $value[$_name] . "\n";
                }
                $csv->insertOne($_cols_val);
                $goods_title = '';
            } else {
                $goods_title .= ',' . $value['goods_title'] . '×' . $value['buy_qty'];
            }
        }
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('电商订单.csv');
    }

    /**
     * 售后导出
     * @param $request
     * @param array $where
     * @param string|null $start_at
     * @param string|null $end_at
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function refund($request, array $where, string|null $start_at = '', string|null $end_at = '')
    {
        self::timeRange($start_at, $end_at);
        $cols = $request->input('cols');
        $query = Refund::select('refund.id', 'refund.m_id', 'refund.order_goods_id', 'refund.refund_no', 'refund.amount', 'refund.refund_type', 'refund.status', 'refund.reason', 'refund.created_at', 'goods.goods_title', 'goods.image', 'goods.spec_value');
        if (isset($where['where']) && $where['where']) {
            $query->where($where['where']);
        }
        if (isset($where['where_in']) && $where['where_in']) {
            foreach ($where['where_in'] as $key => $val) {
                $query->wherein($key, $val);
            }
        }
        $res_list = $query->leftJoin('order_goods as goods', 'goods.id', '=', 'refund.order_goods_id')
            ->orderBy('refund.id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        //导出
        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->insertOne(array_values($cols));//表头
        //表数据
        foreach ($res_list as $value) {
            $value['refund_type_text'] = Refund::REFUND_TYPE_DESC[$value['refund_type']];
            $value['status_text'] = Refund::STATUS_DESC[$value['status']];
            $_cols_val = [];
            foreach ($cols as $_name => $_title) {
                $_cols_val[] = $value[$_name] . "\n";
            }
            $csv->insertOne($_cols_val);
        }
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('售后.csv');
    }

    /**
     * 发货单导出
     * @param $request
     * @param array $where
     * @param string|null $start_at
     * @param string|null $end_at
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function delivery($request, array $where, string|null $start_at = '', string|null $end_at = '')
    {
        self::timeRange($start_at, $end_at);
        $cols = $request->input('cols');
        $query = OrderDelivery::select('order_delivery.id', 'order_delivery.company_name', 'order_delivery.code', 'order_delivery.created_at', 'o.order_no');
        if (isset($where['where']) && $where['where']) {
            $query->where($where['where']);
        }
        if (isset($where['where_in']) && $where['where_in']) {
            foreach ($where['where_in'] as $key => $val) {
                $query->wherein($key, $val);
            }
        }
        $res_list = $query->leftJoin('order as o', 'o.id', '=', 'order_delivery.order_id')
            ->orderBy('order_delivery.id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        //导出
        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->insertOne(array_values($cols));//表头
        //表数据
        foreach ($res_list as $value) {
            $_cols_val = [];
            foreach ($cols as $_name => $_title) {
                $_cols_val[] = $value[$_name] . "\n";
            }
            $csv->insertOne($_cols_val);
        }
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('发货单.csv');
    }

    /**
     * 交易单导出
     * @param $request
     * @param array $where
     * @param string|null $start_at
     * @param string|null $end_at
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function trade($request, array $where, string|null $start_at = '', string|null $end_at = '')
    {
        self::timeRange($start_at, $end_at);
        $cols = $request->input('cols');
        $query = Trade::select('trade.id', 'trade.m_id', 'trade.trade_no', 'trade.type', 'trade.subtotal', 'trade.flag', 'trade.payment_id', 'trade.payment_no', 'trade.pay_total', 'trade.platform', 'trade.status', 'trade.pay_at', 'trade.created_at', 'm.username');
        if (isset($where['where']) && $where['where']) {
            $query->where($where['where']);
        }
        if (isset($where['where_in']) && $where['where_in']) {
            foreach ($where['where_in'] as $key => $val) {
                $query->wherein($key, $val);
            }
        }
        $res_list = $query->leftJoin('member as m', 'm.id', '=', 'trade.m_id')
            ->orderBy('trade.id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        //导出
        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->insertOne(array_values($cols));//表头
        //表数据
        foreach ($res_list as $value) {
            $value['flag'] = Trade::FLAG_DESC[$value['flag']];
            $value['type'] = Trade::TYPE_DESC[$value['type']];
            $value['status_text'] = Trade::STATUS_DESC[$value['status']];
            $value['payment'] = Payment::PAYMENT_DESC[$value['payment_id']] ?? '';
            $_cols_val = [];
            foreach ($cols as $_name => $_title) {
                $_cols_val[] = $value[$_name] . "\n";
            }
            $csv->insertOne($_cols_val);
        }
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('交易单.csv');
    }

    /**
     * 退款交易单导出
     * @param $request
     * @param array $where
     * @param string|null $start_at
     * @param string|null $end_at
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function tradeRefund($request, array $where, string|null $start_at = '', string|null $end_at = '')
    {
        self::timeRange($start_at, $end_at);
        $cols = $request->input('cols');
        $query = TradeRefund::select('trade_refund.id', 'trade_refund.m_id', 'trade_refund.refund_no', 'trade_refund.trade_no', 'trade_refund.order_no', 'trade_refund.type', 'trade_refund.subtotal', 'trade_refund.payment_id', 'trade_refund.payment_id', 'trade_refund.payment_no', 'trade_refund.platform', 'trade_refund.status', 'trade_refund.note', 'trade_refund.pay_at', 'trade_refund.created_at', 'm.username');
        if (isset($where['where']) && $where['where']) {
            $query->where($where['where']);
        }
        if (isset($where['where_in']) && $where['where_in']) {
            foreach ($where['where_in'] as $key => $val) {
                $query->wherein($key, $val);
            }
        }
        $res_list = $query->leftJoin('member as m', 'm.id', '=', 'trade_refund.m_id')
            ->orderBy('trade_refund.id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        //导出
        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->insertOne(array_values($cols));//表头
        //表数据
        foreach ($res_list as $value) {
            $value['type'] = TradeRefund::TYPE_DESC[$value['type']];
            $value['payment'] = Payment::PAYMENT_DESC[$value['payment_id']] ?? '';
            $value['status_text'] = TradeRefund::STATUS_DESC[$value['status']];
            $_cols_val = [];
            foreach ($cols as $_name => $_title) {
                $_cols_val[] = $value[$_name] . "\n";
            }
            $csv->insertOne($_cols_val);
        }
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('退款交易单.csv');
    }

    /**
     * 用户提现导出
     * @param $request
     * @param array $where
     * @param string|null $start_at
     * @param string|null $end_at
     * @return void
     * @throws \App\Exceptions\ApiError
     * @throws \League\Csv\CannotInsertRecord
     */
    public static function withdraw($request, array $where, string|null $start_at = '', string|null $end_at = '')
    {
        self::timeRange($start_at, $end_at);
        $cols = $request->input('cols');
        $query = Withdraw::select('withdraw.id', 'withdraw.m_id', 'withdraw.type', 'withdraw.amount', 'withdraw.name', 'withdraw.bank_name', 'withdraw.pay_number', 'withdraw.refuse_note', 'withdraw.status', 'withdraw.created_at', 'withdraw.done_at', 'm.username');
        if (isset($where['where']) && $where['where']) {
            $query->where($where['where']);
        }
        if (isset($where['where_in']) && $where['where_in']) {
            foreach ($where['where_in'] as $key => $val) {
                $query->wherein($key, $val);
            }
        }
        $res_list = $query->leftJoin('member as m', 'm.id', '=', 'withdraw.m_id')
            ->orderBy('withdraw.status', 'asc')
            ->orderBy('withdraw.id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        //导出
        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->insertOne(array_values($cols));//表头
        //表数据
        foreach ($res_list as $value) {
            $value['type'] = Withdraw::TYPE_DESC[$value['type']];
            $value['status_text'] = Withdraw::STATUS_DESC[$value['status']];
            $_cols_val = [];
            foreach ($cols as $_name => $_title) {
                $_cols_val[] = $value[$_name] . "\n";
            }
            $csv->insertOne($_cols_val);
        }
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('提现列表.csv');
    }

    /**
     * 商家提现导出
     * @param $request
     * @param array $where
     * @param string|null $start_at
     * @param string|null $end_at
     * @return void
     * @throws \App\Exceptions\ApiError
     * @throws \League\Csv\CannotInsertRecord
     */
    public static function sellerWithdraw($request, array $where, string|null $start_at = '', string|null $end_at = '')
    {
        self::timeRange($start_at, $end_at);
        $cols = $request->input('cols');
        $query = SellerWithdraw::select('seller_withdraw.id', 'seller_withdraw.m_id', 'seller_withdraw.type', 'seller_withdraw.amount', 'seller_withdraw.name', 'seller_withdraw.bank_name', 'seller_withdraw.pay_number', 'seller_withdraw.refuse_note', 'seller_withdraw.status', 'seller_withdraw.created_at', 'seller_withdraw.done_at', 's.username');
        if (isset($where['where']) && $where['where']) {
            $query->where($where['where']);
        }
        if (isset($where['where_in']) && $where['where_in']) {
            foreach ($where['where_in'] as $key => $val) {
                $query->wherein($key, $val);
            }
        }
        $res_list = $query->leftJoin('seller as s', 's.id', '=', 'seller_withdraw.m_id')
            ->orderBy('seller_withdraw.status', 'asc')
            ->orderBy('seller_withdraw.id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        //导出
        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->insertOne(array_values($cols));//表头
        //表数据
        foreach ($res_list as $value) {
            $value['type'] = SellerWithdraw::TYPE_DESC[$value['type']];
            $value['status_text'] = SellerWithdraw::STATUS_DESC[$value['status']];
            $_cols_val = [];
            foreach ($cols as $_name => $_title) {
                $_cols_val[] = $value[$_name] . "\n";
            }
            $csv->insertOne($_cols_val);
        }
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->output('商家提现列表.csv');
    }
}