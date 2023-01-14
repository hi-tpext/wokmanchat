ALTER TABLE `__PREFIX__wok_chat_app`
	ADD `push_url` varchar(255) NOT NULL DEFAULT '' COMMENT '事件推送url' COLLATE 'utf8_general_ci' AFTER `secret`;
ALTER TABLE `__PREFIX__wok_chat_user`
	ADD `auto_reply` varchar(255) NOT NULL DEFAULT '' COMMENT '自动回复' COLLATE 'utf8_general_ci' AFTER `room_owner_uid`;
ALTER TABLE `__PREFIX__wok_chat_user`
	ADD `auto_reply_offline` varchar(255) NOT NULL DEFAULT '' COMMENT '自动回复[离线]' COLLATE 'utf8_general_ci' AFTER `auto_reply`;
ALTER TABLE `__PREFIX__wok_chat_msg`
	CHANGE COLUMN `content` `content` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '消息内容' COLLATE 'utf8_general_ci' AFTER `app_id`;
ALTER TABLE `__PREFIX__wok_chat_session`
	CHANGE COLUMN `rank` `rank` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '优先级' COLLATE 'utf8_general_ci' AFTER `last_msg_id`;