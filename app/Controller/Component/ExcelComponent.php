<?php
/**
 * tmp部分为有待决定和修改的部分, 存疑
 * todo部分有待完善
 */
App::uses('Component', 'Controller');

class ExcelComponent extends Component {

    /**
     * 考勤假期类型定义
     */
    const EMPTYDAY = 0;    //留空
    const NORMAL = 1;   //正常
    const LATE = 2;     //迟到
    const ABSENT = 3;   //旷工
    const EARLY = 4;    //早退, leave early
    const HALFWAY = 5;  //中途脱岗
    const CASUAL = 6;   //事假
    const TRAVEL = 7;   //出差
    const ANNUAL = 8;   //年假
    const SICK = 9;     //病假
    const FUNERAL = 10; //丧假
    const PAYBACK = 11; //调休
    const HOLIDAY = 12; //假期


    protected $reader = false;
    protected $year = false;    //要统计的表格的年份, 两位数字, 2014年就是14
    protected $month = false;   //要统计的表格的月份, 1月1, 12月为12


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



    /**
     * 分析考勤文件, 保存数据到signbook.sign_record表中
     * @param  $file     string 文件路径
     * @param  $holidays array  要分析的月份的假期(几号)数组,包括周六周日,例如[1, 4, 6, 30]
     * @return $boolean  分析成功返回true, 失败返回false
     */
    public function parseSign($file, $holidays = array())    {
        //load phpexcel
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');


        if($this->reader === false) {
            $this->reader = PHPExcel_IOFactory::createReader('Excel2007');
        }

        $sheet = $this->reader->load($file)->getsheet(0);
        $tmpDate = $sheet->getCellByColumnAndRow($this->signIndex['date'], 2)->getFormattedValue(); // '06-01-14'
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
        $this->epy2rule = $Department->get_epy2rule(); //员工=>考勤规则
        $this->epy2leave = $LeaveRecord->get_epy2leave($this->year, $this->month); //员工=>请假记录

        $result = array();

        //todo, 小于两行提示出错
        for($i = 2; $i <= $highestRow ; $i++) {
            $job_id = $sheet->getCellByColumnAndRow($this->signIndex['job_id'], $i)->getValue();
            $date = $sheet->getCellByColumnAndRow($this->signIndex['date'], $i)->getFormattedValue(); //'06-01-14'
            $sign_start = $sheet->getCellByColumnAndRow($this->signIndex['signtime'], $i)->getFormattedValue();
            $sign_end = $sheet->getCellByColumnAndRow($this->signIndex['leavetime'], $i)->getFormattedValue();

            $tmpDateAry = explode('-', $date);
            $whichDay = (int)$tmpDateAry[1];
            $is_holiday = in_array($whichDay, $holidays);
            $date = $this->year . '-' . $this->month . '-' . $whichDay;
            $epy_id = (int)$Employee->field('id', array('Employee.job_id' => $job_id));
            if($epy_id === false) {//  通过工号找不到员工id时, 通知出错, 需要先更新员工表
                //todo
            }
            list($state_forenoon,$state_afternoon) = $this->getSignState($epy_id, $whichDay, $sign_start, $sign_end, $is_holiday);
            $result[] = array(
                'date' => $date,
                'employee_id' => $epy_id,
                'sign_start' => $sign_start,
                'sign_end' => $sign_end,
                'state_forenoon' => $state_forenoon,
                'state_afternoon' => $state_afternoon
            );
        }
        $SignRecord = ClassRegistry::init('SignRecord');
        if( $SignRecord->saveMany($result) ) {
            return true;
        }
        else {
            return false;
        }
    }



    /** 分析请假的Excel文件, 保存到signbook.leave_record表格中
     *  @param $file 文件路径
     *  @return boolean 处理成功返回true, 处理失败返回false
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

            $type = $this->getLeaveType(trim($type));
            $epyModel = ClassRegistry::init('Employee');
            $employee_id = $epyModel->field('id', array('Employee.job_id' => $job_id));
            if($employee_id === false) {//找不到员工号提示出错, 要先更新员工表
                //todo
            }

            $result[] = array(
                'type' => $type,
                'employee_id' => $employee_id,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'name' => trim($name),
                'duration' => $duration,
                'reason' => $reason,
                'year' =>  $this->year
            );

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
     *
     * @param $type string 中文类型名称
     * @return $result int 类型编号, 数字
     */
    private function getLeaveType($type) {
        switch ($type) {
            case '事假':
                $result = self::CASUAL;
                break;

            case '出差':
                $result = self::TRAVEL;
                break;

            case '年假':
                $result = self::ANNUAL;
                break;

            case '病假':
                $result = self::SICK;
                break;

            case '丧假':
                $result = self::FUNERAL;
                break;

            case '调休':
                $result = self::PAYBACK;
                break;

            default:
                $result = 99;
                break;
        }
        return $result;
    }// end getLeaveType


    /**
     * 获取员工某一天的考勤状态
     * @param  $id    int   员工id
     * @param  $date  int   日期, 几号, 例如1, 15, 31
     * @param  $sign_start  string  上班打卡时间
     * @param  $sign_end    string  下班打卡时间
     * @param  $is_holiday  boolean 是否为假期
     * @return array($forenoonState, $afternoonState)    早上的考勤状态, 下午的考勤状态
     */
    private function getSignState($id, $date, $sign_start, $sign_end, $is_holiday=false) {
        //ttp == timestamp
        $sign_start_ttp = strtotime($sign_start);
        $sign_end_ttp = strtotime($sign_end);

        $sign_rule = $this->epy2rule[$id];
        $flextime = $sign_rule['flextime']; //弹性时间
        $rule_starttime_ttp = strtotime( $sign_rule['starttime'] ); //规定的上班打卡时间
        $rule_endtime_ttp = strtotime( $sign_rule['endtime'] ); //规定的下班打卡时间

        $leaveItems = @$this->epy2leave[$id] or false; //获取员工请假记录
        $hasLeave = !empty($leaveItems);
        if(empty($sign_start) && empty($sign_end)) { //早上下午都没打卡
            if($is_holiday) { //是否在假期之内, 在假期内
                return array(self::HOLIDAY, self::HOLIDAY);
            }
            else {//不在假期内, 查看是否在请假范围内
                if(!$hasLeave) { //没有请假记录
                    return array(self::EMPTYDAY, self::EMPTYDAY);
                }
                $forenoonPoint = sprintf('%s-%s-%s 10:00', $this->year, $this->month, $date);
                $afternoonPoint = sprintf('%s-%s-%s 15:00', $this->year, $this->month, $date);
                $forenoonPointTtp = strtotime($forenoonPoint);
                $afternoonPointTtp = strtotime($afternoonPoint);
                $forenoonState = self::EMPTYDAY;
                $afternoonState = self::EMPTYDAY;
                foreach($leaveItems as $leaveItem) { //是否在请假范围内
                    $isForenoonInLeave = $forenoonPointTtp > $leaveItem['start_time']
                                         && $forenoonPointTtp < $leaveItem['end_time'];
                    $isAfternoonInLeave = $afternoonPointTtp > $leaveItem['start_time']
                                          && $afternoonPointTtp < $leaveItem['end_time'];
                    if($isForenoonInLeave) {
                        $forenoonState = $leaveItem['type'];
                    }
                    if($isAfternoonInLeave) {
                        $afternoonState = $leaveItem['type'];
                    }
                }
                return array($forenoonState, $afternoonState);
            }
        }// end 早上下午都没打卡

        if(empty($sign_start)) {//仅早上没打卡
            if($is_holiday) { //tmp是否在假期之内, 在假期内
                return array(self::EMPTYDAY, self::NORMAL);
            }
            $forenoonPoint = sprintf('%s-%s-%s 10:00', $this->year, $this->month, $date);
            $forenoonPointTtp = strtotime($forenoonPoint);
            $forenoonState = self::EMPTYDAY; //tmp
            if($hasLeave) {
                foreach($leaveItems as $leaveItem) { //是否在请假范围内
                    $isForenoonInLeave = $forenoonPointTtp > $leaveItem['start_time']
                                         && $forenoonPointTtp < $leaveItem['end_time'];
                    if($isForenoonInLeave) {
                        $forenoonState = $leaveItem['type'];
                    }
                }
            }
            if($sign_end_ttp < $rule_endtime_ttp) { //下班打卡时间过早, 为早退
                $afternoonState = self::EMPTYDAY;
            }
            else {
                $afternoonState = self::NORMAL;
            }
            return array($forenoonState, $afternoonState);
        }

        if(empty($sign_end)) {//仅下午没打卡
            if($is_holiday) { //tmp是否在假期之内, 在假期内
                return array(self::NORMAL, self::EMPTYDAY);
            }
            $afternoonPoint = sprintf('%s-%s-%s 15:00', $this->year, $this->month, $date);
            $afternoonPointTtp = strtotime($afternoonPoint);
            $afternoonState = self::EMPTYDAY; //tmp
            if($hasLeave) {
                foreach($leaveItems as $leaveItem) { //是否在请假范围内
                    $isAfternoonInLeave = $afternoonPointTtp > $leaveItem['start_time']
                                         && $afternoonPointTtp < $leaveItem['end_time'];
                    if($isAfternoonInLeave) {
                        $forenoonState = $leaveItem['type'];
                    }
                }
            }
            $edgetime_ttp = $rule_starttime_ttp + $flextime * 60; //规定的上班打卡时间+弹性时间=最迟的上班打卡时间
            if($sign_start_ttp > $edgetime_ttp) { //上班迟到鸟
                $forenoonState = self::LATE;
            }
            else {
                $forenoonState = self::NORMAL;
            }
            return array($forenoonState, $afternoonState);
        }

        /**
         * tmp
         * 以下处理一天打两次卡的情况
         * 要考虑有的同事来得晚, 但是也走得比较晚的情况,
         * 这样不能算迟到. 比如说早上10点来晚上7点走
         */
        $between_hours = ($sign_end_ttp - $sign_start_ttp) / 3600;
        if($between_hours >= 9) {
            return array(self::NORMAL, self::NORMAL);
        }
        $edgetime_ttp = $rule_starttime_ttp + $flextime * 60;
        if($sign_start_ttp > $edgetime_ttp) { //上班迟到鸟
            $forenoonState = self::LATE;
        }
        else {
            $forenoonState = self::NORMAL;
        }

        if($sign_end_ttp < $rule_endtime_ttp) { //早退
            $afternoonState = self::EARLY;
        }
        else {
            $afternoonState = self::NORMAL;
        }
        return array($forenoonState, $afternoonState);

    }// end getSignState


}// end class
