(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const $ = (s) => document.querySelector(s);
  const grid = $('#book-grid');
  const dialog = $('#cart-dialog');
  const FALLBACK_COVER = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="420"><rect width="100%" height="100%" fill="#1d4ed8"/>' +
    '<text x="50%" y="50%" fill="#fff" font-family="sans-serif" font-size="22" text-anchor="middle">Buku</text></svg>'
  );

  async function api(url, data = null) {
    const opts = data
      ? {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrf, 
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        }
      : {};

    try {
      const res = await fetch(url, opts);
      return await res.json();
    } catch (err) {
      console.error('[API_ERROR]', err);
      return { ok: false, message: 'Tidak dapat terhubung ke server.' };
    }
  }

  let toastTimer;
  function toast(message, isError = false) {
    const el = $('#toast');
    if (!el) {
      alert((isError ? '⚠️ ' : '✅ ') + message);
      return;
    }
    el.textContent = message;
    el.className = 'toast' + (isError ? ' error' : '');
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (el.hidden = true), 3200);
  }

  const coverFallback = (img) => img.addEventListener('error', () => { img.src = FALLBACK_COVER; }, { once: true });
  if (grid) grid.querySelectorAll('img').forEach(coverFallback);

  function el(tag, props = {}, ...children) {
    const node = Object.assign(document.createElement(tag), props);
    children.forEach((c) => node.append(c));
    return node;
  }

  function renderCart(cart) {
    if (!cart) return;

    const badge = $('#cart-count');
    if (badge) {
      badge.textContent = cart.count;
      badge.hidden = cart.count === 0;
    }

    const totalEl = $('#cart-total');
    if (totalEl) totalEl.textContent = cart.count;

    const checkoutBtn = $('#checkout-btn');
    if (checkoutBtn) checkoutBtn.disabled = cart.count === 0;

    const body = $('#cart-body');
    if (!body) return;

    body.replaceChildren();
    if (!cart.items || !cart.items.length) {
      body.append(el('p', { className: 'cart-empty', textContent: 'Keranjang masih kosong. Pilih buku dari katalog.' }));
      return;
    }

    cart.items.forEach((it) => {
      const img = el('img', { src: it.image_url, alt: '', width: 52, height: 73 });
      coverFallback(img);

      const minus = el('button', { type: 'button', textContent: '−', ariaLabel: 'Kurangi' });
      const plus = el('button', { type: 'button', textContent: '+', ariaLabel: 'Tambah' });
      const input = el('input', { type: 'number', value: it.qty, min: 1, max: it.stock, ariaLabel: 'Jumlah ' + it.title });
      const remove = el('button', { type: 'button', className: 'remove', textContent: 'Hapus' });

      const update = (qty) => cartAction('update', it.id, qty);
      minus.onclick = () => update(it.qty - 1);
      plus.onclick = () => update(Math.min(it.qty + 1, it.stock));
      input.onchange = () => update(parseInt(input.value, 10) || 1);
      remove.onclick = () => cartAction('remove', it.id);

      body.append(el('div', { className: 'cart-item' },
        img,
        el('div', {}, el('h3', { textContent: it.title }), el('small', { textContent: it.author })),
        el('div', { className: 'cart-controls' }, minus, input, plus, remove)
      ));
    });
  }

  async function cartAction(action, id, qty = 1) {
    const r = await api('cart.php', { action, id: parseInt(id, 10), qty: parseInt(qty, 10) });
    if (r.cart) renderCart(r.cart);
    if (r.message) toast(r.message, !r.ok);
    return r;
  }

  async function loadCart() {
    const r = await api('cart.php?action=list');
    if (r.cart) renderCart(r.cart);
  }

  if (grid) {
    grid.addEventListener('submit', async (e) => {
      const form = e.target.closest('.add-form');
      if (!form) return;
      e.preventDefault();

      const bookId = form.dataset.bookId || form.getAttribute('data-book-id');
      const qty = parseInt(form.qty?.value || 1, 10);

      if (!qty || qty < 1) return toast('Jumlah minimal 1.', true);

      await cartAction('add', bookId, qty);
      if (form.qty) form.qty.value = 1;
    });
  }

  const openCartBtn = $('#open-cart');
  if (openCartBtn && dialog) {
    openCartBtn.onclick = () => { loadCart(); dialog.showModal(); };
  }

  const closeCartBtn = $('#close-cart');
  if (closeCartBtn && dialog) {
    closeCartBtn.onclick = () => dialog.close();
  }

  if (dialog) {
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
  }

  const checkoutBtn = $('#checkout-btn');
  if (checkoutBtn) {
    checkoutBtn.onclick = () => {
      window.location.href = 'checkout.php';
    };
  }

  const searchInput = $('#search');
  let searchTimer;

  async function refreshGrid() {
    if (!grid || !searchInput) return;
    const q = searchInput.value.trim();
    const res = await fetch('index.php?ajax=1&q=' + encodeURIComponent(q));
    grid.innerHTML = await res.text();
    grid.querySelectorAll('img').forEach(coverFallback);
    history.replaceState(null, '', q ? '?q=' + encodeURIComponent(q) : 'index.php');
  }

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => refreshGrid().catch(() => toast('Pencarian gagal.', true)), 300);
    });
  }

  const searchForm = $('#search-form');
  if (searchForm) {
    searchForm.addEventListener('submit', (e) => { e.preventDefault(); refreshGrid(); });
  }

  loadCart();
})();