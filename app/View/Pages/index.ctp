        <header>
            <div class="logo text-center"><a href="/"><img src="../img/logo.png" ></a></div>
            <h1 class="title text-center">有米考勤签到分析神器</h1>
        </header>

    

        <div class="sb-container">
            <?php echo $this->Session->flash(); ?>
            <form id="lookupHistory" class="text-center upload-form" action="/show/getdptrecords" method="get">
                <div class="sb-form-group">
                    <input id="monthHistory" type=text class="form-control input-date" placeholder="选择月份" name="month" >
                    <input type="hidden" name="dpt_name" value="技术部" >
                    <p class="sb-help-block">*选择月份后可以直接查看已统计的历史记录</p>
                </div>
                <div class="sb-form-group">
                    <a id="submitHistory" href="#" class="btn btn-green">查看考勤记录</a>
                </div>
                <hr/>
            </form>
            

            <form id="uploadForm" method="post" class="text-center upload-form" action="/handle/parseFile" enctype="multipart/form-data">
                <div class="sb-form-group">
                    <input id="monthNew" type='text' class="form-control input-date" placeholder="选择月份" name="month">
                    <p class="sb-help-block">*选择月份后可以直接查看已统计的历史记录</p>
                </div>
                <div class="sb-form-group">
                    <input class="file-wrap form-control input-file" type="text" placeholder="选择上传文件">
                    <input id="fileUpload" class="fileUpload hide" type="file" name="signfile" />
                </div>
                <div class="uploaded-div">
                    <div class="uploaded-file">
                        <i class="file-icon"></i>
                        <span class="file-name"></span>
                    </div>
                </div>
                <div class="sb-form-group">
                    <a id="submitNew" href="#" class="btn btn-blue">录入最新考勤统计</a>
                </div>
            </form>
        </div>

<?php echo $this->Html->scriptStart(array('block' => 'script')); ?>
    $(document).ready(
        function() {
            signbook.upload.init();
        }
    );
<?php echo $this->Html->scriptEnd(); ?>
