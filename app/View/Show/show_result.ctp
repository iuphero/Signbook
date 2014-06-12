        <header>
          <div class="logo text-center"><a href="/"><img src="/img/logo.png" ></a></div>
            <h1 class="title text-center">有米考勤签到分析神器</h1>
        </header>
<?php echo $this->Html->scriptStart(array('block' => 'script')); ?>
    $(document).ready(
        function() {
            signbook.display.init();
        }
    );
<?php echo $this->Html->scriptEnd(); ?>
