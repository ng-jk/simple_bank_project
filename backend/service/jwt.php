<?php
require_once "status.php";
require_once "config.php";
// data extract from jwt
class jwt {
    public string $jwt;
    public config $config;
    public status $status;

    public function __construct(status $status, config $config, string $jwt) {
        $this->status = $status;
        $this->config = $config;
        $this->jwt = $jwt;
    }
}

?>