<?php /** @var array $book */ ?>
<article class="book-card">
  <img class="book-cover" src="<?= e($book['image_url']) ?>" alt="Sampul buku <?= e($book['title']) ?>" loading="lazy" width="300" height="420">
  <div class="book-body">
    <h3 class="book-title"><?= e($book['title']) ?></h3>
    <p class="book-meta"><?= e($book['author']) ?></p>
    <p class="book-meta muted"><?= e($book['publisher']) ?>, <?= e($book['publish_year']) ?></p>
    <p class="book-stock <?= $book['stock'] < 1 ? 'out' : '' ?>">
      <?= $book['stock'] < 1 ? 'Stok habis' : 'Stok: ' . e($book['stock']) ?>
    </p>
    <form class="add-form" data-book-id="<?= e($book['id']) ?>">
      <label class="visually-hidden" for="qty-<?= e($book['id']) ?>">Jumlah</label>
      <input id="qty-<?= e($book['id']) ?>" class="qty-input" type="number" name="qty"
             value="1" min="1" max="<?= e(max(1, $book['stock'])) ?>" inputmode="numeric"
             <?= $book['stock'] < 1 ? 'disabled' : '' ?>>
      <button type="submit" class="btn btn-primary" <?= $book['stock'] < 1 ? 'disabled' : '' ?>>Masukkan ke Keranjang</button>
    </form>
  </div>
</article>
