signbook.inputLeave1 = (function (sk) {
    sk.init = function () {

        $('.input-file').hide();

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
                handleUpload(text);
            }
        });

        function handleUpload(action) {
            $error = $('.error-warning');
            var actions = {
                'error-type': function () {
                    $error.text('文件类型错误');
                },

                'error-upload': function () {
                    $error.text();
                },

                'error-db': function () {
                    $error.text();
                },

                'done': function () {
                    $error.text();
                }
            }
            return actions[action]();
        }


        $('.btn-month').click(function () {
            var month = $('#the-month').val(); //2014-07-01 00:00:00
            var monthText = month.substr(0, 7);
            if(month.length == 0) {
                $('.modal-title').text('请选择');
                $('.modal-body').text('请选择月份');
                $('#modalBox').modal();
            }
            $.post('/excelAjax/hasLeaveData', {
                'time' : month
            }, function(result) {
                if(result == 0) {//此月数据已存在, 提示错误
                    $('#modalBox').modal();
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