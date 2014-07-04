        <header>
            <div class="logo text-center"><a href="/"><img src="../img/logo.png" ></a></div>
            <h1 class="title text-center">有米考勤签到分析神器</h1>
        </header>



        <div class="sb-container">
            <?php echo $this->Session->flash(); ?>
            <form id="uploadForm" method="post" class="text-center upload-form" action="/show/parseFile" enctype="multipart/form-data">
                <div class="sb-form-group">
                    <input class="form-control" id="holidaySelector" type="text" name="data[date]">
                    <p class="sb-help-block">*输入该月的第一天和最后一天</p>
                    <p class="sb-help-block">*2014-02-01;2014-02-28</p>
                </div>
                <div class="sb-form-group">
                    <input class="form-control" id="holidaySelector" type="text" placeholder="公众假期" name="data[holiday]">
                    <p class="sb-help-block">*用以下方式输入该月公众假期（包括周末）</p>
                    <p class="sb-help-block">*01;02;15;16;19;20</p>
                </div>
                <div class="sb-form-group">
                    <input class="file-wrap form-control input-file" type="text" placeholder="选择上传文件">
                    <input class="fileUpload hide" type="file" name="data[signfile]"  />
                    <p class="sb-help-block">*选择上传总公司考勤excel后点击录入</p>
                </div>
                <div class="sb-form-group">
                    <input class="file-wrap form-control input-file" type="text" placeholder="选择上传文件">
                    <input class="fileUpload hide" type="file" name="data[offfile]"  />
                    <p class="sb-help-block">*选择上传总公司请假汇总excel后点击录入</p>
                </div>
                <div class="sb-form-group">
                    <a id="submitNew" href="#" class="btn btn-green">录入最新考勤统计</a>
                </div>
            </form>
            <div style="text-align:center;">
                <a id="submitNew" href="/show/showResult" class="btn btn-blue">查看最新考勤统计</a>
            </div>
        </div>

<?php echo $this->Html->scriptStart(array('block' => 'script')); ?>
    $(document).ready(
        function() {
            signbook.upload.init();
        }
    );
<?php echo $this->Html->scriptEnd(); ?>
