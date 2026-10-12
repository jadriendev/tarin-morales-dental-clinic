const passwordInput = document.getElementById('dentist_password');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', function () {

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            togglePassword.textContent = 'visibility_off';
        } else {
            passwordInput.type = 'password';
            togglePassword.textContent = 'visibility';
        }

    });