<?
App::uses('Controller', 'Controller');

/**
 * 用于处理Excel分析和导出的Ajax请求
 */
class ExcelAjaxController extends AppController {


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

    public $sign2symbol = array(
        self::NORMAL => '√',
        self::HOLIDAY => '●',
        self::FUNERAL => '○',
        self::CASUAL => '○',
        self::SICK => '☆',
        self::TRAVEL => '△',
        self::ABSENT => '×',
        self::EMPTYDAY => '×',
        self::LATE => '※',
        self::EARLY => '◇',
        self::HALFWAY => '◇',
        self::ANNUAL => '◆',
        self::PAYBACK => '◆'
    );

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

    public $leave2num = array(
        '事假' => self::CASUAL,
        '出差' => self::TRAVEL,
        '年假' => self::ANNUAL,
        '病假' => self::SICK,
        '丧假' => self::FUNERAL,
        '调休' => self::PAYBACK
    );

    public function beforeFilter() {
        parent::beforeFilter();
        $this->autoRender = false;
    }

    public function hasSignData() {
        $time = strtotime( $this->data['time'] );
        $firstDay = date('Y-m-01', $time);
        $lastDay = date('Y-m-t', $time);
        $this->loadModel('SignRecord');
        $count = $this->SignRecord->find('count', array(
            'recursive' => -1,
            'conditions' => array(
                'SignRecord.date BETWEEN ? AND ?' => array($firstDay, $lastDay)
            )
        ));

        if($count < 100) {//todo, 这个判定不严格, 还找一个更好的方法
            return 0;
        }
        else {
            return 1;
        }
    }


    public function hasLeaveData() {
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

    public function outputSign($time = '2014-06') {
        $this->time = $time;
        // $holidays = array(1,2,7,8,14,15,21,22,28,29);//dummy data
        $holidays = array();
        $this->loadModel('Department');
        $employees = $this->Department->get_epy2dpt();
        $department = array();
        foreach($employees as $epy){
            $name = $epy['epy']['name'];
            $dpt1_id = $epy['dpt1']['dpt1_id'];
            $dpt2_id = $epy['dpt2']['dpt2_id'];
            if(!is_numeric($dpt2_id) || empty($name)) {
                continue;
            }
            $dpt3_id = $epy['dpt3']['dpt3_id'];
            $epy_id = $epy['epy']['id'];
            $leaves = $this->getLeavesById($epy_id);
            $signs = $this->getSignsById($epy_id);
            $department[$dpt1_id]['name'] = $epy['dpt1']['dpt1_name'];
            $department[$dpt1_id]['dpt2s'][$dpt2_id]['name'] = $epy['dpt2']['dpt2_name'];
            $department[$dpt1_id]['dpt2s'][$dpt2_id]['employees'][] = array(
                'name' => $epy['epy']['name'],
                'job_id' => $epy['epy']['job_id'],
                'leaves' => $leaves,
                'signs' => $signs
            );
            unset($leaves);
            unset($signs);
        }
        ksort($department);
        unset($employees);
        // header("Content-type:text/html;charset=utf8");
        // debug($department); exit();

        //开始写Excel文件
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vendor','/excel/Classes/PHPExcel/Writer/Excel2007');
        $excel = new PHPExcel();
        $excel->setActiveSheetIndex(0);
        $this->sheet = $sheet = $excel->getActiveSheet();
        $this->setSignHeader();
        $i = 7;
        $dayCount = (int)date('t', strtotime($this->time));
        foreach($department as $dpt) {
            $dpt1_name = $dpt['name'];
            foreach($dpt['dpt2s'] as $dpt2) {
                $dpt2_name = $dpt2['name'];
                foreach($dpt2['employees'] as $epy) {//开始输出一个员工的记录, 一人两行
                    $startColAfterDays = $dayCount + 2;
                    $j = $i + 1;
                    $sheet->setCellValue('A'. $i, $epy['name']);
                    $sheet->mergeCells("A{$i}:A{$j}");
                    $sheet->setCellValue('B'. $i, '上午');
                    $sheet->setCellValue('B'. $j, '下午');
                    $signs = $epy['signs'];
                    $normalCount = 0; //正常天数
                    $holidayCount = 0;
                    $travelCount =  0;
                    $casualCount = 0;
                    $sickCount = 0;
                    $annualAndPaybackCount = 0; //年假和调休天数
                    if (!empty($epy['leaves'])) {
                        if (!empty(@$epy['leaves']['travel'])) {
                            $travelCount = $epy['leaves']['travel']['sum'];
                        }
                        $casualCount = $epy['leaves']['casual'];
                        $sickCount = $epy['leaves']['sick'];
                        $annualAndPaybackCount = $epy['leaves']['casual'] + $epy['leaves']['payback']; //年假和调休天数
                    }

                    $absentCount = 0;
                    $lateCount = 0;
                    $earlyCount = 0;

                    if(empty($signs)) { //没有考勤记录
                        for($day = 1, $col = 2; $day <= $dayCount; $day++, $col++) {
                            if(in_array($day, $holidays)) {
                                $state_afternoon = $state_forenoon = $this->sign2symbol[self::HOLIDAY];
                            }
                            else {
                                $state_afternoon = $state_forenoon = $this->sign2symbol[self::EMPTYDAY];
                            }
                            $sheet->setCellValueByColumnAndRow($col, $i, $state_forenoon);
                            $sheet->setCellValueByColumnAndRow($col, $j, $state_afternoon);
                        } //end for
                    }
                    else { //有考勤记录
                        for($day = 1, $col = 2; $day <= $dayCount; $day++, $col++) {
                            if(in_array($day, $holidays)) {
                                $state_afternoon = $state_forenoon = $this->sign2symbol[self::HOLIDAY];
                            }
                            else {
                                $signOfDay = @$signs[$day] or false;
                                if(empty($signOfDay)) {
                                    $state_afternoon = $state_forenoon = $this->sign2symbol[self::EMPTYDAY];
                                }
                                else {
                                    $raw_forenoon = $signOfDay['state_forenoon'];
                                    $raw_afternoon = $signOfDay['state_afternoon'];
                                    if($raw_forenoon == self::NORMAL ||
                                        $raw_afternoon == self::NORMAL) {
                                        $normalCount ++;
                                    }
                                    if ($raw_forenoon == self::LATE || $raw_afternoon == self::LATE) {
                                        $lateCount ++;
                                    }
                                    if ($raw_forenoon == self::EARLY || $raw_afternoon == self::EARLY) {
                                        $earlyCount ++;
                                    }
                                    if ($raw_forenoon == self::ABSENT|| $raw_afternoon == self::ABSENT) {
                                        $absentCount ++;
                                    }
                                    if ($raw_forenoon == self::HOLIDAY|| $raw_afternoon == self::HOLIDAY) {
                                        $holidayCount ++;
                                    }
                                    $state_forenoon = $this->sign2symbol[$raw_forenoon];
                                    $state_afternoon = $this->sign2symbol[$raw_afternoon];
                                }
                            }
                            $sheet->setCellValueByColumnAndRow($col, $i, $state_forenoon);
                            $sheet->setCellValueByColumnAndRow($col, $j, $state_afternoon);
                        } //end for
                    }
                    //正常出勤天数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $normalCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //外地出差天数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $travelCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //市内出差天数, 注意目前无法统计市内出差, 市内出差的统计到外地出差内部
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, 0);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //休假天数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $holidayCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //事假天数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $casualCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //病假天数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $sickCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //旷工天数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $absentCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //迟到次数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $lateCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //早退次数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $earlyCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //年假调休天数
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $annualAndPaybackCount);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //一级部门
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $dpt1_name);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //二级部门
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $dpt2_name);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //工号
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $epy['job_id']);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    //姓名
                    $sheet->setCellValueByColumnAndRow($startColAfterDays, $i, $epy['name']);
                    $sheet->mergeCells($this->cellsToMergeByRowsCol($i, $j, $startColAfterDays));
                    $startColAfterDays++;

                    $i += 2;
                }//end foreach $dpt2['employees']
            }//end foreach $dpt['dpt2s']
        }//end foreach $department
        $this->setCellCenter('A1:AU1000');
        $excel->getActiveSheet()->setTitle('出差请假汇总');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename=result.xlsx');
        header('Cache-Control: max-age=0');
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }


    public function setSignHeader() {
        $sheet = $this->sheet;
        $sheet->setCellValue('A1', '编制日期'. date('Y/m/d'));
        $sheet->mergeCells('A1:H1');

        $sheet->setCellValue('A5', '姓名');
        $sheet->mergeCells('A5:A6');

        $sheet->setCellValue('B5', '星期');
        $sheet->setCellValue('B6', '日');


        $YM = date('Y-m-', strtotime($this->time));
        $dayCount = (int)date('t', strtotime($this->time));
        $colCount = 2;
        for($i = 1; $i <= $dayCount; $i++) {
            $day = $this->getXQJ($YM. $i);;
            $sheet->setCellValueByColumnAndRow($colCount, 6, $i);
            $sheet->setCellValueByColumnAndRow($colCount, 5, $day);
            $colCount ++;
        }

        $sheet->setCellValueByColumnAndRow($colCount, 5, '正常出勤');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '天数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '外地出差');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '天数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '市内出差');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '天数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '休假');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '天数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '事假');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '天数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '病假');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '天数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '旷工');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '天数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '迟到');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '次数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '早退');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '次数');
        $colCount++;
        $sheet->setCellValueByColumnAndRow($colCount, 5, '年假/调休');
        $sheet->setCellValueByColumnAndRow($colCount, 6, '天数');
        $colCount++;

        $sheet->setCellValueByColumnAndRow($colCount, 5, '一级部门');
        $sheet->mergeCells($this->cellsToMergeByRowsCol(5, 6, $colCount));
        $colCount++;

        $sheet->setCellValueByColumnAndRow($colCount, 5, '二级部门');
        $sheet->mergeCells($this->cellsToMergeByRowsCol(5, 6, $colCount));
        $colCount++;

        $sheet->setCellValueByColumnAndRow($colCount, 5, '工号');
        $sheet->mergeCells($this->cellsToMergeByRowsCol(5, 6, $colCount));
        $colCount++;

        $sheet->setCellValueByColumnAndRow($colCount, 5, '姓名');
        $sheet->mergeCells($this->cellsToMergeByRowsCol(5, 6, $colCount));

        $sheet->getColumnDimension('C')->setWidth(4);
        $sheet->getColumnDimension('D')->setWidth(4);
        $sheet->getColumnDimension('E')->setWidth(4);
        $sheet->getColumnDimension('F')->setWidth(4);
        $sheet->getColumnDimension('G')->setWidth(4);
        $sheet->getColumnDimension('H')->setWidth(4);
        $sheet->getColumnDimension('I')->setWidth(4);
        $sheet->getColumnDimension('J')->setWidth(4);
        $sheet->getColumnDimension('K')->setWidth(4);
        $sheet->getColumnDimension('L')->setWidth(4);
        $sheet->getColumnDimension('M')->setWidth(4);
        $sheet->getColumnDimension('N')->setWidth(4);
        $sheet->getColumnDimension('O')->setWidth(4);
        $sheet->getColumnDimension('P')->setWidth(4);
        $sheet->getColumnDimension('Q')->setWidth(4);
        $sheet->getColumnDimension('R')->setWidth(4);
        $sheet->getColumnDimension('S')->setWidth(4);
        $sheet->getColumnDimension('T')->setWidth(4);
        $sheet->getColumnDimension('U')->setWidth(4);
        $sheet->getColumnDimension('V')->setWidth(4);
        $sheet->getColumnDimension('W')->setWidth(4);
        $sheet->getColumnDimension('X')->setWidth(4);
        $sheet->getColumnDimension('Y')->setWidth(4);
        $sheet->getColumnDimension('Z')->setWidth(4);
        $sheet->getColumnDimension('AA')->setWidth(4);
        $sheet->getColumnDimension('AB')->setWidth(4);
        $sheet->getColumnDimension('AC')->setWidth(4);
        $sheet->getColumnDimension('AD')->setWidth(4);
        $sheet->getColumnDimension('AE')->setWidth(4);
        $sheet->getColumnDimension('AF')->setWidth(4);
        $sheet->getColumnDimension('AG')->setWidth(12);

        $sheet->getColumnDimension('AQ')->setWidth(12);
        $sheet->getColumnDimension('AR')->setWidth(12);
        $sheet->getColumnDimension('AS')->setWidth(12);
        $sheet->getColumnDimension('AT')->setWidth(12);
        $sheet->getColumnDimension('AU')->setWidth(12);

    }

    /**
     * 通过时间获取中文表示的星期几
     * @param  $date  string 如'2014-9-12'
     * @return '一' 到 '日'
     */
    private function getXQJ($date) {
        $day = date('D',strtotime($date));
        $map = array(
            'Mon' => '一',
            'Tue' => '二',
            'Wed' => '三',
            'Thu' => '四',
            'Fri' => '五',
            'Sat' => '六',
            'Sun' => '日'
        );
        return $map[$day];
    }

    public function outputLeave($time = '2014-07') {

        // $this->time = '2014-06'; //dummy data
        $this->time = $time;
        $this->loadModel('Department');
        $employees = $this->Department->get_epy2dpt();
        $department = array();
        foreach($employees as $epy){
            $name = $epy['epy']['name'];
            $dpt1_id = $epy['dpt1']['dpt1_id'];
            $dpt2_id = $epy['dpt2']['dpt2_id'];
            if(!is_numeric($dpt2_id) || empty($name)) {
                continue;
            }
            $dpt3_id = $epy['dpt3']['dpt3_id'];
            $epy_id = $epy['epy']['id'];
            $leaves = $this->getLeavesById($epy_id);
            $signs = $this->getSignsById($epy_id);
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
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vendor','/excel/Classes/PHPExcel/Writer/Excel2007');
        $excel = new PHPExcel();
        $excel->setActiveSheetIndex(0);
        $this->sheet = $sheet = $excel->getActiveSheet();
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
                        $sheet->setCellValue('I' .$i, '无请假记录');
                        $sheet->mergeCells('I'. $i. ':'. 'K'. $i);
                    }
                    else {
                        $sheet->setCellValue('E'. $i, $epy['leaves']['casual']); //事假
                        $sheet->setCellValue('F'. $i, $epy['leaves']['annual']); //病假
                        $sheet->setCellValue('G'. $i, $epy['leaves']['sick']); //年假
                        $sheet->setCellValue('H'. $i, $epy['leaves']['payback']); //调休
                        $leaveText = '';
                        foreach($epy['leaves']['travel']['records'] as $record) {
                            $leaveText .= sprintf('%s至%s出差%s; ', $record['start_time'],
                                $record['end_time'],
                                substr($record['destination'], 0, 9)
                            );
                        }
                        $sheet->setCellValue('I'. $i, $leaveText);
                        $sheet->mergeCells('I'. $i. ':'. 'J'. $i);
                        $sheet->setCellValue('K'. $i, $epy['leaves']['travel']['sum']);
                    }
                    $i++;
                }
            }
        }
        $this->setCellCenter('A5:K'.$i);
        $excel->getActiveSheet()->setTitle('出差请假汇总');
        $excel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename=result.xlsx');
        header('Cache-Control: max-age=0');
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save('php://output');
        exit();
    }


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

        $sheet->setCellValue('I3', '出差');
        $sheet->mergeCells('I3:K3');

        $sheet->setCellValue('I4', '出发时间');
        $sheet->setCellValue('J4', '返程时间');
        $sheet->setCellValue('K4', '出差总天数');


        $this->setCellCenter('A1:K4');
        $this->setCellFont('A1:H4', true, 14);
        $this->setCellBackground('A3:H4', '00f6ff');
        $this->setCellBackground('I3:K4', 'FFFF33');
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(20);
        $sheet->getColumnDimension('J')->setWidth(20);
        $sheet->getColumnDimension('K')->setWidth(10);
        $this->setCellBorder('A1:K4');

    }


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

        if(empty($id)) {
            return array(self::EMPTYDAY, self::EMPTYDAY);
        }

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
                    if($isForenoonInLeave || $isAfternoonInLeave) {
                        break;
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
                        break;
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
                        break;
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




    public function getSignsById($epy_id = 13) {
        $this->time = '2014-06';
        $this->loadModel('SignRecord');
        $firstDay = date('Y-m-01', strtotime($this->time));
        $lastDay = date('Y-m-t', strtotime($this->time));
        $records = $this->SignRecord->find('all', array(
            'fields' => array('DAY(date) as day', 'state_forenoon', 'state_afternoon'),
            'conditions' => array(
                'employee_id' => $epy_id,
                'AND' => array(
                    'DATE(date) >=' => $firstDay,
                    'DATE(date) <=' => $lastDay,
                )
            ),
            'order' => 'date'
        ));

        if(empty($records)) {
            return false;
        }
        else {
            $result = array();
            foreach($records as $record) {
                $key = $record[0]['day'];
                $result[$key] = $record['SignRecord'];
            }
            unset($records);
            return $result;
        }
    }

    /**
     * 根据员工id获取此月(来源于....)请假记录
     * @param  $epy_id integer 员工id
     * @return boolean or array 没有请假记录时返回false, 有请假记录时返回类似的数组:
     * array(
     *       'casual' => (int) 0,
     *       'annual' => (int) 0,
     *       'sick' => (int) 0,
     *       'payback' => (int) 0,
     *       'travel' => array(
     *           'sum' => (int) 5,
     *           'records' => array(
     *              (int) 0 => array(
     *                   'start_time' => '2014-06-24',
     *                   'end_time' => '2014-06-25',
     *                   'diffDay' => (int) 2,
     *                   'destination' => '北京'
     *              ),
     *             (int) 1 => array(
     *                   'start_time' => '2014-06-26',
     *                   'end_time' => '2014-06-28',
     *                   'diffDay' => (int) 3,
     *                   'destination' => '上海'
     *               )
     *           )
     *       )
     *   )
     */
    public function getLeavesById($epy_id) {
        $this->loadModel('LeaveRecord');
        $firstDay = date('Y-m-01', strtotime($this->time));
        $lastDay = date('Y-m-t', strtotime($this->time));
        $firstDay_ttp = strtotime($firstDay);
        $lastDay_ttp = strtotime($lastDay);
        $records = $this->LeaveRecord->find('all', array(
            'fields' => array('type', 'start_time', 'end_time', 'duration', 'reason'),
            'conditions' => array(
                'employee_id' => $epy_id,
                'AND' => array(
                    'DATE(start_time) <=' => $lastDay,
                    'DATE(end_time) >=' => $firstDay
                )
            )
        ));

        if(empty($records)) {
            return false;
        }

        //没有"丧假", 将丧假算入事假
        $result = array(
            'casual' => 0, //事假
            'annual' => 0, //年假
            'sick' => 0,   //病假
            'payback' => 0, //调休
            'travel' => array('sum' => 0, 'records' => array()) //出差记录
        );

        foreach($records as $record) {
            $startDay = date('Y-m-d', strtotime($record['LeaveRecord']['start_time']) );
            $endDay = date('Y-m-d', strtotime($record['LeaveRecord']['end_time']) );
            $startDay_ttp = strtotime($startDay);
            $endDay_ttp = strtotime($endDay);
            $type = (int)$record['LeaveRecord']['type'];
            $reason = $record['LeaveRecord']['reason'];

            if($startDay_ttp < $firstDay_ttp && $endDay_ttp <= $lastDay_ttp) {
                //跨月请假, 在此月前请假, 结束时间在此月中
                $diffDay = ($endDay_ttp - $firstDay_ttp) / 86400;
            }
            else if($startDay_ttp < $firstDay_ttp && $endDay_ttp > $lastDay_ttp) {
                //跨月请假, 在此月前请假, 在此月后结束请假, 整个月都在请假
                $diffDay = (int)date('t', $firstDay_ttp);
            }
            else if($startDay_ttp >= $firstDay_ttp && $endDay_ttp <= $lastDay_ttp) {
                //请假开始/截止时间都在此月中
                $diffDay = ($endDay_ttp - $startDay_ttp) / 86400;
            }
            else if($startDay_ttp >= $firstDay_ttp && $endDay_ttp > $lastDay_ttp) {
                //在此月请假, 请假截止时间在此月之后
                $diffDay = ($lastDay_ttp - $startDay_ttp) / 86400;
            }
            $diffDay += 1;

            switch($type) {
                case self::CASUAL:
                    $result['casual'] += $diffDay;
                    break;

                case self::SICK:
                    $result['sick'] += $diffDay;
                    break;

                case self::FUNERAL:
                    $result['casual'] += $diffDay;
                    break;

                case self::ANNUAL:
                    $result['annual'] += $diffDay;
                    break;

                case self::PAYBACK:
                    $result['payback'] += $diffDay;
                    break;

                case self::TRAVEL:
                    $result['travel']['sum'] += $diffDay;
                    $result['travel']['records'][] = array(
                        'start_time' => $startDay,
                        'end_time' => $endDay,
                        'diffDay' => $diffDay,
                        'destination' => $reason
                    );
                    break;
            }

        }// end foreach
        // debug($result);
       return $result;
    }


    /**
     * 解析上传的考勤文件
     * @param  $month     string 月份,例如'2014-08'
     * @param  $holidays  string 逗号分割的假期列表,例如'1,2,8,9'
     * @return
     */
    public function parseSign($month, $holidays) {
        $holidays = split(',', $holidays);
        $result = $this->uploadFile('sign');
        if($result['code'] == 0) {
            return $result['info'];
        }
        else {
            $filename = $result['info'];
        }

        // $filename = '/home/xfight/Download/signbook/考勤数据.xlsx';

        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vendor','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vendor','/excel/Classes/PHPExcel/Reader/Excel2007');
        $reader = PHPExcel_IOFactory::createReader('Excel2007');

        $sheet = $reader->load($filename)->getsheet(0);
        $tmpDate = $sheet->getCellByColumnAndRow($this->signIndex['date'], 2)->getFormattedValue(); // '06-01-14'
        if($this->year === false) {
            $this->year = (int)substr($tmpDate, -2);
        }
        if($this->month === false) {
            $this->month = (int)substr($tmpDate, 0, 2);
        }
        //todo, 判断年月是否正确

        $highestRow = $sheet->getHighestRow();
        if($highestRow < 100) {
            return json_encode(array(
                'code' => 0,
                'info' => '您上传的可能不是正确的Excel表格,请选择正确的文件'
            ));
        }

        $this->loadModel('Employee');
        $this->loadModel('Department');
        $this->loadModel('LeaveRecord');
        $this->epy2rule = $this->Department->get_epy2rule(); //员工=>考勤规则
        $this->epy2leave = $this->LeaveRecord->get_epy2leave($this->year, $this->month); //员工=>请假记录

        $result = array();

        //todo, 小于两行提示出错
        for($i = 2; $i <= $highestRow ; $i++) {
            $name = $sheet->getCellByColumnAndRow($this->signIndex['epyname'], $i)->getValue();
            $job_id = $sheet->getCellByColumnAndRow($this->signIndex['job_id'], $i)->getValue();
            $date = $sheet->getCellByColumnAndRow($this->signIndex['date'], $i)->getFormattedValue(); //'06-01-14'
            $sign_start = $sheet->getCellByColumnAndRow($this->signIndex['signtime'], $i)->getFormattedValue();
            $sign_end = $sheet->getCellByColumnAndRow($this->signIndex['leavetime'], $i)->getFormattedValue();

            $tmpDateAry = explode('-', $date);
            $whichDay = (int)$tmpDateAry[1];
            $is_holiday = in_array($whichDay, $holidays);
            $date = $this->year . '-' . $this->month . '-' . $whichDay;
            $epy_id = (int)$this->Employee->field('id', array('Employee.name' => $name));
            if($epy_id === false || $epy_id == 0) {//  通过工号找不到员工id时, 可能已经离职
                continue;
            }
            list($state_forenoon,$state_afternoon) = $this->getSignState($epy_id, $whichDay, $sign_start, $sign_end, $is_holiday);
            $result[] = array(
                'date' => $date,
                'employee_id' => $epy_id,
                'employee_name' => $name,
                'sign_start' => $sign_start,
                'sign_end' => $sign_end,
                'state_forenoon' => $state_forenoon,
                'state_afternoon' => $state_afternoon
            );
        }
        $this->loadModel('SignRecord');
        if( $this->SignRecord->saveMany($result) ) {
            return json_encode(array(
                'code' => 1
            ));
        }
        else {
            return json_encode(array(
                'code' => 0,
                'info' => '数据库保存出错，请联系程序猿'
            ));
        }
    }



    public function parseLeave() {

        $result = $this->uploadFile('leave');
        if($result['code'] == 0) {
            return $result['info'];
        }
        else {
            $filename = $result['info'];
        }

        // $filename = '/home/xfight/Download/signbook/请假数据.xlsx';
        //开始使用PHP-Excel分析文件
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vendor','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vendor','/excel/Classes/PHPExcel/Reader/Excel2007');
        $reader = PHPExcel_IOFactory::createReader('Excel2007');


        $sheet = $reader->load($filename)->getsheet(0);
        $highestRow = $sheet->getHighestRow();

        if($highestRow <= 5) {
            return json_encode(array(
                'code' => 0,
                'info' => '您上传的可能不是请假数据的Excel表格,请选择正确的文件'
            ));
        }

        $cellA1 = trim($sheet->getCellByColumnAndRow($this->leaveIndex['name'], 1));
        $cellB1 = trim($sheet->getCellByColumnAndRow($this->leaveIndex['job_id'], 1));
        if($cellA1 != '姓名' || $cellB1 != '考勤号码') {
            return json_encode(array(
                'code' => 0,
                'info' => '您上传的可能不是请假数据的Excel表格,请选择正确的文件'
            ));
        }

        // return $highestRow;
        if($this->year === false) {
            $tmpDate = $sheet->getCellByColumnAndRow($this->leaveIndex['start_time'], 2)->getFormattedValue(); // '2014-06-16 9:00:00'
            $this->year = substr($tmpDate, 2, 2);
        }

        for($i = 2; $i< $highestRow; $i++) {
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

            $type = $this->getLeaveType($type);
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
        if($this->LeaveRecord->saveMany($result)) {
            return json_encode(array(
                'code' => 1
            ));
        }
        else {
            return json_encode(array(
                'code' => 0,
                'info' => '数据库保存出错，请联系程序猿'
            ));
        }

    }// end parseLeave


    /**
     * 分析Excel文件,保存员工和部门的数据
     * 会先清空员工(employee)和部门表(department)
     * @param  $file  string  文件路径
     * @return boolean  成功返回true, 失败返回false
     */
    public function parseEmployee() {

        $result = $this->uploadFile('employee');
        if($result['code'] == 0) {
            return $result['info'];
        }
        else {
            $filename = $result['info'];
        }
        // $filename = '/home/xfight/Download/signbook/个人编号.xlsx';
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vendor','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vendor','/excel/Classes/PHPExcel/Reader/Excel2007');

        $reader = PHPExcel_IOFactory::createReader('Excel2007');
        $sheet = $reader->load($filename)->getsheet(1);
        $rowCount = $sheet->getHighestRow();  //总行数

        if($rowCount < 200) {
            return json_encode(array(
                'code' => 0,
                'info' => '您上传的可能不是正确的Excel表格,请选择正确的文件'
            ));
        }

        $jobIDIndex = 0;
        $nameIndex = 1;
        $dptV1Index = 2;
        $dptV2Index = 3;
        $dptV3Index = 4;

        $dptV1s = array();
        $dptV2s = array();
        $dptV3s = array();

        $employees = array();
        $this->loadModel('Department');
        $this->Department->deleteAll(array('1=1'));
        //todo,先从数据库构造数据,出错时恢复,这里用不到事务


        for ($i = 2; $i < $rowCount; $i++) {
            $jobID = trim( $sheet->getCellByColumnAndRow($jobIDIndex, $i)->getValue() );
            $name = trim( $sheet->getCellByColumnAndRow($nameIndex, $i)->getValue() );
            $dptV1 = trim( $sheet->getCellByColumnAndRow($dptV1Index, $i)->getValue() );
            $dptV2 = trim( $sheet->getCellByColumnAndRow($dptV2Index, $i)->getValue() );
            $dptV3 = trim( $sheet->getCellByColumnAndRow($dptV3Index, $i)->getValue() );

            /**
             *把工号补全为5位，如0001补全为10001
             */
            $jobID = (int)$jobID;
            if ($jobID < 10000) {
                $jobID += 10000;
            }

            if ( !empty($dptV1) && !isset($dptV1s[$dptV1]) ) {
                $this->Department->create();
                $result = $this->Department->save(array(
                        'name' => $dptV1,
                        'sign_rule_id' => 1,
                        'path' => '0/',
                        'level' => 1,
                        'parent_id' => 0
                ));
                if($result) {
                    $dptV1s[$dptV1] = $this->Department->id;
                }
                else {
                    return json_encode(array(
                        'code' => 0,
                        'info' => '数据库保存出错，请联系程序猿'
                    ));
                }
            }

            if ( !empty($dptV2) && !isset($dptV2s[$dptV1][$dptV2]) ) {
                $depV1ID = $dptV1s[$dptV1];
                $this->Department->create();
                $result = $this->Department->save(array(
                        'name' => $dptV2,
                        'sign_rule_id' => 1,
                        'path' => $depV1ID. '/',
                        'level' => 2,
                        'parent_id' => $depV1ID
                ));
                if($result) {
                    $dptV2s[$dptV1][$dptV2] = $this->Department->id;
                }
                else {
                    return json_encode(array(
                        'code' => 0,
                        'info' => '数据库保存出错，请联系程序猿'
                    ));
                }
            }

            if ( !empty($dptV3) && !isset($dptV3s[$dptV1][$dptV2][$dptV3]) ) {
                $dptV1ID = $dptV1s[$dptV1];
                $dptV2ID = $dptV2s[$dptV1][$dptV2];
                $this->Department->create();
                $result = $this->Department->save(array(
                        'name' => $dptV3,
                        'sign_rule_id' => 1,
                        'path' => $dptV1ID. '/' . $dptV2ID,
                        'level' => 3,
                        'parent_id' => $dptV2ID
                ));
                if($result) {
                    $dptV3s[$dptV1][$dptV2][$dptV3] = $this->Department->id;
                }
                else {
                    return json_encode(array(
                        'code' => 0,
                        'info' => '数据库保存出错，请联系程序猿'
                    ));
                }
            }

            $hasDptV3 = isset($dptV3s[$dptV1][$dptV2][$dptV3]);
            $hasDptV2 = isset($dptV2s[$dptV1][$dptV2]);
            $hasDptV1 = isset($dptV1s[$dptV1]);

            if ($hasDptV3) {
                $dptID = $dptV3s[$dptV1][$dptV2][$dptV3];
            }
            else if($hasDptV2) {
                $dptID = $dptV2s[$dptV1][$dptV2];
            }
            else if($hasDptV1) {
                $dptID = $dptV1s[$dptV1];
            }

            if(!isset($dptID)) {
                continue;
            }
            $employees[] = array( //保存员工数据， 一次性插入
                'name' => $name,
                'job_id' => $jobID,
                'dpt_id' => $dptID
            );
        }// end for
        $this->loadModel('Employee');
        $this->Employee->deleteAll(array('1=1'));
        if( $this->Employee->SaveMany($employees) ) {
            return json_encode(array(
                'code' => 1
            ));
        }
        else {
            return json_encode(array(
                'code' => 0,
                'info' => '数据库保存出错，请联系程序猿'
            ));
        }
    }// end parseEmployee


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


    private function uploadFile($file) {
        if(!isset($_FILES[$file]) || $_FILES[$file]['error'] != 0) {
            $result = json_encode(array(
                'code' => 0,
                'info' => '文件上传错误，错误代码为' . $_FILES[$file]['error']
            ));
            return array('code' => 0, 'info' => $result);
        }

        if(strpos($_FILES[$file]['type'], 'excel') === false &&
            strpos($_FILES[$file]['type'], 'sheet') === false &&
            strpos($_FILES[$file]['type'], 'xlsx') === false &&
            strpos($_FILES[$file]['type'], 'xls') === false
        ) { //文件类型不对
            $result = json_encode(array(
                'code' => 0,
                'info' => sprintf('文件类型错误[%s], 请上传正确的文件类型', $_FILES[$file]['type'])
            ));
            return array('code' => 0, 'info' => $result);
        }
        $filename =  sys_get_temp_dir() . DS . basename($_FILES[$file]['name']);
        //要保证目录可写
        if( !@move_uploaded_file($_FILES[$file]['tmp_name'], $filename) ) {
            $result = json_encode(array(
                'code' => 0,
                'info' => sprintf('将文件移动到%s时出错，目录可能不可写', $filename)
            ));
            return array('code' => 0, 'info' => $result);
        }
        else {
            return array('code' => 1, 'info' => $filename);
        }

    }//end uploadFile


    private function setCellFont($cells, $bold = true, $size=10, $color = '000000') {
        $this->sheet->getStyle($cells)->getFont()
        ->setBold(true)
        ->setSize($size)
        ->getColor()->setRGB($color);
    }

    private function setCellBackground($cells, $color = 'ffffff') {
        $this->sheet->getStyle($cells)->getFill()->applyFromArray(array(
            'type' => PHPExcel_Style_Fill::FILL_SOLID,
            'startcolor' => array('rgb' => $color)
        ));
    }

    private function setCellCenter($cells, $ort = 'vertical') {
        if($ort == 'vertical') {
            $this->sheet->getStyle($cells)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }
        else{
            $this->sheet->getStyle($cells)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        }
    }

    private function setCellBorder($cells) {
        $this->sheet->getStyle($cells)->getFill()->applyFromArray(array(
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THICK
                )
            )
        ));
    }


    /**
     * 得到要合并的cell表示法, 合并某一行的列
     * @param  $start int 起始列
     * @param  $end   int 结束列
     * @param  $col   int 行
     * @return $merge cell表示法,如'E3:F3'
     */
    private function cellsToMergeByColsRow($start = NULL, $end = NULL, $row = NULL){
        $merge = 'A1:A1';
        if($start && $end && $row){
            $start = PHPExcel_Cell::stringFromColumnIndex($start);
            $end = PHPExcel_Cell::stringFromColumnIndex($end);
            $merge = "$start{$row}:$end{$row}";

        }
        return $merge;
    }

    /**
     * 得到要合并的cell表示法, 合并某一列的行
     * @param  $start int 起始行
     * @param  $end   int 结束行
     * @param  $col   int 列
     * @return $merge cell表示发,如'E3:E7'
     */
    private function cellsToMergeByRowsCol($start = NULL, $end = NULL, $col = NULL){
        $merge = 'A1:A1';
        if($start && $end && $col){
            $colName = PHPExcel_Cell::stringFromColumnIndex($col);
            $merge = "$colName{$start}:$colName{$end}";

        }
        return $merge;
    }

}
