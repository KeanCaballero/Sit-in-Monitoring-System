document.addEventListener('DOMContentLoaded', function() {
  // Login Form
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const idNumber = document.getElementById('idNumber').value.trim();
      const password = document.getElementById('password').value.trim();
      
      if (!idNumber || !password) {
        alert('Please fill in all fields.');
        return;
      }
      
      // Connect to PHP: login.php
      alert('Logging in with ID: ' + idNumber);
      // fetch('login.php', { method: 'POST', body: new FormData(this) });
    });
  }

  // Register Form
  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const fields = ['idNumber','lastName','firstName','courseLevel','password','repeatPassword','email','address'];
      for (const f of fields) {
        if (!document.getElementById(f).value.trim()) {
          alert('Please fill in all fields.');
          document.getElementById(f).focus();
          return;
        }
      }
      
      const pw = document.getElementById('password').value;
      const rpw = document.getElementById('repeatPassword').value;
      if (pw !== rpw) {
        alert('Passwords do not match.');
        return;
      }
      
      // Connect to PHP: register.php
      alert('Registration submitted successfully!');
      // fetch('register.php', { method: 'POST', body: new FormData(this) });
    });
  }
});
