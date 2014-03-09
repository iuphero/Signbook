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


    public function getDptRecords($dpt_name, $month) {
        $dpt_name = '技术部';
        $month = '2014-02';
        $month2 = str_replace('-', '', $month); //201402
        
        $dpt_id = $this->Department->field('id',array('name'=>$dpt_name));

        $has_records = $this->SignRecord->find('count',array(
                'conditions' => array(
                    'year_and_month' => $month2,
                    'dpt_id' => $dpt_id
                )
            )
        );

        if(!$has_records) {
            echo 'has not records';
        }
        

        $results = $this->Department->find('all', array(
                'conditions' => array(
                    'Department.name' => $dpt_name
                )
            )
        );
           
        $employees = $results[0]['Employee'];
        $month_timestamp = strtotime($month);
        $days = (int)date('t', $month_timestamp);   

        $results = array();
        foreach ($employees as $employee) {//for a employee
            
            $employee_id = $employee['id'];
            $name = $employee['name'];

            for ($i= 1; $i <= $days ; $i++ ) { //for the emploeyee's a day
                $whichday = $month . '-' .$i;
                
                $records = $this->SignRecord->find('first',array(
                        'conditions' => array(
                            'whichday' => $whichday,
                            'employee_id' => $employee_id
                        )
                    )
                );

                if(empty($records)) {
                    $results[$name][$i]['sign_start'] = null;
                    $results[$name][$i]['sign_end'] = null;
                    $results[$name][$i]['state_forenoon'] = 0;
                    $results[$name][$i]['state_afternoon'] = 0;
                    $results[$name][$i]['is_abnormal'] = 0;
                }
                else {
                    $results[$name][$i]['sign_start'] = $records['SignRecord']['sign_start'];
                    $results[$name][$i]['sign_end'] = $records['SignRecord']['sign_end'];
                    $results[$name][$i]['state_forenoon'] = $records['SignRecord']['state_forenoon'];
                    $results[$name][$i]['state_afternoon'] = $records['SignRecord']['state_afternoon'];
                    $results[$name][$i]['is_abnormal'] = (int)$records['SignRecord']['is_abnormal'];
                }

            }

        }// end foreach.employees

        $this->Department->recursive = -1;
        $dpt_records = $this->Department->find('all',array(
                'fields' => array('name')
            )
        );
        $dpt_names = array();
        foreach ($dpt_records as $dpt_record) {
            $dpt_names[] = $dpt_record['Department']['name'];
        }

        list($year, $month) = explode('-', $month);
        $this->set('year', $year);
        $this->set('month', $month);
        $this->set('department', $dpt_name);
        $this->set('dpt_names', $dpt_names);
        $this->set('results', $results);
     

// array(
//     (int) 54 => array(
//         (int) 1 => array(
//             'sign_start' => null,
//             'sign_end' => null,
//             'state_forenoon' => (int) 0,
//             'state_afternoon' => (int) 0,
//             'is_abnormal' => (int) 0
//         ),
//         (int) 2 => array(
//             'sign_start' => null,
//             'sign_end' => null,
//             'state_forenoon' => (int) 0,
//             'state_afternoon' => (int) 0,
//             'is_abnormal' => (int) 0
//         ),
//         (int) 3 => array(
//             'sign_start' => null,
//             'sign_end' => null,
//             'state_forenoon' => (int) 0,
//             'state_afternoon' => (int) 0,
//             'is_abnormal' => (int) 0
//         ),
//         (int) 4 => array(
//             'sign_start' => null,
//             'sign_end' => null,
//             'state_forenoon' => (int) 0,
//             'state_afternoon' => (int) 0,
//             'is_abnormal' => (int) 0
//         ),

        

        

        // $employee_ids = $this->Employee->find('all',array(
        //         'conditions' => array(
        //             'dpt_id' => $dpt_id
        //         )
        //     ) 
        // );

        
    }
}