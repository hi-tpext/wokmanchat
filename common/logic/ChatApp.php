<?php

namespace wokmanchat\common\logic;

use wokmanchat\common\model;
use wokmanchat\common\Module;

/**
 * 封装后台操作，添加用户、修改用户
 */

class ChatApp
{
    protected $app_id = 0;

    /**
     * Undocumented variable
     *
     * @var model\WokChatApp
     */
    protected $app = null;

    protected $config = [];

    public function __construct()
    {
        $this->config = Module::getInstance()->getConfig();
    }

    /**
     * Undocumented function
     *
     * @param int $app_id
     * @param string $sign
     * @param int $time
     * @return array
     */
    public function validateApp($app_id, $sign, $time)
    {
        if (empty($app_id) || empty($sign) || empty($time)) {
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
            return ['code' => 0, 'msg' => '应用未开启'];
        }

        if (empty($app['secret'])) {
            return ['code' => 0, 'msg' => '系统错误，secret配置有误'];
        }

        if ($sign != md5($app['secret'] . $time)) {
            return ['code' => 0, 'msg' => 'sign验证失败'];
        }

        unset($app['secret']);

        $this->app = $app;
        $this->app_id = $app_id;

        return ['code' => 1, 'msg' => '成功'];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function isValidateApp()
    {
        if (empty($this->app) || empty($this->app_id)) {
            return ['code' => 0, 'msg' => 'app验证未通过'];
        }

        return ['code' => 1, 'msg' => '成功'];
    }

    /**
     * 免验证，切换到用户
     *
     * @param array $app
     * @return void
     */
    public function switchApp($app)
    {
        unset($user['secret']);
        $this->app = $app;
        $this->app_id = $app['id'];
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
     * @param string $uid
     * @param string $nickname
     * @param string $remark
     * @param string $avatar
     * @param string $token
     * @return array
     */
    public function pushUser($uid, $nickname, $remark, $avatar, $token)
    {
        $valdate = $this->isValidateApp();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if (empty($remark)) {
            $remark = $nickname;
        }

        if (empty($avatar)) {
            $avatar = 'http://' . request()->host() . '/assets/wokmanchat/images/avatar.png';
        }

        if ($exist = model\WokChatUser::where(['app_id' => $this->app_id, 'uid' => $uid])->find()) {
            $res = $exist->save([
                'nickname' => $nickname,
                'remark' => $remark,
                'avatar' => $avatar,
                'token' => $token,
            ]);

            if ($res) {
                return ['code' => 1, 'msg' => '成功'];
            }

            return ['code' => 0, 'msg' => '保存失败'];
        }

        $user = new model\WokChatUser;

        $data = [
            'app_id' => $this->app_id,
            'uid' => $uid,
            'nickname' => $this->filterEmoji($nickname),
            'remark' => $this->filterEmoji($remark),
            'avatar' => $avatar,
            'token' => $token,
            'room_owner_uid' => 0
        ];

        $res = $user->save($data);

        if ($res) {
            return ['code' => 1, 'msg' => '成功'];
        }

        return ['code' => 0, 'msg' => '添加失败'];
    }

    /**
     * Undocumented function
     *
     * @param int $uid
     * @param array $data
     * @return array
     */
    public function editUser($uid, $data)
    {
        $valdate = $this->isValidateApp();

        if ($valdate['code'] != 1) {
            return $valdate;
        }

        if ($exist = model\WokChatUser::where(['uid' => $uid, 'app_id' => $this->app_id])->find()) {
            $res = $exist->allowField(['nickname', 'remark', 'avatar', 'token'])->save($data);

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
     * @param int $uid
     * @param string $token
     * @return array
     */
    public function editUserToken($uid, $token)
    {
        return $this->editUser($uid, ['token' => $token]);
    }

    /**
     * Undocumented function
     *
     * @param int $uid
     * @param string $nickname
     * @return array
     */
    public function editUserNickname($uid, $nickname)
    {
        return $this->editUser($uid, ['nickname' => $this->filterEmoji($nickname)]);
    }

    /**
     * Undocumented function
     *
     * @param int $uid
     * @param string $remark
     * @return array
     */
    public function editUserRemark($uid, $remark)
    {
        return $this->editUser($uid, ['remark' => $this->filterEmoji($remark)]);
    }

    /**
     * Undocumented function
     *
     * @param int $uid
     * @param string $avatar
     * @return array
     */
    public function editUserAvatar($uid, $avatar)
    {
        return $this->editUser($uid, ['avatar' => $avatar]);
    }
}
