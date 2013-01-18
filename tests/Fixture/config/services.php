<?php
return array(
    'redis.local' => array(
        'class' => '\Lysine\Service\Redis',
        'host' => '127.0.0.1',
        'database' => 0,
    ),
    'pgsql.local' => array(
        'class' => '\Lysine\Service\DB\Adapter\Pgsql',
        'user' => 'dev',
        'password' => 'abc',
        'dsn' => 'pgsql:host=127.0.0.1 port=5432 dbname=lysine',
        'options' => array(
            \PDO::ATTR_TIMEOUT => 3,
        ),
    ),
    'mock.storage' => array(
        'class' => '\Test\Mock\DataMapper\Storage',
    ),
);
