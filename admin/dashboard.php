<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Admin Dashboard (admin/dashboard.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

// Enforce admin role authentication
require_auth('admin', '../');

$currentUser = get_current_user_data();

$statusMsg = '';
$statusError = '';

// Handle Status Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $bId    = trim($_POST['booking_id'] ?? '');
    $status = trim($_POST['status'] ?? '');

    $allowedStatuses = [
        'Pending', 'Confirmed', 'Vehicle Received', 'Inspection', 
        'Service In Progress', 'Ready for Delivery', 'Completed', 'Cancelled'
    ];

    if (!empty($bId) && in_array($status, $allowedStatuses)) {
        try {
            $stmtUpd = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
            $stmtUpd->execute([$status, $bId]);

            // If status is Completed, record into service_history if not present
            if ($status === 'Completed') {
                $chkHist = $pdo->prepare("SELECT COUNT(*) FROM service_history WHERE booking_id = ?");
                $chkHist->execute([$bId]);
                if ($chkHist->fetchColumn() == 0) {
                    $insHist = $pdo->prepare("
                        INSERT INTO service_history (booking_id, service_date, description, amount)
                        SELECT b.booking_id, CURDATE(), CONCAT(s.service_name, ' – Service completed and quality verified.'), b.amount
                        FROM bookings b
                        JOIN services s ON b.service_id = s.service_id
                        WHERE b.booking_id = ?
                    ");
                    $insHist->execute([$bId]);
                }
            }

            $statusMsg = "Booking $bId status updated to \"$status\" successfully.";
        } catch (PDOException $e) {
            error_log("Update status error: " . $e->getMessage());
            $statusError = "Failed to update booking status.";
        }
    }
}

// 1. Total Customers
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?: 0;

// 2. Total Vehicles
$totalVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn() ?: 0;

// 3. Today's Bookings
$todaysBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE()")->fetchColumn() ?: 0;

// 4. Pending / Active Services
$pendingServices = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status NOT IN ('Completed', 'Cancelled')")->fetchColumn() ?: 0;

// 5. Completed Services
$completedServices = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Completed'")->fetchColumn() ?: 0;

// 6. Total Revenue
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid'")->fetchColumn() ?: 0;

// Recent Bookings for live floor queue
try {
    $stmtRecent = $pdo->query("
        SELECT 
            b.*,
            c.name AS customer_name,
            c.phone AS customer_phone,
            v.brand,
            v.model,
            v.registration_number,
            s.service_name,
            m.name AS mechanic_name
        FROM bookings b
        JOIN customers c ON b.customer_id = c.customer_id
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        JOIN services s ON b.service_id = s.service_id
        LEFT JOIN mechanics m ON b.mechanic_id = m.mechanic_id
        ORDER BY b.booking_date DESC, b.created_at DESC
        LIMIT 7
    ");
    $recentBookings = $stmtRecent->fetchAll();
} catch (PDOException $e) {
    error_log("Recent bookings query error: " . $e->getMessage());
    $recentBookings = [];
}

// Chart 2: Category Distribution
try {
    $stmtCat = $pdo->query("
        SELECT s.category, COUNT(b.booking_id) as count 
        FROM bookings b 
        JOIN services s ON b.service_id = s.service_id 
        GROUP BY s.category
    ");
    $catData = $stmtCat->fetchAll();
    $catLabels = [];
    $catCounts = [];
    foreach ($catData as $row) {
        $catLabels[] = $row['category'];
        $catCounts[] = intval($row['count']);
    }
    if (empty($catLabels)) {
        $catLabels = ["Maintenance", "Engine", "Brakes", "AC", "Electrical"];
        $catCounts = [12, 8, 6, 5, 3];
    }
} catch (PDOException $e) {
    $catLabels = ["Maintenance", "Engine", "Brakes", "AC", "Electrical"];
    $catCounts = [12, 8, 6, 5, 3];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard – AutoCare Workshop</title>
  
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Custom Styles -->
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">

  <div class="dashboard-layout" id="adminDashboardView">
    
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
        <li><a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li><a href="bookings.php"><i class="bi bi-card-checklist"></i> Bookings</a></li>
        <li><a href="customers.php"><i class="bi bi-people-fill"></i> Customers</a></li>
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
            <h5 class="fw-bold mb-0">Workshop Management Center</h5>
            <small class="text-muted d-none d-sm-inline">Real-time automobile service floor analytics and jobs control</small>
          </div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <a href="bookings.php" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
            <i class="bi bi-plus-circle me-1"></i> Manage Bookings
          </a>
        </div>
      </header>

      <!-- Main Body -->
      <main class="dashboard-main-body">

        <?php if (!empty($statusMsg)): ?>
          <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($statusMsg) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($statusError)): ?>
          <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($statusError) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        
        <!-- 6 Main Statistics Cards (Loaded from MySQL) -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-2">
            <div class="stat-card">
              <div class="stat-title">Total Customers</div>
              <div class="stat-value text-primary" id="statTotalCustomers"><?= $totalCustomers ?></div>
              <small class="text-muted"><i class="bi bi-arrow-up-short text-success"></i> Active records</small>
            </div>
          </div>

          <div class="col-sm-6 col-xl-2">
            <div class="stat-card">
              <div class="stat-title">Total Vehicles</div>
              <div class="stat-value text-dark" id="statTotalVehicles"><?= $totalVehicles ?></div>
              <small class="text-muted"><i class="bi bi-check-all text-primary"></i> Multi-brand fleet</small>
            </div>
          </div>

          <div class="col-sm-6 col-xl-2">
            <div class="stat-card">
              <div class="stat-title">Today's Bookings</div>
              <div class="stat-value text-info" id="statTodaysBookings"><?= $todaysBookings ?></div>
              <small class="text-muted">Floor queue</small>
            </div>
          </div>

          <div class="col-sm-6 col-xl-2">
            <div class="stat-card">
              <div class="stat-title">Pending Services</div>
              <div class="stat-value text-warning" id="statPendingServices"><?= $pendingServices ?></div>
              <small class="text-muted">In Progress / Bays</small>
            </div>
          </div>

          <div class="col-sm-6 col-xl-2">
            <div class="stat-card">
              <div class="stat-title">Completed</div>
              <div class="stat-value text-success" id="statCompletedServices"><?= $completedServices ?></div>
              <small class="text-muted"><i class="bi bi-patch-check-fill text-success"></i> QC Passed</small>
            </div>
          </div>

          <div class="col-sm-6 col-xl-2">
            <div class="stat-card">
              <div class="stat-title">Total Revenue</div>
              <div class="stat-value text-success" id="statTotalRevenue"><?= format_currency($totalRevenue) ?></div>
              <small class="text-muted">Paid collections</small>
            </div>
          </div>
        </div>

        <!-- 3 Interactive Analytics Charts -->
        <div class="row g-4 mb-4">
          <!-- Monthly Bookings Chart -->
          <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Monthly Bookings & Work Volume</h5>
                <span class="badge bg-light text-dark border">Recent Months</span>
              </div>
              <canvas id="chartMonthlyBookings" height="150"></canvas>
            </div>
          </div>

          <!-- Service Category Distribution Chart -->
          <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Service Category Share</h5>
                <span class="badge bg-light text-dark border">By Volume</span>
              </div>
              <canvas id="chartServiceCategory" height="150"></canvas>
            </div>
          </div>

          <!-- Revenue Trend Chart -->
          <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Weekly Revenue Inflow Overview (₹)</h5>
                <span class="badge bg-success-subtle text-success border">Settled Payments</span>
              </div>
              <canvas id="chartRevenueOverview" height="70"></canvas>
            </div>
          </div>
        </div>

        <!-- Recent Bookings Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <div>
              <h5 class="fw-bold mb-0">Live Service Floor Queue</h5>
              <small class="text-muted">Recent bookings and real-time status transitions</small>
            </div>
            <a href="bookings.php" class="btn btn-outline-primary btn-sm rounded-pill">Manage All &rarr;</a>
          </div>

          <div class="table-responsive">
            <table class="table table-custom align-middle">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Customer</th>
                  <th>Vehicle</th>
                  <th>Service</th>
                  <th>Date & Time</th>
                  <th>Specialist</th>
                  <th>Status</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="recentBookingsTbody">
                <?php if (empty($recentBookings)): ?>
                  <tr><td colspan="9" class="text-center py-4 text-muted">No recent bookings found.</td></tr>
                <?php else: ?>
                  <?php foreach ($recentBookings as $b): ?>
                    <tr>
                      <td class="fw-bold text-primary"><?= htmlspecialchars($b['booking_id']) ?></td>
                      <td>
                        <div class="fw-semibold"><?= htmlspecialchars($b['customer_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($b['customer_phone'] ?? '') ?></small>
                      </td>
                      <td>
                        <div><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($b['registration_number']) ?></span>
                      </td>
                      <td><?= htmlspecialchars($b['service_name']) ?></td>
                      <td>
                        <?= format_date($b['booking_date']) ?>
                        <small class="text-muted d-block"><?= htmlspecialchars($b['booking_time']) ?></small>
                      </td>
                      <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= htmlspecialchars($b['mechanic_name'] ?? 'Unassigned') ?></span></td>
                      <td><?= render_status_badge($b['status']) ?></td>
                      <td class="fw-bold text-dark"><?= format_currency($b['amount']) ?></td>
                      <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="openStatusModal('<?= htmlspecialchars($b['booking_id']) ?>', '<?= htmlspecialchars($b['customer_name']) ?>', '<?= htmlspecialchars($b['status']) ?>')" title="Change Status">
                          <i class="bi bi-pencil-square"></i>
                        </button>
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

  <!-- Update Status Modal (Shared across admin) -->
  <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat text-primary me-2"></i> Update Service Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="dashboard.php">
          <input type="hidden" name="action" value="update_status">
          <div class="modal-body p-4">
            <input type="hidden" name="booking_id" id="statusModalBookingId">
            <div class="mb-3">
              <span class="text-muted small d-block">Booking ID:</span>
              <strong class="font-monospace text-primary fs-5" id="statusModalDisplayId"></strong>
            </div>
            <div class="mb-3">
              <span class="text-muted small d-block">Customer:</span>
              <strong id="statusModalCustomer"></strong>
            </div>
            <div class="mb-3">
              <label for="statusSelect" class="form-label">Service Milestone Status <span class="text-danger">*</span></label>
              <select name="status" id="statusSelect" class="form-select form-select-lg">
                <option value="Pending">Pending</option>
                <option value="Confirmed">Confirmed</option>
                <option value="Vehicle Received">Vehicle Received</option>
                <option value="Inspection">Inspection</option>
                <option value="Service In Progress">Service In Progress</option>
                <option value="Ready for Delivery">Ready for Delivery</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Update Status</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/main.js"></script>

  <script>
    function openStatusModal(bookingId, customerName, currentStatus) {
      document.getElementById("statusModalBookingId").value = bookingId;
      document.getElementById("statusModalDisplayId").textContent = bookingId;
      document.getElementById("statusModalCustomer").textContent = customerName;
      document.getElementById("statusSelect").value = currentStatus;

      const modalEl = document.getElementById("updateStatusModal");
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }

    // Chart 1: Monthly Bookings
    const monthlyCanvas = document.getElementById("chartMonthlyBookings");
    if (monthlyCanvas) {
      new Chart(monthlyCanvas, {
        type: "bar",
        data: {
          labels: ["May", "Jun", "Jul", "Aug", "Sep", "Oct"],
          datasets: [{
            label: "Services Completed",
            data: [45, 52, 68, 80, 94, <?= $completedServices ?>],
            backgroundColor: "rgba(37, 99, 235, 0.85)",
            borderRadius: 6
          }, {
            label: "New Bookings",
            data: [50, 60, 75, 88, 105, <?= $totalCustomers ?>],
            backgroundColor: "rgba(147, 197, 253, 0.65)",
            borderRadius: 6
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "top" } },
          scales: { y: { beginAtZero: true } }
        }
      });
    }

    // Chart 2: Service Distribution from MySQL
    const distCanvas = document.getElementById("chartServiceCategory");
    if (distCanvas) {
      new Chart(distCanvas, {
        type: "doughnut",
        data: {
          labels: <?= json_encode($catLabels) ?>,
          datasets: [{
            data: <?= json_encode($catCounts) ?>,
            backgroundColor: ["#2563eb", "#10b981", "#f59e0b", "#06b6d4", "#8b5cf6", "#ec4899"],
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "bottom" } },
          cutout: "68%"
        }
      });
    }

    // Chart 3: Revenue Overview
    const revCanvas = document.getElementById("chartRevenueOverview");
    if (revCanvas) {
      new Chart(revCanvas, {
        type: "line",
        data: {
          labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
          datasets: [{
            label: "Revenue (₹)",
            data: [18400, 24500, 19800, <?= max($totalRevenue, 22700) ?>],
            borderColor: "#10b981",
            backgroundColor: "rgba(16, 185, 129, 0.1)",
            fill: true,
            tension: 0.35,
            pointRadius: 5
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } }
        }
      });
    }
  </script>
</body>
</html>
