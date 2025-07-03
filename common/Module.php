<?php

namespace wokmanchat\common;

use tpext\common\Module as baseModule;
use tpext\common\ExtLoader;

/**
 * Undocumented class
 */
class Module  extends baseModule
{
    protected $version = '1.0.4';

    protected $name = 'wokman.chat';

    protected $title = 'workerman聊天';

    protected $description = '基于workerman实现的聊天';

    protected $root = __DIR__ . '/../';

    protected $assets = 'assets';

    protected $modules = [
        'admin' => ['wokchatapp', 'wokchatuser', 'wokchatmsg'],
        'api' => ['wokchatadmin', 'wokchatuser']
    ];

    protected $versions = [
        '1.0.1' => '',
        '1.0.2' => '1.0.2.sql',
    ];

    /**
     * 后台菜单
     *
     * @var array
     */
    protected $menus = [
        [
            'title' => '聊天管理',
            'sort' => 1,
            'url' => '#',
            'icon' => 'mdi mdi-message-text',
            'children' => [
                [
                    'title' => '应用管理',
                    'sort' => 1,
                    'url' => '/admin/wokchatapp/index',
                    'icon' => 'mdi mdi-apple-keyboard-command',
                ],
                [
                    'title' => '用户管理',
                    'sort' => 2,
                    'url' => '/admin/wokchatuser/index',
                    'icon' => 'mdi mdi-account-outline',
                ],
                [
                    'title' => '消息记录',
                    'sort' => 3,
                    'url' => '/admin/wokchatmsg/index',
                    'icon' => 'mdi mdi-format-list-numbers',
                ]
            ],
        ]
    ];

    /**
     * 默认的configPath()是composer模式带`src`的，extend模式没有src所以重写一下。
     * 不重写此方法也可以，创建一个`src`目录把config.php放里面
     *
     * @return string
     */
    public function configPath()
    {
        return realpath($this->getRoot() . 'config.php');
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function install()
    {
        if (!ExtLoader::isWebman() && !class_exists('\\think\\worker\\Server')) { //根据think-worker中某一个类是否存在来判断sdk是否已经安装

            if (ExtLoader::isTP51()) {

                $this->errors[] = new \Exception('<p>请使用composer安装think-worker后再安装本扩展！</p><pre>composer require topthink/think-worker:^2.*</pre>');
            } else if (ExtLoader::isTP60()) {

                $this->errors[] = new \Exception('<p>请使用composer安装think-worker后再安装本扩展！</p><pre>composer require topthink/think-worker:^3.*</pre>');
            }

            return false;
        }

        return parent::install();
    }
}
