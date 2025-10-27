<?php
require_once "jwt.php";

if (!interface_exists('jwt_interface_service')) {
interface jwt_interface_service {
    public function generate_token(array $payload);
    public function verify_token(string $token);
    public function decode_token(string $token);
}
}
?>