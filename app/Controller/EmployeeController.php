<?
App::uses('ExcelController', 'Controller');

class EmployeeController extends ExcelController {

    /**
     * 记录员工和部门表中各项信息在第几列, 从0开始
     */
    public $employeeIndex = array(
        'job_id' => 0,
        'name' => 1,
        'dptV1' => 2,  //一级部门
        'dptV2' => 3,  //二级部门
        'dptV3' => 4   //三级部门
    );

    public function beforeFilter() {
        parent::beforeFilter();
    }

    protected function isRightExcel($filename) {
        $loadFile = $this->reader->load($filename);
        //判断1和0，从目前的经验来说，从1开始先能找到，这里和检测请假表和考勤表不同
        for($i = 1; $i >= 0; $i--) {
            try{
                $sheet = $loadFile->getsheet($i);
            }
            catch(Exception $e){
                continue;
            }
            $shouldBeJobID = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['job_id'], 1)->getValue() );
            $shouldBeName = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['name'], 1)->getValue() );
            $shouldBeDpt1 = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['dptV1'], 1)->getValue() );
            $shouldBeDpt2 = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['dptV2'], 1)->getValue() );
            $shouldBeDpt3 = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['dptV3'], 1)->getValue() );
            if($shouldBeJobID != '工号'||
                ($shouldBeName != '姓名' && $shouldBeName != '名字')||
                 $shouldBeDpt1 != '一级部门'||
                 $shouldBeDpt2 != '二级部门'||
                 $shouldBeDpt3 != '三级部门') {
                continue;
            }
            else {
                return $sheet;
            }
        }
        return false;
    }


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

        $reader = $this->setReader('Excel2007');
        $this->sheet = $sheet = $this->isRightExcel($filename);
        if($sheet === false) {
            return $this->getTypeError();
        }

        $rowCount = $sheet->getHighestRow();  //总行数
        if($rowCount < 200) {
            return $this->getTypeError();
        }

        $dptV1s = array();
        $dptV2s = array();
        $dptV3s = array();

        $employees = array();
        $this->loadModel('Department');
        $this->Department->deleteAll(array('1=1'));
        //todo,先从数据库构造数据,出错时恢复,这里用不到事务

        for($i = 2; $i <= $rowCount; $i++) {
            $jobID = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['job_id'], $i)->getValue() );
            $name = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['name'], $i)->getValue() );
            $dptV1 = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['dptV1'], $i)->getValue() );
            $dptV2 = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['dptV2'], $i)->getValue() );
            $dptV3 = trim( $sheet->getCellByColumnAndRow($this->employeeIndex['dptV3'], $i)->getValue() );

            /**
             *把工号补全为5位，如0001补全为10001
             */
            $jobID = (int)$jobID;
            if ($jobID < 10000) {
                $jobID += 10000;
            }

            if( !empty($dptV1) && !isset($dptV1s[$dptV1]) ) {
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
                    return $this->getDBError($this->Department->validationErrors);
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
                    return $this->getDBError($this->Department->validationErrors);
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
                    return $this->getDBError($this->Department->validationErrors);
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
            return $this->getDBError($this->Department->validationErrors);
        }
    }// end parseEmployee

}