<?php 
    
App::uses('AppController', 'Controller');

class ShowController extends AppController {


    public $name = 'Show';

    //处理excel2007文件并写入数据库，数据库保持只有一个月的记录
    public function parseFile() {

        //load phpexcel
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');

        //获取该月第一天和最后一天
        $date_str = $this->request->data['date'] ;
        $date = explode(';',$date_str);
        $first_date = $date[0];
        $last_date = $date[1];

        //获取月中的所有公众假期 包括周末
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

        //分析总公司考勤文件
        for($j=2;$j<=$highestRow;$j++) {
            $one_department = $sheet->getCellByColumnAndRow(21,$j)->getValue();
            $one_employee = $sheet->getCellByColumnAndRow(3,$j)->getValue();
            $one_employee_id = $sheet->getCellByColumnAndRow(1,$j)->getValue();

            //每月更新部门表
            if(!in_array($one_department,$department)) {
                array_push($department,$one_department);
                if(!$this->Department->findByName($one_department)) {

                    $this->Department->create();
                    $this->Department->save(Array(
                        'name' => $one_department
                    ));
                }
            }

            //每月更新职员表
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

            $employee_id = $this->Employee->findByJob_id($one_employee_id);
            $employee_id = $employee_id['Employee']['id'];
            $dpt_id = $this->Department->findByName($one_department);
            $dpt_id = $dpt_id['Department']['id'];

            //默认所有考勤regular
            $state_forenoon = 'regular';
            $state_afternoon = 'regular';

            //部门一天中只有一个打卡记录 记为special
            if($sheet->getCellByColumnAndRow(9,$j)->getValue()==null) {
                $state_forenoon = 'special';
            }
            if($sheet->getCellByColumnAndRow(10,$j)->getValue()==null) {
                $state_afternoon = 'special';
            }

            //上午下午都没有打卡记录的记为absent
            if($sheet->getCellByColumnAndRow(9,$j)->getValue()==null && $sheet->getCellByColumnAndRow(10,$j)->getValue()==null) {
                $state_forenoon = 'absent';
                $state_afternoon = 'absent';
            }

            //迟到早退标记
            if($sheet->getCellByColumnAndRow(13,$j)->getValue()!==null) {
                $state_forenoon = 'late';
            }
            if($sheet->getCellByColumnAndRow(14,$j)->getValue()!==null) {
                $state_afternoon = 'early';
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
                    $off_type = 'regular';
                    break;

                case '病假':
                    $off_type = 'i_leave';
                    break;

                case '出差':
                    $off_type = 'w_outgoing';
                    break;

                case '外出':
                    $off_type = 'n_outgoing';
                    break;

                case '调休':
                    $off_type = 'regular';
                    break;

                case '丧假':
                    $off_type = 'regular';
                    break;

                default : 
                    $off_type = 'wrong';
                    break;
            }


            //修改休假表的每条记录对应的signrecord表
            //公众假期优先于事假病假
            if($off_type == 'p_leave' || $off_type == 'i_leave') {
            //休假一天的情况
            if($off_start_date == $off_end_date) {


                $record = $this->SignRecord->find('first', array(
                    'conditions'=>array(
                        'SignRecord.employee_id' => $employee_id,
                        'SignRecord.date' => $off_start_date
                    )
                ));

                $old_state_forenoon = $record['SignRecord']['state_forenoon'];

                if(isset($record['SignRecord']['id'])){
                    $this->SignRecord->id = $record['SignRecord']['id'];
                }


                if($old_state_forenoon != 'off') {
                    if($off_start_time == '09') {
                        $this->SignRecord->save(array(
                            'state_forenoon' => $off_type
                        ));
                    }
                    if($off_end_time == '18'){
                        $this->SignRecord->save(array(
                            'state_afternoon' => $off_type
                        ));
                    }               
                }

            }

            //连续休假多天的情况
            else {
                if($off_start_date < $first_date) {
                    $off_start_date = $first_date;
                    $off_start_time = '09';
                }
                if($off_end_date > $last_date) {
                    $off_end_date = $last_date;
                    $off_end_time = '18';
                }
                $record = $this->SignRecord->find('first', array(
                    'conditions'=>array(
                        'SignRecord.employee_id' => $employee_id,
                        'SignRecord.date' => $off_start_date
                    )
                ));

                $old_state_forenoon = $record['SignRecord']['state_forenoon'];

                $this->SignRecord->id = $record['SignRecord']['id'];

                if($old_state_forenoon != 'off') {
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
                }

                $record = $this->SignRecord->find('first', array(
                    'conditions'=>array(
                        'SignRecord.employee_id' => $employee_id,
                        'SignRecord.date' => $off_end_date
                    )
                ));

                $old_state_afternoon = $record['SignRecord']['state_afternoon'];

                $this->SignRecord->id = $record['SignRecord']['id'];

                //结束时间
                if($old_state_afternoon != 'off') {
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
                    $old_state_forenoon = $one_date['SignRecord']['state_forenoon'];
                    if($old_state_forenoon != 'off') {
                        $this->SignRecord->save(array(
                            'state_forenoon' => $off_type,
                            'state_afternoon' => $off_type
                        ));
                    }
                }

            }//连续休假多天
            }
            else {
            if($off_start_date == $off_end_date) {

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
                        'state_forenoon' => $off_type
                    ));
                }
                if($off_end_time == '18'){
                    $this->SignRecord->save(array(
                        'state_afternoon' => $off_type
                    ));
                }
            }

            //连续休假多天的情况
            else {
                if($off_start_date < $first_date) {
                    $off_start_date = $first_date;
                    $off_start_time = '09';
                }
                if($off_end_date > $last_date) {
                    $off_end_date = $last_date;
                    $off_end_time = '18';
                }
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

            }//连续休假多天

            }

        }

        //文件分析并写入数据库完成，跳转到展示页面
        $this->redirect('/show/showResult');

    }

    public function showResult(){
        $this->autoRender = false;
        //load phpexcel
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/IComparable');
        APP::import('Vender','/excel/Classes/PHPExcel/Worksheet');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');
        APP::import('Vender','/excel/Classes/PHPExcel/Writer/Excel2007');
        $signResult = new PHPExcel();   
        $state = array('√','●','○','☆','△','×','※','◇','◆','▲');

        $this->loadModel('Department');
        $this->loadModel('Employee');
        $this->loadModel('SignRecord');
        $department_array = $this->Department->find('list');
        foreach($department_array as $index => $dpt) {
            //创建表
            $mySheet = new PHPExcel_Worksheet($signResult, $dpt);
            $signResult->addSheet($mySheet);

            $activeSheet = $signResult->getSheetByName($dpt);            

            $activeSheet->setCellValue('A3','部门 : '.$dpt);
            $activeSheet->setCellValue('E3','√');
            $activeSheet->setCellValue('G3','●');
            $activeSheet->setCellValue('I3','○');
            $activeSheet->setCellValue('K3','☆');
            $activeSheet->setCellValue('M3','△');
            $activeSheet->setCellValue('O3','×');
            $activeSheet->setCellValue('Q3','※');
            $activeSheet->setCellValue('S3','◇');
            $activeSheet->setCellValue('U3','◆');
            $activeSheet->setCellValue('W3','▲');
            $activeSheet->setCellValue('F3','出勤');
            $activeSheet->setCellValue('H3','休假');
            $activeSheet->setCellValue('J3','事假');
            $activeSheet->setCellValue('L3','病假');
            $activeSheet->setCellValue('N3','外地出差');
            $activeSheet->setCellValue('P3','旷工');
            $activeSheet->setCellValue('R3','迟到');
            $activeSheet->setCellValue('T3','早退');
            $activeSheet->setCellValue('V3','中途脱岗');
            $activeSheet->setCellValue('X3','市内出差');

            $activeSheet->setCellValue('AI6','出勤');
            $activeSheet->setCellValue('AJ6','休假');
            $activeSheet->setCellValue('AK6','事假');
            $activeSheet->setCellValue('AL6','病假');
            $activeSheet->setCellValue('AM6','外地出差');
            $activeSheet->setCellValue('AN6','市内出差');
            $activeSheet->setCellValue('AO6','迟到');
            $activeSheet->setCellValue('AP6','早退');
            $activeSheet->setCellValue('AQ6','中途脱岗');
            $activeSheet->setCellValue('AR6','异常');
            $activeSheet->setCellValue('AS6','旷工');

            $activeSheet->getColumnDimension('C')->setWidth(4);
            $activeSheet->getColumnDimension('D')->setWidth(4);
            $activeSheet->getColumnDimension('E')->setWidth(4);
            $activeSheet->getColumnDimension('F')->setWidth(4);
            $activeSheet->getColumnDimension('G')->setWidth(4);
            $activeSheet->getColumnDimension('H')->setWidth(4);
            $activeSheet->getColumnDimension('I')->setWidth(4);
            $activeSheet->getColumnDimension('J')->setWidth(4);
            $activeSheet->getColumnDimension('K')->setWidth(4);
            $activeSheet->getColumnDimension('L')->setWidth(4);
            $activeSheet->getColumnDimension('M')->setWidth(4);
            $activeSheet->getColumnDimension('N')->setWidth(4);
            $activeSheet->getColumnDimension('O')->setWidth(4);
            $activeSheet->getColumnDimension('P')->setWidth(4);
            $activeSheet->getColumnDimension('Q')->setWidth(4);
            $activeSheet->getColumnDimension('R')->setWidth(4);
            $activeSheet->getColumnDimension('S')->setWidth(4);
            $activeSheet->getColumnDimension('T')->setWidth(4);
            $activeSheet->getColumnDimension('U')->setWidth(4);
            $activeSheet->getColumnDimension('V')->setWidth(4);
            $activeSheet->getColumnDimension('W')->setWidth(4);
            $activeSheet->getColumnDimension('X')->setWidth(4);
            $activeSheet->getColumnDimension('Y')->setWidth(4);
            $activeSheet->getColumnDimension('Z')->setWidth(4);
            $activeSheet->getColumnDimension('AA')->setWidth(4);
            $activeSheet->getColumnDimension('AB')->setWidth(4);
            $activeSheet->getColumnDimension('AC')->setWidth(4);
            $activeSheet->getColumnDimension('AD')->setWidth(4);
            $activeSheet->getColumnDimension('AE')->setWidth(4);
            $activeSheet->getColumnDimension('AF')->setWidth(4);
            $activeSheet->getColumnDimension('AG')->setWidth(4);
            $activeSheet->getColumnDimension('AH')->setWidth(4);
            $activeSheet->getColumnDimension('AI')->setWidth(6);
            $activeSheet->getColumnDimension('AJ')->setWidth(6);
            $activeSheet->getColumnDimension('AK')->setWidth(6);
            $activeSheet->getColumnDimension('AL')->setWidth(6);
            $activeSheet->getColumnDimension('AM')->setWidth(6);
            $activeSheet->getColumnDimension('AN')->setWidth(6);
            $activeSheet->getColumnDimension('AO')->setWidth(6);
            $activeSheet->getColumnDimension('AP')->setWidth(6);
            $activeSheet->getColumnDimension('AQ')->setWidth(6);
            $activeSheet->getColumnDimension('AR')->setWidth(6);
            $activeSheet->getColumnDimension('AS')->setWidth(6);

            $activeSheet->getStyle('A1:AS200')->getFont()->setSize(10);

            //设置居中
            $activeSheet->getStyle('A1:AS200')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            
            //所有垂直居中
            $activeSheet->getStyle('A1:AS200')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);


            $activeSheet->setCellValue('A5','姓名');
            $activeSheet->mergeCells('A5:A6');
            $activeSheet->setCellValue('B5','星期');
            $activeSheet->setCellValue('B6','日');

            //填写日期
            $one_employee = $this->SignRecord->find('all',array(
                'conditions' => array(
                    'SignRecord.dpt_id'=> '7',
                    'SignRecord.employee_id'=>'24'
                )
            ));

            $a = 2;
            foreach($one_employee as $one_employee_index=>$data) {
                $date = date('d',strtotime($data['SignRecord']['date']));
                $day = date('D',strtotime($data['SignRecord']['date']));
                switch($day) { 
                    case 'Mon': 
                        $day = '一'; 
                        break; 
                    case 'Tue': 
                        $day = '二'; 
                        break; 
                    case 'Wed': 
                        $day = '三'; 
                        break; 
                    case 'Thu': 
                        $day = '四'; 
                        break; 
                    case 'Fri': 
                        $day = '五'; 
                        break; 
                    case 'Sat': 
                        $day = '六'; 
                        break; 
                    case 'Sun': 
                        $day = '日'; 
                        break; 
                } 
                $activeSheet->setCellValueByColumnAndRow($a,5,$day);
                $activeSheet->setCellValueByColumnAndRow($a,6,$date);
                $a++;
            }

            //分部门查人员填表
            $department_employee = $this->Employee->findAllByDptId($index);

            //分人员填表
            $b = 7;
            foreach($department_employee as $employee) {
                $activeSheet->setCellValueByColumnAndRow(0,$b,$employee['Employee']['name']);
                $activeSheet->mergeCells('A'.$b.':A'.($b+1));
                $activeSheet->setCellValueByColumnAndRow(1,$b,'上午');
                $activeSheet->setCellValueByColumnAndRow(1,$b+1,'下午');

                //每天记录
                $employee_record = $this->SignRecord->find('all',array(
                    'conditions' => array(
                        'employee_id' => $employee['Employee']['id'],
                        'dpt_id' => $index
                    )
                ));
                $c = 2;
                foreach($employee_record as $one_record) {
                    switch($one_record['SignRecord']['state_forenoon']) {

                        case 'regular':
                            $state_forenoon = '√';
                            break;
                        case 'off':
                            $state_forenoon = '●';
                            break;
                        case 'p_leave':
                            $state_forenoon = '○';
                            break;
                        case 'i_leave':
                            $state_forenoon = '☆';
                            break;
                        case 'w_outgoing':
                            $state_forenoon = '△';
                            break;
                        case 'n_outgoing':
                            $state_forenoon = '▲';
                            break;
                        case 'absent':
                            $state_forenoon = '×';
                            break;
                        case 'late':
                            $state_forenoon = '※';
                            break;
                        case 'early':
                            $state_forenoon = '◇';
                            break;
                        case 'special':
                            $state_forenoon = '!';
                            break;

                    }
                    switch($one_record['SignRecord']['state_afternoon']) {
                        case 'regular':
                            $state_afternoon = '√';
                            break;
                        case 'off':
                            $state_afternoon = '●';
                            break;
                        case 'p_leave':
                            $state_afternoon = '○';
                            break;
                        case 'i_leave':
                            $state_afternoon = '☆';
                            break;
                        case 'w_outgoing':
                            $state_afternoon = '△';
                            break;
                        case 'n_outgoing':
                            $state_afternoon = '▲';
                            break;
                        case 'absent':
                            $state_afternoon = '×';
                            break;
                        case 'late':
                            $state_afternoon = '※';
                            break;
                        case 'early':
                            $state_afternoon = '◇';
                            break;
                        case 'special':
                            $state_afternoon = '!';
                            break;
                    }
                    $activeSheet->getCellByColumnAndRow($c,$b)->getDataValidation()
                        -> setType(PHPExcel_Cell_DataValidation::TYPE_LIST)
                        -> setErrorStyle(PHPExcel_Cell_DataValidation::STYLE_INFORMATION)
                        -> setShowInputMessage(true)
                        -> setShowDropDown(true)
                        -> setFormula1('"'.join(',', $state).'"');
                    $activeSheet->getCellByColumnAndRow($c,$b+1)->getDataValidation()
                        -> setType(PHPExcel_Cell_DataValidation::TYPE_LIST)
                        -> setErrorStyle(PHPExcel_Cell_DataValidation::STYLE_INFORMATION)
                        -> setShowInputMessage(true)
                        -> setShowDropDown(true)
                        -> setFormula1('"'.join(',', $state).'"');
                    $activeSheet->setCellValueByColumnAndRow($c,$b,$state_forenoon);
                    $activeSheet->setCellValueByColumnAndRow($c,$b+1,$state_afternoon);
                    $c = $c+1;
                }

                $activeSheet->setCellValueByColumnAndRow(34,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"√")+COUNTIF(C'.($b+1).':AG'.($b+1).',"√"))*0.5');
                $activeSheet->setCellValueByColumnAndRow(38,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"△")+COUNTIF(C'.($b+1).':AG'.($b+1).',"△"))*0.5');
                $activeSheet->setCellValueByColumnAndRow(39,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"▲")+COUNTIF(C'.($b+1).':AG'.($b+1).',"▲"))*0.5');
                $activeSheet->setCellValueByColumnAndRow(35,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"●")+COUNTIF(C'.($b+1).':AG'.($b+1).',"●"))*0.5');
                $activeSheet->setCellValueByColumnAndRow(36,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"○")+COUNTIF(C'.($b+1).':AG'.($b+1).',"○"))*0.5');
                $activeSheet->setCellValueByColumnAndRow(37,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"☆")+COUNTIF(C'.($b+1).':AG'.($b+1).',"☆"))*0.5');
                $activeSheet->setCellValueByColumnAndRow(44,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"×")+COUNTIF(C'.($b+1).':AG'.($b+1).',"×"))*0.5');
                $activeSheet->setCellValueByColumnAndRow(40,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"※")+COUNTIF(C'.($b+1).':AG'.($b+1).',"※"))');
                $activeSheet->setCellValueByColumnAndRow(41,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"◇")+COUNTIF(C'.($b+1).':AG'.($b+1).',"◇"))');
                $activeSheet->setCellValueByColumnAndRow(42,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"◆")+COUNTIF(C'.($b+1).':AG'.($b+1).',"◆"))');
                $activeSheet->setCellValueByColumnAndRow(43,$b,'=(COUNTIF(C'.$b.':AG'.$b.',"!")+COUNTIF(C'.($b+1).':AG'.($b+1).',"!"))');
                $activeSheet->mergeCells('AI'.$b.':AI'.($b+1));
                $activeSheet->mergeCells('AJ'.$b.':AJ'.($b+1));
                $activeSheet->mergeCells('AK'.$b.':AK'.($b+1));
                $activeSheet->mergeCells('AL'.$b.':AL'.($b+1));
                $activeSheet->mergeCells('AM'.$b.':AM'.($b+1));
                $activeSheet->mergeCells('AN'.$b.':AN'.($b+1));
                $activeSheet->mergeCells('AO'.$b.':AO'.($b+1));
                $activeSheet->mergeCells('AP'.$b.':AP'.($b+1));
                $activeSheet->mergeCells('AQ'.$b.':AQ'.($b+1));
                $activeSheet->mergeCells('AR'.$b.':AR'.($b+1));
                $activeSheet->mergeCells('AS'.$b.':AS'.($b+1));
                //b => 行
                $b = $b+2;

            }//分人员

            $department_employ = array();
        }







        //输出xlsx文件
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename=result.xlsx');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($signResult, 'Excel2007');
        $objWriter->save('php://output');
   }

}
