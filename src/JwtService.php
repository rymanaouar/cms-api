<?php
class JwtService {


    private string $secret;

    public function __construct() {
        $this->secret = getenv('JWT_SECRET') ?: 'fallback-secret-change-this';
    }

    public function generate(int $userId, string $role): string {
        // JWT has 3 parts: header.payload.signature

        // Header: says what type of token and algorithm
        $header = $this->base64url(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]));

        // Payload: the actual data we store in the token
        $payload = $this->base64url(json_encode([
            'sub'  => $userId,        // subject = who this token belongs to
            'role' => $role,          // their role (admin or editor)
            'iat'  => time(),         // issued at (now)
            'exp'  => time() + 86400,  // expires in 1 day
        ]));

        // Signature: proves the token wasn't tampered with
        $signature = $this->base64url(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        // Final 
        return "$header.$payload.$signature";
    }

    public function verify(string $token): ?array {
        // Split the token into its 3 parts
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $signature] = $parts;

        
        $expected = $this->base64url(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (!hash_equals($expected, $signature)) return null;

        // Decode the payload
        $data = json_decode(base64_decode($payload), true);

        // Check if  expired
        if ($data['exp'] < time()) return null;

        return $data;
    }

    // Base64 URL encoding — like regular base64 but safe for URLs
    private function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}