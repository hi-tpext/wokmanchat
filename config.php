<?php

return [
    'port' => 22886,
    'daemonize' => 1,
    'user' => 'www',
    'group' => 'www',
    //
    //配置描述
    '__config__' => [
        'port' => ['type' => 'text', 'label' => '端口号', 'size' => [2, 8], 'help' => '1000~65535'],
        'daemonize' => ['type' => 'radio', 'label' => '守护模式', 'options' => [1 => '是', 0 => '否'], 'size' => [2, 8], 'help' => '默认为：22886'],
        'user' => ['type' => 'text', 'label' => '运行用户', 'size' => [2, 8], 'help' => '(linux系统有效)一般为www或www-data，确保系统中用户存在，不行的话填root'],
        'group' => ['type' => 'text', 'label' => '运行用户组', 'size' => [2, 8], 'help' => '(linux系统有效)一般为www或www-data，确保系统中分组存在，不行的话填root'],
    ],
];
