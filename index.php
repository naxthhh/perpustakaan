<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$q     = trim((string) ($_GET['q'] ?? ''));
$books = [];
$error = null;

try {
    $sql    = 'SELECT id, title, author, publisher, publish_year, image_url, stock FROM books';
    $params = [];

    if ($q !== '') {
        $like   = '%' . addcslashes($q, '%_\\') . '%';
        $sql   .= ' WHERE title LIKE :q1 OR author LIKE :q2';
        $params = [':q1' => $like, ':q2' => $like];
    }

    $stmt = db()->prepare($sql . ' ORDER BY author, publish_year, title');
    $stmt->execute($params);
    $books = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[INDEX_FETCH_ERROR] ' . $e->getMessage());
    $error = $e instanceof RuntimeException ? $e->getMessage() : 'Gagal memuat katalog buku.';
}


function render_grid(array $books, string $q, ?string $error): void
{
    if ($error) {
        echo '<p class="empty" role="alert">' . e($error) . '</p>';
    } elseif (!$books) {
        echo '<p class="empty">Tidak ada buku untuk “' . e($q) . '”. Coba pencarian lain.</p>';
    } else {
        foreach ($books as $book) {
            require __DIR__ . '/partials/book_card.php';
        }
    }
}

if (isset($_GET['ajax'])) {
    render_grid($books, $q, $error);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>My Perpustakaan Gua</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  
  <script src="app.js" defer></script>
</head>
<body>

<header class="site-header">
  <div class="container header-row">
    <a class="brand" href="index.php">
      <span class="brand-mark" aria-hidden="true">P</span>
      <span>My Perpustakaan Gua</span>
    </a>
    <button class="cart-btn" id="open-cart" type="button" aria-label="Buka keranjang">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="9" cy="20" r="1.5"/>
        <circle cx="18" cy="20" r="1.5"/>
        <path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.5L21 8H6"/>
      </svg>
      <span class="cart-badge" id="cart-count" hidden>0</span>
    </button>
  </div>
  
  <div class="container search-wrap">
    <form id="search-form" action="index.php" method="get" role="search">
      <label class="visually-hidden" for="search">Cari buku</label>
      <input id="search" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari judul atau nama penulis…" autocomplete="off">
      <button class="btn btn-primary" type="submit">Cari</button>
    </form>
  </div>
</header>

<main class="container">
  <h1 class="page-title">Katalog Buku</h1>
  <section id="book-grid" class="book-grid" aria-live="polite">
    <?php render_grid($books, $q, $error); ?>
  </section>
</main>

<footer class="site-footer">
</footer>

<dialog id="cart-dialog" class="cart-dialog" aria-labelledby="cart-title">
  <div class="cart-head">
    <h2 id="cart-title">Keranjang</h2>
    <button class="icon-btn" id="close-cart" type="button" aria-label="Tutup keranjang">&times;</button>
  </div>
  <div id="cart-body" class="cart-body"></div>
  <div class="cart-foot">
    <p>Total: <strong id="cart-total">0</strong> buku</p>
    <button class="btn btn-primary" id="checkout-btn" type="button">Checkout</button>
  </div>
</dialog>

<div id="toast" class="toast" role="status" aria-live="polite" hidden></div>

</body>
</html>