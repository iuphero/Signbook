<?
App::uses('AppController', 'Controller');

/**
 * 用于处理Excel分析和导出的Ajax请求
 */
class ExcelController extends AppController {

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


    public $reader = false;
    public $excel = false;
    public $year = false;    //要统计的表格的年份, 两位数字, 2014年就是14
    public $month = false;   //要统计的表格的月份, 1月1, 12月为12

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


    /**
     * 记录员工和部门表中各项信息在第几列, 从0开始
     */
    public $employeeIndex = array(
        'job_id' => 0,
        'name' => 1,
        'dptV1Index' => 2,  //一级部门
        'dptV2Index' => 3,  //二级部门
        'dptV3Index' => 4   //三级部门
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


    protected function isRightExcel($filename) {
        return false;
    }


    protected function getTypeError() {
        return json_encode(array(
            'code' => 0,
            'info' => '您上传的可能不是正确的Excel表格,请选择正确的文件'
        ));
    }


    protected function getDBError($error = 'DBerror') {
        return json_encode(array(
            'code' => 0,
            'info' => sprintf('数据库保存出错，请联系程序猿[%s]',
                $error)
        ));
    }


    protected function setWriter() {
        if ($this->excel === false) {
            APP::import('Vendor','/excel/Classes/PHPExcel');
            APP::import('Vendor','/excel/Classes/PHPExcel/Writer/Excel2007');
            $this->excel = new PHPExcel();
            $this->excel->setActiveSheetIndex(0);
            $this->sheet =  $this->excel->getActiveSheet();
        }

    }

    protected function setReader($type = 'Excel2007') {
        if($this->reader === false) {
            APP::import('Vendor','/excel/Classes/PHPExcel');
            APP::import('Vendor','/excel/Classes/PHPExcel/IOFactory');
            APP::import('Vendor','/excel/Classes/PHPExcel/Reader/Excel2007');
            $this->reader = PHPExcel_IOFactory::createReader($type);
        }
    }

    /**
     * 通过时间获取中文表示的星期几
     * @param  $date  string 如'2014-9-12'
     * @return '一' 到 '日'
     */
    protected function getXQJ($date) {
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


    protected function uploadFile($file) {
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


    protected function setCellFont($cells, $bold = true, $size=10, $color = '000000') {
        $this->sheet->getStyle($cells)->getFont()
        ->setBold(true)
        ->setSize($size)
        ->getColor()->setRGB($color);
    }

    protected function setCellBackground($cells, $color = 'ffffff') {
        $this->sheet->getStyle($cells)->getFill()->applyFromArray(array(
            'type' => PHPExcel_Style_Fill::FILL_SOLID,
            'startcolor' => array('rgb' => $color)
        ));
    }

    protected function setCellCenter($cells, $ort = 'vertical') {
        if($ort == 'vertical') {
            $this->sheet->getStyle($cells)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }
        else{
            $this->sheet->getStyle($cells)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        }
    }

    protected function setCellBorder($cells) {
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
    protected function cellsToMergeByColsRow($start = NULL, $end = NULL, $row = NULL){
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
     * @return $merge cell表示法,如'E3:E7'
     */
    protected function cellsToMergeByRowsCol($start = NULL, $end = NULL, $col = NULL){
        $merge = 'A1:A1';
        if($start && $end && $col){
            $colName = PHPExcel_Cell::stringFromColumnIndex($col);
            $merge = "$colName{$start}:$colName{$end}";

        }
        return $merge;
    }

}
