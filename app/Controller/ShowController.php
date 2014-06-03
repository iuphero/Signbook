<?php 
    
App::uses('AppController', 'Controller');

class ShowController extends AppController {


    public $name = 'Show';

    public function parseFile() {

        //load phpexcel
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');

        $holiday_str = $this->request->data['holiday'];
        $holiday = explode(';',$holiday_str);

        $fileInfo = $this->request->data['signfile'];     
        $inputFileName = $fileInfo['tmp_name'];
        $objReader = PHPExcel_IOFactory::createReader('Excel2007');
        $objReader = $objReader->load($inputFileName);
        $sheet = $objReader->getsheet(0);

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);

        $this->loadModel('Department');
        $this->loadModel('Employee');
        $this->loadModel('SignRecord');
        $department = array();
        $employee = array();
        $records = array();
        for($j=2;$j<=$highestRow;$j++) {
            $one_department = $sheet->getCellByColumnAndRow(21,$j)->getValue();
            $one_employee = $sheet->getCellByColumnAndRow(3,$j)->getValue();
            $one_employee_id = $sheet->getCellByColumnAndRow(1,$j)->getValue();
            //更新部门表
            if(!in_array($one_department,$department)) {
                array_push($department,$one_department);
                if(!$this->Department->findByName($one_department)) {

                    $this->Department->create();
                    $this->Department->save(Array(
                        'name' => $one_department
                    ));
                }
            }
            //更新职员表
            if(!in_array($one_employee_id,$employee)) {
                array_push($employee,$one_employee_id);
                if(!$this->Employee->findByJob_id($one_employee_id)) {

                    $employee_note = $this->Department->findByName($one_department);
                    $one_employee_dpt_id = $employee_note['Department']['id'];
                    $this->Employee->create();
                    $this->Employee->save(Array(
                        'name' => $one_employee,
                        'job_id' => $one_employee_id,
                        'dpt_id' => $one_employee_dpt_id
                    ));
                }
            }
            //考勤记录表
            $sign_start = $sheet->getCellByColumnAndRow(9,$j)->getValue();
            $sign_end = $sheet->getCellByColumnAndRow(10,$j)->getValue();
            $date = $sheet->getCellByColumnAndRow(5,$j)->getValue();
            $date = gmdate('Y-m-d',PHPExcel_shared_date::ExcelToPhp($date));
            $date_time = substr($date,0 ,10);
            $day_time = substr($date,8,2);

            $employee_id = $this->Employee->findByName($one_employee);
            $employee_id = $employee_id['Employee']['id'];
            $dpt_id = $this->Department->findByName($one_department);
            $dpt_id = $dpt_id['Department']['id'];

            $state_forenoon = 'regular';
            $state_afternoon = 'regular';

            //特殊情况
            //技术部一天有一个打卡记录即可
            if($one_department != '研发中心') {
                if($sheet->getCellByColumnAndRow(9,$j)->getValue()==null) {
                    $state_forenoon = 'special';
                }
                if($sheet->getCellByColumnAndRow(10,$j)->getValue()==null) {
                    $state_afternoon = 'special';
                }
            }
            if($sheet->getCellByColumnAndRow(9,$j)->getValue()==null && $sheet->getCellByColumnAndRow(10,$j)->getValue()==null) {
                $state_forenoon = 'absent';
                $state_afternoon = 'absent';
            }
            //迟到早退标记
            if($sheet->getCellByColumnAndRow(13,$j)->getValue()!==null) {
                $state_forenoon = 'late';
            }
            if($sheet->getCellByColumnAndRow(14,$j)->getValue()!==null) {
                $state_afternoon = 'late';
            }

            //请假情况
            switch($sheet->getCellByColumnAndRow(18,$j)->getValue()) {
                case '事假':
                    $state_forenoon = 'p_leave';
                    $state_afternoon = 'p_leave';
                    break;

                case '年假':
                    $state_forenoon = 'off';
                    $state_afternoon = 'off';
                    break;

                case '病假':
                    $state_forenoon = 'i_leave';
                    $state_afternoon = 'i_leave';
                    break;

                case '出差':
                    $state_forenoon = 'outgoing';
                    $state_afternoon = 'outgoing';
                    break;

                case '调休':
                    $state_forenoon = 'off';
                    $state_afternoon = 'off';
                    break;

            }

             //表单提交的公众假期 权重最高
            if(in_array($day_time,$holiday)) {
                $state_forenoon = 'off';
                $state_afternoon = 'off';
            }
            $record = array(
                'date' => $date,
                'employee_id' => $employee_id,
                'dpt_id' => $dpt_id,
                'sign_start' => $sign_start,
                'sign_end' => $sign_end,
                'state_forenoon' => $state_forenoon,
                'state_afternoon' => $state_afternoon
            );
            array_push($records,$record);


        }

        //更新考勤记录表
        $this->SignRecord->saveMany($records);

        //文件分析并写入数据库完成，跳转到展示页面
        $this->redirect('/showResult');
/*
        $youmi = array();
        for($j=2;$j<=$highestRow;$j++) {
            $department = $sheet->getCellByColumnAndRow(21,$j)->getValue();
            $employee = $sheet->getCellByColumnAndRow(3,$j)->getValue();

            $date = $sheet->getCellByColumnAndRow(5,$j)->getValue();
            $date = gmdate('Y-m-d-l',PHPExcel_shared_date::ExcelToPhp($date));
            $date_time = substr($date,0 ,10);
            $day_time = substr($date,8,2);
            $date = array();

            $youmi[$department][$employee][$date_time] = array('morning'=>'regular','afternoon'=>'regular');

            //特殊情况
            //技术部一天有一个打卡记录即可
            if($department != '研发中心') {
                if($sheet->getCellByColumnAndRow(9,$j)->getValue()==null) {
                    $youmi[$department][$employee][$date_time]['morning'] = 'special';
                }
                if($sheet->getCellByColumnAndRow(10,$j)->getValue()==null) {
                    $youmi[$department][$employee][$date_time]['afternoon'] = 'special';
                }
            }
            if($sheet->getCellByColumnAndRow(9,$j)->getValue()==null && $sheet->getCellByColumnAndRow(10,$j)->getValue()==null) {
                $youmi[$department][$employee][$date_time]['morning'] = 'absent';
                $youmi[$department][$employee][$date_time]['afternoon'] = 'absent';
            }
            //迟到早退标记
            if($sheet->getCellByColumnAndRow(13,$j)->getValue()!==null) {
                $youmi[$department][$employee][$date_time]['morning'] = 'late';
            }
            if($sheet->getCellByColumnAndRow(14,$j)->getValue()!==null) {
                $youmi[$department][$employee][$date_time]['afternoon'] = 'early';
            }

            //请假情况
            switch($sheet->getCellByColumnAndRow(18,$j)->getValue()) {
                case '事假':
                    $youmi[$department][$employee][$date_time] = array('morning'=>'p_leave','afternoon'=>'p_leave');
                    break;

                case '年假':
                    $youmi[$department][$employee][$date_time] = array('morning'=>'off','afternoon'=>'off');
                    break;

                case '病假':
                    $youmi[$department][$employee][$date_time] = array('morning'=>'i_leave','afternoon'=>'i_leave');
                    break;

                case '出差':
                    $youmi[$department][$employee][$date_time] = array('morning'=>'outgoing','afternoon'=>'outgoing');
                    break;

                case '调休':
                    $youmi[$department][$employee][$date_time] = array('morning'=>'off','afternoon'=>'off');
                    break;

            }

             //表单提交的公众假期 权重最高
            if(in_array($day_time,$holiday)) {
                $youmi[$department][$employee][$date_time] = array('morning'=>'off','afternoon'=>'off');
            }
        }
        

        $this->set('youmi', $youmi);
 */
    }

    public function showResult(){
    
    }

}
