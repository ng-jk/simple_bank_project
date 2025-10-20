<?php
require_once "jwt.php";
require_once "jwt_service.php";
require_once "middleware_interface_service.php";

use Jose\Component\Core\JWK;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Core\AlgorithmManager;

class jwt_generate_service extends jwt_service implements middleware_interface_service {
    public function __construct(public jwt $jwt) {
        $this->jwt = $jwt;
    }
    public function generate_jwt(){

        // generate JSON Web Key
        $jwk = new JWK([
            'kty' => 'oct', //symmetric octet (HMAC) key
            'k' => base64_encode($this->jwt->config->JWT_DAILY_REFRESH_KEY),
        ]);

        // generate jwk
        $payload = json_encode([
            'iss' => $this->jwt->status->host_name, // issuer
            'sub' => $this->jwt->status->user_info['user_name'], // username
            'iat' => time(),
            'exp' => time() + 3600,
            'data' => [
                'role' => $this->jwt->status->user_info['user_role']
            ]
        ]);

        // Create the JWS (signed token)
        $algorithmManager = new AlgorithmManager([new HS256()]);
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $jws = $jwsBuilder
                ->create()
                ->withPayload($payload)
                ->addSignature($jwk, ['alg' => 'HS256']) // Sign
                ->build();

        // Serialize it (make it into the actual JWT string)
        $serializer = new CompactSerializer();
        $jwt = $serializer->serialize($jws, 0);
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