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
}