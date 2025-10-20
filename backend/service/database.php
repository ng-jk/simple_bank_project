<?php
// database dataclass

class database{
    public string $hostname;
    public string $username;
    public string $password;
    public string $database;
    public function __construct(string $hostname, string $username, string $password, string $database) {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
    }
}
?>