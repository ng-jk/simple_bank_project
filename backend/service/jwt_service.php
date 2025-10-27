<?php
require_once "jwt.php";
require_once "jwt_interface_service.php";

abstract class jwt_service implements jwt_interface_service {
    protected jwt $jwt_data;
    protected $secret_key;

    public function __construct(jwt $jwt_data, string $secret_key) {
        $this->jwt_data = $jwt_data;
        $this->secret_key = $secret_key;
    }

    public function get_jwt_data() {
        return $this->jwt_data;
    }

    public function set_secret_key(string $key) {
        $this->secret_key = $key;
    }

    public function get_secret_key() {
        return $this->secret_key;
    }
}
?>