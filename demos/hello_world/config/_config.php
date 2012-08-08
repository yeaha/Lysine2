<?php
return array(
    'application' => array(
        'include_path' => array( ROOT_DIR ),
    ),
    'router' => array(
        'namespace' => array(
            '/admin' => '\Admin\Controller',
            '/' => '\Controller',
        ),
        'rewrite' => array(
        ),
    ),
    'logging' => array(
        'file_name' => ROOT_DIR .'/log/%F.log',
    ),
    'services' => array(
    ),
);
