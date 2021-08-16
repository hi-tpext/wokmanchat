<?php

return [
    'port' => 22886,
    'daemonize' => 1,
    //
    //配置描述
    '__config__' => [
        'port' => ['type' => 'text', 'label' => '端口号', 'size' => [2, 8], 'help' => '1000~65535'],
        'daemonize' => ['type' => 'radio', 'label' => '守护模式', 'options' => [1 => '是', 0 => '否'], 'size' => [2, 8], 'help' => ''],
    ],
];
