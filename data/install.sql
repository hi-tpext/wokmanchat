CREATE TABLE IF NOT EXISTS `__PREFIX__wok_chat_app` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '应用App_id',
  `enable` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '启用',
  `create_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '更新时间',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '名称',
  `secret` varchar(55) NOT NULL DEFAULT '' COMMENT '应用Secret_key',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10001 DEFAULT CHARSET=utf8 COMMENT='聊天应用';

CREATE TABLE IF NOT EXISTS `__PREFIX__wok_chat_msg` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `app_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '应用App_id',
  `content` varchar(500) NOT NULL DEFAULT '' COMMENT '消息内容',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '消息类型',
  `session_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会话id',
  `from_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发送用户外部id',
  `to_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '接收用户外部id',
  `sys_from_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发送用户内部id',
  `sys_to_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '接收用户内部id',
  `create_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '添加时间',
  `delete_time` varchar(55) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_app_id` (`app_id`),
  KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='聊天消息';

CREATE TABLE IF NOT EXISTS `__PREFIX__wok_chat_session` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `app_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '应用App_id',
  `last_msg_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后一条消息',
  `rank` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '优先级',
  `sys_uid1` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户1内部id',
  `sys_uid2` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户2内部id',
  `uid1` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户1外部id',
  `uid2` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户2外部id',
  `is_room` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否为房间',
  `last_read_id1` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户1已读id',
  `last_read_id2` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户2已读id',
  `create_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '更新时间',
  `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_app_id` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='聊天会话';

CREATE TABLE `__PREFIX__wok_chat_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `app_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '应用App_id',
  `nickname` varchar(55) NOT NULL DEFAULT '' COMMENT '昵称',
  `avatar` varchar(200) NOT NULL DEFAULT '' COMMENT '头像',
  `remark` varchar(55) NOT NULL DEFAULT '' COMMENT '备注',
  `token` varchar(100) NOT NULL DEFAULT '' COMMENT 'token',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会员外部id',
  `room_owner_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '房间管理员',
  `login_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '登录时间',
  `create_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_app_id` (`app_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10001 DEFAULT CHARSET=utf8 COMMENT='聊天用户';
