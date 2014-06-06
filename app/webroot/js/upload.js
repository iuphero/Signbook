signbook.upload = (function (sb){

    sb.init = function(){

        //上传文件按钮
        var $fileWrap = $('.file-wrap');
        var $helpBlockWarning = $('<p class="valid-help sb-help-block sb-help-block-warning">*请上传考勤软件导出的xlsx文件</p>');
        var $helpBlockSuccess = $('<p class="valid-help sb-help-block sb-help-block-success">*文件格式正确</p>');

        $fileWrap.click(function(){
            $(this).next('input').trigger('click');
        });
        $('.fileUpload').change(function(){
            var $self = $(this);
            var val = $self.val();
            var fileName = val.split('\\').pop();
            var fileType = fileName.split('.').pop();
            if(!val || fileType!= 'xlsx') {
                $self.next('.valid-help').remove();
                $helpBlockWarning.clone(true).insertAfter($self);
                $self.prev().attr('placeholder','选择上传文件');
                return false;
            }
            else {
                $self.next('.valid-help').remove();
                $helpBlockSuccess.clone(true).insertAfter($self);
                $self.prev().attr('placeholder',fileName);
            }
        });

        //录入最新记录表单验证
        $('#submitNew').click(function(){
            $('#uploadForm').submit();
        });

    };

    return sb;
}(signbook.upload || {}));
