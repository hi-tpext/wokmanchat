# workman-chat

## workman聊天系统

### 请使用composer安装**think-worker**后再安装本扩展

#### tp5.1

```bash
composer require topthink/think-worker:^2.*
```

#### tp6.0

```bash
composer require topthink/think-worker:^3.*
```

### 使用

#### 修改配置

`/config/worker_server.php`

```php
return [
    'worker_class' => 'wokmanchat\\websocket\\Index',
];
```

#### 启动脚本,start.sh

```bash
COUNT1=`ps -ef |grep WorkerMan|grep -v "grep" |wc -l`;

echo $COUNT1

if [ $COUNT1 -eq 0 ];then

    cd /www/wwwroot/www.localhost.com

    php think worker:server start

fi
```

#### 重启脚本,restart.sh

```bash
cd /www/wwwroot/www.localhost.com

php think worker:server restart
```

修改`/www/wwwroot/www.localhost.com`为实际网站路径
创建定时任务执行sh脚本
