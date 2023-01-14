<?php

namespace wokmanchat\admin\controller;

use wokmanchat\common\model\WokChatApp as WokChatAppModel;
use think\Controller;
use tpext\builder\traits\actions;
use think\facade\Lang;
use wokmanchat\common\Module;
use tpext\think\App;

/**
 * @time tpextmanager 生成于2021-08-06 16:58:37
 * @title 聊天app账户
 */
class Wokchatapp extends Controller
{
    use actions\HasBase;
    use actions\HasIndex;
    use actions\HasAutopost;
    use actions\HasAdd;
    use actions\HasView;
    use actions\HasDelete;

    /**
     * Undocumented variable
     * @var WokChatAppModel
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new WokChatAppModel;
        $this->pageTitle = '聊天app账户';
        $this->selectTextField = '{id}#{name}';
        $this->selectSearch = 'name';
        $this->pk = 'id';
        $this->pagesize = 8;
        $this->sortOrder = 'id desc';

        Lang::load(Module::getInstance()->getRoot() . implode(DIRECTORY_SEPARATOR, ['admin', 'lang', App::getDefaultLang(), 'wokchatapp' . '.php']));
    }

    /**
     * 构建搜索
     * @return mixed
     */
    protected function buildSearch()
    {
        $search = $this->search;

        $search->text('name', '', 3);
    }

    /**
     * 构建搜索条件
     * @param array $data
     * @return mixed
     */
    protected function filterWhere()
    {
        $searchData = request()->get();
        $where = [];
        if (isset($searchData['name']) && $searchData['name'] != '') {
            $where[] = ['name', 'like', '%' . trim($searchData['name']) . '%'];
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

        $table->show('id');
        $table->text('name')->autoPost();
        $table->switchBtn('enable')->autoPost();
        $table->show('push_url');
        $table->show('create_time');
        $table->show('update_time');

        $table->getToolbar()
            ->btnAdd()
            ->btnRefresh();

        $table->getActionbar()
            ->btnPostRowid('secret', url('secret'), '重置Secret_key', 'btn-danger', 'mdi-backup-restore')
            ->btnView()
            ->btnDelete();

        $table->sortable('id');
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

        if ($isEdit == 2) {
            $form->show('name');
            $form->show('id');
            $form->raw('secret')->to('<label id="show-secret" class="label label-success"><i class="mdi mdi-mdi-eye"></i>显示</label><span style="display: none;margin-left:5px">{val}<span/>');
            $form->match('enable')->options([0 => '否', 1 => '是']);
            $form->show('push_url');
            $this->builder()->addScript("$('#show-secret').click(function(){\$(this).next('span').toggle()});");
        } else {
            $form->text('name')->maxlength(55)->required();
            $form->text('push_url')->maxlength(255);
        }
    }

    /**
     * Undocumented function
     * @title 重置secret
     * @return void
     */
    public function secret()
    {
        $ids = input('ids', '');

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $res = 0;

        foreach ($ids as $id) {
            if ($this->dataModel->where(['id' => $id])->update(['secret' => $this->randstr()])) {
                $res += 1;
            }
        }

        if ($res) {
            $this->success('成功重置' . $res . '个Secret_key');
        } else {
            $this->error('重置失败');
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

        $result = $this->validate($data, [
            'name|名称' => 'require',
        ]);

        if (true !== $result) {
            $this->error($result);
        }

        if (!$id) {
            $data['secret'] = $this->randstr();
        }


        return $this->doSave($data, $id);
    }

    private function randstr($randLength = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQEST123456789';

        $len = strlen($chars);
        $randStr = '';

        for ($i = 0; $i < $randLength; $i++) {
            $randStr .= $chars[rand(0, $len - 1)];
        }

        return $randStr;
    }
}
