<?php
    if($type == 'leave') {
        $typeText = '请假';
        $resetUrl = '/sign/input/leave';
    }
    else {
        $typeText = '考勤';
        $resetUrl = '/sign/input/sign';
    }
?>
<input type="hidden" id="page-type" value="<?php echo $type; ?>">
<section class="content-header">
    <h1>
        <?php echo '导入'. $typeText. '数据' ?>
        <small class="step">第一步:选择月份</small>
    </h1>
</section>
<div class="row input">
    <div class="input-left col-sm-5">
        <div class="box box-red sk-box input-month clearfix">
            <h4 class="box-header">
                <?php  echo '您要导入几月份的'. $typeText. '数据';?>
            </h4>
            <div id="datetimepicker" class="input-group date form_date" data-link-field="the-month">
                <input class="form-control" size="16" type="text" value="" readonly>
                <span class="input-group-addon"><span class="glyphicon glyphicon-remove"></span></span>
                <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
            </div>
            <input type="hidden" id="the-month" value="" />
            <button type="button" class="btn btn-primary btn-month">确定</button>
        </div>

        <div class="box box-red sk-box input-holidays dn clearfix">
            <h4 class="box-header">
                请输入此月的假期(包括周末)
            </h4>
            <label class="label-day" for="the-days">

                <span class="label label-info label-normal">使用逗号分割.例如:1,2,9,10,17,18</span>
            </label><br>
            <input type="text" id="the-days"  name="the-days" />
            <button type="button" class="btn btn-primary btn-days">确定</button>
        </div>


        <div class="box input-file sk-box clearfix dn">
            <h4 class="box-header">
                 <?php  echo '上传此月'. $typeText. '的Excel表格';?>
            </h4>
            <div>
                <input class="the-file form-control" size="16" type="file" value="">
            </div>
            <a class="btn btn-primary btn-reset" href="<?php echo $resetUrl; ?>">返回重试</a>
        </div>

        <div class="waiting-alert sk-alert alert alert-warning  alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span><span class="sr-only">Close</span>
            </button>
            <strong>请等待...</strong>正在处理结果中
        </div>

        <div class="error-alert sk-alert alert alert-warning alert-dismissible" role="alert">
             有错误
        </div>

        <a href="#" class="btn btn-success btn-output-leave dn">导出Excel表格</a>
    </div>
    <!-- end .input-leave-left -->

    <div class="input-right page-right col-sm-5">
        <div class="input-leave-tip">
            <div class="alert alert-info" role="alert">
                <?php  echo '请在导入/导出'. $typeText. '数据前, 先更新员工和部门信息';?>
                <a href="/set/updateAll" class="alert-link">更新</a>
            </div>

            <div class="alert alert-info" role="alert">
                已有数据的月份可以直接导出
                <a href="/sign/getLeave" class="alert-link">导出</a>
            </div>
        </div>

        <!-- 暂时不需要 -->
        <div class="progress-tip dn">
            <h4>正在导出Excel表格,请稍后...</h4>
            <div class="progress">
                <div class="progress-bar" role="progressbar" aria-valuenow="2" aria-valuemin="0" aria-valuemax="100" style="width: 2%;">
                 2%
                </div>
            </div>
        </div>

    </div>

</div>

<input type="hidden" id="parseUrl">

<div class="modal fade dn" id="sign-modal-box">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title">已有数据</h4>
      </div>
      <div class="modal-body">
        <p>您选择的月份已有数据存在, 请重新选择</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div><!-- /.modal -->


<?php
    echo $this->Html->script('page/input', array('inline' => false));
    echo $this->Html->scriptStart(array('block' => 'script'));
?>
    $(document).ready(function(){
        signbook.input.init();
    });
<?php echo $this->Html->scriptEnd(); ?>