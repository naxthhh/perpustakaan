<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$adminPhone = '6285157566604';

$cart = cart_raw();

if (!$cart) {
    header('Location: index.php');
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $ids = array_map('intval', array_keys($cart));
    sort($ids);

    $in   = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $pdo->prepare("SELECT id, title, author, stock FROM books WHERE id IN ($in) ORDER BY id FOR UPDATE");
    $stmt->execute($ids);
    $books = array_column($stmt->fetchAll(), null, 'id');

    $updateStmt = $pdo->prepare('UPDATE books SET stock = stock - :qty WHERE id = :id AND stock >= :qty2');
    $orderedItems = [];
    $totalQty = 0;

    foreach ($ids as $id) {
        $qty = (int) $cart[$id];

        if (!isset($books[$id])) {
            throw new DomainException('Terdapat buku di keranjang yang sudah tidak tersedia di katalog.');
        }

        $currentStock = (int) $books[$id]['stock'];
        if ($qty > $currentStock) {
            throw new DomainException(
                'Stok “' . $books[$id]['title'] . '” tidak mencukupi (Tersisa: ' . $currentStock . ' eks).'
            );
        }

        $updateStmt->execute([':qty' => $qty, ':qty2' => $qty, ':id' => $id]);

        $orderedItems[] = [
            'title'  => $books[$id]['title'],
            'author' => $books[$id]['author'],
            'qty'    => $qty
        ];
        $totalQty += $qty;
    }

    $pdo->commit();

    $textMessage  = "BANG NABIEL, SAYA MAU MINJEM BUKU\n";

    foreach ($orderedItems as $index => $item) {
        $num = $index + 1;
        $textMessage .= "{$num}. *{$item['title']}*\n";
        $textMessage .= "   • Penulis: {$item['author']}\n";
        $textMessage .= "   • Jumlah: {$item['qty']}\n\n";
    }

    $encodedMessage = urlencode($textMessage);

    cart_save([]);

    $waUrl = "https://wa.me/{$adminPhone}?text={$encodedMessage}";
    header("Location: {$waUrl}");
    exit;

} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<script>
            alert(" . json_encode('⚠️ Checkout Gagal: ' . $e->getMessage()) . ");
            window.location.href = 'index.php';
          </script>";
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[CHECKOUT_ERROR] ' . $e->getMessage());
    
    echo "<script>
            alert('⚠️ Terjadi kesalahan sistem saat memproses checkout. Silakan coba lagi.');
            window.location.href = 'index.php';
          </script>";
    exit;
}