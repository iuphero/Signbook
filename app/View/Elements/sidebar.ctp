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

                    <li class="active">
                            <a href="/">
                                <i class="glyphicon glyphicon-home"></i> <span>首页</span>
                            </a>
                    </li>

                    <li class="treeview active">
                        <a href="#">
                            <i class="glyphicon glyphicon-user"></i>
                            <span>部门</span>
                            <i class="glyphicon glyphicon-chevron-left pull-right"></i>
                        </a>
                        <ul class="treeview-menu">
                            <li>
                                <a href="#"><i class="glyphicon glyphicon-chevron-right"></i> 设置部门考勤规则</a>
                            </li>
                            <li>
                                <a href="/set/updateAll"><i class="glyphicon glyphicon-chevron-right"></i> 更新部门与员工</a>
                            </li>
                        </ul>
                    </li>

                    <li class="treeview active">
                        <a href="#">
                            <i class="glyphicon glyphicon-check"></i>
                            <span>请假</span>
                            <i class="glyphicon glyphicon-chevron-left pull-right"></i>
                        </a>
                        <ul class="treeview-menu">
                            <li><a href="/sign/input/leave"><i class="glyphicon glyphicon-chevron-right"></i> 录入请假数据</a></li>
                            <li><a href="/sign/get/leave"><i class="glyphicon glyphicon-chevron-right"></i> 导出请假表格</a></li>
                        </ul>
                    </li>

                     <li class="treeview active">
                        <a href="#">
                            <i class="glyphicon glyphicon-tasks"></i>
                            <span>考勤</span>
                            <i class="glyphicon glyphicon-chevron-left pull-right"></i>
                        </a>
                        <ul class="treeview-menu">
                            <li><a href="/sign/input/sign"><i class="glyphicon glyphicon-chevron-right"></i> 录入考勤数据</a></li>
                            <li><a href="/sign/get/sign"><i class="glyphicon glyphicon-chevron-right"></i> 导出考勤表格</a></li>
                        </ul>
                    </li>

                </ul>
            </aside>
            <!-- end left-side -->
            <aside class="right-side">
                <?php echo $this->fetch('content'); ?>
            </aside>
        </div>