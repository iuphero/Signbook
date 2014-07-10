<?php


class Department extends AppModel {

    public $name = 'Department';

    public $primaryKey = 'id';

    public $useDbConfig = 'default';

    public $useTable = 'department';

    public $hasMany = array(
        'Employee' => array(
            'className' => 'Employee',
            'foreignKey' => 'dpt_id'
        )
    );

    public $belongsTo = array(
        'SignRule' => array(
            'className' => 'SignRule',
            'foreignKey' => 'sign_rule_id'
        )
    );


    /* 得到员工工号(job_id)到该员工所在部门的规则的映射
    **
    ** @return $epy2rule array
    ** 此array的key为员工工号, value为一个数组, 包含startime(规定的上班时间),
    ** endtime(规定的离开时间), flextime(弹性时间)
    ** 例如其中一个元素为
    ** (int) 10114 => array(
    **    'starttime' => '09:00:00',
    **    'endtime' => '18:00:00',
    **    'flextime' => '0'
    ** )
    */
    public function get_epy2rule() {
        $dpts = $this->find('all', array(
            'recursive' => 1,
            'fields' => array(
                'SignRule.core_starttime',
                'SignRule.core_endtime',
                'SignRule.flex_time'
            ),
            'cacheQueries' => false
        ));

        $epy2rule = array();
        $tmpRule = array();

        foreach($dpts as $dpt) {
            $epys = $dpt['Employee'];
            $tmpRule['starttime'] = $dpt['SignRule']['core_starttime'];
            $tmpRule['endtime'] = $dpt['SignRule']['core_endtime'];
            $tmpRule['flextime'] = $dpt['SignRule']['flex_time'];
            foreach($epys as $epy) {
                $job_id = $epy['job_id'];
                $epy2rule[$job_id] = $tmpRule;
            }
        }

        return $epy2rule;
    }
}
