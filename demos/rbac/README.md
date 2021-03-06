简介
====

以Role-based access control([RBAC](http://en.wikipedia.org/wiki/Role-based_access_control))方式，实现了不同角色用户的访问权限控制

要点描述
========

* 监听router的before dispatch事件，对访问的controller发起rbac检查 (config/boot.php)
* 当不允许访问时，抛出HTTP异常，未登录为401异常，已登录为403异常 (model/rbac.php)
* 捕获到401异常则重定向到登录页面，403异常则显示异常信息(index.php)

配置文件
=======

* config/_config.php，系统及存储配置
* config/_rbac.php，访问权限配置
* config/install.sql，数据库脚本
* config/nginx.conf，nginx配置模板

测试运行
=======

测试之前需要先把config/install.sql导入postgresql，并修改config/_config.php配置到实际环境

使用php内置web服务器直接测试

    > php -S 127.0.0.1:8000 -t demos/rbac/public demos/rbac/index.php

打开浏览器，访问http://127.0.0.1:8000/
