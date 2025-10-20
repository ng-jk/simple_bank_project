<?php
require_once "config.php";
interface config_interface_service{
    public function update_config_key(){}
    public function get_config(mysqli $mysql, ...$columns){}
}
?>