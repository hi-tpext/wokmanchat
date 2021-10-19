# workman-chat

## workman聊天系统

### 请使用composer安装**think-worker**后再安装本扩展

#### tp5.1

```bash
composer require topthink/think-worker:^2.0
```

#### tp6.0

```bash
composer require topthink/think-worker:^3.0
```

### 使用

#### 修改配置

`/config/worker_server.php`

```php
return [
    'worker_class' => ['wokmanchat\\websocket\\Index'],
];
```

#### 环境要求

需要使用以下php方法，确保以下方法未被禁用：

```
pcntl_wait
pcntl_signal
pcntl_fork
pcntl_signal_dispatch
pcntl_alarm
其他（待补充）
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

创建行sh脚本

#### 启动成功

在linux终端执行以下命令，以判断启动成功

`ps aux | grep WorkerMan`

如果输出类似以下，说明启动成功。

```
root      132200  0.0  0.1 217728 13776 ?        S    11:43   0:00 WorkerMan: master process  start_file=/www/wwwroot/www.localhost.com/think
www       133280  0.0  0.2 218316 22000 ?        S    11:55   0:00 WorkerMan: worker process  workmanchat websocket://0.0.0.0:22886
```

如果只有第一条[master process]没有[worker process]，则是启动失败，请到网站的`runimeme`目录里面查看`worker22886.stdout.log`日志分析原因。