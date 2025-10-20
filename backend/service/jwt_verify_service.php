<?php
require_once "jwt.php";
require_once "jwt_service.php";
require_once "middleware_interface_service.php";

use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;

class jwt_generate_service extends jwt_service implements middleware_interface_service {
    public function jwt_verify($jwt){

        global $config;

        // generate JSON Web Key
        $jwk = new JWK([
            'kty' => 'oct', //symmetric octet (HMAC) key
            'k' => base64_encode($config['JWT_DAILY_REFRESH_KEY']),
        ]);

        // Deserialize it
        $serializer = new CompactSerializer();
        $jws = $serializer->unserialize($jwt);

        // Verify signature
        $algorithmManager = new AlgorithmManager([new HS256()]);
        $jwsVerifier = new JWSVerifier($algorithmManager);
        $result = $jwsVerifier->verifyWithKey($jws, $jwk, 0);

        if($result){
            $payload = json_decode($jws->getPayload(), true);
            return $payload;
        }else{
            return false;
        }

    }

    public function is_allow(){

    }
    public function is_pass(){

    }
    public function is_end(){

    }
    public function middleware_check(){

    }

    
}
?>