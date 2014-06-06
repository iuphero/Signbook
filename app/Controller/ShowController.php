<?php 
    
App::uses('AppController', 'Controller');

class ShowController extends AppController {


    public $name = 'Show';

    public function parseFile() {

        //load phpexcel
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');

        //处理考勤记录
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
        //$this->SignRecord->saveMany($records);
        $records = array();



        //处理休假数据
        $fileInfo = $this->request->data['offfile'];     
        $inputFileName = $fileInfo['tmp_name'];
        $objReader = PHPExcel_IOFactory::createReader('Excel2007');
        $objReader = $objReader->load($inputFileName);
        $sheet = $objReader->getsheet(0);

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);

        for($j=2;$j<=$highestRow;$j++) {
            $job_id = $sheet->getCellByColumnAndRow(1,$j)->getValue();
            $off_start = $sheet->getCellByColumnAndRow(2,$j)->getValue();
            $off_end = $sheet->getCellByColumnAndRow(3,$j)->getValue();
            $off_type = $sheet->getCellByColumnAndRow(4,$j)->getValue();


            $off_start_date = date('Y-m-d',strtotime($off_start));
            $off_end_date = date('Y-m-d',strtotime($off_end));

            $off_start_time = date('H',strtotime($off_start));
            $off_end_time = date('H',strtotime($off_end));

            $employee_record = $this->Employee->find('first',array(
                'conditions' => array(
                    'Employee.job_id' => $job_id
                )
            ));

            $employee_id = $employee_record['Employee']['id'];

            //开始修改数据库



            switch ($off_type) {
                case '事假':
                    $off_type = 'p_leave';
                    break;

                case '年假':
                    $off_type = 'off';
                    break;

                case '病假':
                    $off_type = 'i_leave';
                    break;

                case '出差':
                    $off_type = 'outgoing';
                    break;

                case '外出':
                    $off_type = 'outgoing';
                    break;

                case '调休':
                    $off_type = 'off';
                    break;
            }

            //待测试
            //开始时间
            $record = $this->SignRecord->find('first', array(
                'conditions'=>array(
                    'SignRecord.employee_id' => $employee_id,
                    'SignRecord.date' => $off_start_date
                )
            ));
            if(isset($record['SignRecord']['id'])){
                $this->SignRecord->id = $record['SignRecord']['id'];
            }
            if($off_start_time == '09') {
                $this->SignRecord->save(array(
                    'state_forenoon' => $off_type,
                    'state_afternoon' => $off_type
                ));
            }
            else{
                $this->SignRecord->save(array(
                    'state_afternoon' => $off_type
                ));
            }

            $record = $this->SignRecord->find('first', array(
                'conditions'=>array(
                    'SignRecord.employee_id' => $employee_id,
                    'SignRecord.date' => $off_end_date
                )
            ));
            if(isset($record['SignRecord']['id'])) {
                $this->SignRecord->id = $record['SignRecord']['id'];
            }

            //结束时间
            if($off_end_time == '18') {
                $this->SignRecord->save(array(
                    'state_forenoon' => $off_type,
                    'state_afternoon' => $off_type
                ));
            }
            else{
                $this->SignRecord->save(array(
                    'state_forenoon' => $off_type,
                    'state_afternoon' => 'regular'
                ));
            }

            //中间的时间
            $middleDate = $this->SignRecord->find('all', array(
                'conditions' => array(
                    'SignRecord.employee_id' => $employee_id,
                    'SignRecord.date >' => $off_start_date,
                    'SignRecord.date <' => $off_end_date
                )
            ));
            
            foreach($middleDate as $one_date ) {
                $this->SignRecord->id = $one_date['SignRecord']['id'];
                $this->SignRecord->save(array(
                    'state_forenoon' => $off_type,
                    'state_afternoon' => $off_type
                ));
            }

        }


        //文件分析并写入数据库完成，跳转到展示页面
        $this->redirect('/show/showResult');
    }

    public function showResult(){
        
    }

}
