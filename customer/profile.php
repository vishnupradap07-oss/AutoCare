<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Customer Profile & Settings (customer/profile.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

require_auth('customer', '../');

$currentUser = get_current_user_data();
$customerId = get_or_set_customer_id($pdo, $currentUser['user_id']);

$profSuccess = '';
$profError = '';
$passSuccess = '';
$passError = '';

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name) || strlen($name) < 3) {
            $profError = "Full name must be at least 3 characters long.";
        } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
            $profError = "Please enter a valid 10-digit mobile number.";
        } else {
            try {
                $pdo->beginTransaction();

                // Update users table
                $stmtUser = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE user_id = ?");
                $stmtUser->execute([$name, $phone, $currentUser['user_id']]);

                // Update customers table
                $stmtCust = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE customer_id = ?");
                $stmtCust->execute([$name, $phone, $address, $customerId]);

                $pdo->commit();

                // Update session
                $_SESSION['name']  = $name;
                $_SESSION['phone'] = $phone;
                $currentUser['name']  = $name;
                $currentUser['phone'] = $phone;

                $profSuccess = "Profile details updated successfully!";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Profile update error: " . $e->getMessage());
                $profError = "Something went wrong while updating your profile.";
            }
        }
    } elseif ($action === 'change_password') {
        $currPass    = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currPass) || empty($newPass) || empty($confirmPass)) {
            $passError = "All password fields are required.";
        } elseif (strlen($newPass) < 8) {
            $passError = "New password must be at least 8 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $passError = "New passwords do not match. Please verify.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$currentUser['user_id']]);
                $dbPass = $stmt->fetchColumn();

                if ($dbPass && (password_verify($currPass, $dbPass) || $currPass === $dbPass)) {
                    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $upd->execute([$hashed, $currentUser['user_id']]);

                    $passSuccess = "Your password has been changed successfully!";
                } else {
                    $passError = "Current password does not match our records.";
                }
            } catch (PDOException $e) {
                error_log("Change password error: " . $e->getMessage());
                $passError = "Unable to update password. Please try again.";
            }
        }
    }
}

// Fetch latest customer profile data from MySQL
try {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $customerRecord = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Fetch customer error: " . $e->getMessage());
    $customerRecord = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Profile & Settings – AutoCare</title>
  
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Custom Styles -->
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">

  <div class="dashboard-layout">
    
    <!-- Mobile Backdrop -->
    <div class="sidebar-backdrop"></div>

    <!-- Sidebar Navigation -->
    <aside class="dashboard-sidebar">
      <div class="sidebar-brand">
        <span class="brand-badge"><i class="bi bi-shield-shaded"></i></span>
        <span>Auto<span class="text-warning">Care</span></span>
      </div>

      <div class="sidebar-user">
        <div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['name'], 0, 2))) ?></div>
        <div class="overflow-hidden">
          <div class="fw-bold text-white text-truncate small" id="sidebarUserName"><?= htmlspecialchars($currentUser['name']) ?></div>
          <div class="text-white-50 extra-small text-truncate" id="sidebarUserEmail"><?= htmlspecialchars($currentUser['email']) ?></div>
        </div>
      </div>

      <ul class="sidebar-menu">
        <div class="sidebar-heading">Customer Portal</div>
        <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li><a href="vehicles.php"><i class="bi bi-car-front-fill"></i> My Vehicles</a></li>
        <li><a href="../booking.php"><i class="bi bi-calendar-plus"></i> Book Service</a></li>
        <li><a href="bookings.php"><i class="bi bi-card-checklist"></i> My Bookings</a></li>
        <li><a href="bookings.php?filter=Completed"><i class="bi bi-clock-history"></i> Service History</a></li>
        <li><a href="profile.php" class="active"><i class="bi bi-person-gear"></i> Profile Settings</a></li>

        <div class="sidebar-heading mt-3">Quick Navigation</div>
        <li><a href="../services.php"><i class="bi bi-grid"></i> Services Catalog</a></li>
        <li><a href="../status.php"><i class="bi bi-geo-alt"></i> Live Status Tracker</a></li>
      </ul>

      <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-outline-danger w-100 btn-sm d-flex align-items-center justify-content-center gap-2">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="dashboard-content-wrapper">
      
      <!-- Topbar -->
      <header class="dashboard-topbar">
        <div class="d-flex align-items-center gap-3">
          <button id="sidebarToggle" class="btn btn-light d-lg-none" type="button" aria-label="Toggle Sidebar">
            <i class="bi bi-list fs-5"></i>
          </button>
          <div>
            <h5 class="fw-bold mb-0">Profile & Security Settings</h5>
            <small class="text-muted d-none d-sm-inline">Manage personal details, notification contacts and login security</small>
          </div>
        </div>
      </header>

      <!-- Main Body -->
      <main class="dashboard-main-body">
        
        <div class="row g-4">
          
          <!-- Profile Details Form -->
          <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
              <h5 class="fw-bold mb-4 text-primary d-flex align-items-center gap-2">
                <i class="bi bi-person-circle"></i> Personal Information
              </h5>

              <?php if (!empty($profSuccess)): ?>
                <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
                  <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($profSuccess) ?>
                  <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <?php if (!empty($profError)): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($profError) ?>
                  <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-3">
                  <label for="profName" class="form-label">Full Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" id="profName" class="form-control" value="<?= htmlspecialchars($customerRecord['name'] ?? $currentUser['name']) ?>" required>
                </div>

                <div class="mb-3">
                  <label for="profEmail" class="form-label">Email Address (Read-Only)</label>
                  <input type="email" id="profEmail" class="form-control bg-light" value="<?= htmlspecialchars($currentUser['email']) ?>" readonly>
                  <small class="text-muted">Primary login email is verified and permanently linked.</small>
                </div>

                <div class="mb-3">
                  <label for="profPhone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="tel" name="phone" id="profPhone" class="form-control" maxlength="10" value="<?= htmlspecialchars($customerRecord['phone'] ?? $currentUser['phone']) ?>" required>
                  </div>
                </div>

                <div class="mb-4">
                  <label for="profAddress" class="form-label">Delivery & Invoicing Address</label>
                  <textarea name="address" id="profAddress" class="form-control" rows="3" placeholder="Enter house/street address..."><?= htmlspecialchars($customerRecord['address'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary fw-semibold px-4 py-2 rounded-3 shadow">
                  <i class="bi bi-check2 me-1"></i> Save Changes
                </button>
              </form>
            </div>
          </div>

          <!-- Password Change & Account Info -->
          <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
              <h5 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                <i class="bi bi-key-fill"></i> Change Password
              </h5>

              <?php if (!empty($passSuccess)): ?>
                <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
                  <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($passSuccess) ?>
                  <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <?php if (!empty($passError)): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($passError) ?>
                  <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                  <label for="currentPass" class="form-label">Current Password <span class="text-danger">*</span></label>
                  <input type="password" name="current_password" id="currentPass" class="form-control" required>
                </div>

                <div class="mb-3">
                  <label for="newPass" class="form-label">New Password <span class="text-danger">*</span></label>
                  <input type="password" name="new_password" id="newPass" class="form-control" placeholder="Min 8 characters" required>
                </div>

                <div class="mb-4">
                  <label for="confirmNewPass" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                  <input type="password" name="confirm_password" id="confirmNewPass" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-outline-primary fw-semibold w-100 py-2 rounded-3">
                  Update Password
                </button>
              </form>
            </div>

            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
              <h6 class="fw-bold mb-2">Stage 2 Live Security</h6>
              <p class="small text-muted mb-0">
                Your profile is stored in MySQL tables <code>customers</code> and <code>users</code>. Password encryption uses standard PHP <code>password_hash()</code> with BCRYPT.
              </p>
            </div>
          </div>

        </div>

      </main>

      <!-- Customer Footer -->
      <footer class="p-3 bg-white border-top text-center text-muted small mt-auto">
        &copy; 2026 AutoCare Portal &bull; Customer Management Area
      </footer>

    </div>
  </div>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/main.js"></script>
</body>
</html>
