<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Admin Booking Management & Floor Dispatch (admin/bookings.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

require_auth('admin', '../');

$currentUser = get_current_user_data();

$statusFilter = trim($_GET['status'] ?? 'All');
$msgSuccess = '';
$msgError = '';

// Handle Admin Booking Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
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

                // When completing a service, record in service_history if not already present
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

                $msgSuccess = "Booking $bId status updated to \"$status\" successfully.";
            } catch (PDOException $e) {
                error_log("Admin update status error: " . $e->getMessage());
                $msgError = "Error updating booking status.";
            }
        }
    } elseif ($action === 'assign_mechanic') {
        $bId   = trim($_POST['booking_id'] ?? '');
        $mecId = trim($_POST['mechanic_id'] ?? '');

        if (!empty($bId) && !empty($mecId)) {
            try {
                $stmtMec = $pdo->prepare("UPDATE bookings SET mechanic_id = ? WHERE booking_id = ?");
                $stmtMec->execute([$mecId, $bId]);
                $msgSuccess = "Assigned mechanic to booking $bId successfully.";
            } catch (PDOException $e) {
                error_log("Assign mechanic error: " . $e->getMessage());
                $msgError = "Unable to assign mechanic.";
            }
        }
    } elseif ($action === 'delete_booking') {
        $bId = trim($_POST['booking_id'] ?? '');

        if (!empty($bId)) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM bookings WHERE booking_id = ?");
                $stmtDel->execute([$bId]);
                $msgSuccess = "Booking $bId cancelled and removed.";
            } catch (PDOException $e) {
                error_log("Delete booking error: " . $e->getMessage());
                $msgError = "Could not remove booking.";
            }
        }
    }
}

// Fetch all mechanics for assignment modal
try {
    $mechanicsList = $pdo->query("SELECT * FROM mechanics ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $mechanicsList = [];
}

// Fetch bookings with full JOIN
try {
    $query = "
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
    ";
    $params = [];

    if ($statusFilter !== 'All' && !empty($statusFilter)) {
        $query .= " WHERE b.status = ?";
        $params[] = $statusFilter;
    }

    $query .= " ORDER BY b.booking_date DESC, b.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch admin bookings error: " . $e->getMessage());
    $bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Service Bookings & Floor Dispatch – AutoCare Admin</title>
  
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
        <li><a href="bookings.php" class="active"><i class="bi bi-card-checklist"></i> Bookings</a></li>
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
            <h5 class="fw-bold mb-0">Workshop Service Bookings</h5>
            <small class="text-muted d-none d-sm-inline">Assign mechanics, manage job card milestones and update vehicle statuses</small>
          </div>
        </div>

        <a href="../booking.php" class="btn btn-primary btn-sm fw-semibold rounded-pill px-3">
          <i class="bi bi-plus-circle me-1"></i> New Service Job
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
        
        <!-- Search and Filter Bar -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
          <div class="row g-3 align-items-center">
            <div class="col-md-7">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="bookingSearch" class="form-control border-start-0" placeholder="Search by Booking ID, customer name, vehicle reg, or service...">
              </div>
            </div>

            <div class="col-md-5">
              <div class="d-flex align-items-center gap-2 justify-content-md-end">
                <label class="form-label mb-0 small text-muted text-nowrap">Filter Status:</label>
                <select id="bookingStatusFilter" class="form-select form-select-sm" onchange="location = this.value;" style="width: 180px;">
                  <option value="bookings.php?status=All" <?= ($statusFilter === 'All') ? 'selected' : '' ?>>All Statuses</option>
                  <option value="bookings.php?status=Pending" <?= ($statusFilter === 'Pending') ? 'selected' : '' ?>>Pending</option>
                  <option value="bookings.php?status=Confirmed" <?= ($statusFilter === 'Confirmed') ? 'selected' : '' ?>>Confirmed</option>
                  <option value="bookings.php?status=Vehicle Received" <?= ($statusFilter === 'Vehicle Received') ? 'selected' : '' ?>>Vehicle Received</option>
                  <option value="bookings.php?status=Inspection" <?= ($statusFilter === 'Inspection') ? 'selected' : '' ?>>Inspection</option>
                  <option value="bookings.php?status=Service In Progress" <?= ($statusFilter === 'Service In Progress') ? 'selected' : '' ?>>Service In Progress</option>
                  <option value="bookings.php?status=Ready for Delivery" <?= ($statusFilter === 'Ready for Delivery') ? 'selected' : '' ?>>Ready for Delivery</option>
                  <option value="bookings.php?status=Completed" <?= ($statusFilter === 'Completed') ? 'selected' : '' ?>>Completed</option>
                  <option value="bookings.php?status=Cancelled" <?= ($statusFilter === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Bookings Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="adminBookingsTable">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Customer</th>
                  <th>Vehicle</th>
                  <th>Service</th>
                  <th>Scheduled Date</th>
                  <th>Assigned Tech</th>
                  <th>Status</th>
                  <th>Amount</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($bookings)): ?>
                  <tr><td colspan="9" class="text-center py-5 text-muted">No service bookings match the criteria.</td></tr>
                <?php else: ?>
                  <?php foreach ($bookings as $b): ?>
                    <tr class="b-row" data-search="<?= htmlspecialchars(strtolower($b['booking_id'] . ' ' . $b['customer_name'] . ' ' . $b['registration_number'] . ' ' . $b['brand'] . ' ' . $b['model'] . ' ' . $b['service_name'])) ?>">
                      <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($b['booking_id']) ?></td>
                      <td>
                        <div class="fw-bold"><?= htmlspecialchars($b['customer_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($b['customer_phone'] ?? '') ?></small>
                      </td>
                      <td>
                        <div class="fw-medium"><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($b['registration_number']) ?></span>
                      </td>
                      <td><span class="fw-semibold"><?= htmlspecialchars($b['service_name']) ?></span></td>
                      <td>
                        <div><?= format_date($b['booking_date']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($b['booking_time']) ?></small>
                      </td>
                      <td>
                        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($b['mechanic_name'] ?? 'Unassigned') ?></span>
                      </td>
                      <td><?= render_status_badge($b['status']) ?></td>
                      <td class="fw-bold"><?= format_currency($b['amount']) ?></td>
                      <td>
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-primary" onclick="openStatusModal('<?= htmlspecialchars($b['booking_id']) ?>', '<?= htmlspecialchars($b['customer_name']) ?>', '<?= htmlspecialchars($b['status']) ?>')" title="Update Status">
                            <i class="bi bi-arrow-repeat"></i>
                          </button>
                          <button class="btn btn-outline-secondary" onclick="openAssignModal('<?= htmlspecialchars($b['booking_id']) ?>', '<?= htmlspecialchars($b['mechanic_id'] ?? '') ?>')" title="Assign Mechanic">
                            <i class="bi bi-person-gear"></i>
                          </button>
                          <form method="POST" action="bookings.php" onsubmit="return confirm('Cancel/Delete booking <?= htmlspecialchars($b['booking_id']) ?>?');" class="d-inline">
                            <input type="hidden" name="action" value="delete_booking">
                            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['booking_id']) ?>">
                            <button type="submit" class="btn btn-outline-danger" title="Cancel/Delete">
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

  <!-- Update Status Modal -->
  <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat text-primary me-2"></i> Update Service Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="bookings.php">
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

  <!-- Assign Mechanic Modal -->
  <div class="modal fade" id="assignMechanicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-person-gear text-primary me-2"></i> Assign Workshop Specialist</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="bookings.php">
          <input type="hidden" name="action" value="assign_mechanic">
          <input type="hidden" name="booking_id" id="assignModalBookingId">
          <div class="modal-body p-4">
            <div class="mb-3">
              <span class="text-muted small d-block">Booking ID:</span>
              <strong class="font-monospace text-primary fs-5" id="assignModalDisplayId"></strong>
            </div>
            <div class="mb-3">
              <label for="assignMechanicSelect" class="form-label">Available Specialist <span class="text-danger">*</span></label>
              <select name="mechanic_id" id="assignMechanicSelect" class="form-select form-select-lg" required>
                <?php foreach ($mechanicsList as $m): ?>
                  <option value="<?= htmlspecialchars($m['mechanic_id']) ?>">
                    <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['specialization']) ?> - <?= htmlspecialchars($m['status']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Assign Specialist</button>
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

    function openAssignModal(bookingId, currentMechanicId) {
      document.getElementById("assignModalBookingId").value = bookingId;
      document.getElementById("assignModalDisplayId").textContent = bookingId;
      if (currentMechanicId) {
        document.getElementById("assignMechanicSelect").value = currentMechanicId;
      }

      const modalEl = document.getElementById("assignMechanicModal");
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }

    // Filter table by text search
    document.getElementById("bookingSearch").addEventListener("input", function() {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll(".b-row").forEach(row => {
        const text = row.dataset.search || "";
        row.style.display = text.includes(q) ? "" : "none";
      });
    });
  </script>
</body>
</html>
