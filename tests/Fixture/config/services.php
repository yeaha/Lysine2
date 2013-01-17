<?php
return array(
    'redis.local' => array(
        'class' => '\Lysine\Service\Redis',
        'host' => '127.0.0.1',
        'database' => 0,
    ),
    'mock.storage' => array(
        'class' => '\Test\Mock\DataMapper\Storage',
    ),
);
