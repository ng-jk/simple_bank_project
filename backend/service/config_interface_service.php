<?php
require_once "config.php";

if (!interface_exists('config_interface_service')) {
interface config_interface_service{
    public function update_config_key(mysqli $mysqli, $column, $value = null);
    public function get_config(mysqli $mysqli, ...$columns);
}
}
?>