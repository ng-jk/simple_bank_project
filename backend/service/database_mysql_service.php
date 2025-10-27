<?php

class database_mysql_service extends database_service implements middleware_interface_service{

    public function __construct(database $database){
        $this->database = $database;
    }

    // overwirte function in database_service to use another database
    public function is_allow(){
        // everything need database
        return true;
    }

    public function is_pass(){
        if ($this->mysqli->connect_errno) {
            die("Connection failed: " . $this->mysqli->connect_error);
        }else{
            return true;
        }
    }

    public function is_end() {
        // nothign should end here
        return false;
    }

    public function middleware_check(){
        if(!$this->is_allow()){
            return false;
        }
        if($this->is_allow() && $this->is_pass()){
            return true;
        }
        if($this->is_allow() && $this->is_end()){
            $status_code = 401; # Unauthorized
            include "backend/controller/status_code_controller.php";
            exit;
        }
        
    }

}

?>