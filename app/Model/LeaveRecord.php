<?php


class LeaveRecord extends AppModel {

    public $name = 'LeaveRecord';

    public $primaryKey = 'id';

    public $useDbConfig = 'default';

    public $useTable = 'leave_record';

    public $belongsTo = array(
        'Employee' => array(
            'className' => 'Employee',
            'foreignKey' => 'job_id'
        )
    );


    /**
     * 获得每个员工id到其请假记录数组的映射
     *
     * @param $year int 查询哪一年
     * @param $month int 查询哪一个月
     *
     * @return $result array
     * $result的key为员工id, value为一个数组,
     * 这个数组的每一个元素记录一次请假情况, 每个请假情况为一个数组, 包括:
     * start_time:请假开始时间, int 时间戳类型
     * end_time:请假结束时间, int 时间戳类型
     * type:请假类型
     */
    public function get_epy2leave($year = 14, $month) {
        $tmpRecords = $this->find('all', array(
            'fields' => array('employee_id', 'start_time', 'end_time', 'type'),
            'order' => 'employee_id asc',
            'conditions' => array(
                'year' => $year,
                'MONTH(start_time) <=' => $month,
                'MONTH(end_time) >=' => $month
            )
        ));

        $result = array();
        foreach($tmpRecords as $record) {
            $epyid = $record['LeaveRecord']['employee_id'];
            unset($record['LeaveRecord']['employee_id']);
            $record['LeaveRecord']['start_time'] = strtotime($record['LeaveRecord']['start_time']);
            $record['LeaveRecord']['end_time'] = strtotime($record['LeaveRecord']['end_time']);
            $result[$epyid][] =$record['LeaveRecord'];
        }
        return $result;
    }


    /**
     * 根据员工id获取此月(来源于....)请假记录
     * @param  $epy_id integer 员工id
     * @param  $time 年月份时间
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
    public function getLeavesById($epy_id, $time) {

        $firstDay = date('Y-m-01', strtotime($time));
        $lastDay = date('Y-m-t', strtotime($time));
        $firstDay_ttp = strtotime($firstDay);
        $lastDay_ttp = strtotime($lastDay);
        $records = $this->find('all', array(
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

}
