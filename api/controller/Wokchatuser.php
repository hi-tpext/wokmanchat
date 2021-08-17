<?php

namespace wokmanchat\api\controller;

use think\Controller;
use wokmanchat\common\logic\ChatUser;

class Wokchatuser extends Controller
{
    /**
     * Undocumented variable
     *
     * @var ChatUser
     */
    protected $userLogic;

    protected function initialize()
    {
        $this->userLogic = new ChatUser;
    }

    private function validateUser($data = null)
    {
        if ($data == null) {
            $data = request()->post();
        }

        if (isset($data['token'])) {
            return ['code' => 0, 'msg' => '不要传token参数'];
        }

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

        return $res;
    }

    public function connectToUser()
    {
        $data = request()->post();
        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'to_uid|目标用户uid' => 'require|number'
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->userLogic->connectToUser($data['to_uid']);

        return json($res);
    }

    public function getSessionList()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        if (!isset($data['skip'])) {
            $data['skip'] = 0;
        }
        if (!isset($data['kwd'])) {
            $data['kwd'] = '';
        }


        $res = $this->userLogic->getSessionList($data['skip'], $data['kwd']);

        return json($res);
    }

    public function sendBySession()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'session_id|会话id' => 'require|number',
            'content|发送内容' => 'require|number',
            'type|消息类型' => 'require|number|gt:0'
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->userLogic->sendBySession($data['session_id'], $data['content'], $data['type']);

        return json($res);
    }

    public function createRoom()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'that_uid|用户uid' => 'require|number'
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->userLogic->createRoom($data['that_uid']);

        return json($res);
    }

    public function createRoomBySession()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'session_id|会话id' => 'require|number',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->userLogic->createRoomBySession($data['session_id']);

        return json($res);
    }

    public function addUserToRoom()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'room_session_id|群聊会话id' => 'require|number',
            'that_uid|用户uid' => 'require|number'
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->userLogic->addUserToRoom($data['room_session_id'], $data['that_uid']);

        return json($res);
    }

    public function setSessionRank()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'session_id|会话id' => 'require|number',
            'rank|权重' => 'require|number',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->userLogic->setSessionRank($data['session_id'], $data['rank']);

        return json($res);
    }

    public function getHistoryMessageList()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'session_id|会话id' => 'require|number',
            'from_msg_id' => 'number',
            'pagesize' => 'number',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        if (!isset($data['from_msg_id'])) {
            $data['from_msg_id'] = 0;
        }

        if (!isset($data['pagesize'])) {
            $data['pagesize'] = 10;
        }

        $res = $this->userLogic->getMessageList($data['session_id'], true, $data['session_id'], $data['pagesize']);

        return json($res);
    }

    public function getNewMessageList()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'session_id|会话id' => 'require|number',
            'from_msg_id' => 'number',
            'pagesize' => 'number',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        if (!isset($data['from_msg_id'])) {
            $data['from_msg_id'] = 0;
        }

        if (!isset($data['pagesize'])) {
            $data['pagesize'] = 10;
        }

        $res = $this->userLogic->getMessageList($data['session_id'], false, $data['session_id'], $data['pagesize']);

        return json($res);
    }

    public function getNewMessageCount()
    {
        $data = request()->post();

        $valdate = $this->validateUser($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $res = $this->userLogic->getNewMessageCount();

        return json($res);
    }
}
