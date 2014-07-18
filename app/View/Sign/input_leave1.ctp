<div class="input-append date" id="datetimepicker" data-date-format="yyyy-mm-dd">
    <input class="span2" size="16" type="text" value="12-02-2012">
    <span class="add-on"><i class="icon-th"></i></span>
</div>




<?php echo $this->Html->scriptStart(array('block' => 'script')); ?>
    $(document).ready(function(){
        $('#datetimepicker').datetimepicker({
            lang: 'zh-CN'
        });
    });
<?php echo $this->Html->scriptEnd(); ?>
