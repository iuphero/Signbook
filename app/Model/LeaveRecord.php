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
    public function get_epy2leave($year, $month) {
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
}
