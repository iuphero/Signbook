<?php
App::uses('ExcelController', 'Controller');

class SignController extends ExcelController {

    public $layout = 'dashboard';

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


    public function beforeFilter() {
        parent::beforeFilter();
        $this->autoRender = false;
    }


    /*
    录入假期信息
    选择月份  本月假期情况是否存在提示, 不存在就要先上传假期数据
     */
    public function input($type) {
        $this->autoRender = true;
        if( !in_array($type, array('leave','sign') ) ) {
            throw new NotFoundException;
        }
        $this->set('type', $type);
    }


    public function get($type) {
        $this->autoRender = true;
        if( !in_array($type, array('leave','sign') ) ) {
            throw new NotFoundException;
        }
        $this->set('type', $type);
    }



    /**
     * 存储这个月的假期, 供录入假期数据, 生成考勤表格使用
     * @param $holidays string 用英文逗号分割的字符串,保存这个月的假期
     * @param $month string 年月时间
     */
    public function saveHolidays() {
        $holidays = $this->data['holidays'];
        $month = $this->data['month'];
        $month = date('Y-m-1', strtotime($month));
        $this->loadModel('Holiday');
        $this->Holiday->deleteAll(array(
            'month' => $month
        ));
        $result = $this->Holiday->save(array(
            'month' => $month,
            'holidays' => $holidays
        ));
        if($result) {
            return 1;
        }
        else {
            return 0;
        }
    }


    /**
     * 判断指定的月份内是否有考勤数据
     * @return boolean
     */
    public function hasSignData() {
        $this->autoRender = false;
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

    /**
     * 输出考勤数据
     * @param  $time string 月份时间
     * @return An excel file
     */
    public function outputSign($time = '2014-06') {
        $this->time = $time;
        $this->loadModel('Holiday');
        $holidays = $this->Holiday->field('holidays', array(
                'month' => date('Y-m-1', strtotime($time))
        ));
        $holidays = explode(',', $holidays);
        $this->loadModel('Department');
        $this->loadModel('LeaveRecord');
        $this->loadModel('SignRecord');
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
            $leaves = $this->LeaveRecord->getLeavesById($epy_id, $time);
            $signs = $this->SignRecord->getSignsById($epy_id, $time);
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
        $this->setWriter();
        $sheet = $this->sheet;
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

        $this->setSignFooter($i+2);
        $this->setCellCenter('A1:AU1000');
        $this->outputExcel('考勤汇总');
    }


    public function setSignFooter($row) {
        $cells = "A{$row}:AH{$row}";
        $this->sheet->setCellValue("A{$row}", '√出勤  ●休假  ○事假  ☆病假  △外地出差  ×旷工  ※迟到  ◇早退 ◆年假/调休 ▲市内出差');
        $this->sheet->mergeCells($cells);
        $row = $row + 1;
        $cells = "A{$row}:AH{$row}";
        $this->sheet->setCellValue("A{$row}", '注：迟到半小时内扣10元，超过半小时扣当天半天的工资。在上级没批准情况下缺席半天或以上当旷工处理。此表由部门考勤员填写，统一报总部人力资源部。');
        $this->sheet->mergeCells($cells);
    }

    /**
     * 设置考勤表的头信息
     */
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
            $shouldBeJobID = trim( $sheet->getCellByColumnAndRow($this->signIndex['job_id'], 1)->getValue() );
            $shouldBeName = trim( $sheet->getCellByColumnAndRow($this->signIndex['epyname'], 1)->getValue() );
            $shouldBeDate = trim( $sheet->getCellByColumnAndRow($this->signIndex['date'], 1)->getValue() );
            $shouldBeSign = trim( $sheet->getCellByColumnAndRow($this->signIndex['signtime'], 1)->getValue() );
            $shouldBeLeave = trim( $sheet->getCellByColumnAndRow($this->signIndex['leavetime'], 1)->getValue() );

            if( ($shouldBeJobID != '考勤号码' && $shouldBeJobID != '工号')||
                ($shouldBeName != '姓名' && $shouldBeName != '名字')||
                 $shouldBeDate != '日期'||
                 $shouldBeSign != '签到时间'||
                 $shouldBeLeave != '签退时间') {
                continue;
            }
            else {
                return $sheet;
            }
        }
        return false;
    }


    /**
     * 解析上传的考勤文件
     * @param  $month     string 月份,例如'2014-08'
     * @param  $holidays  string 逗号分割的假期列表,例如'1,2,8,9'
     * @return
     */
    public function parseSign($month, $holidays) {
        file_put_contents('/home/xfight/tmp/holidays', print_r($holidays, 1));
        $result = $this->uploadFile('sign');
        if($result['code'] == 0) {
            return $result['info'];
        }
        else {
            $filename = $result['info'];
        }

        $holidays = split(',', $holidays);
        $reader = $this->setReader('Excel2007');
        $this->sheet = $sheet = $this->isRightExcel($filename);
        if($sheet === false) {
            return $this->getTypeError();
        }

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
            return $this->getTypeError();
        }

        $this->loadModel('Employee');
        $this->loadModel('Department');
        $this->loadModel('LeaveRecord');
        $this->epy2rule = $this->Department->get_epy2rule(); //员工=>考勤规则
        $this->epy2leave = $this->LeaveRecord->get_epy2leave($this->year, $this->month); //员工=>请假记录

        $result = array();

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
            return $this->getDBError($this->LeaveRecord->validationErrors);
        }
    }


}
