<?php
require_once "config.php";
require_once "config_interface_service";
require_once "middleware_interface_service.php";
 class config_basic_service extends config_interface_service implements middleware_interface_service{
    public function update_config_key($mysqli, $column, $value){}
    public function is_allow(){

    }
    public function is_pass(){

    }

    public function is_end() {
    }
    public function middleware_check(){
        
    }
}
?>