<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Admin Vehicle Fleet Management (admin/vehicles.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

require_auth('admin', '../');

$currentUser = get_current_user_data();

$brandFilter = trim($_GET['brand'] ?? 'All');

// Fetch distinct brands for filter dropdown
try {
    $stmtBrands = $pdo->query("SELECT DISTINCT brand FROM vehicles ORDER BY brand ASC");
    $distinctBrands = $stmtBrands->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $distinctBrands = [];
}

// Fetch all vehicles with customer info JOIN
try {
    $query = "
        SELECT 
            v.*,
            c.name AS customer_name,
            c.phone AS customer_phone
        FROM vehicles v
        JOIN customers c ON v.customer_id = c.customer_id
    ";
    $params = [];

    if ($brandFilter !== 'All' && !empty($brandFilter)) {
        $query .= " WHERE v.brand = ?";
        $params[] = $brandFilter;
    }

    $query .= " ORDER BY v.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch admin vehicles error: " . $e->getMessage());
    $vehicles = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vehicle Registry – AutoCare Admin</title>
  
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
        <li><a href="customers.php"><i class="bi bi-people-fill"></i> Customers</a></li>
        <li><a href="vehicles.php" class="active"><i class="bi bi-car-front-fill"></i> Vehicles</a></li>
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
            <h5 class="fw-bold mb-0">Registered Vehicle Fleet</h5>
            <small class="text-muted d-none d-sm-inline">Manage registered customer vehicles, specifications and maintenance status</small>
          </div>
        </div>

        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
          <i class="bi bi-printer me-1"></i> Print Fleet List
        </button>
      </header>

      <!-- Main Body -->
      <main class="dashboard-main-body">
        
        <!-- Filter & Search Bar -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
          <div class="row g-3 align-items-center">
            <div class="col-md-7">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="vehicleSearch" class="form-control border-start-0" placeholder="Search by registration number, customer name, brand, or model...">
              </div>
            </div>

            <div class="col-md-5">
              <div class="d-flex align-items-center gap-2 justify-content-md-end">
                <label class="form-label mb-0 small text-muted text-nowrap">Filter Brand:</label>
                <select id="vehicleBrandFilter" class="form-select form-select-sm" onchange="location = this.value;" style="width: 180px;">
                  <option value="vehicles.php?brand=All" <?= ($brandFilter === 'All') ? 'selected' : '' ?>>All Brands</option>
                  <?php foreach ($distinctBrands as $b): ?>
                    <option value="vehicles.php?brand=<?= urlencode($b) ?>" <?= ($brandFilter === $b) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($b) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Vehicles Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="adminVehiclesTable">
              <thead>
                <tr>
                  <th>Vehicle ID</th>
                  <th>Plate Number</th>
                  <th>Customer Owner</th>
                  <th>Make / Brand</th>
                  <th>Model Variant</th>
                  <th>Year</th>
                  <th>Fuel</th>
                  <th>Last Service</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($vehicles)): ?>
                  <tr><td colspan="9" class="text-center py-4 text-muted">No vehicles registered in fleet.</td></tr>
                <?php else: ?>
                  <?php foreach ($vehicles as $v): ?>
                    <tr class="veh-row" data-search="<?= htmlspecialchars(strtolower($v['vehicle_id'] . ' ' . $v['registration_number'] . ' ' . $v['customer_name'] . ' ' . $v['brand'] . ' ' . $v['model'])) ?>">
                      <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($v['vehicle_id']) ?></td>
                      <td>
                        <span class="badge bg-dark font-monospace px-2 py-1 fs-6"><?= htmlspecialchars($v['registration_number']) ?></span>
                      </td>
                      <td class="fw-semibold">
                        <?= htmlspecialchars($v['customer_name']) ?>
                        <small class="text-muted d-block"><?= htmlspecialchars($v['customer_phone'] ?? '') ?></small>
                      </td>
                      <td><?= htmlspecialchars($v['brand']) ?></td>
                      <td><?= htmlspecialchars($v['model']) ?></td>
                      <td><?= htmlspecialchars($v['year']) ?></td>
                      <td><span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($v['fuel_type'] ?? 'Petrol') ?></span></td>
                      <td><?= format_date($v['last_service']) ?></td>
                      <td><span class="status-badge status-completed">Active</span></td>
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

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/main.js"></script>
  <script>
    document.getElementById("vehicleSearch").addEventListener("input", function() {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll(".veh-row").forEach(row => {
        const text = row.dataset.search || "";
        row.style.display = text.includes(q) ? "" : "none";
      });
    });
  </script>
</body>
</html>
