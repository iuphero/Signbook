<?php

App::uses('Component', 'Controller');

class ExcelComponent extends Component {

    protected $reader = false;
    protected $signSheet = false;
    protected $leaveSheet = false;
    protected $year = false;    //要统计的表格的年份, 两位数字, 2014年就是14
    protected $month = false;   //要统计的表格的月份, 1月1, 12月为12
    protected $epy2rule = false; //员工工号到考勤规则的映射

    /**
     * 记录考勤表中各项信息在第几列, 从0开始
     */
    public $signIndex = array(
        'epyid' => 1,  //员工工号
        'epy' => 3,    //员工姓名
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

        if($this->signSheet === false) {
            $this->signSheet = $this->reader->load($file)->getsheet();
        }

        $tmpDate = $this->signSheet->getCellByColumnAndRow($this->date_index, 2)->getFormattedValue(); // '06-01-14'
        if($this->year === false) {
            $this->year = (int)substr($tmpDate, -2);
        }
        if($this->month === false) {
            $this->month = (int)substr($tmpDate, 0, 2);
        }
        //todo, 判断年月是否正确

        if($this->epy2rule === false) {
            $this->Department = ClassRegistry::init('Department');
            $this->epy2rule = $this->Department->get_epy2rule();
        }

        $highestRow = $this->signSheet->getHighestRow();

        //todo, 小于两行提示出错
        for($i = 2; $i <= $highestRow ; $i++) {
            $epyId = $this->signSheet->getCellByColumnAndRow($this->epyid_index, $i)->getValue();
            $epyName = $this->signSheet->getCellByColumnAndRow($this->epy_index, $i)->getValue();
            $date = $this->signSheet->getCellByColumnAndRow($this->date_index, $i)->getFormattedValue(); //'06-01-14'
            $signtime = $this->signSheet->getCellByColumnAndRow($this->signtime_index, $i)->getFormattedValue();
            $leavetime = $this->signSheet->getCellByColumnAndRow($this->leavetime_index, $i)->getFormattedValue();
            $department = $this->signSheet->getCellByColumnAndRow($this->dpt_index, $i)->getValue();

            $date = (int)substr($date, 2, 2);

        }


        // $date = array($employeeId, $employee, $date, $signtime, $leavetime, $department, $this->year, $this->month);


        return array($this->year, $this->month, $tmpDate, $tmpTimestamp);
    }

    public function parseLeave($file) {
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');

        if($this->reader === false) {
            $this->reader = PHPExcel_IOFactory::createReader('Excel2007');
        }

        $sheet = $this->reader->load($file)->getsheet(0);
        $highestRow = $sheet->getHighestRow();
        $result = array();

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

            $row_data = array(
                'type' => $type,
                'job_id' => $job_id,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'name' => trim($name),
                'duration' => $duration,
                'reason' => $reason
            );

            array_push($result, $row_data);
        }//end for

        $LeaveRecord = ClassRegistry::init('LeaveRecord');
        if($LeaveRecord->saveMany($result)) {
            return 'hello';
        }
        else {
            return 'no';
        }
    }

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


}