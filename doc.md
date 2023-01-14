# 使用文档和api文档

## 使用方法

### 1、安装扩展并正常启动

### 2、配置

默认的，`ws`连接地址为：`ws://127.0.0.1:22886`

如果使用域名连接，需要配置ws转发，一般和主网站共用域名，指定一个路径如`/chat/`做转发

配置好后，`ws`连接地址可以为：`ws://www.mysiete.com/chat/`

如果需要使用`wss`协议，那么需要配置`SSL`

配置好后，`ws`连接地址可以为：`wss://www.mysiete.com/chat/`

#### nginx

```bash
server
{
    #主网站配置
    listen 80;
    server_name www.mysiete.com;
    index index.php index.html;
    root /www/wwwroot/www.mysiete.com/public;
    
    #SSL相关配置，使用`wss`协议必填

    #ws配置，需在php规则前
    location /chat/ {
        proxy_redirect off;
        proxy_pass http://127.0.0.1:22886; #ip和端口根据实际情况调整
        proxy_set_header Host $host;
        proxy_set_header X-Real_IP $remote_addr;
        proxy_set_header X-Forwarded-For $remote_addr:$remote_port;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;   # 升级协议头
        proxy_set_header Connection upgrade;
        break;
    }

    #其他配置...
}
```

#### 注意

`location /chat/` 也可为 `location /chat`，但`ws`连接地址填写的时候要与之配套，调整为：

`ws://www.mysiete.com/chat`或`wss://www.mysiete.com/chat`

### 3、在后台添加应用APP

可以添加多个应用，以实现支持多个网站的聊天需求，一个网站一个APP。
添加以后查看该应用的`app_id`和`secret`并记录。

---

### 4、接口说明

#### a.管理员接口

`http://www.mysiete.com/api/wokchatadmin/*`

用于管理用户，如推送用户信息到聊天系统，修改用户信息，获取用户未读消息条数等。

公共验证参数:

```php
[
    'app_id' => 'app_id' //添加app以后生成的id
    'sign' => '签名' // md5($secret + $time)
    'time' => '时间戳' //$time
]
```

#### b.用户接口

`http://www.mysiete.com/api/wokchatuser/*`

很少用到，实现了一些`websocket`里面的聊天接口，可以用`http`的方式完成一些功能。

公共验证参数:

```php
[
    'app_id' => 'app_id' //用户所属app的id
    'uid' => '用户id' //网站里的用户id
    'sign' => '签名' // md5($token + $time)
    'time' => '时间戳' //$time
]
```

#### c.用户接口和管理员接口区别

网站[后端] ==请求==> 聊天系统[理员接口] ✓

网站[后端] ==请求==> 聊天系统[用户接口] ✓

用户[前端] ==请求==> 聊天系统[用户接口] ✓

用户[前端] ==请求==> 聊天系统[理员接口] ✕

[管理员接口]必须使用[后端]访问，因为应用验证信息不能放到前端去，不然会导致泄漏。

---

### 5、同步用户到聊天系统

在用户使用聊天功能前时，需要把此用户信息推送到聊天系统中。

请求后端接口获取建立聊天需要的信息。

api地址为：`http://www.mysiete.com/api/wokchatadmin/pushUser`

参数：

```php
 [
    'uid' => '用户id',//业务系统里的用户id
    'nickname' => '昵称',
    'avatar' => '头像',
    'token' => 'token', //你系统里面的用户token
    'remark' => '备注信息',
    'auto_reply' => '自动回复信息',//见下
    'auto_reply_offline' => '自动回复信息[离线]',//见下
    //公共验证信息，见单独说明
    'app_id' => 'app_id'
    'sign' => '签名'
    'time' => '时间戳'
 ]
 //token不超过100字符，过长建议md5转一下。也可留空，由聊天系统自动生成。
 //自动回复信息：
 //   官方客服才需要，在其他用户连接到此客服时，自动发送一条欢迎信息
 //自动回复信息[离线]：
 //   在其他用户连接到此客服但客服离线时，自动发送一条提示信息，比如提示客服的工作时间
```

返回：

```json
{"code":1,"msg":"成功","data":{"token":"user_token"}}
```

如果请求时传了`token`参数，则原样返回，如果未传则自动生成。
``

---

### 6、websocket连接

#### login：登录

前端页面使用`websocket`进行登录。

关键js代码。

```js
var token = '聊天系统用户token';
var time = Math.floor((new Date()).getTime() / 1000);//当前时间戳
var sign = md5(token + time);//md5加密得sign。此处是伪代码，md5方法需要你自己实现
var data = {
    action: 'login',
    app_id: that.app_id,
    uid: that.uid,
    sign: sign,
    time: time
};

//发送websocket请求到 wss://www.mysiete.com/chat/
wsSend(data);//此处是伪代码，wsSend方法需要你自己实现
```

登录成功会收到服务的的消息：

```json
{"do_action":"login_success","user":{"id":1,"nickname":"测试","other":"其他字段...."}}
```

#### get_session_list：获取会话列表

获取会话列表根据实际情况，可以用`http`方式或`websocket`方式。

1.对于传统页面，会话列表和聊天界面是两个页面，在两个页面都使用`websocket`会导致复杂化。

这种情况可以使用[会话列表(http:ajax)] + [聊天界面(websocket)]

2.单页面应用，如果`vue`等，则两者都可以通过`websocket`实现。

对于http方式获取：

可以使用管理员接口方式：`/api/wokchatadmin/getSessionList`

用户 ==请求==> 你的网站 ==请求==> 聊天系统

也可以使用用户接口方式：`/api/wokchatuser/getSessionList`

用户 ==请求==> 聊天系统

```js
//登录成功以后，后面发送的信息不再需要`sign`,`time`,`uid`等验证字段
var data =  {
    action: 'get_session_list',
    skip: 20, //跳过
    kwd : '',//模糊搜索，对方昵称、备注
    pagesize : 200,//分页大小，尽量一次获取完，搞分页不好弄
};

wsSend(data);
```

成功返回：

```json
{"do_action":"get_session_list_success","list":[{}],"has_more":true}
```

#### connect_to_session：连接到已存在会话

从会话列表发起聊天

```js
var data =  {
    action: 'connect_to_session',
    session_id: 1//会话id
};

wsSend(data);
```

成功返回：

```json
{"do_action":"connect_to_success","session":{"id":1},"to_user":{"id":2,"nickname":"测试2","other":"其他...."}}
```

#### connect_to_user：连接到另外用户

需要提供对方id:`to_uid`，如果两人初次对话，会创建一个会话[session]。如果两人已对话过，则效果和`connect_to_session`类似。

`to_uid`对应的用户需要事先通过`/api/wokchatadmin/pushUser`接口推送到聊天系统中，否则会失败。

对于简单的客服系统，一般是普通用户主动连接客服，客服用户可以事先都推送到聊天系统中，用户连接时不会遇到to_uid用户不存在问题。

如果允许用户之间自由对话，比如普通用户A对话另一个普通用户B，就可能遇到B不在聊天系统中（B从来没使用聊天这个功能）。

解决这个问题需要改变推送用户的时机，定期把你系统中的用户全部推送到聊天系统中，而不是用户A使用聊天功能时才推送A用户进聊天系统。

```js
//登录成功以后，后面发送的信息不再需要`sign`,`time`,`uid`等验证字段
var data =  {
    action: 'connect_to_user',
    to_uid: 2//另外一个用户的id
};

wsSend(data);
```

成功返回：

```json
{"do_action":"connect_to_success","session":{"id":1},"to_user":{"id":2,"nickname":"测试2","other":"其他...."}}
```

以上为开启对话的两种方式：会话连接，用户直连。

拿到`session`信息后保留，后面的接口经常用到`session_id`字段。

### 拉取历史消息记录

可以是首次进入后获取，也可以是下拉后加载更多历史记录

```js
var data =  {
    action: 'get_history_message_list',
    session_id: 1,//会话id
    from_msg_id: from_msg_id,//起始编号，当前已拿到的消息中id最小值。第一次拉则为0
    pagesize: 20//每次获取条数
};

wsSend(data);
```

成功返回：

```json
{"do_action":"get_history_list_success","list":[{}],"has_more":true}
//list为消息列表，按时间倒序排列。
//需要维护一个字段:当前已拿到的消息中id最小值，以作为下一次拉取更多时的[from_msg_id]参数
```

### 发送消息

```js
var data =  {
    action: 'send_by_session',
    session_id: 1,//当前会话id
    content: 'hello world',//文本
    //content: 'http://www.mysiete.com/images/123.png', 图片
    //content: 'http://www.mysiete.com/images/456.mp3', 语音
    //content:  JSON.stringify(card),                    卡片，自定义对象，转换为json字符串
    //content: 'http://www.mysiete.com/images/789.mp4', 视频
    type: 1, // 1:文本，2:图片，3:语音，4:链接卡片，5 视频，其他自行定义并解析
};
//content只支持文本，图片、语音、视频需要自行处理文件上传，发送内容为对应的网络地址。
//内容不超过2000字符串

wsSend(data);
```

成功返回：

```json
{"do_action":"send_success"}//表示发送成功，清空输入框
{"do_action":"new_message","session":{"id":1},"from_uid":1}//拉取新消息
```

接收方如果在线，会收到：

```json
{"do_action":"new_message","session":{"id":1},"from_uid":1}//拉取新消息
```

也就是说，首发双方都会收到`new_message`通知，可根据`from_uid`判断是发送方还是接收，如果是接收方可以做相应的消息提醒(响铃、振动)。

### 拉取新消息记录

可以是首次进入后获取，也可以是下拉后加载更多历史记录

```js
var data =  {
    action: 'get_new_message_list',
    session_id: 1,//会话id
    from_msg_id: from_msg_id,//起始编号，当前已拿到的消息中id最小值。第一次拉则为0
    pagesize: 20//每次获取条数
};

wsSend(data);
```

成功返回：

```json
{"do_action":"get_new_message_list_success","list":[{}],"has_more":true}
//list为消息列表，按时间顺序排列。
//需要维护一个字段:当前已拿到的消息中id最大值，以作为下一次拉取更多时的[from_msg_id]参数
```

---

### 其他websocket接口（不常用或未经验证）

#### set_session_rank

设定指定对话的优先级

```js
var data =  {
    action: 'set_session_rank',
    session_id: 1,//会话id
    rank: 999,//权重
};

wsSend(data);
```

#### create_room

创建群聊，指定另一个人

```js
var data =  {
    action: 'create_room',
    that_uid: 1,//另外一个人id，群聊至少两个人，出来发起人，另外还需要一个人
};

wsSend(data);
```

#### create_room_by_session

创建群聊，从已存在会话创建

```js
var data =  {
    action: 'create_room_by_session',
    session_id: 1,//会话id
};

wsSend(data);
```

#### add_user_to_room

拉人加入群聊，（必须已创建成功群聊）

```js
var data =  {
    action: 'add_user_to_room',
    room_session_id: 1,//群里会话id
    that_uid: 3,//被拉的人id
};

wsSend(data);
```

#### bye

主动断开连接

```js
var data =  {
    action: 'bye',
};

wsSend(data);
```

---

### 其他websocket事件

#### sys_message（暂未实现）

一条系统提示信息，收到后添加到聊天消息列表中

```json
{"do_action":"sys_message","text":"对方当前不在线"}
```

#### close_page（暂未实现）

关闭聊天页面

```json
{"do_action":"close_page"}
```

#### login_duplication （重复登录被挤下线）

如果后台设置不允许登录，但用户在多个设备登录时，后登录用户会把前面的挤下线。

收到此事件，就不要执行[自动登录]，要让用户手动确认再登录。

```json
{"do_action":"login_duplication"}
```

### show_toast

一条提示信息，收到后弹出一条可自动消失的提示

```json
{"do_action":"show_toast","text":"系统错误请稍候再试"}
```

---

### 其他管理员接口

脱离聊天界面的一些便捷接口

#### createMsg 添加消息

直接在聊天系统中添加一条消息

`http://www.mysiete.com/api/wokchatadmin/createMsg`

参数：

```php
//admin-api公共验证参数
'app_id|app_id' => 'require',
'sign|签名' => 'require',
'time|时间戳' => 'require',
//业务参数
'uid|发送用户uid' => 'require|number',
'to_uid|接收用户uid' => 'require|number',
'content|发送内容' => 'require',
'type|消息类型' => 'require|number|gt:0',
```

#### getSessionList 获取指定用户会话列表

`http://www.mysiete.com/api/wokchatadmin/getSessionList`

参数：

```php
//admin-api公共验证参数
'app_id|app_id' => 'require',
'sign|签名' => 'require',
'time|时间戳' => 'require',
//业务参数
'uid|当前用户uid' => 'require|number',
'pagesize|分页大小' => 'number',//尽量一次获取完，搞分页不好弄
'skip|跳过条数' => '',
'kwd|模糊搜索' => '',
```

#### getHistoryMessageList 获取指定用户某个会话的历史记录

`http://www.mysiete.com/api/wokchatadmin/getHistoryMessageList`

参数：

```php
//admin-api公共验证参数
'app_id|app_id' => 'require',
'sign|签名' => 'require',
'time|时间戳' => 'require',
//业务参数
'uid|当前用户uid' => 'require|number',
'session_id|会话id' => 'require|number',
'from_msg_id' => 'number',
'pagesize' => 'number',
```

#### getNewMessageList 获取指定用户某个会话的历史记录

`http://www.mysiete.com/api/wokchatadmin/getNewMessageList`

参数：

```php
//admin-api公共验证参数
'app_id|app_id' => 'require',
'sign|签名' => 'require',
'time|时间戳' => 'require',
//业务参数
'uid|当前用户uid' => 'require|number',
'session_id|会话id' => 'require|number',
'from_msg_id' => 'number',
'pagesize' => 'number',
```

#### getNewMessageCount 获取指定用户未读消息条数

`http://www.mysiete.com/api/wokchatadmin/getNewMessageCount`

参数：

```php
//admin-api公共验证参数
'app_id|app_id' => 'require',
'sign|签名' => 'require',
'time|时间戳' => 'require',
//业务参数
'uid|当前用户uid' => 'require|number',
'session_id|会话id' => 'require|number',
'from_msg_id' => 'number',
'pagesize' => 'number',
```
