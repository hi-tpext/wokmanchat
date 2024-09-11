<?php

namespace wokmanchat\api\controller;

use think\Controller;
use wokmanchat\common\logic\ChatApp;
use wokmanchat\common\logic\ChatUser;
use wokmanchat\common\model;

/**
 * 管理接口
 */
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
     * @return array
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

    /**
     * 同步用户信息
     * 把外部系统用户推送到聊天系统
     * @return mixed
     */
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
            //'token|用户token' => 'require'
            //'auto_reply|自动回复' => 'require'
            //'auto_reply_offline|自动回复[离线]' => 'require'
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        if (!isset($data['token'])) {
            $data['token'] = '';
        }

        if (!isset($data['remark'])) {
            $data['remark'] = $data['nickname'];
        }

        if (!isset($data['avatar'])) {
            $data['avatar'] = '';
        }

        if (!isset($data['auto_reply'])) {
            $data['auto_reply'] = '';
        }

        if (!isset($data['auto_reply_offline'])) {
            $data['auto_reply_offline'] = '';
        }

        $res = $this->appLogic->pushUser($data['uid'], $data['nickname'], $data['remark'], $data['avatar'], $data['token'], $data['auto_reply'], $data['auto_reply_offline']);

        return json($res);
    }

    /**
     * 创建消息
     * [脱离聊天界面]直接在聊天系统中添加一条消息
     *
     * @return mixed
     */
    public function createMsg()
    {
        $data = request()->post();

        $valdate = $this->validateApp($data);
        if ($valdate['code'] != 1) {
            return json($valdate);
        }

        $result = $this->validate($data, [
            'uid|发送用户uid' => 'require|number',
            'to_uid|接收用户uid' => 'require|number',
            'content|发送内容' => 'require',
            'type|消息类型' => 'require|number|gt:0',
        ]);

        if ($result !== true) {
            return json([
                'code' => 0,
                'msg' => $result
            ]);
        }

        $user = model\WokChatUser::where(['app_id' => $data['app_id'], 'uid' => $data['uid']])->find();

        if (!$user) {
            return json([
                'code' => 0,
                'msg' => '用户不存在:' . $data['uid']
            ]);
        }

        $this->userLogic->switchUser($user);

        //获取会话
        $res = $this->userLogic->connectToUser($data['to_uid']);

        if ($res['code'] != 1) {
            return json($res);
        }

        $res =  $this->userLogic->sendBySession($res['session']['id'], $data['content'], $data['type']);

        if ($res['code'] == 1) {
            $client = stream_socket_client('tcp://127.0.0.1:11220', $errno, $errstr, 1);
            $data = ['action' => 'new_message_notify', 'session' => $res['session'], 'from_uid' => $data['uid']];

            fwrite($client, json_encode($data) . "\n");
            // 读取推送结果
            $result = fread($client, 8192) ?: 'failed';

            $res['push_result'] = $result;
        }

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
        if (!isset($data['pagesize'])) {
            $data['pagesize'] = 10;
        }

        $user = model\WokChatUser::where(['app_id' => $data['app_id'], 'uid' => $data['uid']])->find();

        if (!$user) {
            return json([
                'code' => 0,
                'msg' => '用户不存在:' . $data['uid']
            ]);
        }

        $this->userLogic->switchUser($user);

        $res = $this->userLogic->getSessionList($data['skip'], $data['pagesize'], $data['kwd']);

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

        $user = model\WokChatUser::where(['app_id' => $data['app_id'], 'uid' => $data['uid']])->find();

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

        $user = model\WokChatUser::where(['app_id' => $data['app_id'], 'uid' => $data['uid']])->find();

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

        $user = model\WokChatUser::where(['app_id' => $data['app_id'], 'uid' => $data['uid']])->find();

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
