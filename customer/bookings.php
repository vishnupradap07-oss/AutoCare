<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Customer Bookings & History (customer/bookings.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

require_auth('customer', '../');

$currentUser = get_current_user_data();
$customerId = get_or_set_customer_id($pdo, $currentUser['user_id']);

$statusFilter = trim($_GET['filter'] ?? 'All');
$msgSuccess = '';
$msgError = '';

// Cancel Booking Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    $bId = trim($_POST['booking_id'] ?? '');
    if (!empty($bId)) {
        try {
            // Strictly check ownership and allow cancellation only if Pending or Confirmed
            $stmtCancel = $pdo->prepare("UPDATE bookings SET status = 'Cancelled' WHERE booking_id = ? AND customer_id = ? AND status IN ('Pending', 'Confirmed')");
            $stmtCancel->execute([$bId, $customerId]);

            if ($stmtCancel->rowCount() > 0) {
                $msgSuccess = "Booking $bId has been cancelled.";
            } else {
                $msgError = "Unable to cancel booking (it may already be in service or not found).";
            }
        } catch (PDOException $e) {
            error_log("Cancel booking error: " . $e->getMessage());
            $msgError = "Error updating booking status.";
        }
    }
}

// Fetch all bookings for this customer
try {
    $query = "
        SELECT 
            b.*,
            v.registration_number,
            v.brand,
            v.model,
            s.service_name,
            s.duration,
            m.name AS mechanic_name
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        JOIN services s ON b.service_id = s.service_id
        LEFT JOIN mechanics m ON b.mechanic_id = m.mechanic_id
        WHERE b.customer_id = ?
    ";
    $params = [$customerId];

    if ($statusFilter === 'Completed') {
        $query .= " AND b.status = 'Completed'";
    } elseif ($statusFilter === 'Cancelled') {
        $query .= " AND b.status = 'Cancelled'";
    } elseif ($statusFilter === 'Active') {
        $query .= " AND b.status NOT IN ('Completed', 'Cancelled')";
    }

    $query .= " ORDER BY b.booking_date DESC, b.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch customer bookings error: " . $e->getMessage());
    $bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Bookings & Service History – AutoCare</title>
  
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
        <li><a href="bookings.php" class="<?= ($statusFilter !== 'Completed') ? 'active' : '' ?>"><i class="bi bi-card-checklist"></i> My Bookings</a></li>
        <li><a href="bookings.php?filter=Completed" class="<?= ($statusFilter === 'Completed') ? 'active' : '' ?>"><i class="bi bi-clock-history"></i> Service History</a></li>
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
            <h5 class="fw-bold mb-0"><?= ($statusFilter === 'Completed') ? 'My Completed Service History' : 'My Service Bookings' ?></h5>
            <small class="text-muted d-none d-sm-inline">Real-time status tracking, appointment management and past invoices</small>
          </div>
        </div>

        <a href="../booking.php" class="btn btn-primary btn-sm fw-semibold rounded-pill px-3">
          <i class="bi bi-calendar2-plus me-1"></i> New Booking
        </a>
      </header>

      <!-- Main Body -->
      <main class="dashboard-main-body">

        <?php if (!empty($msgSuccess)): ?>
          <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($msgSuccess) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($msgError)): ?>
          <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($msgError) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        
        <!-- Filter and Search Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
          <div class="row g-3 align-items-center">
            <div class="col-md-5">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="custBookingSearch" class="form-control border-start-0" placeholder="Search by Booking ID, vehicle or service...">
              </div>
            </div>

            <div class="col-md-7">
              <div class="d-flex flex-wrap gap-2 justify-content-md-end" id="statusFilterTabs">
                <a href="bookings.php" class="btn btn-sm rounded-pill px-3 <?= ($statusFilter === 'All') ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
                <a href="bookings.php?filter=Active" class="btn btn-sm rounded-pill px-3 <?= ($statusFilter === 'Active') ? 'btn-primary' : 'btn-outline-secondary' ?>">Active / In Progress</a>
                <a href="bookings.php?filter=Completed" class="btn btn-sm rounded-pill px-3 <?= ($statusFilter === 'Completed') ? 'btn-primary' : 'btn-outline-secondary' ?>">Completed</a>
                <a href="bookings.php?filter=Cancelled" class="btn btn-sm rounded-pill px-3 <?= ($statusFilter === 'Cancelled') ? 'btn-primary' : 'btn-outline-secondary' ?>">Cancelled</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Bookings Table Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="customerBookingsTable">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Vehicle</th>
                  <th>Service</th>
                  <th>Scheduled Date</th>
                  <th>Time Slot</th>
                  <th>Status</th>
                  <th>Estimated Cost</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="custBookingsTbody">
                <?php if (empty($bookings)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                      No matching bookings found. Click "New Booking" to schedule a maintenance service!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($bookings as $b): ?>
                    <tr class="booking-row" 
                        data-search="<?= htmlspecialchars(strtolower($b['booking_id'] . ' ' . $b['brand'] . ' ' . $b['model'] . ' ' . $b['registration_number'] . ' ' . $b['service_name'])) ?>">
                      <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($b['booking_id']) ?></td>
                      <td>
                        <div class="fw-bold"><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($b['registration_number']) ?></span>
                      </td>
                      <td><?= htmlspecialchars($b['service_name']) ?></td>
                      <td><?= format_date($b['booking_date']) ?></td>
                      <td><span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($b['booking_time']) ?></span></td>
                      <td><?= render_status_badge($b['status']) ?></td>
                      <td class="fw-bold"><?= format_currency($b['amount']) ?></td>
                      <td>
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-info" onclick="viewBookingDetails(<?= htmlspecialchars(json_encode($b)) ?>)" title="View Details">
                            <i class="bi bi-info-circle"></i>
                          </button>
                          <a href="../status.php?id=<?= urlencode($b['booking_id']) ?>" class="btn btn-outline-primary" title="Live Tracker">
                            <i class="bi bi-geo-alt"></i>
                          </a>
                          <?php if (in_array($b['status'], ['Pending', 'Confirmed'])): ?>
                            <form method="POST" action="bookings.php" onsubmit="return confirm('Cancel this appointment?');" class="d-inline">
                              <input type="hidden" name="action" value="cancel_booking">
                              <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['booking_id']) ?>">
                              <button type="submit" class="btn btn-outline-danger" title="Cancel Booking">
                                <i class="bi bi-x-circle"></i>
                              </button>
                            </form>
                          <?php endif; ?>
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

      <!-- Customer Footer -->
      <footer class="p-3 bg-white border-top text-center text-muted small mt-auto">
        &copy; 2026 AutoCare Portal &bull; Customer Management Area
      </footer>

    </div>
  </div>

  <!-- Booking View Details Modal -->
  <div class="modal fade" id="bookingDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-info-circle text-primary me-2"></i> Booking Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <span class="font-monospace fw-bold text-primary fs-5" id="detBookingId">-</span>
            <span id="detStatusBadge"></span>
          </div>

          <div class="row g-3 small">
            <div class="col-6">
              <span class="text-muted d-block">Vehicle:</span>
              <strong id="detVehicle">-</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block">Service Package:</span>
              <strong id="detService">-</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block">Scheduled Date:</span>
              <strong id="detDate">-</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block">Time Slot:</span>
              <strong id="detTime">-</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block">Assigned Specialist:</span>
              <strong id="detMechanic">-</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block">Payment Status:</span>
              <strong id="detPayment">-</strong>
            </div>
            <div class="col-12">
              <span class="text-muted d-block">Problem / Notes:</span>
              <div class="p-2 bg-light rounded border mt-1" id="detNotes">-</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <a id="detTrackLink" href="#" class="btn btn-primary btn-sm fw-bold px-3">
            <i class="bi bi-geo-alt me-1"></i> Live Tracker
          </a>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/main.js"></script>

  <script>
    function viewBookingDetails(b) {
      document.getElementById("detBookingId").textContent = b.booking_id;
      document.getElementById("detStatusBadge").innerHTML = `<span class="badge bg-primary">${b.status}</span>`;
      document.getElementById("detVehicle").textContent = `${b.brand} ${b.model} (${b.registration_number})`;
      document.getElementById("detService").textContent = b.service_name;
      document.getElementById("detDate").textContent = b.booking_date;
      document.getElementById("detTime").textContent = b.booking_time;
      document.getElementById("detMechanic").textContent = b.mechanic_name || "Assigned Master Tech";
      document.getElementById("detPayment").textContent = `${b.payment_status} (₹${Number(b.amount).toLocaleString('en-IN')})`;
      document.getElementById("detNotes").textContent = b.problem_description || "Routine maintenance";
      document.getElementById("detTrackLink").href = `../status.php?id=${encodeURIComponent(b.booking_id)}`;

      const modalEl = document.getElementById("bookingDetailsModal");
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }

    // Client-side quick filter search on current table
    document.getElementById("custBookingSearch").addEventListener("input", function() {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll(".booking-row").forEach(row => {
        const text = row.dataset.search || "";
        row.style.display = text.includes(q) ? "" : "none";
      });
    });
  </script>
</body>
</html>
