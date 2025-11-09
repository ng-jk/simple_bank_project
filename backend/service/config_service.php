<?php
require_once "config.php";
require_once "config_interface_service.php";
require_once __DIR__ . '/../util/EncryptionHelper.php';

abstract class config_service implements config_interface_service{
    private $encryption_key;

    public function __construct() {
        $this->encryption_key = EncryptionHelper::getKey();
    }

    public function update_config_key(mysqli $mysqli, $column, $value = null){

        if($value == null){
            $value = base64_encode(random_bytes(16));
        }

        try {
            // Update using encrypted column
            $stm = $mysqli->prepare("UPDATE system_config SET config_value_encrypted = AES_ENCRYPT(?, ?) WHERE config_key = ?");
            $stm->bind_param('sss', $value, $this->encryption_key, $column);
            $stm->execute();
        } catch (\Throwable $th) {
            $status_code = 500;
            $error = $stm->error;
            include "../controller/status_code_controller.php";
            exit;
        }
    }

    public function get_config(mysqli $mysqli, ...$columns){
        try{
            // If no columns specified, return null
            if (empty($columns)) {
                return null;
            }

            // If single column, return single value for backward compatibility
            if (count($columns) === 1) {
                $config_key = $columns[0];
                // Read from encrypted column
                $stm = $mysqli->prepare("
                    SELECT AES_DECRYPT(config_value_encrypted, ?) as config_value
                    FROM system_config
                    WHERE config_key = ?
                ");
                $stm->bind_param('ss', $this->encryption_key, $config_key);
                $stm->execute();
                $result = $stm->get_result();

                if ($row = $result->fetch_assoc()) {
                    return $row['config_value'];
                }

                return null;
            }

            // Multiple columns - return associative array
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $stm = $mysqli->prepare("
                SELECT config_key, AES_DECRYPT(config_value_encrypted, ?) as config_value
                FROM system_config
                WHERE config_key IN ($placeholders)
            ");

            $types = 's' . str_repeat('s', count($columns));
            $params = array_merge([$this->encryption_key], $columns);
            $stm->bind_param($types, ...$params);
            $stm->execute();
            $result = $stm->get_result();

            $configs = [];
            while ($row = $result->fetch_assoc()) {
                $configs[$row['config_key']] = $row['config_value'];
            }

            return $configs;
        } catch (Exception $e) {
            error_log('Error getting config: ' . $e->getMessage());
            return count($columns) === 1 ? null : [];
        }
    }
}
?>