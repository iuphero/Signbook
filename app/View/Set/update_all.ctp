<section class="content-header">
    <h1>
        <i class="glyphicon glyphicon-list-alt"></i>
        更新部门和员工
        <small class="step">上传Excel表格</small>
    </h1>
</section>

<div class="row update-all">
    <div class="update-all-left col-sm-5">
        <div class="sk-box box box-blue clearfix">
            <h4 class="box-header">
                上传部门和员工的Excel表格
            </h4>
            <div>
                <input class="the-file form-control" size="16" type="file" value="">
            </div>

        </div>

        <div class="waiting-alert sk-alert alert alert-warning  alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span><span class="sr-only">Close</span>
            </button>
            <strong>请等待...</strong>正在处理结果中
        </div>

        <div class="error-alert sk-alert alert alert-warning alert-dismissible leave-alert " role="alert">
             有错误
        </div>

    </div>

    <div class="page-right col-sm-5">
        <div class="input-leave-tip">
            <div class="alert alert-info" role="alert">
                直接使用Excel表格更新部门和员工有风险, 请慎重
                <a href="/sign/inputLeave" class="alert-link">使用普通方法</a>
            </div>
        </div>
    </div>
</div>
<!-- end .row -->

<?php
    echo $this->Html->script('page/updateAll', array('inline' => false));
    echo $this->Html->scriptStart(array('block' => 'script'));
?>
    $(document).ready(function(){
        signbook.updateAll.init();
    });
<?php echo $this->Html->scriptEnd(); ?>