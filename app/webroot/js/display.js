signbook.display = (function (sb){

    sb.init = function(){

        $('.sb-table td').tooltip({
            container : 'body'
        });
    };

    return sb;
}(signbook.display || {}));
