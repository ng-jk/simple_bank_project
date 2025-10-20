<?php 
require_once "status_service.php";
require_once "middleware_interface_service.php";

class status_basic_service extends status_service implements middleware_interface_service{

    public function is_allow(){
        # every request need status
        return true;
    }

    public function is_pass(){
        # if login then status cheking pass
        if($this->status->is_login){
            return true;
        }
        return false;
    }

    public function is_end(){
        # request will not end here if not pass
        if($this->is_pass()){
            return false;
        }
        return true;
    }

    public function middleware_check(){

        // check if status init is allowed
        if(!$this->is_allow()){
            return false;
        }

        if($this->is_pass() && $this->is_allow()){
            return true;
        }

        // check if should end whole process here
        if($this->is_end() && $this->is_allow()){
            $status_code = 401; # Unauthorized
            include "backend/controller/status_code_controller.php";
            exit;
        }
    }
}

?>