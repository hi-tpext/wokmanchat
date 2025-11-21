<?php

namespace wokmanchat\common\logic;

use think\Db;
use wokmanchat\common\model;
use wokmanchat\common\Module;
use tpext\builder\common\Module as BuilderModule;
use tpext\builder\logic\Upload as UploadTool;

/**
 * 封装前台操作，用户聊天
 */
class ChatUser
{
    protected $app_id = 0;

    protected $uid = 0;

    protected $sys_uid = 0;

    /**
     * Undocumented variable
     *
     * @var model\WokChatUser
     */
    protected $user = null;

    protected $config = [];

    public function __construct($config = [])
    {
        $this->config = $config;
        Module::getInstance()->getConfig();
    }

    /**
     * Undocumented function
     *
     * @return model\WokChatUser
     */
    public function getSelf()
    {
        return $this->user;
    }

    /**
     * Undocumented function
     *
     * @param int $app_id
     * @param int $uid
     * @param string $sign
     * @param int $time
     * @return array
     */
    public function validateUser($app_id, $uid, $sign, $time)
    {
        if (empty($app_id) || empty($uid) || empty($sign) || empty($time)) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        $sign_timeout = intval($this->config['sign_timeout'] ?? 60);

        if ($sign_timeout < 10) {
            $sign_timeout = 10;
        }

        if (abs(time() - $time) > $sign_timeout) {
            return ['code' => 0, 'msg' => 'sign超时请检查设备时间'];
        }

        $app = model\WokChatApp::where('id', $app_id)->find();

        if (!$app) {
            return ['code' => 0, 'msg' => 'app_id:应用未找到'];
        }

        if ($app['enable'] == 0) {
            return ['code' => 0, 'msg' => '聊天应用未开启'];
        }

        $this->app_id = $app_id;

        $user = model\WokChatUser::where(['app_id' => $this->app_id, 'uid' => $uid])->find();

        if (!$user) {
            return ['code' => 0, 'msg' => 'uid:用户未找到' . $uid . '-' . $app_id];
        }

        if (empty($user['token'])) {
            return ['code' => 0, 'msg' => '用户token未设置'];
        }

        if ($sign != md5($user['token'] . $time)) {
            return ['code' => 0, 'msg' => 'sign验证失败'];
        }

        $user->save(['login_time' => date('Y-m-d H:i:s')]);

        unset($user['token']);

        $this->uid = $uid;
        $this->user = $user;
        $this->sys_uid = $user['id'];

        return ['code' => 1, 'msg' => '成功'];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function isValidateUser()
    {
        if (empty($this->user) || empty($this->uid) || empty($this->sys_uid)) {
            return ['code' => 0, 'msg' => '用户验证未通过'];
        }

        return ['code' => 1, 'msg' => '成功'];
    }

    /**
     * 免验证，切换到用户
     *
     * @param array|mixed $user
     * @return void
     */
    public function switchUser($user)
    {
        unset($user['token']);
        $this->uid = $user['uid'];
        $this->user = $user;
        $this->sys_uid = $user['id'];
        $this->app_id = $user['app_id'];
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
     */
    public function editUser($data)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if ($exist = model\WokChatUser::where(['uid' => $this->uid, 'app_id' => $this->app_id])->find()) {
            $res = $exist->allowField(['nickname', 'remark', 'avatar'])->save($data);

            if ($res) {
                return ['code' => 1, 'msg' => '成功'];
            }

            return ['code' => 0, 'msg' => '修改失败'];
        }

        return ['code' => 0, 'msg' => '用户不存在'];
    }

    /**
     * Undocumented function
     *
     * @param string $remark
     * @return array
     */
    public function editUserRemark($remark)
    {
        return $this->editUser(['remark' => $remark]);
    }

    /**
     * Undocumented function
     *
     * @param string $avatar
     * @return array
     */
    public function editUserAvatar($avatar)
    {
        return $this->editUser(['avatar' => $avatar]);
    }

    /**
     * Undocumented function
     *
     * @param int $to_uid
     * @return array
     */
    public function connectToUser($to_uid)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if ($this->uid == $to_uid) {
            return ['code' => 0, 'msg' => '不能给自己发送消息'];
        }

        $fromUser = $this->user;

        if ($fromUser['room_owner_uid']) {
            return ['code' => 0, 'msg' => '系统错误,不能以群聊身份开始对话'];
        }

        $toUser = $this->getUserByUid($to_uid, ['auto_reply', 'auto_reply_offline']);

        if (!$toUser) {

            $user = new model\WokChatUser;

            $data = [
                'app_id' => $this->app_id,
                'uid' => $to_uid,
                'nickname' => $to_uid,
                'remark' => '',
            ];

            $res = $user->save($data);
            if (!$res) {
                return ['code' => 0, 'msg' => '接收用户不存在,uid:' . $to_uid . ',app_id:' . $this->app_id];
            }
        }

        $sres = $this->getSession($toUser, true, $toUser['room_owner_uid'] > 0 ? 1 : 0);

        if ($sres['code'] != 1) {
            return $sres;
        }

        $session = $sres['session'];

        return ['code' => 1, 'msg' => '会话创建成功', 'session_id' => $session['id'], 'session' => $session, 'to_user' => $toUser];
    }

    /**
     * Undocumented function
     *
     * @param int $to_uid
     * @return array
     */
    public function connectToSession($session_id)
    {
        $session = model\WokChatSession::where(['id' => $session_id, 'app_id' => $this->app_id])->find();

        if (!$session) {
            return ['code' => 0, 'msg' => '会话不存在,session_id:' . $session_id . ',app_id:' . $this->app_id];
        }

        $sys_to_uid = $this->getSysToUid($this->sys_uid, $session);

        if (!$sys_to_uid) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        $toUser = $this->getUserBySysId($sys_to_uid, ['auto_reply', 'auto_reply_offline']);

        if (!$toUser) {
            return ['code' => 0, 'msg' => '接收用户不存在'];
        }

        return ['code' => 1, 'msg' => '会话创建成功', 'session_id' => $session['id'], 'session' => $session, 'to_user' => $toUser];
    }

    // 过滤掉emoji表情
    protected function filterEmoji($str)
    {
        $str = preg_replace_callback(    //执行一个正则表达式搜索并且使用一个回调进行替换
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '/e:' . base64_encode($match[0]) . 'e/' : $match[0];
            },
            $str
        );

        return $str;
    }

    // 过滤还原emoji表情
    protected function recoverEmoji($str)
    {
        $str = preg_replace_callback(    //执行一个正则表达式搜索并且使用一个回调进行替换
            '/\/e:(.+?)e\//is',
            function (array $match) {
                return base64_decode($match[1]);
            },
            $str
        );

        return $str;
    }

    /**
     * Undocumented function
     *
     * @param int $session_id
     * @param string $content
     * @param int $type
     * @return array
     */
    public function sendBySession($session_id, $content, $type)
    {
        $session = model\WokChatSession::where(['id' => $session_id, 'app_id' => $this->app_id])->find();

        if (!$session) {
            return ['code' => 0, 'msg' => '会话不存在'];
        }

        $to_uid = 0;

        $sys_to_uid = $this->getSysToUid($this->sys_uid, $session);

        if (!$sys_to_uid) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        $toUser = $this->getUserBySysId($sys_to_uid);

        if (!$toUser) {
            return ['code' => 0, 'msg' => '接收用户不存在'];
        }

        if ($type == 4) {
            if (mb_strlen($content) > 2500) {
                return ['code' => 0, 'msg' => '发送内容应在2500字以内'];
            }
        } else {
            if (mb_strlen($content) > 500) {
                return ['code' => 0, 'msg' => '发送内容应在500字以内'];
            }
        }



        $to_uid = $toUser['uid'];

        $msg = new model\WokChatMsg;

        $data = [
            'app_id' => $this->app_id,
            'from_uid' => $this->uid,
            'to_uid' => $to_uid,
            'sys_from_uid' => $this->sys_uid,
            'sys_to_uid' => $sys_to_uid,
            'content' => $this->filterEmoji($content),
            'session_id' => $session['id'],
            'type' => intval($type),
        ];

        $res = $msg->save($data);

        if ($res) {
            if ($session['sys_uid1'] == $this->sys_uid) {
                $session->save(['last_msg_id' => $msg['id'], 'last_read_id1' => $msg['id']]);
            } else {
                $session->save(['last_msg_id' => $msg['id'], 'last_read_id2' => $msg['id']]);
            }

            return ['code' => 1, 'msg' => '消息发送成功', 'session_id' => $session['id'], 'session' => $session, 'message_id' => $msg['id'], 'message' => $msg];
        }

        return ['code' => 0, 'msg' => '发送失败'];
    }

    /**
     * Undocumented function
     *
     * @param int $that_uid
     * @return array
     */
    public function createRoom($that_uid)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        $self = $this->user;

        $that = $this->getUserByUid($that_uid);

        if (empty($that)) {
            return ['code' => 0, 'msg' => 'that_uid:用户不存在'];
        }

        $room = new model\WokChatUser;

        $data = [
            'app_id' => $this->app_id,
            'uid' => 0,
            'nickname' => '群聊：' . $this->uid . '-' . date('YmdHis'),
            'remark' => '[' . $this->user['nickname'] . ']创建的群聊',
            'avatar' => '',
            'token' => md5($this->uid . '_' . $that_uid),
            'room_owner_uid' => $this->uid
        ];

        $res1 = 0;
        $res2 = $res3 = [];

        Db::startTrans();

        $res1 = $room->save($data);

        if ($res1) {

            $res2 = $this->userJoinRoom($self, $room);

            $res3 = $this->userJoinRoom($that, $room);

            $this->switchUser($self);
        }

        if ($res1 && $res2['code'] == 1 && $res3['code'] == 1) {
            Db::commit();

            return $res2;
        } else {

            trace("创建群聊失败，res2:" . $res2['msg']);
            trace("创建群聊失败，res3:" . $res3['msg']);

            Db::rollback();

            return ['code' => 0, 'msg' => '创建群聊失败'];
        }
    }

    /**
     * Undocumented function
     *
     * @param int $room_session_id
     * @param int $that_uid
     * @return array
     */
    public function addUserToRoom($room_session_id, $that_uid)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        $session = model\WokChatSession::where(['id' => $room_session_id, 'app_id' => $this->app_id])->find();

        if (!$session) {
            return ['code' => 0, 'msg' => '会话不存在'];
        }

        if (!$session['is_room']) {
            return ['code' => 0, 'msg' => '目标会话类型错误，不是群聊'];
        }

        $that = $this->getUserByUid($that_uid);

        $sys_to_uid = $this->getSysToUid($this->sys_uid, $session);

        if (!$sys_to_uid) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        $this->getUserBySysId($sys_to_uid);

        $toRoom = $this->getUserBySysId($sys_to_uid);

        if (!$toRoom) {
            return ['code' => 0, 'msg' => '群聊不存在'];
        }

        return $this->userJoinRoom($that, $toRoom);
    }

    /**
     * Undocumented function
     * 
     * @param array|\think\Model $userJoin
     * @param array|\think\Model $toRoom
     * @return array
     */
    protected function userJoinRoom($userJoin, $toRoom)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        $this->switchUser($userJoin);

        $fromUser = $this->user;

        if ($fromUser['room_owner_uid']) {
            return ['code' => 0, 'msg' => '系统错误,不能以群聊身份加入'];
        }

        if (!$toRoom['room_owner_uid']) {
            return ['code' => 0, 'msg' => '目标会话类型错误，不是群聊'];
        }

        $sres = $this->getSession($toRoom, true, true);

        if ($sres['code'] != 1) {
            return $sres;
        }

        $session = $sres['session'];

        $content = '[' . $userJoin['nickname'] . '加入群聊';

        $msg = new model\WokChatMsg;

        $data = [
            'app_id' => $this->app_id,
            'from_uid' => $this->uid,
            'to_uid' => 0,
            'sys_from_uid' => $fromUser['id'],
            'sys_to_uid' => $toRoom['id'],
            'content' => $this->filterEmoji($content),
            'session_id' => $session['id'],
            'type' => 0
        ];

        $res = $msg->save($data);

        if ($res) {
            $session->save(['last_msg_id' => $msg['id']]);
            return ['code' => 1, 'msg' => '加入成功', 'session_id' => $session['id'], 'session' => $session, 'message_id' => $msg['id'], 'message' => $msg];
        }

        return ['code' => 0, 'msg' => '加入失败'];
    }

    /**
     * Undocumented function
     *
     * @param int $session_id
     * @return array
     */
    public function createRoomBySession($session_id)
    {
        $session = model\WokChatSession::where(['id' => $session_id, 'app_id' => $this->app_id])->find();

        if (!$session) {
            return ['code' => 0, 'msg' => '会话不存在'];
        }

        if ($session['is_room']) {
            return ['code' => 0, 'msg' => '会话类型错误，不能从群聊创建另一个群聊'];
        }

        $sys_to_uid = $this->getSysToUid($this->sys_uid, $session);

        if (!$sys_to_uid) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        $toUser = $this->getUserBySysId($sys_to_uid);

        if (!$toUser) {
            return ['code' => 0, 'msg' => '接收用户不存在'];
        }

        return $this->createRoom($toUser['uid']);
    }

    /**
     * Undocumented function
     *
     * @param array $that
     * @param boolean $autoCreate
     * @return object|array|null|false
     */
    public function getSession($that, $autoCreate = true, $is_room = false)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if (empty($that)) {
            return ['code' => 0, 'msg' => '对方用户不能为空'];
        }

        $arr = [$this->user['id'], $that['id']];

        sort($arr, SORT_NUMERIC);

        $session = model\WokChatSession::where(['app_id' => $this->app_id, 'sys_uid1' => $arr[0], 'sys_uid2' => $arr[1]])->find();

        if ($session) {
            return ['code' => 1, 'msg' => '成功', 'session' => $session];
        }

        if (!$autoCreate) {
            return ['code' => 0, 'msg' => '会话不存在'];
        }

        $session = new model\WokChatSession;

        $data = [
            'app_id' => $this->app_id,
            'sys_uid1' => $arr[0],
            'sys_uid2' => $arr[1],
            'uid1' => $arr[0] == $this->user['id'] ? $this->user['uid'] : $that['uid'],
            'uid2' => $arr[1] == $this->user['id'] ? $this->user['uid'] : $that['uid'],
            'last_msg_id' => 0,
            'is_room' => $is_room ? 1 : 0,
        ];

        $res = $session->save($data);

        if ($res) {
            return ['code' => 1, 'msg' => '成功', 'session' => $session];
        }

        return ['code' => 0, 'msg' => '会话创建失败'];
    }

    /**
     * Undocumented function
     *
     * @param int $skip
     * @param int $pagesize
     * @param string $kwd
     * @return array
     */
    public function getSessionList($skip = 0, $pagesize = 20, $kwd = '')
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        $self = $this->user;

        if (!$self) {
            return ['code' => 0, 'msg' => '当前用户不能为空'];
        }

        $app_id = $this->app_id;
        $sys_uid = $this->sys_uid;

        $userFields = ['id', 'nickname', 'remark', 'avatar', 'uid', 'room_owner_uid'];
        $msgFields = ['id', 'content', 'type', 'from_uid', 'to_uid', 'create_time'];
        $sessionFields = ['id', 'last_msg_id', 'sys_uid1', 'sys_uid2', 'is_room', 'last_read_id1', 'last_read_id2', 'update_time'];

        $sessions = model\WokChatSession::whereRaw('app_id = :app_id and (sys_uid1 = :sys_uid1 or sys_uid2 = :sys_uid2) and last_msg_id > 0', ['app_id' => $app_id, 'sys_uid1' => $sys_uid, 'sys_uid2' => $sys_uid])
            ->order('rank desc,last_msg_id desc')
            ->with([
                'lastMsg' => function ($query) use ($msgFields) {
                    $query->field($msgFields);
                },
                'sysUser1' => function ($query) use ($userFields) {
                    $query->field($userFields);
                },
                'sysUser2' => function ($query) use ($userFields) {
                    $query->field($userFields);
                }
            ])
            ->field($sessionFields)
            ->limit($skip, $pagesize)
            ->select();

        $list = [];

        $where = [];

        $today = date('Y-m-d');

        foreach ($sessions as &$ses) {

            $ses['time'] = strstr($ses['update_time'], $today) ? date('H:i', strtotime($ses['update_time'])) : date('m-d H:i', strtotime($ses['update_time']));

            if ($ses['last_msg']) {
                $ses['last_msg']['content'] = $this->recoverEmoji($ses['last_msg']['content']);
                $ses['last_msg']['time'] = strstr($ses['last_msg']['create_time'], $today) ? date('H:i', strtotime($ses['last_msg']['create_time'])) : date('m-d H:i', strtotime($ses['last_msg']['create_time']));
            }

            if ($ses['sys_uid1'] == $self['id']) {
                $ses['that'] = $ses['sys_user2'];
            } else {
                $ses['that'] = $ses['sys_user1'];
            }

            if ($kwd) {
                if (!stripos($ses['that']['nickname'], $kwd) && !stripos($ses['that']['remark'], $kwd)) {
                    continue;
                }
            }

            $where = [];
            $where[] = ['app_id', '=', $this->app_id];

            if ($ses['is_room']) {

                $sys_to_uid = $this->getSysToUid($this->sys_uid, $ses);

                if (!$sys_to_uid) {
                    return ['code' => 0, 'msg' => '参数错误'];
                }

                $where[] = ['sys_to_uid', '=', $sys_to_uid];
            } else {
                $where[] = ['session_id', '=', $ses['id']];
            }

            if ($ses['sys_uid1'] == $this->sys_uid) {
                $where[] = ['id', '>', $ses['last_read_id1']];
            } else {
                $where[] = ['id', '>', $ses['last_read_id2']];
            }

            $ses['new_msg_count'] = model\WokChatMsg::where($where)->count();

            $ses = $ses->toArray();

            unset($ses['last_msg_id'], $ses['sys_user2'], $ses['sys_user1'], $ses['sys_uid1'], $ses['sys_uid2'], $ses['last_read_id1'], $ses['last_read_id2']);

            $list[] = $ses;
        }

        unset($ses);

        return ['code' => 1, 'msg' => '成功', 'list' => $list, 'has_more' => count($list) >= $pagesize];
    }

    /**
     * Undocumented function
     *
     * @param int $session_id
     * @param int $rank
     * @return array
     */
    public function setSessionRank($session_id, $rank)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if ($rank < 0) {
            return ['code' => 0, 'msg' => 'rank为大于0的整数'];
        }

        $session = model\WokChatSession::where(['id' => $session_id, 'app_id' => $this->app_id])->find();

        if (!$session) {
            return ['code' => 0, 'msg' => '会话不存在'];
        }

        $res = $session->save(['rank' => $rank]);

        if ($res) {
            return ['code' => 1, 'msg' => '设置成功'];
        }

        return ['code' => 0, 'msg' => '设置失败'];
    }

    /**
     * Undocumented function
     *
     * @param array $session_id
     * @param boolean $is_history
     * @param int $from_msg_id
     * @param int $pagesize
     * @return array
     */
    public function getMessageList($session_id, $is_history = false, $from_msg_id = 0, $pagesize = 10)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        $self = $this->user;

        if (!$self) {
            return ['code' => 0, 'msg' => '当前用户不能为空'];
        }

        $session = model\WokChatSession::where(['id' => $session_id, 'app_id' => $this->app_id])->find();

        if (!$session) {
            return ['code' => 0, 'msg' => '会话不存在'];
        }

        if ($session['sys_uid1'] != $this->sys_uid && $session['sys_uid2'] != $this->sys_uid) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        $where = [];
        $orderBy = '';
        $messages = [];

        $where[] = ['app_id', '=', $this->app_id];

        if ($session['is_room']) {

            $sys_to_uid = $this->getSysToUid($this->sys_uid, $session);

            if (!$sys_to_uid) {
                return ['code' => 0, 'msg' => '参数错误'];
            }

            $where[] = ['sys_to_uid', '=', $sys_to_uid];
        } else {
            $where[] = ['session_id', '=', $session['id']];
        }

        //读取历史消息记录
        if ($is_history) {

            if ($from_msg_id) {
                $where[] = ['id', '<', $from_msg_id];
            }
            $orderBy = 'id desc';
        }
        //读取新消息
        else {
            if ($from_msg_id == 0) { //但from_msg_id为0，一般打开对话后先查询历史消息，但如果错误的先调用新消息接口，会有此问题
                //读取最新的几条消息
                $ids = model\WokChatMsg::where($where)->order('id desc')->limit(0, $pagesize)->column('id');
                $where[] = ['id', 'in', $ids];
            } else {
                $where[] = ['id', '>', $from_msg_id];
            }
            $orderBy = 'id asc';
        }

        $userFields = ['id', 'nickname', 'remark', 'avatar', 'uid', 'room_owner_uid'];
        $msgFields = ['id', 'content', 'type', 'sys_from_uid', 'from_uid', 'to_uid', 'create_time'];

        $messages = model\WokChatMsg::where($where)
            ->order($orderBy)
            ->with([
                'fromUser' => function ($query) use ($userFields) {
                    $query->field($userFields);
                }
            ])
            ->field($msgFields)
            ->limit(0, $pagesize)
            ->select();

        $ids = [];

        $today = date('Y-m-d');

        foreach ($messages as &$msg) {
            $msg['content'] = $this->recoverEmoji($msg['content']);
            $msg['time'] = strstr($msg['create_time'], $today) ? date('H:i', strtotime($msg['create_time'])) : date('m-d H:i', strtotime($msg['create_time']));
            if ($msg['type'] == 4) {
                $msg['content'] = json_decode($msg['content'], true); //自定义内容，转换为json
            }
            $ids[] = $msg['id'];
        }

        unset($msg);

        if (count($ids)) {

            $maxId = max($ids);

            if ($this->sys_uid == $session['sys_uid1']) {
                if ($session['last_read_id1'] < $maxId) {
                    $session['last_read_id1'] = $maxId;
                    $session->save();
                }
            } else {
                if ($session['last_read_id2'] < $maxId) {
                    $session['last_read_id2'] = $maxId;
                    $session->save();
                }
            }
        }

        $to_uid = $this->sys_uid == $session['sys_uid1'] ? $session['uid2'] : $session['uid1'];

        $to_user = $this->getUserByUid($to_uid, ['auto_reply', 'auto_reply_offline']);
        $self = $this->getUserByUid($to_uid, ['auto_reply', 'auto_reply_offline']);

        return [
            'code' => 1,
            'msg' => '成功',
            'list' => $messages,
            'has_more' => count($messages) >= $pagesize,
            'to_user' => $to_user,
            'self' => $self,
            'session' => $session
        ];
    }

    public function getNewMessageCount()
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        $self = $this->user;

        if (!$self) {
            return ['code' => 0, 'msg' => '当前用户不能为空'];
        }

        $app_id = $this->app_id;
        $sys_uid = $this->sys_uid;

        $sessions = model\WokChatSession::whereRaw('app_id = :app_id and (sys_uid1 = :sys_uid1 or sys_uid2 = :sys_uid2) and last_msg_id > 0', ['app_id' => $app_id, 'sys_uid1' => $sys_uid, 'sys_uid2' => $sys_uid])->select();

        $where = [];
        $count = 0;

        foreach ($sessions as &$ses) {
            $where = [];
            $where[] = ['app_id', '=', $this->app_id];

            if ($ses['is_room']) {

                $sys_to_uid = $this->getSysToUid($this->sys_uid, $ses);

                if (!$sys_to_uid) {
                    return ['code' => 0, 'msg' => '参数错误'];
                }

                $where[] = ['sys_to_uid', '=', $sys_to_uid];
            } else {
                $where[] = ['session_id', '=', $ses['id']];
            }

            if ($ses['sys_uid1'] == $this->sys_uid) {
                $where[] = ['id', '>', $ses['last_read_id1']];
            } else {
                $where[] = ['id', '>', $ses['last_read_id2']];
            }

            $count += model\WokChatMsg::where($where)->count();
        }

        unset($ses);

        return ['code' => 1, 'msg' => '成功', 'count' => $count];
    }

    /**
     * Undocumented function
     *
     * @param int $session
     * @return array
     */
    public function getRoomSessionUsers($sys_to_uid)
    {
        if (!$sys_to_uid) {
            return [];
        }

        $users = model\WokChatSession::where('sys_to_uid', $sys_to_uid)
            ->column('from_uid');

        return $users;
    }

    /**
     * Undocumented function
     *
     * @param int $uid
     * @param array $apend_fileds
     * @return object|array|null|false
     */
    public function getUserByUid($uid, $apend_fileds = [])
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return null;
        }

        $userFields = array_merge(['id', 'nickname', 'remark', 'avatar', 'uid', 'room_owner_uid'], $apend_fileds);

        return model\WokChatUser::where(['app_id' => $this->app_id, 'uid' => $uid])
            ->field($userFields)
            ->find();
    }

    /**
     * Undocumented function
     *
     * @param int $sys_uid
     * @param array $apend_fileds
     * @return object|array|null|false
     */
    public function getUserBySysId($sys_uid, $apend_fileds = [])
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return null;
        }

        $userFields = array_merge(['id', 'nickname', 'remark', 'avatar', 'uid', 'room_owner_uid'], $apend_fileds);

        return model\WokChatUser::where(['app_id' => $this->app_id, 'id' => $sys_uid])
            ->field($userFields)
            ->find();
    }

    /**
     * Undocumented function
     * @param int $self_sys_uid
     * @param array|mixed $session
     * @return int|false that_sys_uid
     */
    public function getSysToUid($this_sys_uid, $session)
    {
        if ($session['sys_uid1'] == $this_sys_uid) {
            return $session['sys_uid2'];
        } else if ($session['sys_uid2'] == $this_sys_uid) {
            return $session['sys_uid1'];
        }

        return 0;
    }

    /**
     * 文件上传
     * @param string $type 文件类型
     * @return array
     */
    public function upfile($type)
    {
        $is_rand_name = 1;
        $config = BuilderModule::getInstance()->getConfig();

        if ($type == 'image') {
            $_config['allowSuffix'] = ['jpg', 'jpeg', 'png', 'gif'];
            $_config['maxSize'] = 1024 * 1024 * 1;
        } else if ($type == 'audio') {
            $_config['allowSuffix'] = ['m4a', 'mp3', 'wav', 'aac', 'ogg'];
            $_config['maxSize'] = 1024 * 1024 * 10;
        } else if ($type == 'video') {
            $_config['allowSuffix'] = ['mp4', 'avi', 'mkv', 'mov', 'wmv'];
            $_config['maxSize'] = 1024 * 1024 * 100;
        } else {
            $_config['allowSuffix'] = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
            $_config['maxSize'] = 1024 * 1024 * 10;
        }

        if ($is_rand_name == 'n') {
            $_config['isRandName'] = 0;
        } else if ($is_rand_name == 'y') {
            $_config['isRandName'] = 1;
        } else {
            $_config['isRandName'] = $config['is_rand_name'];
        }

        $_config['fileByDate'] = $config['file_by_date'];

        $storageDriver = $config['storage_driver'];
        $storageDriver = empty($storageDriver) || !class_exists($storageDriver)
            ? \tpext\builder\logic\LocalStorage::class : $storageDriver;

        $driver = new $storageDriver;
        $_config['driver'] = $driver;
        $_config['imageDriver'] = new \tpext\builder\logic\ImageHandler;
        $_config['imageCommonds'] = [];
        if ($config['image_water']) {
            $_config['imageCommonds'][] = [
                'name' => 'water',
                'args' => ['imgPath' => $config['image_water'], 'position' => $config['image_water_position']],
                'is_global_config' => 'image_water',
            ];
        }
        if ($config['image_size_limit']) {
            $arr = explode(',', $config['image_size_limit']);
            if (count($arr) > 1 && (intval($arr[0]) > 0 || intval($arr[0]) > 0)) {
                $_config['imageCommonds'][] = [
                    'name' => 'resize',
                    'args' => ['width' => intval($arr[0]) ?: null, 'height' => intval($arr[1]) ?: null],
                    'is_global_config' => 'image_size_limit',
                ];
            }
        }

        $_config['user_id'] = $this->sys_uid;
        $_config['dirName'] = 'wokchat/' . $this->app_id . '/' . $type; //存放路径
        $up = new UploadTool($_config);
        $newPath = $up->uploadFile('file');
        if ($newPath === false) {
            return [
                'code' => 0,
                'msg' => __blang('bilder_file_uploading_failed') . '-' . $up->errorInfo,
            ];
        } else {
            return [
                'code' => 1,
                'url' => '//' . request()->host() . $newPath,
                'file_path' => $newPath
            ];
        }
    }
}
