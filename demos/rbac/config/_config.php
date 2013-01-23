<?php
return array(
    'application' => array(
        'include_path' => array( ROOT_DIR ),
    ),
    'router' => array(
        'namespace' => array(
            '/' => '\Controller',
        ),
        'rbac' => require __DIR__ .'/_rbac.php',
        'rewrite' => array(
        ),
    ),
    'logging' => array(
        'file_name' => ROOT_DIR .'/log/%F.log',
    ),
    'services' => array(
        'db' => array(
            'class' => '\Lysine\Service\DB\Adapter\Pgsql',
            'user' => 'dev',
            'password' => 'abc',
            'dsn' => 'pgsql:host=127.0.0.1;dbname=lysine',
        ),
        // 可选，见model/user.php注释
        'redis' => array(
            'class' => '\Lysine\Service\Redis',
            'host' => '127.0.0.1',
        ),
    ),
);
