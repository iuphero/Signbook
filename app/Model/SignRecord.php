<?php 
    

class SignRecord extends AppModel {
    
    public $primaryKey = 'id';

    public $useDbConfig = 'default';

    public $useTable = 'sign_record';

    public $name = 'SignRecord';

    public function getCompanyRecords(){
        
    }

}