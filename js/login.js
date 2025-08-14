// js/login.js: Enhanced Login JS with Loading Spinner
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    if (!username || !password) {
        alert('아이디와 비밀번호를 입력하세요.');
        e.preventDefault();
        return;
    }
    // Show spinner
    document.getElementById('loginText').classList.add('d-none');
    document.getElementById('loginSpinner').classList.remove('d-none');
    document.getElementById('loginBtn').disabled = true;
    // Form submits to PHP
});
