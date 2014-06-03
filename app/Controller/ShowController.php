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

             //周六周日公众假期 权重最高
            if($day_time == 'Saturday' || $day_time == 'Sunday') {
                $youmi[$department][$employee][$date_time] = array('morning'=>'off','afternoon'=>'off');
            }
        }

        $this->set('youmi', $youmi);

    }

}
