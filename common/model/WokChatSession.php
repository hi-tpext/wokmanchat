<?php

namespace wokmanchat\common\model;

use think\Model;

class WokChatSession extends Model
{
    protected $name = 'wok_chat_session';

    protected $autoWriteTimestamp = 'datetime';

    public function lastMsg()
    {
        return $this->belongsTo(WokChatMsg::class, 'last_msg_id', 'id');
    }

    public function app()
    {
        return $this->belongsTo(WokChatApp::class, 'app_id', 'id');
    }

    public function sysUser1()
    {
        return $this->belongsTo(WokChatUser::class, 'sys_uid1', 'id');
    }

    public function sysUser2()
    {
        return $this->belongsTo(WokChatUser::class, 'sys_uid2', 'id');
    }
}
