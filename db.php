<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'perpustakaan';
const DB_USER = 'root';
const DB_PASS = '';          

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, 
            ]
        );
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        throw new RuntimeException('Tidak dapat terhubung ke database. Pastikan MySQL di XAMPP sudah berjalan.');
    }
    return $pdo;
}

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        json_response(['ok' => false, 'message' => 'Sesi tidak valid. Muat ulang halaman.'], 419);
    }
}


function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}


function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_save(array $cart): void
{
    $_SESSION['cart'] = $cart;
}

function cart_summary(): array
{
    $cart = cart_raw();
    if (!$cart) {
        return ['items' => [], 'count' => 0];
    }
    $ids = array_map('intval', array_keys($cart));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT id, title, author, image_url, stock FROM books WHERE id IN ($in) ORDER BY title"
    );
    $stmt->execute($ids);

    $items = [];
    $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        $qty = min((int) $cart[$row['id']], (int) $row['stock']);
        if ($qty < 1) {
            continue;
        }
        $items[] = [
            'id'        => (int) $row['id'],
            'title'     => $row['title'],
            'author'    => $row['author'],
            'image_url' => $row['image_url'],
            'stock'     => (int) $row['stock'],
            'qty'       => $qty,
        ];
        $count += $qty;
    }
    return ['items' => $items, 'count' => $count];
}
