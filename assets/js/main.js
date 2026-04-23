// ================================================================
// TeaStore – Main JavaScript
// ================================================================

// Site URL from embedded meta (set in header.php)
function getSiteUrl() {
    const m = document.querySelector('meta[name="site-url"]');
    return m ? m.getAttribute('content').replace(/\/$/, '') : window.location.origin;
}
function getCartActionUrl() {
    const m = document.querySelector('meta[name="cart-action-url"]');
    return m ? m.getAttribute('content') : getSiteUrl() + '/pages/cart-action.php';
}
function getWishlistActionUrl() {
    const m = document.querySelector('meta[name="wishlist-action-url"]');
    return m ? m.getAttribute('content') : getSiteUrl() + '/pages/wishlist-action.php';
}

// ===== CART COUNT BADGE REALTIME =====
function updateCartBadge(count) {
    count = parseInt(count) || 0;
    document.querySelectorAll('.cart-count').forEach(el => {
        el.textContent = count;
        el.style.display = count > 0 ? '' : 'none';
    });
    // Also animate cart icon
    const cartBtn = document.querySelector('.cart-icon-btn');
    if (cartBtn && count > 0) {
        cartBtn.style.transform = 'scale(1.18)';
        setTimeout(() => cartBtn.style.transform = '', 220);
    }
}

// ===== MOBILE MENU =====
function openMobileMenu() {
    document.getElementById('mobileNav')?.classList.add('open');
    document.getElementById('backdrop')?.classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeMobileMenu() {
    document.getElementById('mobileNav')?.classList.remove('open');
    document.getElementById('backdrop')?.classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SEARCH TOGGLE (mobile) =====
function toggleSearch() {
    let overlay = document.getElementById('mobileSearchOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'mobileSearchOverlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9990;display:flex;align-items:flex-start;padding:80px 20px 20px;backdrop-filter:blur(4px);';
        overlay.innerHTML = `<div style="width:100%;max-width:500px;margin:0 auto;">
            <form action="${getSiteUrl()}/pages/shop.php" method="GET" style="display:flex;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.3);">
                <input type="text" name="q" placeholder="Search teas, accessories..." autofocus style="flex:1;border:none;padding:16px 20px;font-size:16px;outline:none;font-family:inherit;">
                <button type="submit" style="background:var(--primary);color:#fff;border:none;padding:0 20px;cursor:pointer;font-size:18px;"><i class="fas fa-search"></i></button>
            </form>
            <p style="color:#fff;font-size:12px;text-align:center;margin-top:12px;opacity:.7;">Tap outside to close</p>
        </div>`;
        overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
        document.body.appendChild(overlay);
        setTimeout(() => overlay.querySelector('input')?.focus(), 80);
    } else {
        overlay.remove();
    }
}

// ===== STICKY HEADER SHADOW =====
window.addEventListener('scroll', () => {
    const h = document.getElementById('siteHeader');
    if (h) h.style.boxShadow = window.scrollY > 10 ? '0 2px 20px rgba(0,0,0,0.1)' : '';
});

// ===== TOAST NOTIFICATIONS =====
function showToast(msg, type = 'success') {
    let t = document.getElementById('broteachToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'broteachToast';
        document.body.appendChild(t);
    }
    const bg = type === 'error' ? '#e53935' : '#1a1a1a';
    t.style.cssText = `position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(0);background:${bg};color:#fff;padding:13px 24px;border-radius:30px;font-size:14px;font-weight:600;z-index:99999;box-shadow:0 4px 24px rgba(0,0,0,.3);transition:opacity .35s,transform .35s;opacity:1;white-space:nowrap;pointer-events:none;max-width:90vw;`;
    t.textContent = msg;
    clearTimeout(t._tmr);
    t._tmr = setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(12px)'; }, 2800);
}

// ===== ESCAPE HTML =====
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = String(str || '');
    return d.innerHTML;
}

// ================================================================
// PRODUCT MODAL STATE
// ================================================================
let _modal = {
    productId: null,
    basePrice: 0,
    qty: 1,
    selected: {},
    groups: [],
    outOfStock: false
};

function openModal(productId) {
    _modal.productId = productId;
    _modal.qty = 1;
    _modal.selected = {};
    _modal.groups = [];
    _modal.outOfStock = false;

    const modal = document.getElementById('productModal');
    const content = document.getElementById('modalContent');
    if (!modal || !content) return;

    const qEl = document.getElementById('modalQty');
    if (qEl) qEl.textContent = '1';
    const minBtn = document.getElementById('modalQtyMinus');
    if (minBtn) minBtn.disabled = true;
    const opts = document.getElementById('modalOptions');
    if (opts) opts.innerHTML = `
        <div style="padding:30px;text-align:center;color:#999;">
            <div style="font-size:28px;margin-bottom:10px;animation:spin 1s linear infinite;">⟳</div>
            Loading...
        </div>`;

    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch(`${getSiteUrl()}/pages/product-modal-data.php?id=${productId}`)
        .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(data => renderModal(data))
        .catch(err => {
            console.error('Modal load error:', err);
            if (opts) opts.innerHTML = `
                <div style="padding:20px;text-align:center;color:#e53935;">
                    <i class="fas fa-exclamation-triangle"></i> Could not load product. Please try again.
                </div>`;
        });
}

function closeModal() {
    document.getElementById('productModal')?.classList.remove('open');
    document.body.style.overflow = '';
}
function closeModalOutside(e) {
    if (e.target === document.getElementById('productModal')) closeModal();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function renderModal(data) {
    if (!data || data.error) {
        const opts = document.getElementById('modalOptions');
        if (opts) opts.innerHTML = `<div style="padding:20px;color:#e53935;">Product not found.</div>`;
        return;
    }
    const p = data.product;
    _modal.basePrice = parseFloat(p.effective_price) || 0;
    _modal.outOfStock = parseInt(p.stock) <= 0;
    _modal.groups = data.option_groups || [];

    // Hero image
    const heroDiv = document.getElementById('modalHeroImg');
    if (heroDiv) {
        if (p.image) {
            heroDiv.innerHTML = `<img src="${getSiteUrl()}/assets/img/products/${escHtml(p.image)}" alt="${escHtml(p.name)}" style="width:100%;height:100%;object-fit:cover;">`;
        } else {
            const emoji = p.tea_type === 'green' ? '🍃' : p.tea_type === 'black' ? '🫖' : '🍵';
            heroDiv.innerHTML = `<span style="font-size:70px;">${emoji}</span>`;
        }
    }

    // Badges
    let badges = '';
    if (parseInt(p.is_new)) badges += '<span style="background:#23a45a;color:#fff;font-size:10px;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px;margin-right:6px;">New</span>';
    if (p.sale_price) {
        const d = Math.round((1 - p.sale_price / p.price) * 100);
        badges += `<span style="background:var(--primary);color:#fff;font-size:10px;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase;">${d}% Off</span>`;
    }
    const badgesEl = document.getElementById('modalBadges');
    if (badgesEl) badgesEl.innerHTML = badges ? `<div style="margin-bottom:8px;">${badges}</div>` : '';

    // Title & meta
    const titleEl = document.getElementById('modalTitle');
    if (titleEl) titleEl.textContent = p.name;
    const metaEl = document.getElementById('modalMeta');
    if (metaEl) {
        const parts = [p.brand_name, p.cat_name].filter(Boolean);
        metaEl.innerHTML = parts.length ? `<span style="color:var(--text-muted);font-size:13px;">${escHtml(parts.join(' · '))}</span>` : '';
    }
    const descEl = document.getElementById('modalDesc');
    if (descEl) descEl.textContent = p.short_desc || p.description || '';
    const priceEl = document.getElementById('modalPrice');
    if (priceEl) priceEl.textContent = `$${parseFloat(p.effective_price).toFixed(2)}`;
    const oldPriceEl = document.getElementById('modalOldPrice');
    if (oldPriceEl) oldPriceEl.textContent = p.sale_price ? `$${parseFloat(p.price).toFixed(2)}` : '';

    // Discount tiers widget inside modal
    const tiers = data.discount_tiers || [];
    const tierContainer = document.getElementById('modalDiscountTiers');
    if (tierContainer) {
        if (tiers.length > 0) {
            const baseP = parseFloat(p.effective_price) || 0;
            let tiersHtml = `<div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:14px;padding:12px 14px;margin:10px 0;">
                <div style="font-size:12px;font-weight:800;color:#92400e;margin-bottom:8px;">🔥 Buy more, save more!</div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;">`;
            tiers.forEach(t => {
                const discPct  = parseFloat(t.discount_pct);
                const discPrice = (baseP * (1 - discPct / 100)).toFixed(2);
                tiersHtml += `<div style="flex:1;min-width:70px;background:#fff;border:1.5px solid #fde68a;border-radius:10px;padding:8px;text-align:center;">
                    <div style="font-size:10px;font-weight:700;color:#92400e;">Buy ${t.min_qty}+</div>
                    <div style="font-size:14px;font-weight:900;color:#b45309;">${discPct}% Off</div>
                    <div style="font-size:10px;color:#78716c;">$${discPrice}/ea</div>
                </div>`;
            });
            tiersHtml += `</div><div style="font-size:10px;color:#78716c;">Applied to total qty in cart</div></div>`;
            tierContainer.innerHTML = tiersHtml;
            tierContainer.style.display = 'block';
        } else {
            tierContainer.innerHTML = '';
            tierContainer.style.display = 'none';
        }
    }

    // Option groups
    let optHtml = '';
    _modal.groups.forEach(group => {
        const isMulti = parseInt(group.max_select) > 1;
        const isRequired = parseInt(group.is_required) === 1;
        const maxSel = parseInt(group.max_select) || 1;
        const minSel = parseInt(group.min_select) || 0;
        const subtitle = isRequired ? `Required · Select ${minSel === maxSel ? minSel : minSel + '–' + maxSel}` : `Optional · Select up to ${maxSel}`;
        const badgeStyle = isRequired
            ? 'background:#1a1a1a;color:#fff;'
            : 'background:#f0f0f0;color:#666;border:1px solid #ddd;';

        optHtml += `<div class="option-group" id="og_${group.id}">
            <div class="option-group-header">
                <div>
                    <div class="option-group-title">${escHtml(group.name)}</div>
                    <div class="option-group-subtitle">${subtitle}</div>
                </div>
                <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;flex-shrink:0;${badgeStyle}">${isRequired ? 'Required' : 'Optional'}</span>
            </div>`;

        group.items.forEach(item => {
            const pa = parseFloat(item.price_add) || 0;
            const priceStr = pa > 0 ? `+$${pa.toFixed(2)}` : pa < 0 ? `−$${Math.abs(pa).toFixed(2)}` : '';
            const checkShape = isMulti ? 'border-radius:6px;' : 'border-radius:50%;';

            optHtml += `<div class="option-item" id="oi_${group.id}_${item.id}" 
                onclick="toggleOption(${group.id},${item.id},${isMulti?1:0},${maxSel},${pa})">
                <div class="option-item-left">
                    <div class="option-item-check" id="check_${group.id}_${item.id}" style="${checkShape}"></div>
                    <div>
                        <div class="option-item-name">${escHtml(item.name)}</div>
                        ${parseInt(item.is_default) ? '<div style="font-size:11px;color:#23a45a;font-weight:600;">Recommended</div>' : ''}
                    </div>
                </div>
                <span class="option-item-price">${priceStr ? priceStr : '<span style="color:#bbb;font-size:12px;">Free</span>'}</span>
            </div>`;
        });

        optHtml += `</div>`;
    });

    if (!optHtml) {
        optHtml = `<div style="padding:16px 20px 20px;border-top:8px solid var(--bg);font-size:13px;color:var(--text-muted);text-align:center;">
            <i class="fas fa-check-circle" style="color:var(--green);margin-right:6px;"></i> No extra options – ready to add!
        </div>`;
    }

    const optsEl = document.getElementById('modalOptions');
    if (optsEl) optsEl.innerHTML = optHtml;

    // Pre-select defaults
    _modal.groups.forEach(group => {
        group.items.forEach(item => {
            if (parseInt(item.is_default)) {
                toggleOption(group.id, item.id, parseInt(group.max_select) > 1 ? 1 : 0, parseInt(group.max_select) || 1, parseFloat(item.price_add) || 0);
            }
        });
    });

    updateModalTotal();
    updateModalBtn();
}

function toggleOption(groupId, itemId, isMulti, maxSelect, priceAdd) {
    if (!_modal.selected[groupId]) _modal.selected[groupId] = [];
    const sel = _modal.selected[groupId];
    const idx = sel.findIndex(o => o.id === itemId);

    if (isMulti) {
        if (idx > -1) {
            sel.splice(idx, 1);
            setCheckState(groupId, itemId, false, true);
        } else {
            if (sel.length >= maxSelect) {
                const removed = sel.splice(0, 1)[0];
                setCheckState(groupId, removed.id, false, true);
            }
            sel.push({ id: itemId, price: parseFloat(priceAdd) || 0 });
            setCheckState(groupId, itemId, true, true);
        }
    } else {
        sel.forEach(o => setCheckState(groupId, o.id, false, false));
        if (idx > -1) {
            _modal.selected[groupId] = [];
        } else {
            _modal.selected[groupId] = [{ id: itemId, price: parseFloat(priceAdd) || 0 }];
            setCheckState(groupId, itemId, true, false);
        }
    }
    updateModalTotal();
}

function setCheckState(groupId, itemId, checked, isMulti) {
    const el = document.getElementById(`check_${groupId}_${itemId}`);
    const row = document.getElementById(`oi_${groupId}_${itemId}`);
    if (!el) return;
    if (checked) {
        el.classList.add('checked');
        row?.classList.add('selected');
        el.innerHTML = isMulti
            ? '<i class="fas fa-check" style="font-size:11px;color:#fff;"></i>'
            : '<div style="width:10px;height:10px;border-radius:50%;background:#fff;"></div>';
    } else {
        el.classList.remove('checked');
        row?.classList.remove('selected');
        el.innerHTML = '';
    }
}

function getOptionsExtra() {
    let extra = 0;
    Object.values(_modal.selected).forEach(opts => opts.forEach(o => extra += (o.price || 0)));
    return extra;
}

function updateModalTotal() {
    const total = (_modal.basePrice + getOptionsExtra()) * _modal.qty;
    const el = document.getElementById('modalBtnTotal');
    const lbl = document.getElementById('modalBtnLabel');
    if (el) el.textContent = `$${total.toFixed(2)}`;
    if (lbl && !_modal.outOfStock) lbl.textContent = _modal.qty > 1 ? `Add ${_modal.qty} items` : 'Add to cart';
}

function updateModalBtn() {
    const btn = document.getElementById('modalAddBtn');
    if (!btn) return;
    if (_modal.outOfStock) {
        btn.disabled = true;
        const lbl = document.getElementById('modalBtnLabel');
        if (lbl) lbl.textContent = 'Out of Stock';
        const tot = document.getElementById('modalBtnTotal');
        if (tot) tot.textContent = '';
    } else {
        btn.disabled = false;
    }
}

function modalQtyChange(delta) {
    _modal.qty = Math.max(1, _modal.qty + delta);
    const qEl = document.getElementById('modalQty');
    if (qEl) qEl.textContent = _modal.qty;
    const minBtn = document.getElementById('modalQtyMinus');
    if (minBtn) minBtn.disabled = _modal.qty <= 1;
    updateModalTotal();
}

async function modalAddToCart() {
    if (!_modal.productId || _modal.outOfStock) return;

    // Validate required groups
    for (const group of _modal.groups) {
        if (parseInt(group.is_required) === 1) {
            const sel = _modal.selected[group.id] || [];
            const minSel = parseInt(group.min_select) || 1;
            if (sel.length < minSel) {
                const ogEl = document.getElementById(`og_${group.id}`);
                if (ogEl) {
                    ogEl.style.borderLeft = '3px solid var(--primary)';
                    ogEl.style.background = '#fff8f8';
                    ogEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => { ogEl.style.borderLeft = ''; ogEl.style.background = ''; }, 2000);
                }
                showToast(`⚠️ Please select: ${group.name}`, 'error');
                return;
            }
        }
    }

    const optionsText = [];
    _modal.groups.forEach(group => {
        (_modal.selected[group.id] || []).forEach(o => {
            const item = group.items.find(i => String(i.id) === String(o.id));
            if (item) optionsText.push(`${group.name}: ${item.name}`);
        });
    });

    const btn = document.getElementById('modalAddBtn');
    const lbl = document.getElementById('modalBtnLabel');
    if (btn) btn.disabled = true;
    if (lbl) lbl.textContent = 'Adding...';

    try {
        const fd = new FormData();
        fd.append('action', 'add');
        fd.append('product_id', _modal.productId);
        fd.append('qty', _modal.qty);
        fd.append('options', JSON.stringify(_modal.selected));
        fd.append('options_text', optionsText.join('; '));

        const res = await fetch(getCartActionUrl(), { method: 'POST', body: fd });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        if (data.success) {
            updateCartBadge(data.count);
            closeModal();
            showToast('🛒 Added to cart!');
        } else {
            showToast('❌ ' + (data.msg || 'Could not add to cart'), 'error');
            if (btn) btn.disabled = false;
            if (lbl) lbl.textContent = _modal.qty > 1 ? `Add ${_modal.qty} items` : 'Add to cart';
        }
    } catch (e) {
        console.error('Cart add error:', e);
        showToast('❌ Network error. Try again.', 'error');
        if (btn) btn.disabled = false;
        if (lbl) lbl.textContent = _modal.qty > 1 ? `Add ${_modal.qty} items` : 'Add to cart';
    }
    updateModalTotal();
}

// ===== QUICK ADD (direct, no modal) — with realtime badge =====
async function addToCart(productId, qty = 1, variantId = null) {
    // Show loading on any quick-add button that triggered this
    try {
        const fd = new FormData();
        fd.append('action', 'add');
        fd.append('product_id', productId);
        fd.append('qty', qty);
        if (variantId) fd.append('variant_id', variantId);
        const res = await fetch(getCartActionUrl(), { method: 'POST', body: fd });
        if (!res.ok) throw new Error(res.status);
        const data = await res.json();
        if (data.success) {
            updateCartBadge(data.count);
            showToast('🛒 Added to cart!');
        } else {
            showToast('❌ ' + (data.msg || 'Could not add to cart'), 'error');
        }
    } catch (e) {
        showToast('❌ Could not add to cart', 'error');
    }
}

// ===== WISHLIST TOGGLE =====
async function toggleWishlist(productId, btn) {
    try {
        const fd = new FormData();
        fd.append('product_id', productId);
        fd.append('action', 'toggle');
        const res = await fetch(getWishlistActionUrl(), { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error(res.status);
        const data = await res.json();
        if (data.success) {
            if (btn) {
                btn.innerHTML = data.wishlisted ? '<i class="fas fa-heart" style="color:var(--primary)"></i>' : '<i class="far fa-heart"></i>';
            }
            document.querySelectorAll('.wishlist-count').forEach(el => {
                el.textContent = data.count || 0;
                el.style.display = (data.count || 0) > 0 ? 'inline-flex' : 'none';
            });
            showToast(data.wishlisted ? '❤️ Added to wishlist' : 'Removed from wishlist');
        }
    } catch (e) {
        showToast('Please sign in to use wishlist', 'error');
    }
}

// ===== CHECKOUT PAYMENT SELECT =====
function selectCheckoutPaymentCard(el) {
    document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
    document.querySelectorAll('.pm-radio').forEach(r => { r.classList.remove('active'); r.innerHTML = ''; });
    el.classList.add('active');
    const val = el.querySelector('input[type=radio]')?.value;
    if (el.querySelector('input[type=radio]')) el.querySelector('input[type=radio]').checked = true;
    const radioEl = el.querySelector('.pm-radio');
    if (radioEl) {
        radioEl.classList.add('active');
        radioEl.innerHTML = '<div style="width:10px;height:10px;border-radius:50%;background:var(--primary);"></div>';
    }
    const ks = document.getElementById('khqrSection');
    if (ks) ks.classList.toggle('visible', val === 'khqr');
}

// ===== PRODUCT DETAIL PAGE =====
function changeQty(dir) {
    const inp = document.getElementById('qty');
    if (!inp) return;
    let v = parseInt(inp.value) || 1;
    v = dir === '+' ? v + 1 : Math.max(1, v - 1);
    inp.value = v;
}

// ===== DOM READY =====
document.addEventListener('DOMContentLoaded', () => {
    // Variant select on product detail page
    document.querySelectorAll('.variant-option').forEach(opt => {
        opt.addEventListener('click', function () {
            this.closest('.variant-options')?.querySelectorAll('.variant-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            const vid = this.dataset.variantId;
            const vp = this.dataset.price;
            if (vid) { const hid = document.getElementById('selected-variant'); if (hid) hid.value = vid; }
            if (vp) { const dp = document.getElementById('displayPrice'); if (dp) dp.textContent = '$' + parseFloat(vp).toFixed(2); }
        });
    });

    // Price range filter
    const pr = document.getElementById('priceRange');
    const pd = document.getElementById('priceDisplay');
    if (pr && pd) pr.addEventListener('input', () => { pd.textContent = '$' + pr.value; });

    // Cart qty controls (cart page) — realtime badge update
    document.querySelectorAll('.cart-qty-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id;
            const delta = parseInt(this.dataset.delta);
            const countEl = this.parentElement?.querySelector('.cart-qty-num');
            if (!countEl) return;
            let newQty = Math.max(1, parseInt(countEl.textContent) + delta);
            countEl.textContent = newQty;
            const fd = new FormData();
            fd.append('action', 'update');
            fd.append('id', id);
            fd.append('qty', newQty);
            try {
                const res = await fetch(getCartActionUrl(), { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) updateCartBadge(data.count);
            } catch (e) {}
        });
    });

    // Quick-add buttons on product cards (btn-quick-add class)
    // These call openModal so modal handles it — no extra wiring needed

    // Sync cart count on page load (get current count from server)
    fetch(getCartActionUrl(), { method: 'POST', body: (() => { const f = new FormData(); f.append('action','count'); return f; })() })
        .then(r => r.json())
        .then(d => { if (d.success !== undefined) updateCartBadge(d.count); })
        .catch(() => {});
});

// ===== BACK TO TOP =====
window.addEventListener('scroll', () => {
    let btn = document.getElementById('backToTop');
    if (!btn) {
        btn = document.createElement('button');
        btn.id = 'backToTop';
        btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        btn.style.cssText = 'position:fixed;bottom:80px;right:24px;width:44px;height:44px;border-radius:50%;background:var(--primary);color:#fff;border:none;cursor:pointer;font-size:16px;box-shadow:0 4px 16px rgba(0,0,0,.2);transition:opacity .3s,transform .3s;z-index:100;display:flex;align-items:center;justify-content:center;';
        btn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
        document.body.appendChild(btn);
    }
    btn.style.opacity = window.scrollY > 400 ? '1' : '0';
    btn.style.pointerEvents = window.scrollY > 400 ? 'auto' : 'none';
});

// CSS for spin animation
const _style = document.createElement('style');
_style.textContent = `@keyframes spin{to{transform:rotate(360deg)}} .option-item.selected{background:var(--bg);}`;
document.head.appendChild(_style);
