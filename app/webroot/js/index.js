signbook.index = (function (sb){


    sb.init = function( ) {

        $('#signfile').uploadify({
            'method': 'post',
            'debug': true,
            'auto' : true,
            'uploader': '/ajax/uploadFile',
            'swf' : '../img/uploadify.swf',
            'buttonText' : '开始上传',
            'buttonClass' : 'btn',
            'multi': false,
            'formData' : {'name':'xfight', 'age':22},
            // 'fileTypeExts': '*.dat',
            'fileTypeDesc': '请上传正确的文件格式，以.dat结尾',
            'fileSizeLimit' : '600KB',
            'progressData': 'speed',
            'onUploadSuccess': function(file, data, response) {
                alert('The file ' + file.name + ' was successfully uploaded with a response of ' + response + ':' + data);
            },
            'onUploadError' : function(file, errorCode, errorMsg, errorString) {
                alert(''+errorCode+"##"+errorMsg+"##"+errorString);
                // alert('文件上传错误，请稍后再试');
            },
        });
    }


    return sb;
}(signbook.upload || {}));