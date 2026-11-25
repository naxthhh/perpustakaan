<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $inputData = [];

    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $inputData = json_decode($raw, true) ?? [];
    } else {
        $inputData = $_POST;
    }

    $action = $inputData['action'] ?? $_GET['action'] ?? 'list';

    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        json_response(['ok' => true, 'cart' => cart_summary()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'message' => 'Metode HTTP tidak diizinkan.'], 405);
    }

    require_csrf();

    $id  = filter_var($inputData['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $qty = filter_var($inputData['qty'] ?? 1, FILTER_VALIDATE_INT);

    if ($id === false || $id === null || $qty === false) {
        json_response(['ok' => false, 'message' => 'Data parameter tidak valid.'], 422);
    }

    $stmt = db()->prepare('SELECT title, stock FROM books WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch();

    if (!$book) {
        json_response(['ok' => false, 'message' => 'Buku tidak ditemukan di database.'], 404);
    }

    $cart    = cart_raw();
    $stock   = (int) $book['stock'];
    $message = '';

    switch ($action) {
        case 'add':
            if ($qty < 1) {
                json_response(['ok' => false, 'message' => 'Jumlah minimal adalah 1.'], 422);
            }
            if ($stock < 1) {
                json_response(['ok' => false, 'message' => 'Stok buku ini sedang habis.'], 409);
            }

            $currentQty = $cart[$id] ?? 0;
            $newQty     = $currentQty + $qty;
            $cart[$id]  = min($newQty, $stock);

            $message = ($newQty > $stock)
                ? "Jumlah dibatasi sesuai stok yang tersedia ({$stock})."
                : '“' . $book['title'] . '” berhasil dimasukkan ke keranjang.';
            break;

        case 'update':
            if ($qty < 1) {
                unset($cart[$id]);
                $message = 'Buku telah dihapus dari keranjang.';
            } else {
                $cart[$id] = min($qty, $stock);
                $message   = 'Jumlah buku diperbarui.';
            }
            break;

        case 'remove':
            unset($cart[$id]);
            $message = 'Buku dihapus dari keranjang.';
            break;

        default:
            json_response(['ok' => false, 'message' => 'Aksi tidak valid atau tidak dikenal.'], 400);
    }

    cart_save($cart);
    json_response([
        'ok'      => true, 
        'message' => $message, 
        'cart'    => cart_summary()
    ]);

} catch (Throwable $e) {
    error_log('[CART_ERROR] ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Terjadi kesalahan internal pada server.'], 500);
}