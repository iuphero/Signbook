<?php


class SignRecord extends AppModel {

    public $name = 'SignRecord';

    public $primaryKey = 'id';

    public $useDbConfig = 'default';

    public $useTable = 'sign_record';

    /**
     * 获取指定年月时间内员工的考勤记录
     * @param  $epy_id int 员工的id
     * @param  $time   string 年月时间
     * @return 没有记录就返回false， 有就返回记录
     */
    public function getSignsById($epy_id, $time) {
        $firstDay = date('Y-m-01', strtotime($time));
        $lastDay = date('Y-m-t', strtotime($time));
        $records = $this->find('all', array(
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

}