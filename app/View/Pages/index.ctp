        <header>
            <div class="logo text-center"><a href="/"><img src="../img/logo.png" ></a></div>
            <h1 class="title text-center">有米考勤签到分析神器</h1>
        </header>



        <div class="sb-container">
            <?php echo $this->Session->flash(); ?>
            <form id="uploadForm" method="post" class="text-center upload-form" action="/show/parseFile" enctype="multipart/form-data">
                <div class="sb-form-group">
                    <input class="file-wrap form-control input-file" type="text" placeholder="选择上传文件">
                    <input id="fileUpload" class="fileUpload hide" type="file" name="data[signfile]"  />
                    <p class="sb-help-block">*选择上传总公司考勤excel后点击录入</p>
                </div>
                <div class="uploaded-div">
                    <div class="uploaded-file">
                        <i class="file-icon"></i>
                        <span class="file-name"></span>
                    </div>
                </div>
                <div class="sb-form-group">
                    <a id="submitNew" href="#" class="btn btn-green">录入最新考勤统计</a>
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
