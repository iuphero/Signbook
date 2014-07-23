signbook.inputLeave = (function (sk) {

    sk.init = function () {

        var monthText = false;
        var month = false;

        $('#datetimepicker').datetimepicker({
            format: 'yyyy-MM',
            startView: 'year',
            language: 'zh-CN',
            minView: 4,
            autoclose: 1
        });

        $('.the-file').html5Uploader({
            name: 'leave',
            postUrl: '/excelAjax/parseLeave',
            onClientLoadStart: function () {
                $('.waiting-alert').slideDown();
            },
            onSuccess : function (e, xhr, text){
                try{
                    var result = JSON.parse(text);
                }catch(error){
                    $('.error-alert').text('可能是程序错误或上传文件不对, 请刷新重试, 上传正确的请假Excel表格').slideDown();
                    $('.waiting-alert').hide();
                    return;
                }
                if(result.code == 0) {
                    $('.error-alert').text(result.info + '， 刷新重试').slideDown();
                }
                else {//导入请假数据文件成功
                    $('.error-alert').text('成功导入数据，可以导出Excel文件了').slideDown();
                    $('.input-file').slideUp();
                    var href = '/excelAjax/outputLeave/' + monthText;
                    $('.btn-output-leave').attr('href', href)
                    .text('导出' + monthText + '月份请假Excel表格').show();
                }
                $('.waiting-alert').hide();
            }
        });

        $('.btn-month').click(function(){
            month = $('#the-month').val(); //2014-07-01 00:00:00
            monthText = month.substr(0, 7);
            if(month.length == 0) {
                $('.modal-title').text('请选择');
                $('.modal-body').text('请选择月份');
                $('#leave-modal-box').modal();
                return;
            }
            $.post('/excelAjax/hasLeaveData', {
                'time' : month
            }, function(result) {
                if(result == 1) {//此月数据已存在, 提示错误
                    $('.modal-title').text('已有数据');
                    $('.modal-body').text('您选择的月份已有数据存在, 请重新选择');
                    $('#leave-modal-box').modal();
                }
                else {
                    $('.step').text('第二步:上传Excel文件');
                    $('.input-file').slideDown();
                    $('.input-month').hide();
                    $('.input-file .box-header').text('上传' + monthText + '月份的Excel请假文件');
                }
            });
        });

    };//end sk.init

    return sk;
}(signbook.inputLeave1 || {}));