signbook.upload = (function (sb){

    sb.init = function(){
        $('#monthSelector').datetimepicker({
            format : 'yyyy-mm',
            startView : 3,
            minView : 3,
            autoclose : true,
            language : 'zh-CN'
        });

        //上传文件按钮
        var $fileWrap = $('.file-wrap');
        $fileWrap.click(function(){
            $(this).next().trigger('click');
        });
        $('.fileUpload').change(function(){
            var $self = $(this);
            var val = $self.val();
            var $helpBlockWarning = $('<p class="sb-help-block sb-help-block-warning">*请上传考勤软件导出的excel文件</p>');
            var $helpBlockSuccess = $('<p class="sb-help-block sb-help-block-success">*文件格式正确</p>');
            var fileName = val.split('\\').pop();
            var fileType = fileName.split('.').pop();
            if(!val || fileType!= 'xls') {
                $('.uploaded-div').hide();
                $self.next('.sb-help-block').remove();
                $fileWrap.attr('placeholder','选择上传文件');
                $helpBlockWarning.insertAfter($self);
                return false;
            }
            else {
                $('.file-name').html(fileName);
                $('.uploaded-div').show();
                $self.next('.sb-help-block').remove();
                $helpBlockSuccess.insertAfter($self);
                $fileWrap.attr('placeholder','重新选择文件');
            }
        });
    };

    return sb;
}(signbook.upload || {}));
