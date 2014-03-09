<?php 
    
App::uses('AppController', 'Controller');



class ShowController extends AppController {

    public $uses = array('Department', 'Employee', 'SignRule', 'SignRecord');
    public $name = 'Show';


    public function getDateFromMonth($month,$dpt = 'all'){//201402
        
        $month = 201402;
        $result = $this->SignRecord->find('all', array(
                'conditions' => array(
                    'year_and_month' => $month
                ),
                'order' => array('whichday asc','employee_id asc') 
            )
        );

        debug($result);
    }


    public function getCompanyRecords($month) {
        $month = 201402;
        $dpts = $this->Department->find('all');    

        debug($dpts);exits;
    }


    public function getDptRecords($dpt_name,$month){
        $month = 201402;
        $dpt_name = '技术部';
        $dpt_id = $this->Department->field('id',array('name'=>$dpt_name));

        $results = $this->Department->find('all', array(
                'conditions' => array(
                    'Department.name' => $dpt_name
                )
            )
        );

        debug($employees);exit;

        // $employee_ids = $this->Employee->find('all',array(
        //         'conditions' => array(
        //             'dpt_id' => $dpt_id
        //         )
        //     ) 
        // );

        $records = $this->SignRecord->find('all',array(
                'conditions' => array(
                    'dpt_id' => $dpt_id,
                    'year_and_month' => $month
                )
            )
        );

        debug($records);exit;

        
    }
}