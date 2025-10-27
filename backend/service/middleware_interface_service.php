<?php

interface middleware_interface_service{    
    public function is_allow();
    public function is_pass();
    public function is_end();
    public function middleware_check();
}
?>