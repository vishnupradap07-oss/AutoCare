<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Account Login (login.php)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';

// If user already logged in, redirect based on role
if (is_logged_in()) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: customer/dashboard.php");
    }
    exit;
}

$loginError = '';
$loginSuccess = '';

// Check query messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'logged_out') {
        $loginSuccess = 'You have logged out successfully.';
    } elseif ($_GET['msg'] === 'registered') {
        $loginSuccess = 'Account and vehicle registered successfully! Please log in with your credentials.';
    }
}

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $loginError = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loginError = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $passwordMatch = false;
                // Verify bcrypt hashed password
                if (password_verify($password, $user['password'])) {
                    $passwordMatch = true;
                } elseif ($password === $user['password']) {
                    // Transparent migration if password was previously plain text
                    $passwordMatch = true;
                    $rehashed = password_hash($password, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $upd->execute([$rehashed, $user['user_id']]);
                }

                if ($passwordMatch) {
                    // Populate session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role']    = $user['role'];
                    $_SESSION['email']   = $user['email'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['phone']   = $user['phone'] ?? '';

                    // For customers, cache customer_id
                    if ($user['role'] === 'customer') {
                        $custStmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1");
                        $custStmt->execute([$user['user_id']]);
                        $_SESSION['customer_id'] = $custStmt->fetchColumn() ?: '';
                        header("Location: customer/dashboard.php");
                    } else {
                        header("Location: admin/dashboard.php");
                    }
                    exit;
                } else {
                    $loginError = 'Invalid email or password. Please check your credentials or click a demo account below.';
                }
            } else {
                $loginError = 'Invalid email or password. Please check your credentials or click a demo account below.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $loginError = 'Something went wrong during sign-in. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Login – AutoCare</title>
  
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Custom Styles -->
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <!-- Minimal Brand Navigation -->
  <nav class="navbar navbar-expand navbar-dark autocare-navbar">
    <div class="container">
      <a class="navbar-brand" href="index.php">
        <span class="brand-badge"><i class="bi bi-shield-shaded"></i></span>
        <span>Auto<span class="text-brand-accent">Care</span></span>
      </a>
      <div class="ms-auto">
        <a href="index.php" class="btn btn-outline-light btn-sm">
          <i class="bi bi-house me-1"></i> Back to Home
        </a>
      </div>
    </div>
  </nav>

  <!-- Login Area -->
  <main class="auth-wrapper">
    <div class="auth-card row g-0">
      
      <!-- Left Side: Login Form -->
      <div class="col-lg-6 p-4 p-md-5 d-flex flex-column justify-content-center">
        <div class="mb-4">
          <span class="badge bg-primary-subtle text-primary border px-3 py-1 mb-2 rounded-pill">Secure Authentication</span>
          <h2 class="fw-bold mb-1">Welcome Back</h2>
          <p class="text-muted small">Sign in to manage your vehicles, bookings and workshop status.</p>
        </div>

        <!-- Alert Notification Messages -->
        <?php if (!empty($loginError)): ?>
          <div id="loginAlert" class="alert alert-danger py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($loginError) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($loginSuccess)): ?>
          <div class="alert alert-success py-2 small" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($loginSuccess) ?>
          </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php" novalidate>
          <div class="mb-3">
            <label for="loginEmail" class="form-label">Email Address</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" id="loginEmail" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label for="loginPassword" class="form-label mb-0">Password</label>
              <a href="javascript:void(0)" onclick="alert('Demo Credentials: Use customer123 for Customer, or admin123 for Admin.')" class="small text-muted">Forgot password?</a>
            </div>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Enter password" required>
            </div>
          </div>

          <div class="mb-4 form-check">
            <input type="checkbox" class="form-check-input" id="rememberMe" checked>
            <label class="form-check-label small text-muted" for="rememberMe">Remember my session</label>
          </div>

          <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-3 shadow">
              <i class="bi bi-box-arrow-in-right me-2"></i> Login to Portal
            </button>
          </div>

          <div class="text-center small text-muted">
            Don't have an account? <a href="register.php" class="fw-semibold">Create an Account</a>
          </div>
        </form>
      </div>

      <!-- Right Side: Demo Credentials Helper Banner -->
      <div class="col-lg-6 auth-side-banner">
        <div>
          <div class="d-flex align-items-center gap-2 mb-3">
            <div class="brand-badge"><i class="bi bi-shield-lock-fill"></i></div>
            <span class="fw-bold fs-5 text-white">Academic Demo Sandbox</span>
          </div>
          <h4 class="fw-bold text-white mb-2">Stage 2 Live Database Roles</h4>
          <p class="text-white-50 small mb-4">
            Click on any credential badge below to instantly auto-fill credentials and test role-based MySQL authentication.
          </p>

          <!-- Customer Demo Card -->
          <div class="demo-account-box mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-primary text-white"><i class="bi bi-person me-1"></i> CUSTOMER ROLE</span>
              <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill small" onclick="fillDemo('customer')">
                Auto-fill
              </button>
            </div>
            <div class="small font-monospace text-white-50">
              Email: <span class="text-white">customer@autocare.com</span><br>
              Password: <span class="text-white">customer123</span>
            </div>
            <small class="text-info mt-1 d-block">Redirects to Customer Dashboard</small>
          </div>

          <!-- Admin Demo Card -->
          <div class="demo-account-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-warning text-dark"><i class="bi bi-shield-shaded me-1"></i> ADMIN ROLE</span>
              <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill small" onclick="fillDemo('admin')">
                Auto-fill
              </button>
            </div>
            <div class="small font-monospace text-white-50">
              Email: <span class="text-white">admin@autocare.com</span><br>
              Password: <span class="text-white">admin123</span>
            </div>
            <small class="text-warning mt-1 d-block">Redirects to Admin Management Dashboard</small>
          </div>
        </div>

        <div class="pt-4 border-top border-white border-opacity-10 text-white-50 small">
          <i class="bi bi-shield-check me-1"></i> Protected by PHP Sessions & Bcrypt password_hash verification with MySQL PDO.
        </div>
      </div>

    </div>
  </main>

  <!-- Public Footer -->
  <footer class="autocare-footer py-3 mt-0">
    <div class="container text-center small text-muted">
      &copy; 2026 AutoCare Service Management System. Developed for Academic Project.
    </div>
  </footer>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Quick Autofill for Demo Roles -->
  <script>
    function fillDemo(role) {
      const emailInput = document.getElementById("loginEmail");
      const passwordInput = document.getElementById("loginPassword");
      if (!emailInput || !passwordInput) return;

      if (role === "customer") {
        emailInput.value = "customer@autocare.com";
        passwordInput.value = "customer123";
      } else if (role === "admin") {
        emailInput.value = "admin@autocare.com";
        passwordInput.value = "admin123";
      }
    }
  </script>
</body>
</html>
