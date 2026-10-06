<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Customer & Vehicle Registration (register.php)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? "admin/dashboard.php" : "customer/dashboard.php"));
    exit;
}

$registerError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect personal info
    $fullName        = trim($_POST['full_name'] ?? '');
    $email           = trim(strtolower($_POST['email'] ?? ''));
    $mobile          = trim($_POST['mobile'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Collect vehicle info
    $regNumber   = strtoupper(trim(preg_replace('/\s+/', '', $_POST['vehicle_number'] ?? '')));
    $brand       = trim($_POST['brand'] ?? '');
    $model       = trim($_POST['model'] ?? '');
    $year        = intval($_POST['year'] ?? 0);
    $fuelType    = trim($_POST['fuel_type'] ?? 'Petrol');

    $currentYear = intval(date('Y'));

    // Server-side validations
    if (empty($fullName) || strlen($fullName) < 3) {
        $registerError = "Please enter your full name (minimum 3 characters).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = "Please enter a valid email address.";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $registerError = "Please enter a valid 10-digit Indian mobile number starting with 6-9.";
    } elseif (strlen($password) < 8) {
        $registerError = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirmPassword) {
        $registerError = "Passwords do not match. Please verify.";
    } elseif (!preg_match('/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/', $regNumber)) {
        $registerError = "Vehicle registration format is invalid. Example: TN38AB1234 or DL01A1234.";
    } elseif (empty($brand) || empty($model)) {
        $registerError = "Please specify both your vehicle brand and model.";
    } elseif ($year < 1995 || $year > $currentYear + 1) {
        $registerError = "Manufacturing year must be between 1995 and " . ($currentYear + 1) . ".";
    } else {
        try {
            // Check for existing email in users table
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $registerError = "An account with this email address already exists. Please login instead.";
            } else {
                // Check if vehicle registration number already exists
                $stmtVeh = $pdo->prepare("SELECT vehicle_id FROM vehicles WHERE registration_number = ? LIMIT 1");
                $stmtVeh->execute([$regNumber]);
                if ($stmtVeh->fetch()) {
                    $registerError = "A vehicle with registration number $regNumber is already registered.";
                } else {
                    // Generate unique IDs
                    $userId = 'USR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                    $custId = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                    $vehId  = 'VEH-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

                    // Hash password with bcrypt
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                    // Begin transaction for multi-table atomicity
                    $pdo->beginTransaction();

                    // 1. Insert into users
                    $stmtUser = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role, phone) VALUES (?, ?, ?, ?, 'customer', ?)");
                    $stmtUser->execute([$userId, $fullName, $email, $hashedPassword, $mobile]);

                    // 2. Insert into customers
                    $stmtCust = $pdo->prepare("INSERT INTO customers (customer_id, user_id, name, email, phone, address, status) VALUES (?, ?, ?, ?, ?, 'Registered Online Customer', 'active')");
                    $stmtCust->execute([$custId, $userId, $fullName, $email, $mobile]);

                    // 3. Insert into vehicles
                    $stmtVehIns = $pdo->prepare("INSERT INTO vehicles (vehicle_id, customer_id, registration_number, brand, model, year, fuel_type, last_service, next_service) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
                    $stmtVehIns->execute([$vehId, $custId, $regNumber, $brand, $model, $year, $fuelType]);

                    $pdo->commit();

                    // Automatically start session for immediate access
                    $_SESSION['user_id']     = $userId;
                    $_SESSION['role']        = 'customer';
                    $_SESSION['email']       = $email;
                    $_SESSION['name']        = $fullName;
                    $_SESSION['phone']       = $mobile;
                    $_SESSION['customer_id'] = $custId;

                    header("Location: customer/dashboard.php?registered=1");
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Registration error: " . $e->getMessage());
            $registerError = "Something went wrong during registration. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer & Vehicle Registration – AutoCare</title>
  
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
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="text-white-50 small d-none d-sm-inline">Already have an account?</span>
        <a href="login.php" class="btn btn-outline-light btn-sm">
          <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
        </a>
      </div>
    </div>
  </nav>

  <!-- Registration Area -->
  <main class="auth-wrapper">
    <div class="auth-card row g-0" style="max-width: 900px;">
      
      <div class="col-12 p-4 p-md-5">
        <div class="mb-4 text-center">
          <span class="badge bg-primary-subtle text-primary border px-3 py-1 mb-2 rounded-pill">Customer Portal Onboarding</span>
          <h2 class="fw-bold mb-1">Create Your AutoCare Account</h2>
          <p class="text-muted small">Register yourself and your primary vehicle to schedule maintenance and track services online.</p>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($registerError)): ?>
          <div id="registerAlert" class="alert alert-danger py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($registerError) ?>
          </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="register.php" novalidate>
          
          <!-- Step 1: Customer Personal Details -->
          <div class="mb-4">
            <h6 class="fw-bold text-primary text-uppercase letter-spacing-1 border-bottom pb-2 mb-3">
              <i class="bi bi-person-fill me-1"></i> 1. Personal & Contact Details
            </h6>
            
            <div class="row g-3">
              <div class="col-md-6">
                <label for="regFullName" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" id="regFullName" class="form-control" placeholder="e.g. Suresh Kumar" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
              </div>

              <div class="col-md-6">
                <label for="regEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" id="regEmail" class="form-control" placeholder="suresh@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
              </div>

              <div class="col-md-4">
                <label for="regMobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">+91</span>
                  <input type="tel" name="mobile" id="regMobile" class="form-control" placeholder="9876543210" maxlength="10" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" required>
                </div>
              </div>

              <div class="col-md-4">
                <label for="regPassword" class="form-label">Create Password <span class="text-danger">*</span></label>
                <input type="password" name="password" id="regPassword" class="form-control" placeholder="Min. 8 characters" required>
              </div>

              <div class="col-md-4">
                <label for="regConfirmPassword" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" placeholder="Re-enter password" required>
              </div>
            </div>
          </div>

          <!-- Step 2: Primary Vehicle Information -->
          <div class="mb-4">
            <h6 class="fw-bold text-primary text-uppercase letter-spacing-1 border-bottom pb-2 mb-3">
              <i class="bi bi-car-front-fill me-1"></i> 2. Vehicle Details
            </h6>

            <div class="row g-3">
              <div class="col-md-4">
                <label for="regVehicleNumber" class="form-label">Registration Number <span class="text-danger">*</span></label>
                <input type="text" name="vehicle_number" id="regVehicleNumber" class="form-control font-monospace text-uppercase" placeholder="e.g. TN38AB1234" value="<?= htmlspecialchars($_POST['vehicle_number'] ?? '') ?>" required>
                <small class="text-muted">Standard Indian format</small>
              </div>

              <div class="col-md-4">
                <label for="regVehicleBrand" class="form-label">Brand / Make <span class="text-danger">*</span></label>
                <input type="text" name="brand" id="regVehicleBrand" class="form-control" placeholder="e.g. Maruti Suzuki, Tata, Hyundai" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" required>
              </div>

              <div class="col-md-4">
                <label for="regVehicleModel" class="form-label">Model & Variant <span class="text-danger">*</span></label>
                <input type="text" name="model" id="regVehicleModel" class="form-control" placeholder="e.g. Swift VXI, Nexon XZ+" value="<?= htmlspecialchars($_POST['model'] ?? '') ?>" required>
              </div>

              <div class="col-md-6">
                <label for="regVehicleYear" class="form-label">Manufacturing Year <span class="text-danger">*</span></label>
                <input type="number" name="year" id="regVehicleYear" class="form-control" min="1995" max="<?= date('Y') + 1 ?>" placeholder="e.g. 2022" value="<?= htmlspecialchars($_POST['year'] ?? '2023') ?>" required>
              </div>

              <div class="col-md-6">
                <label for="regFuelType" class="form-label">Fuel Type <span class="text-danger">*</span></label>
                <select name="fuel_type" id="regFuelType" class="form-select" required>
                  <?php $selectedFuel = $_POST['fuel_type'] ?? 'Petrol'; ?>
                  <option value="Petrol" <?= $selectedFuel === 'Petrol' ? 'selected' : '' ?>>Petrol</option>
                  <option value="Diesel" <?= $selectedFuel === 'Diesel' ? 'selected' : '' ?>>Diesel</option>
                  <option value="Electric" <?= $selectedFuel === 'Electric' ? 'selected' : '' ?>>Electric (EV)</option>
                  <option value="CNG" <?= $selectedFuel === 'CNG' ? 'selected' : '' ?>>CNG / Bi-Fuel</option>
                  <option value="Hybrid" <?= $selectedFuel === 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="d-grid mt-4">
            <button type="submit" class="btn btn-primary btn-lg fw-bold py-3 rounded-3 shadow">
              <i class="bi bi-check2-circle me-2"></i> Complete Registration & Open Dashboard
            </button>
          </div>

          <p class="text-center text-muted small mt-3 mb-0">
            By creating an account, you agree to AutoCare terms of service and simulated workshop privacy practices.
          </p>

        </form>
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
</body>
</html>
