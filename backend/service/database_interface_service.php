<?php
require_once "database.php";
interface database_interface_service{
    public function database_init(database $database);
    public function database_close($mysqli);
    public function database_check_table($mysqli, $staging_mysqli);
    public function databse_migration();
}
?>