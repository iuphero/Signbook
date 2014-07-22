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

    public function hasLeaveData() {
        $this->loadModel('LeaveRecord');
        $time = strtotime( $this->data['time'] );
        $firstDay = date('Y-m-01', $time);
        $lastDay = date('Y-m-t', $time);
        $count = $this->LeaveRecord->find('count', array(
            'recursive' => -1,
            'conditions' => array(
                'LeaveRecord.start_time BETWEEN ? AND ?' => array($firstDay, $lastDay)
            )
        ));

        if($count < 10) {//todo, 这个判定不严格, 还找一个更好的方法
            return 1;
        }
        else {
            return 0;
        }
    }


    public function outputLeave($time) {

        // $this->time = '2014-06'; //dummy data
        $this->time = $time;
        $this->loadModel('Department');
        $employees = $this->Department->query('SELECT epy.id AS id, epy.name AS name, epy.job_id as job_id, dpt1.name AS dpt1_name, dpt1.id as dpt1_id, dpt2.id as dpt2_id, dpt3.id as dpt3_id, dpt2.name AS dpt2_name, dpt3.name AS dpt3_name
            FROM department AS dpt2
            LEFT JOIN department AS dpt3 ON dpt2.id = dpt3.parent_id
            AND dpt3.level =3
            LEFT JOIN department AS dpt1 ON dpt1.id = dpt2.parent_id
            LEFT JOIN employee AS epy ON epy.dpt_id
            IN (
            dpt1.id, dpt2.id, dpt3.id
            )
            GROUP BY epy.id order by dpt1.id,epy.job_id');

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
        APP::import('Vender','/excel/Classes/PHPExcel/Writer/Excel2007');
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
    public function getLeavesById($epy_id = 13) {
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



    public function parseLeave() {
        if(!isset($_FILES['leave']) || $_FILES['leave']['error'] != 0) {
            return json_encode(array(
                'code' => 0,
                'info' => '文件上传错误，错误代码为' . $_FILES['leave']['error']
            ));
        }

        if(strpos($_FILES['leave']['type'], 'excel') === false &&
            strpos($_FILES['leave']['type'], 'sheet') === false &&
            strpos($_FILES['leave']['type'], 'xlsx') === false &&
            strpos($_FILES['leave']['type'], 'xls') === false
        ) { //文件类型不对
            return json_encode(array(
                'code' => 0,
                'info' => sprintf('文件类型错误[%s], 请上传正确的文件类型', $_FILES['leave']['type'])
            ));
        }
        $filename =  sys_get_temp_dir() . DS . basename($_FILES['leave']['name']);
        //要保证目录可写
        if( !@move_uploaded_file($_FILES['leave']['tmp_name'], $filename) ) {
            return json_encode(array(
                'code' => 0,
                'info' => sprintf('将文件移动到%s时出错，目录可能不可写', $filename)
            ));
        }


        // $filename = '/home/xfight/download/请假单06.xlsx';
        //开始使用PHP-Excel分析文件
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');
        $reader = PHPExcel_IOFactory::createReader('Excel2007');

        // $sheet = $reader->load($_FILES['leave']['tmp_name']))->getsheet(0);
        // $sheet = $reader->load('/home/xfight/tmp/leave.xlsx')->getsheet(0);
        $sheet = $reader->load($filename)->getsheet(0);
        $highestRow = $sheet->getHighestRow();

        // return $highestRow;
        if($this->year === false) {
            $tmpDate = $sheet->getCellByColumnAndRow($this->leaveIndex['start_time'], 2)->getFormattedValue(); // '2014-06-16 9:00:00'
            $this->year = substr($tmpDate, 2, 2);
        }

        $this->Session->write('Leave.year', $this->year);
        $this->Session->write('Leave.month', substr($tmpDate, 5, 2));

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

            $type = $this->getLeaveType(trim($type));
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
                'name' => trim($name),
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
                'info' => '数据库保存出错，情联系程序猿'
            ));
        }

    }// end parseLeave


    /**
     * 分析Excel文件,保存员工和部门的数据
     * 会先清空员工(employee)和部门表(department)
     * @param  $file  string  文件路径
     * @return boolean  成功返回true, 失败返回false
     */
    public function parseEmployee($file = null) {
        $file = '/home/xfight/download/个人编号.xlsx';
        APP::import('Vendor','/excel/Classes/PHPExcel');
        APP::import('Vender','/excel/Classes/PHPExcel/IOFactory');
        APP::import('Vender','/excel/Classes/PHPExcel/Reader/Excel2007');

        $reader = PHPExcel_IOFactory::createReader('Excel2007');
        $sheet = $reader->load($file)->getsheet(1);
        $rowCount = $sheet->getHighestRow();  //总行数

        if($rowCount < 200) {
            //todo, 提示错误
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
            return true;
        }
        else {
            return false;
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


}