<?php

use tpext\builder\common\Form;
use tpext\common\ExtLoader;

return [
    'port' => 22886,
    'daemonize' => 1,
    'user' => 'www',
    'group' => 'www',
    'login_duplication' => 0,
    'sign_timeout' => 60,
    //
    //配置描述
    '__config__' => function (Form $form) {
        if (!ExtLoader::isWebman()) {
            $form->text('port', '端口号')->help('1000~65535');
            $form->text('user', '运行用户')->help('(linux系统有效)一般为www或www-data，不确定则留空');
            $form->text('group', '运行用户组')->help('(linux系统有效)一般为www或www-data，不确定则留空');
            if (!ExtLoader::isTP80()) {
                $form->radio('daemonize', '守护模式')->options([1 => '是', 0 => '否'])->help('运行模式，daemonize');
            }
        } else {
            $form->raw('tips', '提示')->value('<p>进程配置信息在`/config/process.php`中设置</p>');
        }

        $form->radio('login_duplication', '多点登录')->options([0 => '禁止', 1 => '允许'])->help('是否允许同一用户在不同地方登录，如果不允许，新登录用户会把之前登录的挤下线');
        $form->text('sign_timeout', '设备时间误差')->help('允许的时间误差，当客户端与服务器时间不同步超过值时sign会验证失败');
    },
];
