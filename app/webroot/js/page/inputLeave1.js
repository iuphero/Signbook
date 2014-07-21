signbook.inputLeave1 = (function (sk) {

    sk.init = function () {

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
            onSuccess : function (e, xhr, text){
                console.log(text);
                $('.leave-alert').text(text);
                // var result = JSON.parse(text);
                // if(result.code == 0) {
                //     $('.leave-alert').text(result.info + '， 然后刷新重试').slideDown();
                // }
                // else {
                //     $('.leave-alert').text('成功导入数据，可以导出Excel文件了').slideDown();
                // }
            }
        });


        $('.btn-month').click(function (event) {
            var month = $('#the-month').val(); //2014-07-01 00:00:00
            var monthText = month.substr(0, 7);
            if(month.length == 0) {
                $('.modal-title').text('请选择');
                $('.modal-body').text('请选择月份');
                $('#leave-modal-box').modal();
                return;
            }
            $.post('/excelAjax/hasLeaveData', {
                'time' : month
            }, function(result) {
                if(result == 0) {//此月数据已存在, 提示错误
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