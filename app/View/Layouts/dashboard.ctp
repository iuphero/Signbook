<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>
        <?php
            if(isset($page_title)) {
                echo $page_title;
            }
            else echo 'Signbook';
        ?>
        </title>
        <?php echo $this->element('block-css', array(), array('cache' => 'false')); ?>
    </head>
    <body>
        <?php echo $this->element('header'); ?>
        <div class="container">
            <aside class="left-side">
                <form action="#" method="get" class="sidebar-form">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Search...">
                        <span class="input-group-btn">
                            <button type="submit" name="seach" id="search-btn" class="btn">
                            <i class="glyphicon glyphicon-search"></i>
                            </button>
                        </span>
                    </div>
                </form>

                <ul class="sidebar-menu">

                    <li class="treeview">
                        <a href="#">
                            <i class="fa fa-edit"></i> <span>考勤设置</span>
                            <i class="glyphicon glyphicon-chevron-left pull-right"></i>
                        </a>
                        <ul class="treeview-menu">
                            <li><a href="#"><i class="glyphicon glyphicon-chevron-right"></i> 录入考勤数据</a></li>
                            <li><a href="#"><i class="glyphicon glyphicon-chevron-right"></i> 导出考勤表格</a></li>
                        </ul>
                    </li>

                    <li class="treeview">
                        <a href="#">
                            <i class="fa fa-bar-chart-o"></i>
                            <span>部门设置</span>
                            <i class="glyphicon glyphicon-chevron-left pull-right"></i>
                        </a>
                        <ul class="treeview-menu">
                            <li>
                                <a href="#"><i class="glyphicon glyphicon-chevron-right"></i> 设置部门考勤规则</a>
                            </li>
                            <li>
                                <a href="#"><i class="glyphicon glyphicon-chevron-right"></i> 更新部门与员工</a>
                            </li>
                        </ul>
                    </li>

                    <li class="treeview">
                        <a href="#">
                            <i class="fa fa-laptop"></i>
                            <span>请假设置</span>
                            <i class="glyphicon glyphicon-chevron-left pull-right"></i>
                        </a>
                        <ul class="treeview-menu">
                            <li><a href="#"><i class="glyphicon glyphicon-chevron-right"></i> 录入请假数据</a></li>
                            <li><a href="#"><i class="glyphicon glyphicon-chevron-right"></i> 导出请假表格</a></li>
                        </ul>
                    </li>
                </ul>
            </aside>
            <!-- end left-side -->
            <aside class="right-side">
                <?php echo $this->fetch('content'); ?>
            </aside>
        </div>
        <?php
            echo $this->element('block-lib-js');
            echo $this->fetch('script');
            echo $this->fetch('page-script');
        ?>
    </body>
</html>