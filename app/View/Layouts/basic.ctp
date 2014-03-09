<!doctype html>
<html>
    <head>
        <meta charset="utf-8" />
        <title>
        <?php 
            if(isset($page_title)) {
                echo $page_title;
            }
            else echo 'Signbook';
        ?>

        </title>
        <?php 
            echo $this->element('block-css', array(), array('cache' => 'false'));
        ?>
    </head>
    <body>
    
        <?php 
            echo $this->fetch('content');
            echo $this->element('block-js');
            echo $this->fetch('script');
        ?>
    </body>
</html>
