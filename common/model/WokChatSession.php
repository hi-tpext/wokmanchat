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
}
