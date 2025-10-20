<?php
// status dataclass
class status {
    public string $permission;
    public string $is_login;
    public string $current_uri;
    public string $request_method;
    public array $user_info;
    public string $host_name;

    public function __construct(string $permission, bool $is_login, string $current_uri, string $request_method, array $user_info = [], string $host_name) {
        $this->permission = $permission;
        $this->user_info = $user_info;
        $this->current_uri = $current_uri;
        $this->request_method = $request_method;
        $this->is_login = $is_login;
        $this->host_name = $host_name;
    }
}
?>