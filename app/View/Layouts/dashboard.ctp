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
        <?php
            echo $this->element('header');
            echo $this->element('sidebar');
            echo $this->element('block-lib-js');
            echo $this->fetch('script');
            echo $this->fetch('page-script');
        ?>
    </body>
</html>