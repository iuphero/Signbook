<?php 
    
App::uses('AppController', 'Controller');



class ShowController extends AppController {

    public $uses = array('Department', 'Employee', 'SignRule', 'SignRecord');
    public $name = 'Show';



    public function getDptRecords($dpt_name='', $month='') {
/*      $dpt_name = '技术部';
        $month = '2014-02';*/
        if(empty($dpt_name) || empty($month)) {
            $dpt_name = '技术部';
            $month = $_GET['month'];
        }

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
            $this->Session->setFlash('亲，没有查到相关记录，请先上传文件分析');
            $this->redirect('/');
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
    }
}
