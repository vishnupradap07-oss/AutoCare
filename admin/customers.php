<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Admin Customer Management (admin/customers.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

require_auth('admin', '../');

$currentUser = get_current_user_data();

$custMsg = '';
$custError = '';

// Handle Customer Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_customer') {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim(strtolower($_POST['email'] ?? ''));
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name) || empty($email) || empty($phone)) {
            $custError = "Customer name, email and phone number are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $custError = "Please enter a valid email address.";
        } else {
            try {
                // Check if user exists
                $stmtChk = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
                $stmtChk->execute([$email]);
                $existingUser = $stmtChk->fetch();

                $pdo->beginTransaction();

                if (!$existingUser) {
                    $newUserId = 'USR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                    // Default secure password hash for accounts created by admin
                    $defaultPass = password_hash('customer123', PASSWORD_BCRYPT);
                    $insUser = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role, phone) VALUES (?, ?, ?, ?, 'customer', ?)");
                    $insUser->execute([$newUserId, $name, $email, $defaultPass, $phone]);
                } else {
                    $newUserId = $existingUser['user_id'];
                }

                $newCustId = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                $insCust = $pdo->prepare("INSERT INTO customers (customer_id, user_id, name, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $insCust->execute([$newCustId, $newUserId, $name, $email, $phone, $address]);

                $pdo->commit();
                $custMsg = "Customer $name ($email) added successfully!";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Add customer error: " . $e->getMessage());
                $custError = "Failed to add customer. Please verify input data.";
            }
        }
    } elseif ($action === 'delete_customer') {
        $custId = trim($_POST['customer_id'] ?? '');

        if (!empty($custId)) {
            try {
                // Check bookings to maintain referential integrity
                $chkBook = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id = ?");
                $chkBook->execute([$custId]);
                $bCount = $chkBook->fetchColumn();

                if ($bCount > 0) {
                    $custError = "Cannot delete customer $custId because they have $bCount existing service bookings. Consider marking them inactive instead.";
                } else {
                    $stmtDel = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
                    $stmtDel->execute([$custId]);
                    $custMsg = "Customer $custId deleted successfully.";
                }
            } catch (PDOException $e) {
                error_log("Delete customer error: " . $e->getMessage());
                $custError = "Unable to delete customer.";
            }
        }
    }
}

// Fetch all customers with vehicle count and booking count
try {
    $stmt = $pdo->query("
        SELECT 
            c.*,
            COUNT(DISTINCT v.vehicle_id) AS vehicle_count,
            COUNT(DISTINCT b.booking_id) AS booking_count
        FROM customers c
        LEFT JOIN vehicles v ON c.customer_id = v.customer_id
        LEFT JOIN bookings b ON c.customer_id = b.customer_id
        GROUP BY c.customer_id
        ORDER BY c.created_at DESC
    ");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch customers error: " . $e->getMessage());
    $customers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Management – AutoCare Admin</title>
  
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
        <span>Auto<span class="text-warning">Care</span> <span class="badge bg-warning text-dark fs-6 ms-1 py-0 px-2">ADMIN</span></span>
      </div>

      <div class="sidebar-user">
        <div class="user-avatar bg-warning text-dark"><i class="bi bi-person-fill-gear"></i></div>
        <div class="overflow-hidden">
          <div class="fw-bold text-white text-truncate small" id="sidebarUserName"><?= htmlspecialchars($currentUser['name']) ?></div>
          <div class="text-white-50 extra-small text-truncate">Service Center Head</div>
        </div>
      </div>

      <ul class="sidebar-menu">
        <div class="sidebar-heading">Operations</div>
        <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li><a href="bookings.php"><i class="bi bi-card-checklist"></i> Bookings</a></li>
        <li><a href="customers.php" class="active"><i class="bi bi-people-fill"></i> Customers</a></li>
        <li><a href="vehicles.php"><i class="bi bi-car-front-fill"></i> Vehicles</a></li>
        <li><a href="mechanics.php"><i class="bi bi-tools"></i> Mechanics</a></li>
        
        <div class="sidebar-heading mt-3">Catalog & Finance</div>
        <li><a href="services.php"><i class="bi bi-gear-wide-connected"></i> Services</a></li>
        <li><a href="payments.php"><i class="bi bi-cash-stack"></i> Payments</a></li>

        <div class="sidebar-heading mt-3">General</div>
        <li><a href="javascript:void(0)" onclick="window.print()"><i class="bi bi-file-earmark-bar-graph"></i> Reports (Print)</a></li>
        <li><a href="../index.php"><i class="bi bi-globe"></i> View Public Site</a></li>
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
            <h5 class="fw-bold mb-0">Customer Directory</h5>
            <small class="text-muted d-none d-sm-inline">Manage registered customer profiles, linked vehicle registries and booking histories</small>
          </div>
        </div>

        <button class="btn btn-primary btn-sm fw-semibold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
          <i class="bi bi-person-plus me-1"></i> Add New Customer
        </button>
      </header>

      <!-- Main Body -->
      <main class="dashboard-main-body">

        <?php if (!empty($custMsg)): ?>
          <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($custMsg) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($custError)): ?>
          <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($custError) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        
        <!-- Search Bar -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
          <div class="row g-3 align-items-center">
            <div class="col-md-6">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="customerSearch" class="form-control border-start-0" placeholder="Search customer by name, email, phone or Customer ID...">
              </div>
            </div>
            <div class="col-md-6 text-md-end text-muted small">
              Database records synced via MySQL <code>customers</code> table
            </div>
          </div>
        </div>

        <!-- Customer Directory Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="adminCustomersTable">
              <thead>
                <tr>
                  <th>Customer ID</th>
                  <th>Customer Details</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Vehicles</th>
                  <th>Bookings</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($customers)): ?>
                  <tr><td colspan="8" class="text-center py-4 text-muted">No customers registered yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($customers as $c): ?>
                    <tr class="cust-row" data-search="<?= htmlspecialchars(strtolower($c['customer_id'] . ' ' . $c['name'] . ' ' . $c['email'] . ' ' . $c['phone'])) ?>">
                      <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($c['customer_id']) ?></td>
                      <td>
                        <div class="fw-bold"><?= htmlspecialchars($c['name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($c['address'] ?? 'Address not listed') ?></small>
                      </td>
                      <td><?= htmlspecialchars($c['email']) ?></td>
                      <td><?= htmlspecialchars($c['phone']) ?></td>
                      <td><span class="badge bg-primary-subtle text-primary border"><?= $c['vehicle_count'] ?> Vehicles</span></td>
                      <td><span class="badge bg-info-subtle text-info border"><?= $c['booking_count'] ?> Bookings</span></td>
                      <td><span class="status-badge status-completed"><?= htmlspecialchars(ucfirst($c['status'])) ?></span></td>
                      <td>
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-info" onclick="viewCustomerModal(<?= htmlspecialchars(json_encode($c)) ?>)" title="View Details">
                            <i class="bi bi-eye"></i>
                          </button>
                          <form method="POST" action="customers.php" onsubmit="return confirm('Delete customer <?= htmlspecialchars($c['customer_id']) ?>?');" class="d-inline">
                            <input type="hidden" name="action" value="delete_customer">
                            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($c['customer_id']) ?>">
                            <button type="submit" class="btn btn-outline-danger" title="Delete Customer">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </main>

      <!-- Admin Footer -->
      <footer class="p-3 bg-white border-top text-center text-muted small mt-auto">
        &copy; 2026 AutoCare Portal &bull; Enterprise Workshop Management
      </footer>

    </div>
  </div>

  <!-- Add Customer Modal -->
  <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="addCustomerModalLabel"><i class="bi bi-person-plus text-primary me-2"></i> Register New Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="customers.php">
          <input type="hidden" name="action" value="add_customer">
          <div class="modal-body p-4">
            <div class="mb-3">
              <label for="addCustName" class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="addCustName" class="form-control" placeholder="e.g. Anand Prakash" required>
            </div>
            <div class="mb-3">
              <label for="addCustEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" id="addCustEmail" class="form-control" placeholder="anand@example.com" required>
            </div>
            <div class="mb-3">
              <label for="addCustPhone" class="form-label">Mobile Number <span class="text-danger">*</span></label>
              <input type="tel" name="phone" id="addCustPhone" class="form-control" placeholder="9876543210" maxlength="10" required>
            </div>
            <div class="mb-3">
              <label for="addCustAddress" class="form-label">Address</label>
              <textarea name="address" id="addCustAddress" class="form-control" rows="2" placeholder="Street, City, Pin..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Create Customer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Customer Modal -->
  <div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-person-badge text-primary me-2"></i> Customer Profile Information</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-6">
              <small class="text-muted d-block">Name:</small>
              <strong id="viewCustName">-</strong>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Customer ID:</small>
              <strong class="font-monospace text-primary" id="viewCustId">-</strong>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Email:</small>
              <strong id="viewCustEmail">-</strong>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Phone:</small>
              <strong id="viewCustPhone">-</strong>
            </div>
            <div class="col-12">
              <small class="text-muted d-block">Address:</small>
              <div class="p-2 bg-light rounded border mt-1" id="viewCustAddress">-</div>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Vehicles Count:</small>
              <span class="badge bg-primary-subtle text-primary border" id="viewCustVehiclesCount">-</span>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Bookings Count:</small>
              <span class="badge bg-info-subtle text-info border" id="viewCustBookingsCount">-</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/main.js"></script>

  <script>
    function viewCustomerModal(c) {
      document.getElementById("viewCustName").textContent = c.name;
      document.getElementById("viewCustId").textContent = c.customer_id;
      document.getElementById("viewCustEmail").textContent = c.email;
      document.getElementById("viewCustPhone").textContent = c.phone;
      document.getElementById("viewCustAddress").textContent = c.address || "Address not provided";
      document.getElementById("viewCustVehiclesCount").textContent = `${c.vehicle_count} Vehicles`;
      document.getElementById("viewCustBookingsCount").textContent = `${c.booking_count} Bookings`;

      const modalEl = document.getElementById("viewCustomerModal");
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }

    // Client search filter on table
    document.getElementById("customerSearch").addEventListener("input", function() {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll(".cust-row").forEach(row => {
        const text = row.dataset.search || "";
        row.style.display = text.includes(q) ? "" : "none";
      });
    });
  </script>
</body>
</html>
