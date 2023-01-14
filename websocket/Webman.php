<?php

namespace wokmanchat\websocket;

use wokmanchat\common\logic;
use Workerman\Connection\TcpConnection;

class Webman
{
    /**
     * @var logic\Chat
     */
    protected $chatLogic = null;

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
     * @return void
     */
    public function onWebSocketConnect($connection)
    {
        $this->chatLogic->onWebSocketConnect($connection);
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
