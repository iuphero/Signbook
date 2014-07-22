<section class="content-header">
    <h1>
        导入请假数据
        <small class="step">第一步:选择月份</small>
    </h1>
</section>
<div class="row input-leave">
    <div class="input-leave-left col-sm-4">
        <div class="box box-red input-month clearfix">
            <h4 class="box-header">
                您要导入几月份的请假数据
            </h4>
            <div id="datetimepicker" class="input-group date form_date" data-link-field="the-month">
                <input class="form-control" size="16" type="text" value="" readonly>
                <span class="input-group-addon"><span class="glyphicon glyphicon-remove"></span></span>
                <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
            </div>
            <input type="hidden" id="the-month" value="" />
            <button type="button" class="btn btn-primary btn-month">确定</button>
        </div>

        <div class="box input-file clearfix dn">
            <h4 class="box-header">
                上传此月请假的Excel表格
            </h4>
            <div>
                <input class="the-file form-control" size="16" type="file" value="">
            </div>
            <a class="btn btn-primary btn-reset" href="/sign/inputLeave1">重新选择月份</a>
        </div>

        <div class="alert alert-warning alert-dismissible leave-alert dn" role="alert">
             有错误
        </div>

        <a href="#" class="btn btn-success btn-output-leave dn">导出Excel表格</a>
    </div>
    <!-- end .input-leave-left -->

    <div class="input-leave-right col-sm-4">
        <div class="input-leave-tip">
            <div class="alert alert-info" role="alert">
                小提示: 请在导入/导出请假数据前, 先更新员工和部门信息
                <a href="#" class="alert-link">更新</a>
            </div>

            <div class="alert alert-info" role="alert">
                已有数据的月份可以直接导出
                <a href="#" class="alert-link">导出</a>
            </div>
        </div>

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


<div class="modal fade dn" id="leave-modal-box">
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


<?php echo $this->Html->scriptStart(array('block' => 'script')); ?>
    $(document).ready(function(){
        signbook.inputLeave1.init();
    });
<?php
echo $this->Html->scriptEnd();
echo $this->Html->script('page/inputLeave1', array('inline' => false));
?>