<?php
App::uses('ExcelController', 'Controller');

class LeaveController extends ExcelController {

    public function beforeFilter() {
        parent::beforeFilter();
        $this->autoRender = false;
    }

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
     * 判断指定的月份内是否有请假数据
     * @return boolean
     */
    public function hasLeaveData() {
        // $time = strtotime('2014-06'); //dummy data just for test
        $time = strtotime( $this->data['time'] );
        $firstDay = date('Y-m-01', $time);
        $lastDay = date('Y-m-t', $time);
        $this->loadModel('LeaveRecord');
        $count = $this->LeaveRecord->find('count', array(
            'recursive' => -1,
            'conditions' => array(
                'LeaveRecord.start_time BETWEEN ? AND ?' => array($firstDay, $lastDay)
            )
        ));

        if($count < 10) {//todo, 这个判定不严格, 还找一个更好的方法
            return 0;
        }
        else {
            return 1;
        }
    }


    /**
     * 输出指定年月时间内的请假记录
     * @param  $time string 年月时间，例如'2014-07'
     * @return An excel file
     */
    public function outputLeave($time) {

        // $this->time = '2014-06'; //dummy data
        $this->time = $time;
        $this->loadModel('Department');
        $employees = $this->Department->get_epy2dpt();
        $department = array();
        $this->loadModel('LeaveRecord');
        $this->loadModel('SignRecord');
        foreach($employees as $epy){
            $name = $epy['epy']['name'];
            $dpt1_id = $epy['dpt1']['dpt1_id'];
            $dpt2_id = $epy['dpt2']['dpt2_id'];
            if(!is_numeric($dpt2_id) || empty($name)) {
                continue;
            }
            $dpt3_id = $epy['dpt3']['dpt3_id'];
            $epy_id = $epy['epy']['id'];
            $leaves = $this->LeaveRecord->getLeavesById($epy_id, $time);
            $department[$dpt1_id]['name'] = $epy['dpt1']['dpt1_name'];
            $department[$dpt1_id]['dpt2s'][$dpt2_id]['name'] = $epy['dpt2']['dpt2_name'];
            $department[$dpt1_id]['dpt2s'][$dpt2_id]['employees'][] = array(
                'name' => $epy['epy']['name'],
                'job_id' => $epy['epy']['job_id'],
                'leaves' => $leaves
            );
            unset($leaves);
        }
        ksort($department);
        unset($employees);

        //开始写Excel文件
        $this->setWriter();
        $sheet = $this->sheet;
        $this->setLeaveHeader();
        $i = 5; //从A5开始写
        foreach($department as $dpt) {
            $dpt1_name = $dpt['name'];
            foreach($dpt['dpt2s'] as $dpt2) {
                $dpt2_name = $dpt2['name'];
                foreach($dpt2['employees'] as $epy) {
                    $sheet->setCellValue('A'. $i, $dpt1_name); //一级部门
                    $sheet->setCellValue('B'. $i, $dpt2_name); //二级部门
                    $sheet->setCellValue('C'. $i, $epy['job_id']);
                    $sheet->setCellValue('D'. $i, $epy['name']);
                    if($epy['leaves'] === false) {
                        $sheet->setCellValue('E'. $i, 0);
                        $sheet->setCellValue('F'. $i, 0);
                        $sheet->setCellValue('G'. $i, 0);
                        $sheet->setCellValue('H'. $i, 0);
                        $sheet->setCellValue('I' .$i, 0);
                        $sheet->setCellValue('J'. $i, ' ');
                        $sheet->mergeCells('J'. $i. ':'. 'K'. $i);
                    }
                    else {
                        $sheet->setCellValue('E'. $i, $epy['leaves']['casual']); //事假
                        $sheet->setCellValue('F'. $i, $epy['leaves']['annual']); //病假
                        $sheet->setCellValue('G'. $i, $epy['leaves']['sick']); //年假
                        $sheet->setCellValue('H'. $i, $epy['leaves']['payback']); //调休
                        $leaveText = '';
                        foreach($epy['leaves']['travel']['records'] as $record) { //2014-06-1
                            $leaveText .= sprintf('%s至%s出差%s; ', substr($record['start_time'], 5),
                                substr($record['end_time'], 5),
                                $record['destination']
                                // substr($record['destination'], 0, 12)
                            );
                        }
                        $sheet->setCellValue('I'. $i, $epy['leaves']['travel']['sum']);
                        $sheet->setCellValue('J'. $i, $leaveText);
                        $sheet->mergeCells('J'. $i. ':'. 'K'. $i);
                    }
                    $i++;
                }
            }
        }
        $this->setCellCenter('A5:K'.$i);
        $this->outputExcel('出差请假汇总');
    }


    /**
     * 设置假期Excel文件头
     */
    private function setLeaveHeader() {
        $sheet = $this->sheet;
        $sheet->setCellValue('A1', '编制日期'. date('Y/m/d'));
        $sheet->mergeCells('A1:C2');
        $this->setCellFont('A1:C2', true, 12);

        $sheet->setCellValue('A3', '一级部门');
        $sheet->mergeCells('A3:A4');

        $sheet->setCellValue('B3', '二级部门');
        $sheet->mergeCells('B3:B4');

        $sheet->setCellValue('C3', '工号');
        $sheet->mergeCells('C3:C4');

        $sheet->setCellValue('D3', '姓名');
        $sheet->mergeCells('D3:D4');

        $sheet->setCellValue('E3', '事假');
        $sheet->mergeCells('E3:E4');

        $sheet->setCellValue('F3', '病假');
        $sheet->mergeCells('F3:F4');

        $sheet->setCellValue('G3', '年假');
        $sheet->mergeCells('G3:G4');

        $sheet->setCellValue('H3', '调休');
        $sheet->mergeCells('H3:H4');

        $sheet->setCellValue('I3', '出差情况');
        $sheet->mergeCells('I3:K3');

        $sheet->setCellValue('I4', '出差总天数');
        $sheet->setCellValue('J4', '出发时间');
        $sheet->setCellValue('K4', '返程时间');


        $this->setCellCenter('A1:K4');
        $this->setCellFont('A1:H4', true, 14);
        $this->setCellBackground('A3:H4', '00f6ff');
        $this->setCellBackground('I3:K4', 'FFFF33');
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(25);
        $sheet->getColumnDimension('K')->setWidth(25);
        $this->setCellBorder('A1:K4');
    }



    protected function isRightExcel($filename) {
        $loadFile = $this->reader->load($filename);
        //判断0和1，从0开始
        for($i = 0; $i <= 1; $i++) {
            try{
                $sheet = $loadFile->getsheet($i);
            }
            catch(Exception $e){
                continue;
            }
            $shouldBeJobID = trim( $sheet->getCellByColumnAndRow($this->leaveIndex['job_id'], 1)->getValue() );
            $shouldBeName = trim( $sheet->getCellByColumnAndRow($this->leaveIndex['name'], 1)->getValue() );
            $shouldBeStart = trim( $sheet->getCellByColumnAndRow($this->leaveIndex['start_time'], 1)->getValue() );
            $shouldBeEnd = trim( $sheet->getCellByColumnAndRow($this->leaveIndex['end_time'], 1)->getValue() );
            $shouldBeType = trim( $sheet->getCellByColumnAndRow($this->leaveIndex['type'], 1)->getValue() );
            if( ($shouldBeJobID != '考勤号码' && $shouldBeJobID != '工号')||
                ($shouldBeName != '姓名' && $shouldBeName != '名字')||
                 $shouldBeStart != '开始时间'||
                 $shouldBeEnd != '结束时间'||
                 $shouldBeType != '假类') {
                continue;
            }
            else {
                return $sheet;
            }
        }
        return false;
    }


    /**
     * 解析上传的请假文件
     *
     */
    public function parseLeave() {

        $result = $this->uploadFile('leave');
        if($result['code'] == 0) {
            return $result['info'];
        }
        else {
            $filename = $result['info'];
        }

        $reader = $this->setReader('Excel2007');
        $this->sheet = $sheet = $this->isRightExcel($filename);
        if($sheet === false) {
            return $this->getTypeError();
        }

        $highestRow = $sheet->getHighestRow();  //总行数

        if($highestRow <= 5) {
            return $this->getTypeError();
        }

        if($this->year === false) {
            $tmpDate = $sheet->getCellByColumnAndRow($this->leaveIndex['start_time'], 2)->getFormattedValue(); // '2014-06-16 9:00:00'
            $this->year = substr($tmpDate, 2, 2);
        }

        $result = array();
        for($i = 2; $i <= $highestRow; $i++) {
            $name = trim($sheet->getCellByColumnAndRow($this->leaveIndex['name'], $i)->getValue());
            $job_id = trim($sheet->getCellByColumnAndRow($this->leaveIndex['job_id'], $i)->getValue());

            //'2014-06-22 9:00:00'
            $start_time = trim($sheet->getCellByColumnAndRow($this->leaveIndex['start_time'], $i)->getValue());

            //'2014-06-25 18:00:00'
            $end_time = trim($sheet->getCellByColumnAndRow($this->leaveIndex['end_time'], $i)->getValue());

            $type = trim($sheet->getCellByColumnAndRow($this->leaveIndex['type'], $i)->getValue());
            $reason = trim($sheet->getCellByColumnAndRow($this->leaveIndex['reason'], $i)->getValue());


            $tmp_hours = ( strtotime($end_time) - strtotime($start_time) ) / 3600; //请假持续小时数
            if($tmp_hours < 5) {
                $duration = 1; // 小于5个小时为半天, $duration以半天为基本单位
            }
            else {
                $duration = ceil($tmp_hours / 24) * 2;
            }

            $type = $this->leave2num[$type];
            $this->loadModel('Employee');
            $employee_id = $this->Employee->field('id', array('Employee.name' => $name));
            if($employee_id === false) {//找不到员工号
                continue;
            }

            $result[] = array(
                'type' => $type,
                'employee_id' => $employee_id,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'name' => $name,
                'duration' => $duration,
                'reason' => $reason,
                'year' =>  $this->year
            );

        }//end for
        $this->loadModel('LeaveRecord');
        file_put_contents('/home/xfight/tmp/hello', print_r($result, true));
        if($this->LeaveRecord->saveMany($result)) {
            return json_encode(array(
                'code' => 1
            ));
        }
        else {
            return $this->getDBError($this->LeaveRecord->validationErrors);
        }

    }// end parseLeave

}
