<?php

namespace wokmanchat\common\logic;

use wokmanchat\common\model;
use think\Db;

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

    /**
     * 重置用户状态
     *
     * @return void
     */
    public function reset()
    {
        $this->app_id = 0;
        $this->uid = 0;
        $this->sys_uid = 0;
        $this->user = null;
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

        if (time() - $time > 10) {
            return ['code' => 0, 'msg' => 'sign超时请检查设备时间'];
        }

        $app = model\WokChatApp::where('id', $app_id)->find();

        if (!$app) {
            return ['code' => 0, 'msg' => 'app_id:应用未找到'];
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
     * @param array $user
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

        $toUser = $this->getUserByUid($to_uid);

        if (!$toUser) {

            return ['code' => 0, 'msg' => '接收用户不存在,uid:' . $to_uid . ',app_id:' . $this->app_id];
        }

        $sres = $this->getSession($toUser, true, $toUser['room_owner_uid'] > 0 ? 1 : 0);

        if ($sres['code'] != 1) {
            return $sres;
        }

        $session = $sres['session'];

        return ['code' => 1, 'msg' => '会话创建成功', 'session_id' => $session['id'], 'session' => $session, 'toUser' => $toUser];
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

        $toUser = $this->getUserBySysId($sys_to_uid);

        if (!$toUser) {
            return ['code' => 0, 'msg' => '接收用户不存在'];
        }

        return ['code' => 1, 'msg' => '会话创建成功', 'session_id' => $session['id'], 'session' => $session, 'toUser' => $toUser];
    }

    // 过滤掉emoji表情
    protected function filterEmoji($str)
    {
        $str = preg_replace_callback(    //执行一个正则表达式搜索并且使用一个回调进行替换
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '?' : $match[0];
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

        if (mb_strlen($content) > 500) {
            return ['code' => 0, 'msg' => '发送内容应在500字以内'];
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

            $res2 = $this->userJoinRoom($self, $room, '[' . $self['nickname'] . ']创建群聊');

            $res3 = $this->userJoinRoom($that, $room, '[' . $that['nickname'] . '加入群聊');

            $this->switchUser($self);
        }

        if ($res1 &&  $res2['code'] == 1 && intval($res3['code']) == 1) {
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
     * @param array $userJoin
     * @param array $toRoom
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

        $sessions = model\WokChatSession::whereRaw('app_id = :app_id and (sys_uid1 = :sys_uid1 or sys_uid2 = :sys_uid2) and last_msg_id > 0', ['app_id' => $app_id, 'sys_uid1' => $sys_uid, 'sys_uid2' => $sys_uid])
            ->order('last_msg_id desc,rank desc')
            ->with(['lastMsg'])
            ->limit($skip, $pagesize)
            ->select();

        $list = [];

        $where = [];

        $today = date('Y-m-d');

        foreach ($sessions as &$ses) {

            $ses['time'] = strstr($ses['update_time'], $today) ? date('H:i', strtotime($ses['update_time'])) : date('m-d H:i', strtotime($ses['update_time']));

            if ($ses['last_msg']) {
                $ses['last_msg']['time'] = strstr($ses['last_msg']['create_time'], $today) ? date('H:i', strtotime($ses['last_msg']['create_time'])) : date('m-d H:i', strtotime($ses['last_msg']['create_time']));
            }

            if ($ses['sys_uid1'] == $self['id']) {
                $ses['that'] = $this->getUserBySysId($ses['sys_uid2']);
            } else {
                $ses['that'] = $this->getUserBySysId($ses['sys_uid1']);
            }

            if ($kwd) {
                if (!stripos($ses['that']['nickname'], $kwd) && !stripos($ses['that']['remark'], $kwd)) {
                    continue;
                }
            }
            //

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
     * @return boolean|string
     */
    public function setSessionRank($session_id, $rank)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if ($rank < 0 || $rank > 99) {
            return ['code' => 0, 'msg' => 'rank为0~99之间的整数'];
        }

        $session = model\WokChatSession::where(['id' => $session_id, 'app_id' => $this->app_id])->find();

        if (!$session) {
            return ['code' => 0, 'msg' => '会话不存在'];
        }

        $res =  $session->save(['rank' => $rank]);

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

        if ($is_history) {
            if ($from_msg_id) {
                $where[] = ['id', '<', $from_msg_id];
            }
            $orderBy = 'id desc';
        } else {
            $where[] = ['id', '>', $from_msg_id];
            $orderBy = 'id asc';
        }

        $messages = model\WokChatMsg::where($where)->with(['fromUser'])->order($orderBy)->limit(0, $pagesize)->select();

        $ids = [];

        $today = date('Y-m-d');



        foreach ($messages as &$msg) {
            $msg['time'] = strstr($msg['create_time'], $today) ? date('H:i', strtotime($msg['create_time'])) : date('m-d H:i', strtotime($msg['create_time']));
            if ($msg['type'] == 4) {
                $msg['content'] = json_decode($msg['content'], true); //自定义内容，转换为json
            }
            $ids[] = $msg['id'];

            if ($msg['fromUser']) {
                unset($msg['fromUser']['app_id'], $msg['fromUser']['login_time'], $msg['fromUser']['create_time'], $msg['fromUser']['update_time']);
            }
        }

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

        unset($msg);

        return ['code' => 1, 'msg' => '成功', 'list' => $messages, 'has_more' => count($messages) >= $pagesize];
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

        $users = model\WokChatSession::where(['sys_to_uid', '=', $sys_to_uid])
            ->column('from_uid');

        return $users;
    }

    /**
     * Undocumented function
     *
     * @param int $uid
     * @return object|array|null|false
     */
    public function getUserByUid($uid)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return null;
        }

        return model\WokChatUser::where(['app_id' => $this->app_id, 'uid' => $uid])
            ->field('id,nickname,remark,avatar,uid,room_owner_uid')
            ->find();
    }

    /**
     * Undocumented function
     *
     * @param int $sys_uid
     * @return object|array|null|false
     */
    public function getUserBySysId($sys_uid)
    {
        $valdate = $this->isValidateUser();

        if ($valdate['code'] != 1) {
            return null;
        }

        return model\WokChatUser::where(['app_id' => $this->app_id, 'id' => $sys_uid])
            ->field('id,nickname,remark,avatar,uid,room_owner_uid')
            ->find();
    }

    /**
     * Undocumented function
     * @param int $self_sys_uid
     * @param array $session
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
}
