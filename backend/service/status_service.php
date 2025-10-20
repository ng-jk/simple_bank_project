<?php 
require_once "status_interface_service.php";
require_once "status.php";

// basic status service, add another status service by adding class extent it
abstract class status_service implements status_interface_service {

    public function __construct(public status $status){
        $this->status = $status;
    }
    public function set_status($status){
        $this->status = $status;
    }
    public function get_status(){
        return $this->status;
    }
    
}