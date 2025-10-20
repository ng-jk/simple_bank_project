<?php 
require_once "status.php";
interface status_interface_service{
    # any common function
    public function set_status(status $status){}
    public function get_status(){}
}
?>