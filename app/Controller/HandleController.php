<?php

App::uses('AppController', 'Controller');


class HandleController extends AppController {

    public $name = 'handle';


    public function beforeFilter() {
        parent::beforeFilter();
        $this->autoRender = false;
    }


    public function setRule() {

    }


    
    public function parseFile() {

        if(!$this->request->is('post')) {
            $this->Session->setFlash('亲，这不科学', 'flash_custom');
            $this->redirect(array(
                    'controller' => 'pages',
                    'action' => 'index'
                )
            );
        }

        $month= $this->request->data['month'];//e.g 2014-02
        list($year, $month2) = explode('-', $month);
        $is_existing = $this->SignRecord->find('count',array(
                'conditions' => array(
                    'year_and_month' => (int)(''.$year.$month2)
                )
            )
        );

        if($is_existing) {
            $this->Session->setFlash('亲，您要上传的文件对应的月份数据已存在，不用再上传啦','flash_custom');
            $this->redirect(array(
                    'controller' => 'pages',
                    'action' => 'index'
                )
            );
        }


        $fileInfo = $this->request->data['signfile'];
        if($fileInfo['error'] != 0) {
            $this->Session->setFlash('亲，上传文件出现错误，请重新上传','flash_custom');
            $this->redirect(array(
                    'controller' => 'pages',
                    'action' => 'index'
                )
            );
        }


        $fileExt = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
        if($fileExt !== 'dat') {
            $this->Session->setFlash('亲，上传的文件格式不正确，请确定上传考勤机生成的dat结尾的文件', 'flash_custom');
            $this->redirect(array(
                    'controller' => 'pages',
                    'action' => 'index'
                )
            );
        }

        $file = $fileInfo['tmp_name'];
        $rows = file($file, FILE_IGNORE_NEW_LINES);
        $n = count($rows);

        $first_tmp_ary = preg_split('/\s+/', $rows[0]);
        $last_tmp_ary = preg_split('/\s+/', $rows[$n-1]);

        $first_timestamp  = strtotime( date('Y-m', strtotime($first_tmp_ary[1])) );
        $last_timestamp  = strtotime( date('Y-m', strtotime($last_tmp_ary[1])) );    ;

        $the_timestamp = strtotime($month);

        if(!($the_timestamp >= $first_timestamp && $the_timestamp <= $last_timestamp)) {
            $this->Session->setFlash('亲，您上传的文件不正确，请上传该月份对应的正确文件', 'flash_custom');
            $this->redirect(array(
                    'controller' => 'pages',
                    'action' => 'index'
                )
            );
        }



        $data = array();

        foreach ($rows as $row) {
            list($jobid, $datetime, $_, $_, $_, $_) = explode("\t", $row);
            $datetimespan = strtotime($datetime);
            $date = date('Y-m-d', $datetimespan);
            $time = date('H:i:s', $datetimespan);

            $row_timestamp = strtotime( date('Y-m', $datetimespan) );

            if($row_timestamp < $the_timestamp) {
                continue;
            }
            //有效打卡时间8：30-23：59
            $sign_start_boundry = strtotime('8:30');
            $sign_end_boundry = strtotime('23:59');
            $the_time = strtotime($time);

            if(($the_time >= $sign_start_boundry) && ($the_time <= $sign_end_boundry)) {
                $data[$jobid][$date][] =$time;
            }
        }


        $noon_ttp = strtotime('12:00');
        $techDptId = $this->Department->field('id',array('name' => '技术部'));
        foreach ($data as $jobid => $date_ary) { //每一个员工的遍历
            $employee = $this->Employee->findByJobId($jobid);
            if(empty($employee)) continue;

            //根据员工部门得到考勤规则
            $employee_id = $employee['Employee']['id'];
            $dpt_id = $employee['Department']['id'];
            $sign_rule_id = $employee['Department']['sign_rule_id'];
            $this->SignRule->recursive = 0;
            $sign_rule = $this->SignRule->findById($sign_rule_id);

            $core_starttime = $sign_rule['SignRule']['core_starttime'];
            $core_endtime = $sign_rule['SignRule']['core_endtime'];
            $flex_time = $sign_rule['SignRule']['flex_time']; //弹性时间，单位为分钟
            $core_start_ttp = strtotime($core_starttime);
            $core_end_ttp = strtotime($core_endtime);
            $edge_ttp= $core_start_ttp + 60*1000*$flex_time;  //最终的早晨打卡时间，不超过弹性时间+规定的打卡时间

            $all_record_data = array();

            foreach ($date_ary as $date => $times) { //每一个员工某天的循环

                $is_existing = $this->SignRecord->find('count',array(
                        'conditions' => array(
                            'employee_id' => $employee_id,
                            'whichday' => $date
                        )
                    )
                );
                if($is_existing) continue;

                $n = count($times);   //打了n次卡
                $record_data = array();
                $record_data['employee_id'] = $employee_id;
                $record_data['whichday'] = $date;
                $record_data['dpt_id'] = $dpt_id;
                $y_m_str = substr($date, 0 ,-3);
                list($year_str, $month_str) = explode('-', $y_m_str);
                $record_data['year_and_month'] = (int)$year_str.$month_str;

                //以下需填充这条记录的sign_start, sign_end, state_forenoon, state_afternoon
                if($n == 0) {
                    $record_data['state_forenoon'] = $record_data['state_afternoon'] = 0;
                    $timestamp = strtotime($date);
                    $weekday = date('w',$date);
                    if( $weekday !== '0' && $weekday !== '6' ) {
                        $record_data['is_abnormal'] = 1; //不是周六，周日即为异常
                    }
                }

                /*
                **  rule state mapping
                **  0=>留空，1=>出勤,2=>迟到,3=>旷工,4=>早退,5=>休假,6=>事假,7=>病假,8=>外地出差,9=>中途脱岗
                */

                else if($n == 1) {
                    $sign_timestamp = strtotime( $times[0] );

                    $record_data['sign_start'] = $record_data['sign_end'] = $times[0];
                    if($sign_timestamp <= $noon_ttp) {//sign in forenoon
                        if ($sign_timestamp < $edge_ttp) {//not late
                            $record_data['state_forenoon'] = 1;
                        }
                        else $record_data['state_forenoon'] = 2;
                        $record_data['state_afternoon'] = 0;
                    }

                    else{//sign in afternoon
                        $record_data['state_forenoon'] = 0;
                        $record_data['state_afternoon'] = 1;
                    }

                    if($dpt_id != $techDptId) {//not in IT department
                        $record_data['is_abnormal'] = 1;
                    }
                    else $record_data['is_abnormal'] = 0;

                }

                else { // sign in twice and more
                    $record_data['sign_start'] = reset($times);
                    $record_data['sign_end'] = end($times);
                    $come_ttp = strtotime( $record_data['sign_start'] );
                    $leave_ttp = strtotime( $record_data['sign_end'] );
                    

                    if( $leave_ttp <= $noon_ttp) {//两次打卡都在上午
                        if( $come_ttp <= $edge_ttp ) {
                            $record_data['state_forenoon'] = 1;
                        }
                        else $record_data['state_forenoon'] = 2;
                        $record_data['state_afternoon'] = 0;
                    }
                    else if( $come_ttp >= $noon_ttp ) {//两次打卡都在下午
                        $record_data['state_forenoon'] = 0;
                        if( $leave_ttp < $core_end_ttp) {
                            $record_data['state_afternoon'] = 4;
                        }
                        else $record_data['state_afternoon'] = 1;
                    }
                    else {//一次上午打卡，一次下午打卡
                        if( $come_ttp <= $edge_ttp ) {
                            $record_data['state_forenoon'] = 1;
                        }
                        else $record_data['state_forenoon'] = 2;

                        if( $leave_ttp < $core_end_ttp) {
                            $record_data['state_afternoon'] = 4;
                        }
                        else $record_data['state_afternoon'] = 1;
                    }
                }

                $all_record_data[] = $record_data;

            } //end foreach for the employee someone date

            $this->SignRecord->saveAll($all_record_data);

        }//end foreach for the employee

        $month_timestamp = strtotime($month);
        $month = date('Y-m', $month_timestamp);
        $this->Session->setFlash('考勤文件上传并分析成功', 'flash_custom');
        $this->redirect(array(
                'controller' => 'Show',
                'action' => 'getdptrecords',
                '?' => array(
                    'dpt_name' => '行政部',
                    'month' => $month
                )
            )
        );
    }

}
