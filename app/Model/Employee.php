<?php 
    

class Employee extends AppModel {
    
    public $primaryKey = 'id';

    public $useDbConfig = 'default';

    public $useTable = 'employee';

    public $name = 'Employee';

    public $belongsTo = array(
        'Department' => array(
            'className' => 'Department',
            'foreignKey' => 'dpt_id'
        )
    );

}