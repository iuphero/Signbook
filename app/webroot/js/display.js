signbook.display = (function (sb){

    sb.init = function(){

        $('#monthSelector').datetimepicker({
            format : 'yyyy-mm',
            startView : 3,
            minView : 3,
            autoclose : true,
            language : 'zh-CN'
        });

        $('.sb-table td').tooltip({
            container : 'body'
        });
    };

    return sb;
}(signbook.display || {}));
