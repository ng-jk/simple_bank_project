<?php
require_once "database.php";
require_once "database_interface_service.php";

// default function
abstract class database_service implements database_interface_service{
    public $mysqli;

    public function get_database_conn(){
        return $this->mysqli;
    }
    
    public function database_init(database $database){
        $mysqli = new mysqli($database->hostname, $database->username, $database->password, $database->database);
        if ($mysqli->connect_error) {
            die("Failed to connect: " . $mysqli->connect_error);
        }
        $this->mysqli = $mysqli;
        return true;
    }

    public function database_close($mysqli){
        $mysqli->close();
        return true;
    }

    public function databse_migration(){
        include_once "../migration/migration.php";
        return true;
    }

    public function database_check_table($mysqli, $staging_mysqli){
        include_once "../migration/check_table.php";
        return true;
    }

}
?>