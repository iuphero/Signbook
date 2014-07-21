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

    public function beforeFilter() {
        parent::beforeFilter();
        $this->autoRender = false;
        // $this->Auth->allow(); //to do
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

    public function parseLeave() {
        // if(!isset($_FILES['leave']) || $_FILES['leave']['error'] != 0) {
        //     return json_encode(array(
        //         'code' => 0,
        //         'info' => '文件上传错误，错误代码为' . $_FILES['leave']['error']
        //     ));
        // }

        // if(strpos($_FILES['leave']['type'], 'excel') === false &&
        //     strpos($_FILES['leave']['type'], 'sheet') === false &&
        //     strpos($_FILES['leave']['type'], 'xlsx') === false &&
        //     strpos($_FILES['leave']['type'], 'xls') === false
        // ) { //文件类型不对
        //     return json_encode(array(
        //         'code' => 0,
        //         'info' => sprintf('文件类型错误[%s], 请上传正确的文件类型', $_FILES['leave']['type'])
        //     ));
        // }
        // $filename =  sys_get_temp_dir() . DS . basename($_FILES['leave']['name']);
        // //要保证目录可写
        // if( !@move_uploaded_file($_FILES['leave']['tmp_name'], $filename) ) {
        //     return json_encode(array(
        //         'code' => 0,
        //         'info' => sprintf('将文件移动到%s时出错，目录可能不可写', $filename)
        //     ));
        // }


        $filename = '/home/xfight/download/请假单06.xlsx';
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
            if($employee_id === false) {//找不到员工号提示出错, 要先更新员工表
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
                        'path' => '0/'
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
                        'path' => $depV1ID. '/'
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
                        'path' => $dptV1ID. '/' . $dptV2ID
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