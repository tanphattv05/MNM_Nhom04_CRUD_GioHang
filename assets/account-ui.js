document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-toggle-password]');
    if (!button) return;
    const input = document.getElementById(button.dataset.togglePassword);
    input.type = input.type === 'password' ? 'text' : 'password';
    button.textContent = input.type === 'password' ? 'Hiện' : 'Ẩn';
    button.setAttribute('aria-pressed', String(input.type === 'text'));
});
