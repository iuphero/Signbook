<?php 
    
App::uses('AppController', 'Controller');

class ShowController extends AppController {


    public $name = 'Show';

    public function parseFile() {

        //load phpexcel
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');
        
        $fileInfo = $this->request->data['signfile'];
        if($fileInfo['error'] != 0) {
            $this->Session->setFlash('亲，上传文件出现错误，请重新上传','flash_custom');
            $this->redirect(array(
                    'controller' => 'pages',
                    'action' => 'index'
                )
            );
        }

        $inputFileName = $fileInfo['tmp_name'];
        $objReader = PHPExcel_IOFactory::createReader('Excel2007');
        $objReader = $objReader->load($inputFileName);
        $sheet = $objReader->getsheet(0);

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);

        $youmi = array();

        for($j=2;$j<=$highestRow;$j++) {
            $department = $sheet->getCellByColumnAndRow(21,$j)->getValue();
            $employee = $sheet->getCellByColumnAndRow(3,$j)->getValue();

            $date = $sheet->getCellByColumnAndRow(5,$j)->getValue();
            $date = gmdate('Y-m-d-l',PHPExcel_shared_date::ExcelToPhp($date));
            $date_time = substr($date,0,10);
            $day_time = substr($date,11);
            $date = array();

            $youmi[$department][$employee][$date_time] = array('morning'=>'regular','afternoon'=>'regular');

            //特殊情况
             if($sheet->getCellByColumnAndRow(9,$j)->getValue()==null) {
                $youmi[$department][$employee][$date_time]['morning'] = 'special';
             }
             if($sheet->getCellByColumnAndRow(10,$j)->getValue()==null) {
                $youmi[$department][$employee][$date_time]['afternoon'] = 'special';
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

             //周六周日公众假期 权重最高
            if($day_time == 'Saturday' || $day_time == 'Sunday') {
                $youmi[$department][$employee][$date_time] = array('morning'=>'off','afternoon'=>'off');
            }
        }

        $this->set('youmi', $youmi);

        /*
        echo '<table class="sb-table">';
        echo '<tr>';
        $one_employee = $youmi['研发中心']['梁家豪'];
        echo '<td>姓名</td>';
        foreach($one_employee as $key => $value) {
            $key = substr($key,8);
            echo '<td>'.$key.'</td>';
        }
        echo '</tr>';

        foreach($youmi as $department => $dpt) {
            foreach($dpt as $employee => $emp) {
                echo '<tr>';
                echo '<td rowspan="2">'.$employee.'</td>';

                foreach($emp as $date_time => $date ){
                    echo '<td>';
                    echo $date['morning'];
                    echo '</td>';         
                }
                echo '</tr>';
                echo '<tr>';
                foreach($emp as $date_time => $date ){
                    echo '<td>';
                    echo $date['afternoon'];
                    echo '</td>';         
                         
                }
                echo '</tr>';
            }
        }

        echo '</table>';
         */

    }
    /*
    public function getDptRecords() {

        //load phpexcel
        APP::uses('PHPExcel','Vendor/excel/Classes');
        $objPHPExcel = new PHPExcel();

        $dpt_name = $this->request->query['dpt_name'];
        $month = $this->request->query['month'];

        $month2 = str_replace('-', '', $month); //201402

        //查找该部门对应标识id
        $dpt_id = $this->Department->field('id',array('name'=>$dpt_name));

        $has_records = $this->SignRecord->find('count',array(
                'conditions' => array(
                    'year_and_month' => $month2,
                    'dpt_id' => $dpt_id
                )
            )
        );

        if(!$has_records) {
            $this->Session->setFlash('亲，没有查到相关记录，请先上传文件分析','flash_custom');
            $this->redirect('/');
        }

        //department表内所有该部门的记录
        $results = $this->Department->find('all', array(
                'conditions' => array(
                    'Department.name' => $dpt_name
                )
            )
        );

        $employees = $results[0]['Employee'];
        $month_timestamp = strtotime($month);
        $days = (int)date('t', $month_timestamp);   

        $results = array();
        foreach ($employees as $employee) {//for a employee
            
            $employee_id = $employee['id'];
            $name = $employee['name'];

            for ($i= 1; $i <= $days ; $i++ ) { //for the emploeyee's a day
                $whichday = $month . '-' .$i;
                
                $records = $this->SignRecord->find('first',array(
                        'conditions' => array(
                            'whichday' => $whichday,
                            'employee_id' => $employee_id
                        )
                    )
                );

                if(empty($records)) {
                    $results[$name][$i]['sign_start'] = null;
                    $results[$name][$i]['sign_end'] = null;
                    $results[$name][$i]['state_forenoon'] = 0;
                    $results[$name][$i]['state_afternoon'] = 0;
                    $results[$name][$i]['is_abnormal'] = 0;
                }
                else {
                    $results[$name][$i]['sign_start'] = $records['SignRecord']['sign_start'];
                    $results[$name][$i]['sign_end'] = $records['SignRecord']['sign_end'];
                    $results[$name][$i]['state_forenoon'] = $records['SignRecord']['state_forenoon'];
                    $results[$name][$i]['state_afternoon'] = $records['SignRecord']['state_afternoon'];
                    $results[$name][$i]['is_abnormal'] = (int)$records['SignRecord']['is_abnormal'];
                }

            }

        }// end foreach.employees

        $this->Department->recursive = -1;
        $dpt_records = $this->Department->find('all',array(
                'fields' => array('name')
            )
        );
        $dpt_names = array();
        foreach ($dpt_records as $dpt_record) {
            $dpt_names[] = $dpt_record['Department']['name'];
        }

        list($year, $month) = explode('-', $month);
        $this->set('year', $year);
        $this->set('month', $month);
        $this->set('department', $dpt_name);
        $this->set('dpt_names', $dpt_names);
        $this->set('results', $results);
    }
     */

}
