<?php

return [
    'port' => 22886,
    'daemonize' => 1,
    'user' => 'www',
    'group' => 'www',
    'login_duplication' => 0,
    //
    //配置描述
    '__config__' => [
        'port' => ['type' => 'text', 'label' => '端口号', 'size' => [2, 8], 'help' => '1000~65535'],
        'daemonize' => ['type' => 'radio', 'label' => '守护模式', 'options' => [1 => '是', 0 => '否'], 'size' => [2, 8], 'help' => '运行模式，daemonize'],
        'user' => ['type' => 'text', 'label' => '运行用户', 'size' => [2, 8], 'help' => '(linux系统有效)一般为www或www-data，确保系统中用户存在，不行的话填root'],
        'group' => ['type' => 'text', 'label' => '运行用户组', 'size' => [2, 8], 'help' => '(linux系统有效)一般为www或www-data，确保系统中分组存在，不行的话填root'],
        'login_duplication' => ['type' => 'radio', 'label' => '多点登录', 'options' => [0 => '禁止', 1 => '允许'], 'size' => [2, 8], 'help' => '是否允许同一用户在不同地方登录，如果不允许，新登录用户会把之前登录的挤下线'],
    ],
];
