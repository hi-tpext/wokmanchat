<?php

namespace wokmanchat\websocket;

use think\Db;
use \think\Validate;
use think\facade\Log;
use Workerman\Worker;
use think\facade\Config;
use think\worker\Server;
use Workerman\Lib\Timer;
use tpext\common\ExtLoader;
use wokmanchat\common\logic;
use wokmanchat\common\model;
use wokmanchat\common\Module;
use think\exception\ValidateException;
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

    public const HEARTBEAT_TIME = 60;

    protected $appConnections = [];

    /**
     * Undocumented variable
     *
     * @var logic\ChatApp
     */
    protected $appLogic;

    /**
     * Undocumented variable
     *
     * @var logic\ChatUser
     */
    protected $userLogic;

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
        $connection->lastMessageTime = time();

        try {

            $this->userLogic->reset();

            $res = $this->handler($connection, $data);

            $this->userLogic->reset();

            if ($res && $res['code'] != 1) {
                $connection->send(json_encode(['do_action' => 'show_toast', 'text' => $res['msg']]));
            }
        } catch (\Exception $e) {
            Log::error($e->__toString());
            echo $e->getMessage() . "\n";
        }
    }

    public function onWorkerStart($worker)
    {
        Log::info("wokmanchat onWorkerStart");

        $this->initDb();

        $this->appLogic = new logic\ChatApp;
        $this->userLogic = new logic\ChatUser;

        $this->heartBeat($worker);
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @param string $data
     * @return array
     */
    protected function handler($connection, $data = '{}')
    {
        $data = json_decode($data, true);
        if (!empty($data) && isset($data['action'])) {
            $res = ['code' => 0, 'msg' => '??????' . $data['action']];
            if ($data['action'] == 'heartbeat') {
                return;
            } else if ($data['action'] == 'login') {

                $result = $this->validate($data, [
                    'app_id|??????app_id' => 'require|number',
                    'uid|??????id' => 'require|number',
                    'sign|sign??????' => 'require',
                    'time|?????????' => 'require|number',
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->validateUser($data['app_id'], $data['uid'], $data['sign'], $data['time']);

                if ($res['code'] == 1) {

                    if (!isset($this->appConnections[$data['app_id']])) {
                        $this->appConnections[$data['app_id']] = [
                            $data['uid'] => [],
                        ];
                    } else {
                        if (isset($this->appConnections[$data['app_id']][$data['uid']]) && count($this->appConnections[$data['app_id']][$data['uid']]) > 1) { //????????????

                            if (Module::config('login_duplication') != 1) { //???????????????????????????

                                $oldConnections = $this->appConnections[$data['app_id']][$data['uid']];

                                foreach ($oldConnections as $oldConn) {
                                    $oldConn->send(json_encode(['do_action' => 'show_toast', 'text' => '???????????????????????????????????????????????????']));

                                    $oldConn->send(json_encode(['do_action' => 'login_duplication'])); //???????????????????????????????????????????????????

                                    $oldConn->close();

                                    unset($this->appConnections[$data['app_id']][$data['uid']][$oldConn->id]);
                                }

                                $this->appConnections[$data['app_id']][$data['uid']] = [];
                            }
                        }
                    }

                    $self = $this->userLogic->getSelf();

                    $connection->app_id = $self['app_id'];
                    $connection->uid = $data['uid'];
                    $connection->sys_uid = $self['id'];

                    // $connection->id ???`workerman`??????????????????
                    $this->appConnections[$self['app_id']][$data['uid']][$connection->id] = $connection;

                    Timer::del($connection->auth_timer_id); //??????????????????????????????????????????????????????

                    $connection->send(json_encode(['do_action' => 'login_success', 'user' => $self]));
                }

                return $res;
            } else if ($data['action'] == 'connect_to_user') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'to_uid|????????????uid' => 'require|number'
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->connectToUser($data['to_uid']);

                if ($res['code'] == 1) {
                    $connection->send(json_encode(['do_action' => 'connect_to_success', 'session' => $res['session'], 'to_user' => $res['toUser']]));
                }

                return $res;
            } else if ($data['action'] == 'connect_to_session') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'session_id|??????id' => 'require|number',
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->connectToSession($data['session_id']);

                if ($res['code'] == 1) {
                    $connection->send(json_encode(['do_action' => 'connect_to_success', 'session' => $res['session'], 'to_user' => $res['toUser']]));
                }

                return $res;
            } else if ($data['action'] == 'get_session_list') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                if (!isset($data['skip'])) {
                    $data['skip'] = 0;
                }

                if (!isset($data['kwd'])) {
                    $data['kwd'] = '';
                }
                if (!isset($data['pagesize'])) {
                    $data['pagesize'] = 20;
                }

                $res = $this->userLogic->getSessionList($data['skip'], $data['pagesize'], $data['kwd']);

                if ($res['code'] == 1) {

                    $connection->send(json_encode(['do_action' => 'get_session_list_success', 'list' => $res['list'], 'has_more' => $res['has_more']]));
                }

                return $res;
            } else if ($data['action'] == 'send_by_session') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'session_id|??????id' => 'require|number',
                    'content|????????????' => 'require',
                    'type|????????????' => 'require|number|gt:0'
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->sendBySession($data['session_id'], $data['content'], $data['type']);

                if ($res['code'] == 1) {

                    $connection->send(json_encode(['do_action' => 'send_success']));

                    $this->newMessageNotify($connection, $res);
                }

                return $res;
            } else if ($data['action'] == 'create_room') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'that_uid|??????uid' => 'require|number'
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->createRoom($data['that_uid']);

                if ($res['code'] == 1) {

                    $connection->send(json_encode(['do_action' => 'create_room_success', 'new_session' => $res['session']]));

                    $this->newMessageNotify($connection, $res);
                }

                return $res;
            } else if ($data['action'] == 'create_room_by_session') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'session_id|??????id' => 'require|number',
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->createRoomBySession($data['session_id']);

                if ($res['code'] == 1) {

                    $connection->send(json_encode(['do_action' => 'create_room_success', 'new_session' => $res['session']]));

                    $this->newMessageNotify($connection, $res);
                }

                return $res;
            } else if ($data['action'] == 'add_user_to_room') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'room_session_id|????????????id' => 'require|number',
                    'that_uid|??????uid' => 'require|number'
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->addUserToRoom($data['room_session_id'], $data['that_uid']);

                if ($res['code'] == 1) {

                    $connection->send(json_encode(['do_action' => 'add_user_to_room_success']));

                    $this->newMessageNotify($connection, $res);
                }

                return $res;
            } else if ($data['action'] == 'set_session_rank') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'session_id|??????id' => 'require|number',
                    'rank|??????' => 'require|number',
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->setSessionRank($data['session_id'], $data['rank']);

                return $res;
            } else if ($data['action'] == 'get_history_message_list') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'session_id|??????id' => 'require|number',
                    'from_msg_id' => 'number',
                    'pagesize' => 'number',
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                if (!isset($data['from_msg_id'])) {
                    $data['from_msg_id'] = 0;
                }

                if (!isset($data['pagesize'])) {
                    $data['pagesize'] = 50;
                }

                $res = $this->userLogic->getMessageList($data['session_id'], true, $data['from_msg_id'], $data['pagesize']);

                if ($res['code'] == 1) {
                    $connection->send(json_encode(['do_action' => 'get_history_list_success', 'list' => $res['list'], 'has_more' => $res['has_more']]));
                }

                return $res;
            } else if ($data['action'] == 'get_new_message_list') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '????????????'];
                }

                $result = $this->validate($data, [
                    'session_id|??????id' => 'require|number',
                    'from_msg_id' => 'number',
                    'pagesize' => 'number',
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                if (!isset($data['from_msg_id'])) {
                    $data['from_msg_id'] = 0;
                }

                if (!isset($data['pagesize'])) {
                    $data['pagesize'] = 50;
                }

                $res = $this->userLogic->getMessageList($data['session_id'], false, $data['from_msg_id'], $data['pagesize']);

                if ($res['code'] == 1) {
                    $connection->send(json_encode(['do_action' => 'get_new_message_list_success', 'list' => $res['list'], 'has_more' => $res['has_more']]));
                }

                return $res;
            } else if ($data['action'] == 'bye') {
                $data = ['code' => 1, 'msg' => 'bye'];
                $connection->send(json_encode($data));
                $connection->close();
            } else {
                return ['code' => 0, 'msg' => '??????-' . $data['action']];
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param mixed $connection
     * @return boolean
     */
    protected function switchUser($connection)
    {
        $app_id = $connection->app_id;
        $uid = $connection->uid;
        $sys_uid = $connection->sys_uid;

        if (!$app_id || !$uid || !$sys_uid) {
            //
            return false;
        }

        $user = model\WokChatUser::where(['app_id' => $app_id, 'uid' => $uid])->find();

        if ($user) {
            $this->userLogic->switchUser($user);
            return true;
        }

        return false;
    }

    /**
     * ??????
     */
    protected function heartBeat($worker)
    {
        Timer::add(5, function () {
            model\WokChatUser::where('id', 1)->find(); //?????????????????????
        });

        Timer::add(10, function () use ($worker) {

            $timeNow = time();
            foreach ($worker->connections as $connection) {
                // ????????????connection???????????????????????????lastMessageTime?????????????????????
                if (empty($connection->lastMessageTime)) {
                    $connection->lastMessageTime = $timeNow;
                    continue;
                }
                // ??????????????????????????????????????????????????????????????????????????????????????????
                if ($timeNow - $connection->lastMessageTime > static::HEARTBEAT_TIME) {
                    $data = ['code' => 1, 'msg' => 'bye'];
                    $connection->send(json_encode($data));
                    $connection->close();
                }
            }
        });
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onWebSocketConnect($connection)
    {
        //????????????h5??????wss???????????????:during WebSocket handshake: Sent non-empty 'Sec-WebSocket-Protocol' header but no response was received
        if (isset($_SERVER['HTTP_SEC_WEBSOCKET_PROTOCOL'])) {
            $protocols = explode(',', $_SERVER['HTTP_SEC_WEBSOCKET_PROTOCOL']);
            $connection->headers = [
                'Sec-WebSocket-Protocol: ' . $protocols[0],
            ];
        }
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect($connection)
    {
        $connection->maxSendBufferSize = 4 * 1024 * 1024; //4MB?????????????????????(??????1MB)

        // ?????????$connection??????????????????auth_timer_id?????????????????????id
        // ??????10?????????????????????????????????10??????????????????????????????
        $connection->auth_timer_id = Timer::add(10, function () use ($connection) {
            $connection->close();
        }, null, false);
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose($connection)
    {
        if (isset($connection->app_id) && isset($connection->uid)) {
            // ???????????????????????????

            // $connection->id ???`workerman`??????????????????

            unset($this->appConnections[$connection->app_id][$connection->uid][$connection->id]);

            $connection->uid = 0;
        }
    }

    public function onWorkerReload($worker)
    {
        Log::info("wokmanchat onWorkerReload");

        $this->initDb();
    }

    /**
     * ?????????????????????????????????????????????
     * @param TcpConnection $connection
     * @param string $code
     * @param string $msg
     */
    public function onError($connection, $code, $msg)
    {
        Log::error("wokmanchat error $code $msg");
    }

    /**
     * Undocumented function
     *
     * @param TcpConnection $connection
     * @param mixed $res
     * @return void
     */
    protected function newMessageNotify($connection, $res)
    {
        //$res ['code' => 1, 'msg' => '??????????????????', 'session_id' => $session['id'], 'session' => $session, 'message_id' => $msg['id'], 'message' => $msg];
        $session = $res['session'];
        //
        if ($session['is_room']) { //???????????????

            $uids = $this->userLogic->getRoomSessionUsers($session['sys_to_uid']); //?????????????????????????????????uid??????

            foreach ($uids as $uid) {
                $this->sendMessageByUid($session['app_id'], $uid, ['do_action' => 'new_message', 'session' => $session, 'from_uid' => $connection->uid]);
            }
        } else {
            $this->sendMessageByUid($session['app_id'], $session['uid1'], ['do_action' => 'new_message', 'session' => $session, 'from_uid' => $connection->uid]);
            $this->sendMessageByUid($session['app_id'], $session['uid2'], ['do_action' => 'new_message', 'session' => $session, 'from_uid' => $connection->uid]);
        }
    }

    /**
     * Undocumented function
     * 
     * @param int $app_id
     * @param int $uid
     * @param array|string $message
     * @return boolean
     */
    protected function sendMessageByUid($app_id, $uid, $message)
    {
        if (!is_string($message)) {
            $message = json_encode($message);
        }

        if (isset($this->appConnections[$app_id]) && isset($this->appConnections[$app_id][$uid])) {

            $userConnections = $this->appConnections[$app_id][$uid];

            foreach ($userConnections as $conn) {
                $conn->send($message);
            }

            return true;
        }
    }

    /**
     * ????????????
     * @access protected
     * @param  array        $data     ??????
     * @param  string|array $validate ????????????????????????????????????
     * @param  array        $message  ????????????
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate($data, $validate, $message = [])
    {
        $v = new Validate;
        $v->rule($validate);

        if (is_array($message)) {
            $v->message($message);
        }

        if (!$v->check($data)) {
            return $v->getError();
        }

        return true;
    }

    protected function initDb()
    {
        if (ExtLoader::isTP51()) {
            $breakMatchStr = [
                'server has gone away',
                'no connection to the server',
                'Lost connection',
                'is dead or not enabled',
                'Error while sending',
                'decryption failed or bad record mac',
                'server closed the connection unexpectedly',
                'SSL connection has been closed unexpectedly',
                'Error writing data to the connection',
                'Resource deadlock avoided',
                'failed with errno',
                'child connection forced to terminate due to client_idle_limit',
                'query_wait_timeout',
                'reset by peer',
                'Physical connection is not usable',
                'TCP Provider: Error code 0x68',
                'ORA-03114',
                'Packets out of order. Expected',
                'Adaptive Server connection failed',
                'Communication link failure',
                'connection is no longer usable',
                'Login timeout expired',
                'SQLSTATE[HY000] [2002] Connection refused',
                'running with the --read-only option so it cannot execute this statement',
                'The connection is broken and recovery is not possible. The connection is marked by the client driver as unrecoverable. No attempt was made to restore the connection.',
                'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Try again',
                'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Name or service not known',
                'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected',
                'SQLSTATE[HY000] [2002] Connection timed out',
                'SSL: Connection timed out',
                'SQLSTATE[HY000]: General error: 1105 The last transaction was aborted due to Seamless Scaling. Please retry.',
                'bytes failed with errno=32 Broken pipe'
            ];

            $config = array_merge(Config::pull('database'), ['break_reconnect' => true, 'break_match_str' => $breakMatchStr]);

            Db::init($config);
            Db::connect($config);
        } else if (ExtLoader::isTP60()) {
            $config = array_merge(Config::get('database.connections.mysql'), ['break_reconnect' => true]);

            Db::setConfig($config);
            Db::connect('mysql')->connect($config);
        }
    }
}
