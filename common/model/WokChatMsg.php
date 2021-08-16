<?php

namespace wokmanchat\common\model;

use think\Model;

class WokChatMsg extends Model
{
    protected $name = 'wok_chat_msg';

    protected $autoWriteTimestamp = 'datetime';

    protected $updateTime = false;

    public function fromUser()
    {
        return $this->belongsTo(WokChatUser::class, 'sys_from_uid', 'id');
    }

    public function toUser()
    {
        return $this->belongsTo(WokChatUser::class, 'sys_to_uid', 'id');
    }

    public function app()
    {
        return $this->belongsTo(WokChatApp::class, 'app_id', 'id');
    }
}
