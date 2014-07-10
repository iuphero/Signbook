<?php


class SignRule extends AppModel {

    public $name = 'SignRule';

    public $primaryKey = 'id';

    public $useDbConfig = 'default';

    public $useTable = 'sign_rule';

    public $hasMany = array(
        'Department' => array(
            'className' => 'Department',
            'foreignKey' => 'sign_rule_id'
        )
    );
}