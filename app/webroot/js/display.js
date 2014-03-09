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

        var rest_days_str = $('.rest_days').text();
        if(rest_days_str) {
            var rest_days = rest_days_str.split(',');
            $.each(rest_days, function(index,value){
                value = value+1;
                var selector = 'td:nth-child(' + value +')';
                $(selector).addClass('rest');
            });             
        }

        
    };

    return sb;
}(signbook.display || {}));
