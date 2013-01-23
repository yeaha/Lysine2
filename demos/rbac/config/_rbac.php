<?php
define('ROLE_ANONYMOUS', 'anonymous');
define('ROLE_ADMIN', 'admin');

return array(
    // 默认任何人都可以访问
    '__default__' => array('allow' => '*'),
    // 只能管理员访问
    'controller\admin' => array('allow' => ROLE_ADMIN),
    // 只能登录之后访问
    'controller\user' => array('deny' => ROLE_ANONYMOUS),
);
