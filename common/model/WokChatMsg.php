<?php

namespace wokmanchat\common\model;

use think\Model;

class WokChatMsg extends Model
{
    protected $name = 'wok_chat_msg';

    protected $autoWriteTimestamp = 'datetime';

    protected $updateTime = false;

    //tp6模型关联字段驼峰转下划线
    protected $mapping = [
        'fromUser' => 'from_user',
        'toUser' => 'to_user',
    ];

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
