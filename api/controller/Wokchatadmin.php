<?php

namespace wokmanchat\api\controller;

use think\Controller;
use wokmanchat\common\logic\ChatApp;
use wokmanchat\common\logic\ChatUser;
use wokmanchat\common\model;

class Wokchatadmin extends Controller
{
    /**
     * Undocumented variable
     *
     * @var ChatApp
     */
    protected $appLogic;

    /**
     * Undocumented variable
     *
     * @var ChatUser
     */
    protected $userLogic;

    protected function initialize()
    {
        $this->appLogic = new ChatApp;
        $this->userLogic = new ChatUser;
    }

    /**
     * Undocumented function
     *
     * @param array|null $data
     * @return void
     */
    private function validateApp($data = null)
    {
        if ($data == null) {
            $data = request()->post();
        }

        if (isset($data['secret'])) {
            return ['code' => 0, 'msg' => '不要传secret参数'];
        }

        $result = $this->validate($data, [
            'app_id|应用app_id' => 'require|number',
            'sign|sign签名' => 'require',
            'time|时间戳' => 'require|number',
        ]);

        if ($result !== true) {
            return ['code' => 0, 'msg' => $result];
        }

        $res = $this->appLogic->validateApp($data['app_id'], $data['sign'], $data['time']);

        return $res;
    }

    public function pushUser()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);

        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|用户uid' => 'require|number',
            'nickname|用户昵称' => 'require',
            //'remark|用户备注' => 'require',
            //'avatar|用户头像' => 'require',
            'token|用户token' => 'require'
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        if (!isset($data['remark'])) {
            $data['remark'] = $data['nickname'];
        }

        if (!isset($data['avatar'])) {
            $data['avatar'] = '';
        }

        $res = $this->appLogic->pushUser($data['uid'], $data['nickname'], $data['remark'], $data['avatar'], $data['token']);

        return json($res);
    }

    public function editUser()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|用户uid' => 'require'
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editUser($data['uid'], $data);

        return json($res);
    }

    public function editUserToken()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);

        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|用户uid' => 'require',
            'token|用户token' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editUserToken($data['uid'], $data['token']);

        return json($res);
    }

    public function editUserNickname()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);

        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|用户uid' => 'require',
            'nickname|用户昵称' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editUserNickname($data['uid'], $data['nickname']);

        return json($res);
    }

    public function editUserRemark()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);

        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|用户uid' => 'require',
            'remark|用户备注' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editUserRemark($data['uid'], $data['remark']);

        return json($res);
    }

    public function editUserAvatar()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);

        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|用户uid' => 'require',
            'avatar|用户头像' => 'require',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $res = $this->appLogic->editUserAvatar($data['uid'], $data['avatar']);

        return json($res);
    }

    public function getSessionList()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|当前用户uid' => 'require|number',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        if (!isset($data['skip'])) {
            $data['skip'] = 0;
        }
        if (!isset($data['kwd'])) {
            $data['kwd'] = '';
        }

        $user = model\WokChatUser::where(['app_id' => $this->app_id, 'uid' => $data['uid']])->find();

        if (!$user) {
            return json([
                'code' => 0,
                'msg' => '用户不存在:' . $data['uid']
            ]);
        }

        $this->userLogic->switchUser($user);

        $res = $this->userLogic->getSessionList($data['skip'], $data['kwd']);

        return json($res);
    }

    public function getHistoryMessageList()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|当前用户uid' => 'require|number',
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

        $user = model\WokChatUser::where(['app_id' => $this->app_id, 'uid' => $data['uid']])->find();

        if (!$user) {
            return json([
                'code' => 0,
                'msg' => '用户不存在:' . $data['uid']
            ]);
        }

        $this->userLogic->switchUser($user);

        $res = $this->userLogic->getMessageList($data['session_id'], true, $data['session_id'], $data['pagesize']);

        return json($res);
    }

    public function getNewMessageList()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|当前用户uid' => 'require|number',
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

        $user = model\WokChatUser::where(['app_id' => $this->app_id, 'uid' => $data['uid']])->find();

        if (!$user) {
            return json([
                'code' => 0,
                'msg' => '用户不存在:' . $data['uid']
            ]);
        }

        $this->userLogic->switchUser($user);

        $res = $this->userLogic->getMessageList($data['session_id'], false, $data['session_id'], $data['pagesize']);

        return json($res);
    }

    public function getNewMessageCount()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|当前用户uid' => 'require|number',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $user = model\WokChatUser::where(['app_id' => $this->app_id, 'uid' => $data['uid']])->find();

        if (!$user) {
            return json([
                'code' => 0,
                'msg' => '用户不存在:' . $data['uid']
            ]);
        }

        $this->userLogic->switchUser($user);

        $res = $this->userLogic->getNewMessageCount();

        return json($res);
    }
}
