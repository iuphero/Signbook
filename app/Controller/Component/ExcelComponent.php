<?php

App::uses('Component', 'Controller');

class ExcelComponent extends Component {

    /**
     * 考勤假期类型定义
     */
    CONST EMPTY = 0;    //留空
    CONST NORMAL = 1;   //正常
    CONST LATE = 2;     //迟到
    CONST ABSENT = 3;   //旷工
    CONST EARLY = 4;    //早退, leave early
    CONST HALFWAY = 5;  //中途脱岗
    CONST CASUAL = 6;   //事假
    CONST TRAVEL = 7;   //出差
    CONST ANNUAL = 8;   //年假
    CONST SICK = 9;     //病假
    CONST FUNERAL = 10; //丧假
    CONST PAYBACK = 11; //调休
    CONST HOLIDAY = 12; //假期


    protected $reader = false;
    protected $year = false;    //要统计的表格的年份, 两位数字, 2014年就是14
    protected $month = false;   //要统计的表格的月份, 1月1, 12月为12
    protected $epy2rule = false; //员工工号到考勤规则的映射

    /**
     * 记录考勤表中各项信息在第几列, 从0开始
     */
    public $signIndex = array(
        'job_id' => 1,  //员工工号
        'epyname' => 3,    //员工姓名
        'date' => 5,   //日期
        'signtime' => 9, //来时打卡时间
        'leavetime' => 10, //离开打卡时间
        'dpt' => 21  //部门
    );

    /**
     * 记录请假表中各项信息在第几列, 从0开始
     */
    public $leaveIndex = array(
        'name' => 0,   //员工姓名
        'job_id' => 1,  //工号
        'start_time' => 2,  //请假开始时间
        'end_time' => 3,    //请假结束时间
        'type' => 4,        //请假类型
        'reason' => 5       //请假原因
    );

    public function parseSign($file, $holidays = array())    {
        //load phpexcel
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');


        if($this->reader === false) {
            $this->reader = PHPExcel_IOFactory::createReader('Excel2007');
        }

        $sheet = $this->reader->load($file)->getsheet();
        $tmpDate = $sheet->getCellByColumnAndRow($this->date_index, 2)->getFormattedValue(); // '06-01-14'
        if($this->year === false) {
            $this->year = (int)substr($tmpDate, -2);
        }
        if($this->month === false) {
            $this->month = (int)substr($tmpDate, 0, 2);
        }
        //todo, 判断年月是否正确

        $highestRow = $sheet->getHighestRow();
        $Employee = ClassRegistry::init('Employee');

        $Department = ClassRegistry::init('Department');
        $LeaveRecord = ClassRegistry::init('LeaveRecord');
        $this->epy2rule = $Department->get_epy2rule();
        $this->epy2leave = $LeaveRecord->get_epy2leave();

        //todo, 小于两行提示出错
        for($i = 2; $i <= $highestRow ; $i++) {
            $job_id = $sheet->getCellByColumnAndRow($this->signIndex['job_id'], $i)->getValue();
            $epyname = $sheet->getCellByColumnAndRow($this->signIndex['epyname'], $i)->getValue();
            $date = $sheet->getCellByColumnAndRow($this->signIndex['date'], $i)->getFormattedValue(); //'06-01-14'
            $sign_start = $sheet->getCellByColumnAndRow($this->signIndex['signtime'], $i)->getFormattedValue();
            $sign_end = $sheet->getCellByColumnAndRow($this->signIndex['leavetime'], $i)->getFormattedValue();
            $department = $sheet->getCellByColumnAndRow($this->signIndex['dpt'], $i)->getValue();

            $date = (int)substr($date, 2, 2);
            $date = $this->year . '-' . $this->month . '-' . $date;
            $epy_id = $Employee->field('id', array('Employee.job_id' => $job_id));
            if($epy_id === false) {//  通过工号找不到员工id时, 通知出错, 需要先更新员工表
                //todo
            }


        }


        // $date = array($employeeId, $employee, $date, $signtime, $leavetime, $department, $this->year, $this->month);


        return array($this->year, $this->month, $tmpDate, $tmpTimestamp);
    }



    /** 分析请假的Excel文件, 保存到signbook.leave_record表格中
     *
     */
    public function parseLeave($file) {
        //todo 重复分析插入的检测
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');

        if($this->reader === false) {
            $this->reader = PHPExcel_IOFactory::createReader('Excel2007');
        }

        $sheet = $this->reader->load($file)->getsheet(0);
        $highestRow = $sheet->getHighestRow();
        $result = array();

        if($this->year === false) {
            $tmpDate = $sheet->getCellByColumnAndRow($this->leaveIndex['start_time'], 2)->getFormattedValue(); // '2014-06-16 9:00:00'
            $this->year = substr($tmpDate, 2, 2);
        }

        for($i = 2; $i< $highestRow; $i++) {
            $name = $sheet->getCellByColumnAndRow($this->leaveIndex['name'], $i)->getValue();
            $job_id = $sheet->getCellByColumnAndRow($this->leaveIndex['job_id'], $i)->getValue();

            //'2014-06-22 9:00:00'
            $start_time = $sheet->getCellByColumnAndRow($this->leaveIndex['start_time'], $i)->getValue();

            //'2014-06-25 18:00:00'
            $end_time = $sheet->getCellByColumnAndRow($this->leaveIndex['end_time'], $i)->getValue();

            $type = $sheet->getCellByColumnAndRow($this->leaveIndex['type'], $i)->getValue();
            $reason = $sheet->getCellByColumnAndRow($this->leaveIndex['reason'], $i)->getValue();


            $tmp_hours = ( strtotime($end_time) - strtotime($start_time) ) / 3600; //请假持续小时数
            if($tmp_hours < 5) {
                $duration = 1; // 小于5个小时为半天, $duration以半天为基本单位
            }
            else {
                $duration = ceil($tmp_hours / 24) * 2;
            }

            $type = $this->getLeaveType($type);
            $epyModel = ClassRegistry::init('Employee');
            $employee_id = $epyModel->field('id', array('Employee.job_id' => $job_id));
            if($employee_id === false) {//找不到员工号提示出错, 要先更新员工表
                //todo
            }

            $row_data = array(
                'type' => $type,
                'employee_id' => $employee_id,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'name' => trim($name),
                'duration' => $duration,
                'reason' => $reason,
                'year' =>  $this->year
            );

            array_push($result, $row_data);
        }//end for

        $LeaveRecord = ClassRegistry::init('LeaveRecord');
        if($LeaveRecord->saveMany($result)) {
            return true;
        }
        else {
            return false;
        }
    }// end parseLeave


    /**
     * 根据请假类型名称(string), 获取对应的类型编号(int)
     * 事假=>0,出差=>1,年假=>2,病假=>3,丧假=>4,调休=>5
     *
     * @param $type string 中文类型名称
     * @return $result int 类型编号, 数字
     */
    private function getLeaveType($type) {
        switch ($type) {
            case '事假':
                $result = 0;
                break;

            case '出差':
                $result = 1;
                break;

            case '年假':
                $result = 2;
                break;

            case '病假':
                $result = 3;
                break;

            case '丧假':
                $result = 4;
                break;

            case '调休':
                $result = 5;
                break;

            default:
                $result = 99;
                break;
        }
        return $result;
    }// end getLeaveType


    /**
     * 得到员工的上午打卡状态和下午打卡状态
     * @param  [type] $id     [description]
     * @param  [type] $sign_start [description]
     * @param  [type] $sign_end   [description]
     * @param  [type] $holidays   [description]
     * @return [type]             [description]
     */
    private function getSignState($id, $sign_start, $sign_end) {

        //ttp == timestamp
        $ttp_sign_start = strtotime($sign_start);
        $ttp_sign_end = strtotime($sign_end);

        $sign_rule = $this->epy2rule[$id];
        $flextime = $sign_rule['flextime'];
        $starttime = $sign_rule['starttime'];
        $endtime = $sign_rule['endtime'];

        $tmpDate = $sheet->getCellByColumnAndRow($this->date_index, 2)->getFormattedValue(); // '06-01-14'
        $date = (int)substr($tmpDate, 3, 2);

        if(empty($sign_start) && empty($sign_end)) {//看是否在假期之内
            if(in_array($date, $this->holidays)) { //是假期
                return array(HOLIDAY, HOLIDAY);
            }
            else {
                $leaveItems = $this->epy2leave[$id]; //获取员工请假记录
                $forenoonPoint = sprintf('%s-%s-%s 10:00', $this->year, $this->month, $date);
                $afternoonPoint = sprintf('%s-%s-%s 15:00', $this->year, $this->month, $date);
                $forenoonPointTtp = strtotime($forenoonPoint);
                $afternoonPointTtp = strtotime($afternoonPoint);
                foreach($leaveItems as $leaveItem) {
                    $forenoonLeave = $forenoonPoint > $leaveItem['start_time'];
                    $afternoonLeave = $afternoonPoint < $leaveItem['end_time'];
                    if() {

                    }
                }
            }

            //不在假期内
            //判断是否在请假
            //在请假就返回状态, 不是就返回array(0, 0)
        }

        if(empty($sign_start)) {//看是否在请假

        }

        if(empty($sign_end)) {//看是否在请假

        }

        if($flextime == 0) { //弹性时间为0

        }
        else {

        }
    }// end getSignState


}
