<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DentalPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/css/all.min.css">
    <link href="assets/vendor/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/css/sweetalert2.min.css">
    <style>
        :root {
            --primary-navy: #0A1628;
            --primary-teal: #00BFA6;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .card-header {
            background-color: var(--primary-navy);
            color: white;
            text-align: center;
            padding: 40px 20px;
            border: none;
        }
        .logo i {
            font-size: 40px;
            color: var(--primary-teal);
            margin-bottom: 10px;
        }
        .logo h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            margin: 0;
        }
        .logo span {
            color: var(--primary-teal);
        }
        .btn-teal {
            background-color: var(--primary-teal);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
        }
        .btn-teal:hover {
            background-color: #00a892;
            color: white;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 1px solid #e1e7ed;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(0, 191, 166, 0.1);
            border-color: var(--primary-teal);
        }
    </style>
</head>
<body>

    <div class="card login-card">
        <div class="card-header">
            <div class="logo">
                <i class="fas fa-tooth"></i>
                <h2>Dental<span>POS</span></h2>
            </div>
            <p class="mb-0 mt-2 opacity-75" data-i18n="login.subtitle">Clinical Stock & POS Management</p>
        </div>
        <div class="card-body p-4">
            <form id="loginForm">
                <div class="mb-3">
                    <label class="form-label small fw-bold" data-i18n="login.email">Email Address</label>
                    <input type="email" name="email" id="emailInput" class="form-control" placeholder="admin@dentalpos.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold" data-i18n="login.password">Password</label>
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label small" for="remember" data-i18n="login.remember">Remember me</label>
                    </div>
                    <a href="#" class="small text-teal text-decoration-none" data-i18n="login.forgot">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-teal" data-i18n="login.btn_signin">SIGN IN</button>
            </form>
            <div class="text-center mt-4">
                <p class="small text-muted" data-i18n="login.demo">Demo Credentials: admin@dentalpos.com / admin123</p>
            </div>
            
            <!-- Language Switcher -->
            <div class="text-center mt-3 border-top pt-3">
                <button onclick="setLang('en')" class="btn btn-sm btn-outline-secondary rounded-circle px-2 py-1 mx-1 border-0" title="English">
                    <img src="assets/vendor/img/flags/us.png" width="20" alt="EN">
                </button>
                <button onclick="setLang('fr')" class="btn btn-sm btn-outline-secondary rounded-circle px-2 py-1 mx-1 border-0" title="Français">
                    <img src="assets/vendor/img/flags/fr.png" width="20" alt="FR">
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/locales.js"></script>
    <script src="assets/vendor/js/sweetalert2.all.min.js"></script>
    <script>
        // Simple I18n for Login Page
        function t(key) {
            const lang = localStorage.getItem('app_language') || 'en';
            return locales[lang][key] || key;
        }

        function translate() {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                el.innerText = t(key);
            });
            // Update placeholders
            document.getElementById('emailInput').placeholder = t('login.email_ph');
            document.getElementById('passwordInput').placeholder = t('login.password_ph');
            document.title = t('login.title');
        }

        function setLang(lang) {
            localStorage.setItem('app_language', lang);
            translate();
        }

        // Run on load
        translate();

        document.getElementById('loginForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = 'index.php';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: t('login.failed_title'),
                        text: result.error || t('login.failed_text'),
                        confirmButtonColor: '#00BFA6'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
            }
        };
    </script>
</body>
</html>
