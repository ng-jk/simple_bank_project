<?php
require_once "config.php";
require_once "config_interface_service.php";
abstract class config_service implements config_interface_service{
    public function update_config_key(mysqli $mysqli, $column, $value = null){

        if($value == null){
            $value = base64_encode(random_bytes(16));
        }

        try {
            $stm = $mysqli->prepare("UPDATE system_config SET config_value = ? WHERE ?");
            $stm->bind_param('ss', $value, $column);
            $stm->execute();
        } catch (\Throwable $th) {
            $status_code = 500;
            $error = $stm->error;
            include "../controller/status_code_controller.php";
            exit;
        }
    }

    public function get_config(mysqli $mysqli, ...$columns){
        foreach ($columns as $key => $column) {
            try{
                $stm = $mysqli->prepare("UPDATE system_config SET config_value = ? WHERE ?");
                $stm->bind_param('ss', $value, $column);
                $stm->execute();
            } catch (Exception $e) {

            }
        }
    }
}
?>