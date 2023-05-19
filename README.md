<h1 align="center">Smallshop</h1>

## About Smallshop

smallshop是一套基于php+mysql开发的B2B2C商城。
系统包括了订单管理、商品管理、购物车功能、微信、支付宝、银联支付功能、信息管理、会员管理、优惠促销、广告管理等功能模块。

平台可使用微信账号直接登录，更可申请开通微信支付功能，手机微信扫码轻松完成支付。

技术交流QQ群:322257814。

环境要求ngixn/apache、php8.0+、mysql5.6+、redis

安装步骤

1.服务器目录绑定到public

2.public里面的admin和seller两个管理后台的文件可以自由修改放置位子，已经完全分离，只要放到能访问html的位置就可以

3.修改.env配置数据库和redis，没有就复制.env.example并改名为.env

4.在目录下运行 php artisan key:generate 生成应用密钥

5.在目录下运行 composer intall 安装

6.运行php artisan storage:link 创建本地文件链接
