<?php


// Load .env file into $_ENV so all classes can read secrets from environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Router.php';
require_once __DIR__ . '/../src/JwtService.php';
require_once __DIR__ . '/../src/AuthMiddleware.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo    = getDB();
$jwt    = new JwtService();
$auth   = new AuthMiddleware($jwt);
$router = new Router();

// ── PUBLIC ROUTES (no token needed) ──────────────────────────

$router->add('GET', '/api', function() {
    return ['message' => 'CMS API running', 'status' => 'ok'];
});

// Login —returns a JWT token
$router->add('POST', '/api/auth/login', function() use ($pdo, $jwt) {
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['email']) || empty($body['password'])) {
        http_response_code(422);
        return ['error' => 'email and password are required'];
    }

    // Find the user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $body['email']]);
    $user = $stmt->fetch();

    // Check password — password_verify compares plain text to hash
    if (!$user || !password_verify($body['password'], $user['password'])) {
        http_response_code(401);
        return ['error' => 'Invalid credentials'];
    }

    // Generate and return the token
    $token = $jwt->generate($user['id'], $user['role']);
    return [
        'token' => $token,
        'user'  => ['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']]
    ];
});

// Register — creates a new user account
$router->add('POST', '/api/auth/register', function() use ($pdo, $jwt) {
    $body = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (empty($body['name']) || empty($body['email']) || empty($body['password'])) {
        http_response_code(422);
        return ['error' => 'name, email and password are required'];
    }

    // Check if email already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute([':email' => $body['email']]);
    if ($check->fetch()) {
        http_response_code(409); // 409 = Conflict
        return ['error' => 'Email already taken'];
    }

    // Hash the password — never store plain text passwords
    $hashedPassword = password_hash($body['password'], PASSWORD_BCRYPT);

    // Insert the new user
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role)
        VALUES (:name, :email, :password, 'editor')
    ");
    $stmt->execute([
        ':name'     => $body['name'],
        ':email'    => $body['email'],
        ':password' => $hashedPassword,
    ]);

    $userId = $pdo->lastInsertId();

    // Generate a token so they're logged in immediately after registering
    $token = $jwt->generate((int)$userId, 'editor');

    http_response_code(201);
    return [
        'message' => 'Account created',
        'token'   => $token,
        'user'    => ['id' => $userId, 'name' => $body['name'], 'role' => 'editor']
    ];
});

// GET routes are public — anyone can read content
$router->add('GET', '/api/contents', function() use ($pdo) {
    $stmt = $pdo->query("SELECT * FROM contents ORDER BY created_at DESC");
    return ['data' => $stmt->fetchAll()];
});

$router->add('GET', '/api/contents/{id}', function(array $p) use ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM contents WHERE id = :id");
    $stmt->execute([':id' => $p['id']]);
    $content = $stmt->fetch();
    if (!$content) {
        http_response_code(404);
        return ['error' => 'Content not found'];
    }
    return ['data' => $content];
});

// ── PROTECTED ROUTES (token required) ────────────────────────

$router->add('POST', '/api/contents', function() use ($pdo) {
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['title']) || empty($body['body'])) {
        http_response_code(422);
        return ['error' => 'title and body are required'];
    }
    $stmt = $pdo->prepare("
        INSERT INTO contents (title, body, status, created_at)
        VALUES (:title, :body, :status, NOW())
    ");
    $stmt->execute([
        ':title'  => $body['title'],
        ':body'   => $body['body'],
        ':status' => $body['status'] ?? 'draft',
    ]);
    http_response_code(201);
    return ['id' => $pdo->lastInsertId(), 'message' => 'Content created'];
// Pass $auth as middleware — this route now requires a token
}, [$auth]);

$router->add('PUT', '/api/contents/{id}', function(array $p) use ($pdo) {
    $body = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("
        UPDATE contents SET title = :title, body = :body, status = :status
        WHERE id = :id
    ");
    $stmt->execute([
        ':title'  => $body['title'],
        ':body'   => $body['body'],
        ':status' => $body['status'] ?? 'draft',
        ':id'     => $p['id'],
    ]);
    return ['message' => 'Content updated'];
}, [$auth]);

$router->add('DELETE', '/api/contents/{id}', function(array $p) use ($pdo) {
    $stmt = $pdo->prepare("DELETE FROM contents WHERE id = :id");
    $stmt->execute([':id' => $p['id']]);
    return ['message' => 'Content deleted'];
}, [$auth]);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
