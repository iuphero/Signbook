<?php 
    

class SignRule extends AppModel {
    
    public $primaryKey = 'id';

    public $useDbConfig = 'default';

    public $useTable = 'sign_rule';

    public $name = 'SignRule';

    public $hasMany = array(
        'Department' => array(
            'className' => 'Department',
            'foreignKey' => 'sign_rule_id'
        )
    );
}