        <header>
            <div class="logo text-center"><img src="../img/logo.png" ></div>
            <h1 class="title text-center">有米考勤签到分析神器</h1>
        </header>


        <div class="sb-container">
            <form id="lookupHistory" class="text-center upload-form">
                <div class="sb-form-group">
                    <input id="monthSelector" type=text class="form-control" placeholder="选择月份">
                    <p class="sb-help-block">*选择月份后可以直接查看已统计的历史记录</p>
                </div>
                <div class="sb-form-group">
                    <a href="display.html" class="btn btn-green">查看考勤记录</a>
                </div>
                <hr/>
            </form>
            <?php $this->Session->flash(); ?>

            <form id="uploadForm" method="post" class="text-center upload-form" action="/handle/parseFile" enctype="multipart/form-data">
                <div class="sb-form-group">
                    <input class="file-wrap form-control" type="text" placeholder="选择上传文件">
                    <input class="fileUpload" type="file" name="signfile" id="signfile" />
                </div>
                <div class="uploaded-div">
                    <div class="uploaded-file">
                        <i class="file-icon"></i>
                        <span class="file-name"></span>
                    </div>
                </div>
                <div class="sb-form-group">
                    <input type="submit"  class="btn btn-blue" value="录入最新考勤统计" />
                </div>
            </form>
            <div class="alert alert-danger alert-dismissable">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <span>没有该月的考勤记录，请先上传!</span>
            </div>
        </div>

<?php echo $this->Html->scriptStart(array('block' => 'script')); ?>

  
        
        signbook.upload.init();  
    
<?php echo $this->Html->scriptEnd(); ?>