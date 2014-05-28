        <header>
          <div class="logo text-center"><a href="/"><img src="/img/logo.png" ></a></div>
            <h1 class="title text-center">有米考勤签到分析神器</h1>
        </header>
        <?php $this->Session->flash(); ?>
        <div style="width:96%;margin:0 auto;">
            <ul class="table-tag">
              <li><span>出勤 <span class="iconfont icon-regular"></span></li>
              <li><span>迟到 <span class="iconfont icon-late"></span></li>
              <li><span>早退 <span class="iconfont icon-early"></span></li>
              <li><span>休假 <span class="iconfont icon-off"></span></li>
              <li><span>事假 <span class="iconfont icon-p_leave"></span></li>
              <li><span>病假 <span class="iconfont icon-i_leave"></span></li>
              <li><span>出差 <span class="iconfont icon-outgoing"></span></li>
              <li><span>旷工 <span class="iconfont icon-absent"></span></li>
            </ul>
            <!-- Nav tabs -->
            <ul class="nav nav-tabs">
                <?php
                    foreach($youmi as $key => $value){
                        echo '<li><a href="#'.$key.'" data-toggle="tab">'.$key.'</a></li>';
                    }
                ?>
            </ul>

            <!-- Tab panes -->
            <div class="tab-content">
                <?php
                    $one_employee = $youmi['研发中心']['梁家豪'];
                    foreach($youmi as $department => $dpt){
                        echo '<div class="tab-pane" id="'.$department.'"><table class="table table-bordered">';
                        echo '<tr>';
                        echo '<th>姓名</th><th>日</th>';
                        foreach($one_employee as $key => $value) {
                            $key = substr($key,8);
                            echo '<th>'.$key.'</th>';
                        }
                        echo '<th>正常</th><th>出差</th><th>休假</th><th>事假</th><th>病假</th><th>旷工</th><th>迟到</th><th>早退</th><th>中途</th></tr>';
                        foreach($dpt as $employee => $emp) {

                            $regular = $outgoing = $off = $p_leave = $i_leave = $late = $early = $absent = 0;
                            echo '<tr>';
                            echo '<td rowspan="2">'.$employee.'</td>';
                            echo '<td>上</td>';
    
                            foreach($emp as $date_time => $date ){
                                switch ($date['morning']) {
                                case 'regular':
                                    $regular ++;
                                    break;
                                case 'outgoing':
                                    $regular ++;
                                    $outgoing ++;
                                    break;
                                case 'p_leave':
                                    $p_leave ++;
                                    break;
                                case 'off':
                                    $off ++;
                                    break;
                                case 'i_leave':
                                    $i_leave ++;
                                    break;
                                case 'late':
                                    $regular ++;
                                    $late ++;
                                    break;
                                case 'absent':
                                    $absent ++;
                                    break;
                                }
                                echo '<td class="iconfont icon-'.$date['morning'].'"></td>';
                            }
                            foreach($emp as $date_time => $date) {
                                if($date['afternoon'] == 'late') {
                                    $regular ++;
                                    $early ++;
                                }
                            }

                            echo '<td rowspan="2">'.$regular.'</td>';
                            echo '<td rowspan="2">'.$outgoing.'</td>';
                            echo '<td rowspan="2">'.$off.'</td>';
                            echo '<td rowspan="2">'.$p_leave.'</td>';
                            echo '<td rowspan="2">'.$i_leave.'</td>';
                            echo '<td rowspan="2">'.$absent.'</td>';
                            echo '<td rowspan="2">'.$late.'</td>';
                            echo '<td rowspan="2">'.$early.'</td>';
                            echo '<td rowspan="2">-</td>';

                            echo '</tr>';
                            echo '<tr>';
                            echo '<td>下</td>';
                            foreach($emp as $date_time => $date ){
                                echo '<td class="iconfont icon-'.$date['afternoon'].'"></td>';
                            }
                            echo '</tr>';
                        }
                        echo '</tr>';
                        echo '</table></div>';
                    }
                ?>
                <div class="tab-pane" id="settings">...</div>
            </div>
        </div>
<?php echo $this->Html->scriptStart(array('block' => 'script')); ?>
    $(document).ready(
        function() {
            signbook.display.init();
        }
    );
<?php echo $this->Html->scriptEnd(); ?>
