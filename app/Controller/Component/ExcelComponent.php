<?php

App::uses('Component', 'Controller');

class ExcelComponent extends Component {

    protected $reader = false;
    protected $signSheet = false;
    protected $year = false;    //要统计的表格的年份, 两位数字, 2014年就是14
    protected $month = false;   //要统计的表格的月份, 1月1, 12月为12
    protected $dptRules = false;

    public $epyid_index = 1; //考勤表中员工考勤号在第几列(从0开始)
    public $epy_index = 3;   //员工名字在第几列
    public $date_index = 5;  //日期在第几列
    public $signtime_index = 9; //打考勤时间在第几列
    public $leavetime_index = 10; //离开时间在第几列
    public $dpt_index = 21;  //部门信息在第几列


    public function handleSign($file, $holidays = array())    {
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

        $highestRow = $this->signSheet->getHighestRow();

        //todo, 小于两行提示出错
        for($i = 2; $i <= $highestRow ; $i++) {
            $employeeId = $this->signSheet->getCellByColumnAndRow($this->epyid_index, $i)->getValue();
            $employee = $this->signSheet->getCellByColumnAndRow($this->epy_index, $i)->getValue();
            $date = $this->signSheet->getCellByColumnAndRow($this->date_index, $i)->getFormattedValue();
            $signtime = $this->signSheet->getCellByColumnAndRow($this->signtime_index, $i)->getFormattedValue();
            $leavetime = $this->signSheet->getCellByColumnAndRow($this->leavetime_index, $i)->getFormattedValue();
            $department = $this->signSheet->getCellByColumnAndRow($this->dpt_index, $i)->getValue();
        }


        // $date = array($employeeId, $employee, $date, $signtime, $leavetime, $department, $this->year, $this->month);


        return array($this->year, $this->month, $tmpDate, $tmpTimestamp);
    }

    public function handleHoliday() {

    }


    protected function dptRuleGen() {
        if($this->dptRules !== false) {
            return;
        }
        $this->loadModel('Department');
        $this->loadModel('SignRule');
    }
}