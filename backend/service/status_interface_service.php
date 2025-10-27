<?php 
require_once "status.php";

if (!interface_exists('status_interface_service')) {
interface status_interface_service{
    # any common function
    public function set_status(status $status);
    public function get_status();
}
}
?>