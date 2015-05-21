**Lysine不再更新，新框架在 https://github.com/yeaha/owl**

Lysine
======

Lysine是一个PHP框架，为RESTful web app开发设计，从2010年开发至今，一直在单日百万动态请求规模下的正式生产环境中持续使用和改进。

绝大多数的web开发均包括4大核心功能：

* MVC，url和程序逻辑之间的调用和映射
* 存储服务读写，数据库和缓存（缓存也是存储的一种）
* ORM，存储数据和业务逻辑之间的映射关系
* 会话管理，多次HTTP请求之间的上下文数据读写

无论现在还是未来，Lysine都只会致力于实现和改进这4大核心功能，无意也无力成为一个大而全的框架。

本项目基于MIT许可证发布，您可以自由使用和修改。

功能特性
========

* 基于PHP 5.4新特性，[namespace](http://php.net/manual/en/language.namespaces.php)及[trait](http://php.net/manual/en/language.oop5.traits.php)
* [RESTful](http://en.wikipedia.org/wiki/Representational_state_transfer) controller，以资源的方式组织web app，通过HTTP标准方法(GET/POST/PUT/DELETE)访问资源 (@src/mvc.php)
* URL路由，自动匹配或者正则匹配 (@src/mvc.php)
* 纯PHP实现的网页视图，支持视图layout特性 (\Lysine\View类 @src/mvc.php)
* 基于PDO的数据库封装，支持postgresql、mysql、sqlite (@src/servcie/db.php)
* 数据库select查询封装 (\Lysine\Service\DB\Select类 @src/service/db.php)
* 常用缓存服务封装，redis和memcached
* ORM实现了精简的[DataMapper](http://en.wikipedia.org/wiki/Data_mapper_pattern)模式，可以自行扩展对象存储方式，易于为水平切分或垂直切分扩展 (@src/datamapper.php)
* 程序日志记录，可输出至文件、[FirePHP](http://www.firephp.org/)、[FireLogger](http://firelogger.binaryage.com/)、[ChromePHP](http://www.chromephp.com/)
* 基于事件驱动的对象组织 (\Lysine\Event和\Lysine\Traits\Event @src/core.php)
* 存储服务管理，快捷定义和调用多种不同的外部服务 (\Lysine\Service\Manager @src/service/manager.php)
* HTTP会话间上下文数据封装，支持存储到session、cookie、redis (\Lysine\Context @src/context.php)

单元测试
========

    > git clone https://github.com/yeaha/Lysine2 && cd Lysine2/tests
    > phpunit .

DEMO
====

* Hello World: 经典示例 (@demos/hellow_world/)
* RBAC: 用户角色访问权限控制 (@demos/rbac/)

详见各demo目录下README.md

代码构成
=========

得益于精心设计和不断改进，整个框架一直保持在比较小的规模，只有4000余行，168k

以下是使用[PHPLoc](https://github.com/sebastianbergmann/phploc)对框架代码的分析结果

    phploc 2.0.2 by Sebastian Bergmann.

    Directories                                          2
    Files                                               21

    Size
      Lines of Code (LOC)                             4628
      Comment Lines of Code (CLOC)                     203 (4.39%)
      Non-Comment Lines of Code (NCLOC)               4425 (95.61%)
      Logical Lines of Code (LLOC)                    1572 (33.97%)
        Classes                                       1407 (89.50%)
          Average Class Length                          23
          Average Method Length                          3
        Functions                                      116 (7.38%)
          Average Function Length                        4
        Not in classes or functions                     49 (3.12%)

    Complexity
      Cyclomatic Complexity / LLOC                    0.40
      Cyclomatic Complexity / Number of Methods       2.42

    Dependencies
      Global Accesses                                   28
        Global Constants                                 2 (7.14%)
        Global Variables                                 3 (10.71%)
        Super-Global Variables                          23 (82.14%)
      Attribute Accesses                               432
        Non-Static                                     411 (95.14%)
        Static                                          21 (4.86%)
      Method Calls                                     530
        Non-Static                                     465 (87.74%)
        Static                                          65 (12.26%)

    Structure
      Namespaces                                        12
      Interfaces                                         2
      Traits                                             3
      Classes                                           60
        Abstract Classes                                 5 (8.33%)
        Concrete Classes                                55 (91.67%)
      Methods                                          395
        Scope
          Non-Static Methods                           360 (91.14%)
          Static Methods                                35 (8.86%)
        Visibility
          Public Method                                274 (69.37%)
          Non-Public Methods                           121 (30.63%)
      Functions                                         26
        Named Functions                                 21 (80.77%)
        Anonymous Functions                              5 (19.23%)
      Constants                                         55
        Global Constants                                 5 (9.09%)
        Class Constants                                 50 (90.91%)
