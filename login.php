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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
            <p class="mb-0 mt-2 opacity-75">Clinical Stock & POS Management</p>
        </div>
        <div class="card-body p-4">
            <form id="loginForm">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@dentalpos.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>
                    <a href="#" class="small text-teal text-decoration-none">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-teal">SIGN IN</button>
            </form>
            <div class="text-center mt-4">
                <p class="small text-muted">Demo Credentials: admin@dentalpos.com / admin123</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
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
                        title: 'Login Failed',
                        text: result.error || 'Invalid credentials',
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
