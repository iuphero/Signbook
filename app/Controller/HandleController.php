<?php 
    
App::uses('AppController', 'Controller');


class HandleController extends AppController {

    public $name = 'handle';

    public $uses = array('Department', 'Employee', 'SignRule', 'SignRecord');  //todo

    public function beforeFilter() {
        parent::beforeFilter();
        $this->autoRender = false;
    }


    public function test() {


        $month='2014-2';
        $month_timestamp = strtotime($month);
        $month = date('Y-m', $month_timestamp);

        $this->Session->setFlash('考勤文件上传并分析成功');
        $this->redirect('/show/test/总公司/'.$month);        
    }

    public function parseFile() {

        if(!$this->request->is('post')) {
            return;
        }
        
        debug($_FILES['signfile']);
        $file = $_FILES['signfile']['tmp_name'];
        $filename = $_FILES['signfile']['name'];
        if(substr($filename, -3) !== 'dat') {
            $this->Session->setFlash('this file is not right');
            $this->redirect('/');
        }

        
        $month='2014-02';
        $rows = file($file);
        $n = count($rows);

        $first_tmp_ary = preg_split('/\s+/', $rows[0]);
        $last_tmp_ary = preg_split('/\s+/', $rows[$n-1]);
        

        $first_datetime = new DateTime( date('Y-m', strtotime($first_tmp_ary[1])) );
        $last_datetime = new DateTime( substr($last_tmp_ary[1],0,-3) );


        $first_timestamp = $first_datetime->getTimestamp();
        $last_timestamp = $last_datetime->getTimestamp();


        $the_datetime = new DateTime($month);
        $the_timestamp = $the_datetime->getTimestamp();

        if(!($the_timestamp >= $first_timestamp && $the_timestamp <= $last_timestamp)) {
            $this->Session->setFlash('亲，您上传的文件不正确，请上传该月份对应的正确文件');
            $this->redirect('/index');
        }
       


        $data = array();

        foreach ($rows as $row) {
            list($jobid, $datetime, $_, $_, $_, $_) = explode("\t", $row);

            list($date,$time) = explode(' ',$datetime);

            $row_datetime = new DateTime(date('Y-m', strtotime($date))) ;
            
            if($row_datetime->getTimestamp() < $the_timestamp) {
                continue;
            }

            $sign_start_boundry = strtotime('8:30');
            $sign_end_boundry = strtotime('23:59');

            $the_time = strtotime($time);
            
            if(($the_time >= $sign_start_boundry) && ($the_time <= $sign_end_boundry)) {
                $data[$jobid][$date][] =$time;
            }

        }
        


        foreach ($data as $jobid => $date_ary) { // for a employee
            $employee = $this->Employee->findByJobId($jobid);
            if(empty($employee)) continue;

            $employee_id = $employee['Employee']['id'];
            $dpt_id = $employee['Department']['id'];
            
            
            $sign_rule_id = $employee['Department']['sign_rule_id'];
            $this->SignRule->recursive = 0;
            $sign_rule = $this->SignRule->findById($sign_rule_id);


            $core_starttime = $sign_rule['SignRule']['core_starttime'];
            $core_endtime = $sign_rule['SignRule']['core_endtime'];
            $flex_time = $sign_rule['SignRule']['flex_time']; //unit is minute
            $edge_timestamp = strtotime($core_starttime) + 60*1000*$flex_time;

            $all_record_data = array();

            foreach ($date_ary as $date => $times) { //for the employee someone date


                $is_existing = $this->SignRecord->find('count',array(
                        'conditions' => array(
                            'employee_id' => $employee_id,
                            'whichday' => $date
                        )
                    )
                );

                if($is_existing) continue;

                $n = count($times);
                $record_data = array();
                $record_data['employee_id'] = $employee_id;
                $record_data['whichday'] = $date;

                $record_data['dpt_id'] = $dpt_id;
                $y_m_str = substr($date, 0 ,-3);
                list($year_str, $month_str) = explode('-', $y_m_str);
                $record_data['year_and_month'] = (int)$year_str.$month_str;


                if($n == 0) {                
                    
                    $record_data['state_forenoon'] = $record_data['state_afternoon'] = 0;
                    
                    $timestamp = strtotime($date);
                    $weekday = date('w',$date);
                    if( $weekday !== '0' && $weekday !== '6' ) {
                        $record_data['is_abnormal'] = 1;
                    }
                    
                }

                else if($n == 1) {
                    $sign_timestamp = strtotime( $times[0] );
                    
                    $record_data['sign_start'] = $record_data['sign_end'] = $times[0];
                    if($sign_timestamp <= strtotime('12:00')) {//sign in forenoon
                        if ($sign_timestamp < $edge_timestamp) {//not late
                            $record_data['state_forenoon'] = 1;
                        }
                        else $record_data['state_forenoon'] = 2;

                        if($dpt_id == 6) {
                            $record_data['state_afternoon'] = 1;
                        }
                        else $record_data['state_afternoon'] = 4;
                    }

                    else{//sign in afternoon
                        $record_data['state_forenoon'] = 0;
                        $record_data['state_afternoon'] = 1;
                    }

                    if($dpt_id != 6) {//not in IT department
                        $record_data['is_abnormal'] = 1;
                    }
                    else $record_data['is_abnormal'] = 0;

                    
                }

                else { // twice and more sign in
                    $record_data['sign_start'] = reset($times);
                    $record_data['sign_end'] = end($times);
                    $come_timestamp = strtotime( $record_data['sign_start'] );
                    $leave_timestamp = strtotime( $record_data['sign_end'] );


                    if($come_timestamp <= $edge_timestamp) { //not late
                        $record_data['state_forenoon'] = 1;
                    }
                    else {//late
                        $record_data['state_forenoon'] = 2;

                    }

                    if($leave_timestamp < strtotime($core_endtime)) {
                        $record_data['state_afternoon'] = 4;
                    }
                    else $record_data['state_afternoon'] = 1;

                }
                
                $all_record_data[] = $record_data;

            } //end foreach for the employee someone date

            $this->SignRecord->saveAll($all_record_data);

        }//end foreach for the employee
        
        $month_timestamp = strtotime($month);
        $month = date('Y-m', $month_timestamp);
        $this->Session->setFlash('考勤文件上传并分析成功');
        $this->redirect('/show/getdptrecords/总公司/'.$month);
    }    

}
