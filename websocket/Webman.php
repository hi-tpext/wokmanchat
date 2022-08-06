<?php

namespace wokmanchat\websocket;

use think\facade\Db;
use think\Validate;
use Webman\Config;
use think\facade\Log;
use Workerman\Lib\Timer;
use wokmanchat\common\logic;
use wokmanchat\common\model;
use wokmanchat\common\Module;
use think\exception\ValidateException;
use Workerman\Connection\TcpConnection;

class Webman
{

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
            $res = ['code' => 0, 'msg' => '失败' . $data['action']];
            if ($data['action'] == 'heartbeat') {
                return;
            } else if ($data['action'] == 'login') {

                $result = $this->validate($data, [
                    'app_id|应用app_id' => 'require|number',
                    'uid|用户id' => 'require|number',
                    'sign|sign签名' => 'require',
                    'time|时间戳' => 'require|number',
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
                        if (isset($this->appConnections[$data['app_id']][$data['uid']]) && count($this->appConnections[$data['app_id']][$data['uid']]) > 1) { //重复登陆

                            if (Module::config('login_duplication') != 1) { //不允许多点重复登录

                                $oldConnections = $this->appConnections[$data['app_id']][$data['uid']];

                                foreach ($oldConnections as $oldConn) {
                                    $oldConn->send(json_encode(['do_action' => 'show_toast', 'text' => '您的账号在其他地方登录，您已下线．']));

                                    $oldConn->send(json_encode(['do_action' => 'login_duplication'])); //发送信号提示，前端不要自动重新登录

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

                    // $connection->id 为`workerman`框架自带属性
                    $this->appConnections[$self['app_id']][$data['uid']][$connection->id] = $connection;

                    Timer::del($connection->auth_timer_id); //验证成功，删除定时器，防止连接被关闭

                    $connection->send(json_encode(['do_action' => 'login_success', 'user' => $self]));
                }

                return $res;
            } else if ($data['action'] == 'connect_to_user') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'to_uid|目标用户uid' => 'require|number'
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
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'session_id|会话id' => 'require|number',
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
                    return ['code' => 0, 'msg' => '账户异常'];
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
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'session_id|会话id' => 'require|number',
                    'content|发送内容' => 'require',
                    'type|消息类型' => 'require|number|gt:0'
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
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'that_uid|用户uid' => 'require|number'
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
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'session_id|会话id' => 'require|number',
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
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'room_session_id|群聊会话id' => 'require|number',
                    'that_uid|用户uid' => 'require|number'
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
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'session_id|会话id' => 'require|number',
                    'rank|权重' => 'require|number',
                ]);

                if ($result !== true) {
                    return ['code' => 0, 'msg' => $result];
                }

                $res = $this->userLogic->setSessionRank($data['session_id'], $data['rank']);

                return $res;
            } else if ($data['action'] == 'get_history_message_list') {

                if (!$this->switchUser($connection)) {
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'session_id|会话id' => 'require|number',
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
                    return ['code' => 0, 'msg' => '账户异常'];
                }

                $result = $this->validate($data, [
                    'session_id|会话id' => 'require|number',
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
                return ['code' => 0, 'msg' => '失败-' . $data['action']];
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
     * 心跳
     */
    protected function heartBeat($worker)
    {
        Timer::add(5, function () {
            model\WokChatUser::where('id', 1)->find(); //保存数据库连接
        });

        Timer::add(10, function () use ($worker) {

            $timeNow = time();
            foreach ($worker->connections as $connection) {
                // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
                if (empty($connection->lastMessageTime)) {
                    $connection->lastMessageTime = $timeNow;
                    continue;
                }
                // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
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
        //解决微信h5连接wss协议时报错:during WebSocket handshake: Sent non-empty 'Sec-WebSocket-Protocol' header but no response was received
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
        $connection->maxSendBufferSize = 4 * 1024 * 1024; //4MB，防止数据截断(默认1MB)

        // 临时给$connection对象添加一个auth_timer_id属性存储定时器id
        // 定时10秒关闭连接，需要客户端10秒内完成用户登陆验证
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
            // 连接断开时删除映射

            // $connection->id 为`workerman`框架自带属性

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
     * 当客户端的连接上发生错误时触发
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
        //$res ['code' => 1, 'msg' => '消息发送成功', 'session_id' => $session['id'], 'session' => $session, 'message_id' => $msg['id'], 'message' => $msg];
        $session = $res['session'];
        //
        if ($session['is_room']) { //如果是群聊

            $uids = $this->userLogic->getRoomSessionUsers($session['sys_to_uid']); //获取群聊相关的所有人员uid列表

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
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
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
        $config = array_merge(Config::get('thinkorm.connections.mysql'), ['break_reconnect' => true]);

        Db::setConfig($config);
        Db::connect('mysql')->connect($config);
    }
}
