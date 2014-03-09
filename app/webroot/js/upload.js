signbook.upload = (function (sb){

    sb.init = function(){
        $('#monthHistory').datetimepicker({
            format : 'yyyy-mm',
            startView : 3,
            minView : 3,
            autoclose : true,
            language : 'zh-CN'
        });
        $('#monthNew').datetimepicker({
            format : 'yyyy-mm',
            startView : 3,
            minView : 3,
            autoclose : true,
            language : 'zh-CN'
        });

        //上传文件按钮
        var $fileWrap = $('.file-wrap');
        var $helpBlockWarning = $('<p class="sb-help-block sb-help-block-warning">*请上传考勤软件导出的dat文件</p>');
        var $helpBlockSuccess = $('<p class="sb-help-block sb-help-block-success">*文件格式正确</p>');
        $fileWrap.click(function(){
            $(this).nextAll('input').trigger('click');
        });
        $('.fileUpload').change(function(){
            var $self = $(this);
            var val = $self.val();
            var fileName = val.split('\\').pop();
            var fileType = fileName.split('.').pop();
            if(!val || fileType!= 'dat') {
                $('.uploaded-div').hide();
                $helpBlockWarning.insertAfter($self);
                $fileWrap.attr('placeholder','选择上传文件');
                return false;
            }
            else {
                $('.file-name').html(fileName);
                $('.uploaded-div').show();
                $self.next('.sb-help-block').remove();
                $fileWrap.attr('placeholder','重新选择文件');
            }
        });

        //录入最新记录表单验证
        $('#submitNew').click(function(){
            var $month = $('#monthNew');
            var $dat = $('#fileUpload');
            if(!$month.val()){
                $month.next('.sb-help-block').addClass('sb-help-block-warning');
                return false;
            }
            else {
                $month.next('.sb-help-block').removeClass('sb-help-block-warning');
            }
            if(!$dat.val()){
                $dat.next('.sb-help-block').remove();
                $helpBlockWarning.insertAfter($dat);
                return false;
            }
            else {
                $dat.next('.sb-help-block').remove();
                $helpBlockSuccess.insertAfter($dat);
            }
            $('#uploadForm').submit();
        });


        //查看历史记录验证
        $('#submitHistory').click(function(){
            var $month = $('#monthHistory');
            if(!$month.val()){
                $month.next('.sb-help-block').addClass('sb-help-block-warning');
                return false;
            }
            else {
                $month.next('.sb-help-block').removeClass('sb-help-block-warning');
            }
            $('#lookupHistory').submit();
        });
    };

    return sb;
}(signbook.upload || {}));
