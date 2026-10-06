<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Customer Dashboard (customer/dashboard.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

// Enforce customer authentication
require_auth('customer', '../');

$currentUser = get_current_user_data();
$customerId = get_or_set_customer_id($pdo, $currentUser['user_id']);

// KPI Metrics from MySQL
$statVehiclesCount = 0;
$statActiveBookingsCount = 0;
$statCompletedServicesCount = 0;
$statPendingPaymentSum = 0;
$activePrimaryBooking = null;
$recentBookings = [];

if ($customerId) {
    // 1. Total vehicles
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $statVehiclesCount = $stmt->fetchColumn();

    // 2. Active bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status NOT IN ('Completed', 'Cancelled')");
    $stmt->execute([$customerId]);
    $statActiveBookingsCount = $stmt->fetchColumn();

    // 3. Completed services
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status = 'Completed'");
    $stmt->execute([$customerId]);
    $statCompletedServicesCount = $stmt->fetchColumn();

    // 4. Pending payments sum
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM bookings WHERE customer_id = ? AND payment_status = 'Pending'");
    $stmt->execute([$customerId]);
    $statPendingPaymentSum = $stmt->fetchColumn();

    // 5. Primary active service for Live Tracker Stepper
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            v.brand,
            v.model,
            v.registration_number,
            s.service_name,
            m.name AS mechanic_name
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        JOIN services s ON b.service_id = s.service_id
        LEFT JOIN mechanics m ON b.mechanic_id = m.mechanic_id
        WHERE b.customer_id = ? AND b.status NOT IN ('Completed', 'Cancelled')
        ORDER BY b.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$customerId]);
    $activePrimaryBooking = $stmt->fetch();

    // If no active, grab latest booking
    if (!$activePrimaryBooking) {
        $stmtLatest = $pdo->prepare("
            SELECT 
                b.*,
                v.brand,
                v.model,
                v.registration_number,
                s.service_name,
                m.name AS mechanic_name
            FROM bookings b
            JOIN vehicles v ON b.vehicle_id = v.vehicle_id
            JOIN services s ON b.service_id = s.service_id
            LEFT JOIN mechanics m ON b.mechanic_id = m.mechanic_id
            WHERE b.customer_id = ?
            ORDER BY b.created_at DESC
            LIMIT 1
        ");
        $stmtLatest->execute([$customerId]);
        $activePrimaryBooking = $stmtLatest->fetch();
    }

    // 6. Recent bookings list
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            v.brand,
            v.model,
            v.registration_number,
            s.service_name,
            m.name AS mechanic_name
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        JOIN services s ON b.service_id = s.service_id
        LEFT JOIN mechanics m ON b.mechanic_id = m.mechanic_id
        WHERE b.customer_id = ?
        ORDER BY b.booking_date DESC, b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$customerId]);
    $recentBookings = $stmt->fetchAll();
}

$stages = [
    "Confirmed",
    "Vehicle Received",
    "Inspection",
    "Service In Progress",
    "Ready for Delivery",
    "Completed"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Dashboard – AutoCare</title>
  
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
        <div class="user-avatar" id="custAvatar">
          <?= htmlspecialchars(strtoupper(substr($currentUser['name'], 0, 2))) ?>
        </div>
        <div class="overflow-hidden">
          <div class="fw-bold text-white text-truncate small" id="sidebarUserName"><?= htmlspecialchars($currentUser['name']) ?></div>
          <div class="text-white-50 extra-small text-truncate" id="sidebarUserEmail"><?= htmlspecialchars($currentUser['email']) ?></div>
        </div>
      </div>

      <ul class="sidebar-menu">
        <div class="sidebar-heading">Customer Portal</div>
        <li><a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li><a href="vehicles.php"><i class="bi bi-car-front-fill"></i> My Vehicles</a></li>
        <li><a href="../booking.php"><i class="bi bi-calendar-plus"></i> Book Service</a></li>
        <li><a href="bookings.php"><i class="bi bi-card-checklist"></i> My Bookings</a></li>
        <li><a href="bookings.php?filter=Completed"><i class="bi bi-clock-history"></i> Service History</a></li>
        <li><a href="profile.php"><i class="bi bi-person-gear"></i> Profile Settings</a></li>
        
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
            <h5 class="fw-bold mb-0" id="welcomeTitle">Welcome, <?= htmlspecialchars($currentUser['name']) ?></h5>
            <small class="text-muted d-none d-sm-inline">Manage your vehicles and workshop service history</small>
          </div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <div class="dropdown">
            <button class="btn btn-light position-relative rounded-circle p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-bell fs-5"></i>
              <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="width: 280px;">
              <li class="dropdown-header fw-bold">Recent Notifications</li>
              <?php if ($activePrimaryBooking): ?>
                <li><a class="dropdown-item rounded small py-2" href="../status.php?id=<?= urlencode($activePrimaryBooking['booking_id']) ?>"><i class="bi bi-gear-fill text-warning me-2"></i><?= htmlspecialchars($activePrimaryBooking['model']) ?>: <?= htmlspecialchars($activePrimaryBooking['status']) ?></a></li>
              <?php else: ?>
                <li class="small text-muted p-2">No active service alerts at this time.</li>
              <?php endif; ?>
            </ul>
          </div>

          <a href="profile.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="bi bi-person me-1"></i> Profile
          </a>
        </div>
      </header>

      <!-- Dashboard Body -->
      <main class="dashboard-main-body">
        
        <!-- Quick Action Buttons -->
        <div class="d-flex flex-wrap gap-2 mb-4 justify-content-between align-items-center">
          <h4 class="fw-bold mb-0">Overview & Activities</h4>
          <div class="d-flex gap-2">
            <a href="vehicles.php" class="btn btn-outline-primary btn-sm fw-semibold rounded-pill px-3">
              <i class="bi bi-plus-circle me-1"></i> Add Vehicle
            </a>
            <a href="../booking.php" class="btn btn-primary btn-sm fw-semibold rounded-pill px-3">
              <i class="bi bi-calendar2-plus me-1"></i> Book Service
            </a>
            <a href="../status.php" class="btn btn-warning btn-sm fw-semibold rounded-pill px-3 text-dark">
              <i class="bi bi-geo-alt me-1"></i> Track Service
            </a>
          </div>
        </div>

        <!-- 4 KPI Cards (Loaded from MySQL) -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="stat-title">My Vehicles</div>
                  <div class="stat-value" id="statCustVehicles"><?= $statVehiclesCount ?></div>
                </div>
                <div class="icon-wrapper icon-blue">
                  <i class="bi bi-car-front-fill"></i>
                </div>
              </div>
              <small class="text-muted mt-2 d-block"><a href="vehicles.php" class="text-decoration-none">View Garage &rarr;</a></small>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="stat-title">Active Bookings</div>
                  <div class="stat-value text-primary" id="statCustActiveBookings"><?= $statActiveBookingsCount ?></div>
                </div>
                <div class="icon-wrapper icon-amber">
                  <i class="bi bi-hourglass-split"></i>
                </div>
              </div>
              <small class="text-muted mt-2 d-block">Live workshop status</small>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="stat-title">Completed Services</div>
                  <div class="stat-value text-success" id="statCustCompletedServices"><?= $statCompletedServicesCount ?></div>
                </div>
                <div class="icon-wrapper icon-green">
                  <i class="bi bi-check2-circle"></i>
                </div>
              </div>
              <small class="text-muted mt-2 d-block">Quality verified</small>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="stat-title">Pending Payments</div>
                  <div class="stat-value text-danger" id="statCustPendingPayments"><?= format_currency($statPendingPaymentSum) ?></div>
                </div>
                <div class="icon-wrapper icon-red">
                  <i class="bi bi-credit-card-2-front"></i>
                </div>
              </div>
              <small class="text-muted mt-2 d-block">Pay upon pickup or online</small>
            </div>
          </div>
        </div>

        <!-- Active Service Progress Tracker Card -->
        <?php if ($activePrimaryBooking): ?>
          <?php
            $currentStatus = $activePrimaryBooking['status'];
            $currentIdx = array_search($currentStatus, $stages);
            $activeIdx = ($currentIdx !== false) ? $currentIdx : 0;
          ?>
          <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" id="activeServiceSection">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="bi bi-broadcast me-1"></i> LIVE SERVICE TRACKER</span>
                <span class="fw-bold text-dark font-monospace" id="liveBookingId"><?= htmlspecialchars($activePrimaryBooking['booking_id']) ?></span>
              </div>
              <a id="liveFullTrackBtn" href="../status.php?id=<?= urlencode($activePrimaryBooking['booking_id']) ?>" class="btn btn-sm btn-outline-primary fw-semibold rounded-pill">
                Inspect Milestones &rarr;
              </a>
            </div>
            
            <div class="card-body p-4">
              <div class="row align-items-center mb-4">
                <div class="col-md-7">
                  <h5 class="fw-bold mb-1" id="liveServiceName"><?= htmlspecialchars($activePrimaryBooking['service_name'] . ' – ' . $activePrimaryBooking['brand'] . ' ' . $activePrimaryBooking['model']) ?></h5>
                  <p class="text-muted small mb-0">Assigned Mechanic: <strong class="text-dark" id="liveMechanic"><?= htmlspecialchars($activePrimaryBooking['mechanic_name'] ?? 'Workshop Master Tech') ?></strong></p>
                </div>
                <div class="col-md-5 text-md-end mt-2 mt-md-0">
                  <span id="liveStatusBadgeWrapper">
                    <?= render_status_badge($activePrimaryBooking['status']) ?>
                  </span>
                </div>
              </div>

              <!-- Stepper Container -->
              <div class="stepper-timeline" id="custStepper">
                <?php foreach ($stages as $idx => $stg): ?>
                  <?php
                    $nodeClass = "";
                    $iconContent = $idx + 1;
                    if ($idx < $activeIdx) {
                        $nodeClass = "completed";
                        $iconContent = '<i class="bi bi-check-lg"></i>';
                    } elseif ($idx === $activeIdx) {
                        $nodeClass = "active";
                        $iconContent = '<i class="bi bi-gear-fill"></i>';
                    }
                  ?>
                  <div class="step-node <?= $nodeClass ?>">
                    <div class="step-circle"><?= $iconContent ?></div>
                    <span class="step-label"><?= htmlspecialchars($stg) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Recent Service History Table -->
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h5 class="fw-bold mb-0">My Service History & Recent Bookings</h5>
            <a href="bookings.php" class="btn btn-outline-secondary btn-sm rounded-pill">View All</a>
          </div>

          <div class="table-responsive">
            <table class="table table-custom align-middle">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Vehicle</th>
                  <th>Service</th>
                  <th>Date & Time</th>
                  <th>Specialist</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="custRecentBookingsTbody">
                <?php if (empty($recentBookings)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No booking records found. Click "Book Service" to get started!</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recentBookings as $b): ?>
                    <tr>
                      <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($b['booking_id']) ?></td>
                      <td>
                        <div class="fw-semibold"><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                        <small class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($b['registration_number']) ?></small>
                      </td>
                      <td><?= htmlspecialchars($b['service_name']) ?></td>
                      <td>
                        <?= format_date($b['booking_date']) ?>
                        <small class="text-muted d-block"><?= htmlspecialchars($b['booking_time']) ?></small>
                      </td>
                      <td>
                        <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($b['mechanic_name'] ?? 'Unassigned') ?></span>
                      </td>
                      <td class="fw-bold"><?= format_currency($b['amount']) ?></td>
                      <td><?= render_status_badge($b['status']) ?></td>
                      <td>
                        <a href="../status.php?id=<?= urlencode($b['booking_id']) ?>" class="btn btn-sm btn-outline-primary" title="Track Status">
                          <i class="bi bi-geo-alt"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
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
  <!-- Main JS for sidebar mobile toggles -->
  <script src="../js/main.js"></script>
</body>
</html>
