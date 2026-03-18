document.addEventListener('DOMContentLoaded', function() {
    // Password strength
    const pw = document.getElementById('password');
    const bars = ['s1','s2','s3','s4'].map(id => document.getElementById(id));
    const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
    
    if (pw && bars.length === 4) {
        pw.addEventListener('input', () => {
            const v = pw.value;
            let score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            bars.forEach((b, i) => {
                b.style.background = i < score ? colors[score-1] : '#e5e7eb';
            });
        });
    }

    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Welcome, ' + data.user.name + '!');
                    window.location.href = 'dashboard.html';
                } else {
                    alert(data.message);
                }
            })
            .catch(err => alert('Server error. Check XAMPP.'));
        });
    }

    // Register form
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const password = document.getElementById('password').value;
            const repeatPassword = document.getElementById('repeatPassword').value;
            
            if (password !== repeatPassword) {
                alert('Passwords do not match!');
                return;
            }
            
            const formData = new FormData(this);
            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Account created! Please login.');
                    window.location.href = 'index.html';
                } else {
                    alert(data.message);
                }
            })
            .catch(err => alert('Server error. Check XAMPP.'));
        });
    }
});
