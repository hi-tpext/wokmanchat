<?php

namespace wokmanchat\common\model;

use think\Model;

class WokChatUser extends Model
{
    protected $name = 'wok_chat_user';

    protected $autoWriteTimestamp = 'datetime';

    //tp6模型关联字段驼峰转下划线
    protected $mapping = [
        'roomOwner' => 'room_owner'
    ];

    public function app()
    {
        return $this->belongsTo(WokChatApp::class, 'app_id', 'id');
    }

    public function roomOwner()
    {
        return $this->belongsTo(WokChatUser::class, 'room_owner_uid', 'id');
    }
}
