<?php
// config dataclass
class config {
    public string $JWT_DAILY_REFRESH_KEY;
    public function __construct(string $JWT_DAILY_REFRESH_KEY){
        $this->JWT_DAILY_REFRESH_KEY = $JWT_DAILY_REFRESH_KEY;
    }
}
?>