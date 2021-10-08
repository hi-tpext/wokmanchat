<?php

namespace wokmanchat\admin\controller;

use think\Controller;
use think\facade\Lang;
use wokmanchat\common\Module;
use tpext\builder\traits\actions;
use wokmanchat\common\model\WokChatSession;
use wokmanchat\common\model\WokChatMsg as WokChatMsgModel;

/**
 * @time tpextmanager 生成于2021-08-06 17:26:10
 * @title 聊天消息
 */
class Wokchatmsg extends Controller
{
    use actions\HasBase;
    use actions\HasIndex;
    use actions\HasView;
    use actions\HasDelete;

    /**
     * Undocumented variable
     * @var WokChatMsgModel
     */
    protected $dataModel;

    protected const MSG_TYPES = [
        0 => '系统',
        1 => '文本',
        2 => '图片',
        3 => '语音',
        4 => '卡片'
    ];

    protected function initialize()
    {
        $this->dataModel = new WokChatMsgModel;
        $this->pageTitle = '聊天消息';
        $this->selectTextField = '{id}#{name}';
        $this->selectSearch = 'name';
        $this->pk = 'id';
        $this->pagesize = 14;
        $this->sortOrder = 'id desc';

        $this->indexWith = ['fromUser', 'toUser', 'app'];

        Lang::load(Module::getInstance()->getRoot() . implode(DIRECTORY_SEPARATOR, ['admin', 'lang', config('default_lang'), 'wokchatmsg' . '.php']));
    }

    /**
     * 构建搜索
     * @return mixed
     */
    protected function buildSearch()
    {
        $search = $this->search;

        $session_id = input('session_id/d');

        $search->defaultDisplayerColSize(3);

        if (!$session_id) {
            $search->select('app_id')->dataUrl(url('/admin/wokchatapp/selectpage'));
        }

        $search->text('content');
        $search->select('type')->options(self::MSG_TYPES);

        if ($session_id) {
            $search->hidden('sys_to_uid')->value(input('sys_to_uid'));
            $search->hidden('session_id')->value($session_id);
        } else {
            $search->text('session_id');
            $search->text('from_uid');
            $search->text('to_uid');
        }

        $search->datetime('create_time_start');
        $search->datetime('create_time_end');
    }

    /**
     * 构建搜索条件
     * @param array $data
     * @return mixed
     */
    protected function filterWhere()
    {
        $searchData = request()->get();

        if ($session_id = input('session_id/d')) {
            $searchData['session_id'] = $session_id;
        }

        $where = [];

        if (isset($searchData['app_id']) && $searchData['app_id'] != '') {
            $where[] = ['app_id', '=', $searchData['app_id']];
        }
        if (isset($searchData['content']) && $searchData['content'] != '') {
            $where[] = ['content', 'like', '%' . trim($searchData['content']) . '%'];
        }
        if (isset($searchData['type']) && $searchData['type'] != '') {
            $where[] = ['type', '=', $searchData['type']];
        }
        if (isset($searchData['session_id']) && $searchData['session_id'] != '') {

            $session = WokChatSession::where('id', $session_id)->find();

            if ($session['is_room']) {
                $where[] = ['sys_to_uid', '=', input('sys_to_uid/d')];
            } else {
                $where[] = ['session_id', '=', $searchData['session_id']];
            }
        }
        if (isset($searchData['from_uid']) && $searchData['from_uid'] != '') {
            $where[] = ['from_uid', '=', $searchData['from_uid']];
        }
        if (isset($searchData['to_uid']) && $searchData['to_uid'] != '') {
            $where[] = ['to_uid', '=', $searchData['to_uid']];
        }
        if (isset($searchData['create_time_start']) && $searchData['create_time_start'] != '') {
            $where[] = ['create_time', '>=', $searchData['create_time_start']];
        }
        if (isset($searchData['create_time_end']) && $searchData['create_time_end'] != '') {
            $where[] = ['create_time', '<=', $searchData['create_time_end']];
        }

        return $where;
    }

    /**
     * 构建表格
     * @param array $data
     * @return mixed
     */
    protected function buildTable(&$data = [], $isExporting = false)
    {
        $table = $this->table;

        $session_id = input('session_id/d');

        $table->show('id');

        if (!$session_id) {
            $table->show('app_id')->to('{app_id}#{app.name}');
        }

        $table->raw('content');

        $table->match('type')->options(self::MSG_TYPES)->mapClassGroup([[0, 'danger'], [1, 'success'], [2, 'warning'], [3, 'info'], [4, 'default']]);

        if (!$session_id) {
            $table->show('session_id');
        }

        foreach ($data as &$d) {
            if ($d['type'] == 0) {
                $d['content'] = '<label id="show-secret" class="label label-default">' . $d['content'] . '</label>';
            } else if ($d['type'] == 1) {
            } else if ($d['type'] == 2) {
                $d['content'] = '<img style="max-width:100px;max-height:100px;" src="' . $d['content'] . '" />';
            }
        }

        $table->show('from_uid')->to('{val}#{from_user.nickname}({from_user.remark})');
        $table->show('to_uid')->to('{val}#{to_user.nickname}({to_user.remark})');
        $table->show('create_time');

        if ($session_id) {

            $table->getToolbar()
                ->btnRefresh();

            $table->getActionbar()
                ->btnView();
        } else {

            $table->getToolbar()
                ->btnDelete()
                ->btnRefresh();

            $table->getActionbar()
                ->btnView()
                ->btnLink('list', url('index', ['session_id' => '__data.session_id__', 'sys_to_uid' => '__data.sys_to_uid__']), '对话', 'btn-success', 'mdi-message-text')
                ->btnDelete();
        }

        $table->sortable('id,app_id,type,from_uid,to_uid,sys_from_uid,sys_to_uid');
    }

    /**
     * 构建搜索条件
     * @param boolean $isEdit
     * @param array $data
     * @return mixed
     */
    protected function buildForm($isEdit, &$data = [])
    {
        $form = $this->form;

        $form->hidden('id');
        $form->show('app_id')->to('{app_id}#{app.name}');
        if ($data['type'] == 0) {
            $form->raw('content')->to('<label id="show-secret" class="label label-default">{val}</label>');
        } else if ($data['type'] == 1) {
            $form->raw('content')->to('<pre>{val}</pre>');
        } else if ($data['type'] == 2) {
            $form->image('content')->mediumSize();
        } else if ($data['type'] == 3) {
            $form->file('content');
        } else if ($data['type'] == 4) {
            $card = json_decode($data['content']);
            $items = [];
            foreach ($card['items'] as $key => $item) {
                $items[$key] = [
                    'id' => $key,
                    'text' => $item
                ];
            }

            $form->items('content')->dataWithId($items)->with(
                $form->show('text', '内容')
            );
        }

        $form->match('type')->options(self::MSG_TYPES)->mapClassGroup([[0, 'danger'], [1, 'success'], [2, 'warning']]);
        $form->show('session_id');
        $form->show('from_uid')->to('{val}#{from_user.nickname}({from_user.remark})');
        $form->show('to_uid')->to('{val}#{to_user.nickname}({to_user.remark})');
        $form->show('sys_from_uid');
        $form->show('sys_to_uid');

        if ($isEdit) {
            $form->show('create_time');
        }
    }

    /**
     * 保存数据
     * @param integer $id
     * @return mixed
     */
    private function save($id = 0)
    {
        $data = request()->post();

        $result = $this->validate($data, []);

        if (true !== $result) {
            $this->error($result);
        }

        return $this->doSave($data, $id);
    }
}
