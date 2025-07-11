<?php

namespace wokmanchat\websocket;

use think\facade\Log;
use Workerman\Worker;
use think\worker\Server;
use wokmanchat\common\logic;
use wokmanchat\common\Module;
use Workerman\Connection\TcpConnection;

class Index extends Server
{
    protected $socket = 'websocket://0.0.0.0:22886';

    protected $option   = [
        'name' => 'workmanchat',
        'count' => 1,
        'user' => 'www',
        'group' => 'www',
        'reloadable' => true,
        'reusePort' => true,
    ];

    /**
     * @var logic\Chat
     */
    protected $chatLogic = null;

    public function __construct()
    {
        $config = Module::getInstance()->getConfig();
        $this->socket = 'websocket://0.0.0.0:' . ($config['port'] ?: 22886);

        $this->option['user'] = $config['user'] ?: 'www';
        $this->option['group'] = $config['group'] ?: 'www';

        Worker::$daemonize = $config['daemonize'] == 1;
        Worker::$pidFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.pid';
        Worker::$logFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.log';
        Worker::$stdoutFile = app()->getRuntimePath() . 'worker' . $config['port'] . '.stdout.log';

        Log::init(['type' => 'File', 'path' => app()->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR . 'worker']);

        parent::__construct();
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @param string $data
     * @return void
     */
    public function onMessage($connection, $data = '{}')
    {
        $this->chatLogic->onMessage($connection, $data);
    }

    public function onWorkerStart($worker)
    {
        $this->chatLogic = new logic\Chat;
        $this->chatLogic->onWorkerStart($worker);
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @param mixed $data
     * @return void
     */
    public function onWebSocketConnect($connection, $data = null)
    {
        $this->chatLogic->onWebSocketConnect($connection, $data);
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect($connection)
    {
        $this->chatLogic->onConnect($connection);
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose($connection)
    {
        $this->chatLogic->onClose($connection);
    }

    public function onWorkerReload($worker)
    {
        $this->chatLogic->onWorkerReload($worker);
    }

    /**
     * 当客户端的连接上发生错误时触发
     * @param TcpConnection $connection
     * @param string $code
     * @param string $msg
     */
    public function onError($connection, $code, $msg)
    {
        $this->chatLogic->onError($connection, $code, $msg);
    }
}
