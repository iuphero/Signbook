<section class="content-header">
    <h1>
        导入请假数据
        <small class="step">第一步:选择月份</small>
    </h1>
</section>
<div class="input-leave">
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