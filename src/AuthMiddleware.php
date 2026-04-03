<?php
class AuthMiddleware {

    public function __construct(private JwtService $jwt) {}

    public function handle(): void {
        // Apache sometimes puts it in different places
        // so we check all of them
        $header = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? getallheaders()['Authorization']
               ?? '';

        if (empty($header)) {
            $this->reject('No token provided');
        }

        // Strip the "Bearer " prefix
        $token = str_replace('Bearer ', '', $header);

        // Verify the token
        $payload = $this->jwt->verify($token);

        if (!$payload) {
            $this->reject('Invalid or expired token');
        }

        $GLOBALS['auth_user'] = $payload;
    }

    private function reject(string $message): void {
        http_response_code(401);
        echo json_encode(['error' => $message]);
        exit;
    }
}