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


    /* 得到所有员工id到该员工所在部门的规则的映射
    **
    ** @return $epy2rule array
    ** 此array的key为员工id, value为一个数组, 包含startime(规定的上班时间),
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
                $epy2rule[ $epy['id'] ] = $tmpRule;
            }
        }

        return $epy2rule;
    }



    /**
     * 获取员工和其部门层级关系
     * @return array, 返回的每一个元素类似于
     *  (int) 62 => array(
     *   'epy' => array( //员工信息
     *       'id' => '7',
     *       'name' => '叶文胜',
     *       'job_id' => '10009'
     *   ),
     *   'dpt1' => array( //所属一级部门
     *       'dpt1_name' => '广告事业部',
     *       'dpt1_id' => '5'
     *   ),
     *   'dpt2' => array( //所属二级部门
     *       'dpt2_id' => '6',
     *       'dpt2_name' => '媒介中心'
     *   ),
     *   'dpt3' => array( //所属三级部门
     *       'dpt3_id' => '7',
     *       'dpt3_name' => '媒介部'
     *   )
     *),
     */
    public function get_epy2dpt() {
        return $this->query('SELECT epy.id AS id, epy.name AS name, epy.job_id as job_id, dpt1.name AS dpt1_name, dpt1.id as dpt1_id, dpt2.id as dpt2_id, dpt3.id as dpt3_id, dpt2.name AS dpt2_name, dpt3.name AS dpt3_name
            FROM department AS dpt2
            LEFT JOIN department AS dpt3 ON dpt2.id = dpt3.parent_id
            AND dpt3.level =3
            LEFT JOIN department AS dpt1 ON dpt1.id = dpt2.parent_id
            LEFT JOIN employee AS epy ON epy.dpt_id
            IN (
            dpt1.id, dpt2.id, dpt3.id
            )
            GROUP BY epy.id order by dpt1.id,epy.job_id');
    }

}
