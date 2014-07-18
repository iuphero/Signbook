/*
** 用于侧边栏菜单的显示和隐藏
 */
(function($) {
    "use strict";

    $.fn.tree = function() {
        return this.each(function() {
            var btn = $(this).children("a").first();
            var menu = $(this).children(".treeview-menu").first();
            var isActive = $(this).hasClass('active');

            //initialize already active menus
            if (isActive) {
                menu.show();
                btn.children(".glyphicon-chevron-left").first().removeClass("glyphicon-chevron-left").addClass("glyphicon-chevron-down");
            }
            //Slide open or close the menu on link click
            btn.click(function(e) {
                e.preventDefault();
                if (isActive) {
                    //Slide up to close menu
                    menu.slideUp();
                    isActive = false;
                    btn.children(".glyphicon-chevron-down").first().removeClass("glyphicon-chevron-down").addClass("glyphicon-chevron-left");
                    btn.parent("li").removeClass("active");
                } else {
                    //Slide down to open menu
                    menu.slideDown();
                    isActive = true;
                    btn.children(".glyphicon-chevron-left").first().removeClass("glyphicon-chevron-left").addClass("glyphicon-chevron-down");
                    btn.parent("li").addClass("active");
                }
            });

            /* Add margins to submenu elements to give it a tree look */
            menu.find("li > a").each(function() {
                var pad = parseInt($(this).css("margin-left")) + 10;
                $(this).css({"margin-left": pad + "px"});
            });

        });
    };
}(jQuery));

$(".treeview").tree();
var signbook = {};
