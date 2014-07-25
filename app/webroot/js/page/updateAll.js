/**
 * 用于/set/updateAll页面
 * 使用Excel表格更新部门和员工
 */
signbook.updateAll = (function (sk) {

    sk.init = function () {

        $('.the-file').html5Uploader({
            name: 'employee',
            postUrl: '/employee/parseEmployee',
            onClientLoadStart: function () {
                $('.waiting-alert').slideDown();
            },
            onSuccess : function (e, xhr, text){
                try{
                    var result = JSON.parse(text);
                }catch(error){
                    $('.error-alert').text('可能是程序错误或上传文件不对, 请刷新重试, 上传正确的员工和部门的Excel表格').slideDown();
                    $('.waiting-alert').hide();
                    return;
                }
                if(result.code == 0) {
                    $('.error-alert').text(result.info + '， 刷新重试').slideDown();
                }
                else {//导入请假数据文件成功
                    $('.error-alert').text('成功导入数据，更新了部门和员工记录').slideDown();
                }
                $('.waiting-alert').hide();
            }
        });

    }
    return sk;
}(signbook.updateAll || {}));