signbook.input = (function (sk) {

    sk.init = function () {

        var type = $('#page-type').val(); //type为leave或sign, 分别对应"请假"或"考勤"
        var monthText = false;
        var month = false;
        var holidays = false;
        var holidayUrl = '/sign/saveHolidays';

        if(type == 'leave') { //请假数据导入
            var parseUrl = '/leave/parseLeave';
            var checkUrl = '/leave/hasLeaveData';
            var outputUrl = '/leave/outputLeave/';
            var filename = 'leave';
            var typeText = '请假';
        }
        else { //考勤数据导入
            var parseUrl = '/sign/parseSign/';
            var checkUrl = '/sign/hasSignData';
            var outputUrl = '/sign/outputSign/';
            var filename = 'sign';
            var typeText = '考勤';
        }

        $('#datetimepicker').datetimepicker({
            format: 'yyyy-MM',
            startView: 'year',
            language: 'zh-CN',
            minView: 4,
            autoclose: 1
        });

        $('.btn-month').click(function(){
            month = $('#the-month').val(); //2014-07-01 00:00:00
            monthText = month.substr(0, 7);
            if(month.length == 0) {
                showModel('请选择', '请选择月份');
                return;
            }
            $.post(checkUrl, {
                'time' : month
            }, function(result) {
                if(result == 1) {//此月数据已存在, 提示错误
                    showModel('已有数据', '您选择的月份已有数据存在, 请重新选择');
                }
                else {//无数据, 可以导入
                    $('.input-month').hide();
                    if(type == 'leave') { //假期
                        uploadShow();
                    }
                    else {//考勤
                        inputHolidaysShow();
                    }
                }
            });
        });


        function inputHolidaysShow() {
            $('.step').text('第二步:输入假期');
            $('.input-holidays .box-header').text('请输入'+ monthText +'月份的假期');
            $('.input-holidays').show();

            $('.btn-days').click(function () {
                var days = $.trim( $('#the-days').val() );
                if(days.length == 0) {
                    showModel('没有输入假期', '您确定'+ monthText+ '月份没有假期?');
                }
                else {
                    if(checkHolidays(days)) {
                        $.post(holidayUrl, { //记录假期到数据库
                            'month': monthText,
                            'holidays': days
                        }, function(result) {
                            if(result == 1) {
                                parseUrl += monthText+ '/' + days;
                                $('.input-holidays').slideUp();
                                uploadShow('三');
                            }
                            else {
                                showModel('保存失败', '数据库保存失败, 请联系管理员');
                            }
                        });//end post
                    }
                    else {
                        showModel('输入错误', '您输入的假期不正确, 请检查后再试');
                    }
                }
            });
        }

        //假期类型检查
        function checkHolidays(days) {
            var ary = days.split(',');
            for(var i=0; i < ary.length; i++) {
                try{
                    var ele = Number(ary[i]);
                    if(isNaN(ele)) {
                        return false;
                    }
                    if(ele <= 0 || ele > 31) {
                        return false;
                    }
                }
                catch(e){
                    return false;
                }
            }
            return true;
        }

        //展示文件上传框
        function uploadShow() {
            var step = arguments[0]? arguments[0]: '二';
            $('.step').text('第'+ step+ '步:上传Excel文件');
            $('.input-file').slideDown();
            $('.input-file .box-header').text('上传' + monthText +
             '月份的Excel'+ typeText + '文件');
            initUploader();
        }


        function initUploader() { //用来延迟加载, 否则传入的parseUrl预先绑定了
             $('.the-file').html5Uploader({
                name: filename,
                postUrl: parseUrl,
                onClientLoadStart: function () {
                    $('.waiting-alert').slideDown();
                },
                onSuccess : function (e, xhr, text){
                    try{
                        var result = JSON.parse(text);
                    }catch(error){
                        $('.error-alert').text('可能是程序错误或上传文件不对, 请刷新重试, 上传正确的Excel表格').slideDown();
                        $('.waiting-alert').hide();
                        return;
                    }
                    if(result.code == 0) {
                        $('.error-alert').text(result.info + '， 刷新重试').slideDown();
                    }
                    else {//导入请假数据文件成功
                        $('.error-alert').text('成功导入数据，可以导出Excel文件了').slideDown();
                        $('.input-file').slideUp();
                        var href = outputUrl + monthText;
                        $('.btn-output-leave').attr('href', href)
                        .text('导出' + monthText + '月份' + typeText + 'Excel表格').show();
                    }
                    $('.waiting-alert').hide();
                }
            });
        } //end initUploader

        function showModel(titleText, bodyText) {
            $('.modal-title').text(titleText);
            $('.modal-body').text(bodyText);
            $('#sign-modal-box').modal();
        }

    };//end sk.init

    return sk;
}(signbook.input || {}));