function resetLoading() {
    document.querySelectorAll('form[aria-busy="true"]').forEach(form => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('.is-loading').forEach(button => {
            button.classList.remove('is-loading');
            button.removeAttribute('aria-disabled');
        });
    });
}
function enhanceUi() {
    document.querySelectorAll('input[type="number"]').forEach(input => {
        if (!/^(quantity|new_quantity|items\[)/.test(input.name) || input.closest('.quantity-stepper')) return;
        const wrapper = document.createElement('span');
        wrapper.className = 'quantity-stepper';
        input.before(wrapper);
        const minus = document.createElement('button');
        const plus = document.createElement('button');
        minus.type = plus.type = 'button';
        minus.textContent = '−'; plus.textContent = '+';
        minus.setAttribute('aria-label', 'Giảm số lượng');
        plus.setAttribute('aria-label', 'Tăng số lượng');
        wrapper.append(minus, input, plus);
        const sync = () => {
            const value = Number(input.value);
            minus.disabled = !Number.isFinite(value) || value <= Number(input.min || 1);
            plus.disabled = !Number.isFinite(value) || (input.max !== '' && value >= Number(input.max));
        };
        [minus, plus].forEach((button, index) => button.addEventListener('click', () => {
            if (!input.validity.badInput && (!input.value || !Number.isFinite(input.valueAsNumber))) input.value = input.min || '1';
            if (index) input.stepUp(); else input.stepDown();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }));
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
        sync();
    });
    document.querySelectorAll('input[type="password"]').forEach(input => {
        if (input.closest('.password-row') || !input.id) return;
        const row = document.createElement('div'); row.className = 'password-row';
        input.before(row); row.append(input);
        const button = document.createElement('button');
        button.type = 'button'; button.textContent = 'Hiện';
        button.dataset.togglePassword = input.id; button.setAttribute('aria-pressed', 'false');
        row.append(button);
    });
    document.querySelectorAll('.read-table').forEach(table => {
        table.querySelectorAll('thead th').forEach((header, index) => {
            table.querySelectorAll('tbody tr').forEach(row => {
                const cell = row.children[index];
                if (cell && !cell.hasAttribute('colspan') && !cell.dataset.label) cell.dataset.label = header.textContent.trim();
            });
        });
    });
}
document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-toggle-password]');
    if (toggle) {
        const input = document.getElementById(toggle.dataset.togglePassword);
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        toggle.textContent = input.type === 'password' ? 'Hiện' : 'Ẩn';
        toggle.setAttribute('aria-pressed', String(input.type === 'text'));
    }
    const open = event.target.closest('[data-open-book]');
    if (open) { document.getElementById(open.dataset.openBook)?.showModal(); }
    const close = event.target.closest('[data-close-dialog]');
    if (close) { close.closest('dialog')?.close(); }
    if (event.target instanceof HTMLDialogElement) {
        const box = event.target.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) event.target.close();
    }
});
document.addEventListener('change', event => {
    const form = event.target.closest('.cart-controls');
    if (!form || event.target.name !== 'quantity') return;
    let note = form.parentElement.querySelector('.quantity-feedback');
    if (!note) { note = document.createElement('p'); note.className = 'quantity-feedback'; note.setAttribute('role', 'status'); form.after(note); }
    note.textContent = 'Nhấn Cập nhật để lưu số lượng và tính lại tổng tiền.';
});
document.addEventListener('submit', event => {
    const form = event.target;
    if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
    if (event.defaultPrevented) return;
    form.setAttribute('aria-busy', 'true');
    const button = event.submitter;
    if (button) { button.classList.add('is-loading'); button.setAttribute('aria-disabled', 'true'); }
    // Keep submitter name/value enabled: update/remove rely on that request value.
});
window.addEventListener('pageshow', () => { resetLoading(); enhanceUi(); });
document.addEventListener('turbo:load', enhanceUi);
document.addEventListener('turbo:before-cache', resetLoading);
<<<<<<< HEAD
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhanceUi); else enhanceUi();
// Native disclosure supports Enter/Space and remains usable without JavaScript.
function closeAccountMenus(except = null) {
    document.querySelectorAll('.account-menu[open]').forEach(menu => {
        if (menu !== except) menu.open = false;
    });
}
document.addEventListener('click', event => {
    const menu = event.target.closest('.account-menu');
    closeAccountMenus(menu);
});
document.addEventListener('focusin', event => closeAccountMenus(event.target.closest('.account-menu')));
document.addEventListener('keydown', event => {
    const menu = event.target.closest('.account-menu');
    if (event.key === 'Escape') {
        const opened = menu?.open ? menu : document.querySelector('.account-menu[open]');
        if (opened) { event.preventDefault(); opened.open = false; opened.querySelector('summary').focus(); }
        return;
    }
    if (!menu || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    const items = [...menu.querySelectorAll('.account-dropdown a, .account-dropdown button')];
    if (!items.length) return;
    event.preventDefault();
    menu.open = true;
    const index = items.indexOf(document.activeElement);
    let next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : event.key === 'ArrowDown' ? (index + 1) % items.length : (index <= 0 ? items.length : index) - 1;
    items[next].focus();
});
document.addEventListener('turbo:before-cache', () => closeAccountMenus());
=======
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhanceUi); else enhanceUi();
>>>>>>> 8c84cdb6e351eccfe79cd86068c80e673e18764d
