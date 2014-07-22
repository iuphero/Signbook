<?
App::uses('Controller', 'Controller');

class SignController extends AppController {

    public $layout = 'dashboard';

/*
录入考勤数据:
1.选择月份  存在提示, 本月假期情况是否存在, 不存在就要先上传假期数据

2.选择假期
3.上传Excel表格
4.Ajax调用component
5.提示成功
 */
    public function inputSign($year=2014, $month) {

    }

    /*
    录入假期信息
    选择月份  本月假期情况是否存在提示, 不存在就要先上传假期数据
     */
    public function inputLeave() {


    }

    public function getLeave() {

    }
}
