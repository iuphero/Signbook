signbook.display = (function (sb){

    sb.init = function(){

        $('#monthSelector').datetimepicker({
            format : 'yyyy-mm',
            startView : 3,
            minView : 3,
            autoclose : true,
            language : 'zh-CN'
        });

        var rest_days_str = $('.rest_days').text();
        if(rest_days_str) {
            var rest_days = rest_days_str.split(',');
            $('table tbody tr').each(function(){
                var $self = $(this);
                $.each(rest_days, function(index,value){
                    var selector = 'td:nth-child('+(value*1+2)+')';
                    console.log(selector);
                    $self.find(selector).addClass('rest-day');
                });
            });
        }

        //查看更多数据表单验证
        var $helpBlockWarning = $('<p class="sb-help-block color-danger text-center">*请选择查看月份</p>');
        $('#submitMore').submit(function(){
            if(!$('#monthSelector').val()){
                $helpBlockWarning.appendTo($(this));
                return false;
            }
        });
    };

    return sb;
}(signbook.display || {}));
