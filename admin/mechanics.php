<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Admin Mechanics Management (admin/mechanics.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

require_auth('admin', '../');

$currentUser = get_current_user_data();

$statusFilter = trim($_GET['status'] ?? 'All');
$mecMsg = '';
$mecError = '';

// Handle Mechanic Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_mechanic') {
        $name   = trim($_POST['name'] ?? '');
        $spec   = trim($_POST['specialization'] ?? '');
        $exp    = trim($_POST['experience'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $status = trim($_POST['status'] ?? 'Available');

        if (empty($name) || empty($spec) || empty($phone)) {
            $mecError = "Mechanic name, specialization and contact phone are required.";
        } else {
            try {
                // Generate mechanic ID
                $newId = 'MEC-' . str_pad((string)(($pdo->query("SELECT COUNT(*) FROM mechanics")->fetchColumn() ?: 0) + 1), 2, '0', STR_PAD_LEFT);
                $avatar = 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80';

                $stmtIns = $pdo->prepare("INSERT INTO mechanics (mechanic_id, name, specialization, experience, phone, status, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtIns->execute([$newId, $name, $spec, $exp, $phone, $status, $avatar]);
                $mecMsg = "Technician $name added to service crew!";
            } catch (PDOException $e) {
                error_log("Add mechanic error: " . $e->getMessage());
                $mecError = "Error creating mechanic.";
            }
        }
    } elseif ($action === 'toggle_status') {
        $mId = trim($_POST['mechanic_id'] ?? '');

        if (!empty($mId)) {
            try {
                $stmtCur = $pdo->prepare("SELECT status FROM mechanics WHERE mechanic_id = ?");
                $stmtCur->execute([$mId]);
                $cur = $stmtCur->fetchColumn();

                $next = ($cur === 'Available') ? 'Busy' : (($cur === 'Busy') ? 'On Leave' : 'Available');

                $stmtUpd = $pdo->prepare("UPDATE mechanics SET status = ? WHERE mechanic_id = ?");
                $stmtUpd->execute([$next, $mId]);
                $mecMsg = "Technician status updated to $next.";
            } catch (PDOException $e) {
                error_log("Toggle status error: " . $e->getMessage());
                $mecError = "Unable to toggle technician status.";
            }
        }
    } elseif ($action === 'edit_mechanic') {
        $mId   = trim($_POST['mechanic_id'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        $spec  = trim($_POST['specialization'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($mId) && !empty($name)) {
            try {
                $stmtUpd = $pdo->prepare("UPDATE mechanics SET name = ?, specialization = ?, phone = ? WHERE mechanic_id = ?");
                $stmtUpd->execute([$name, $spec, $phone, $mId]);
                $mecMsg = "Technician details updated successfully.";
            } catch (PDOException $e) {
                error_log("Edit mechanic error: " . $e->getMessage());
                $mecError = "Unable to update technician.";
            }
        }
    }
}

// Fetch mechanics from MySQL
try {
    $query = "SELECT * FROM mechanics";
    $params = [];

    if ($statusFilter !== 'All' && !empty($statusFilter)) {
        $query .= " WHERE status = ?";
        $params[] = $statusFilter;
    }

    $query .= " ORDER BY name ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $mechanics = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch mechanics error: " . $e->getMessage());
    $mechanics = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mechanic Crew Management – AutoCare Admin</title>
  
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
        <li><a href="vehicles.php"><i class="bi bi-car-front-fill"></i> Vehicles</a></li>
        <li><a href="mechanics.php" class="active"><i class="bi bi-tools"></i> Mechanics</a></li>
        
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
            <h5 class="fw-bold mb-0">Certified Mechanics & Technicians</h5>
            <small class="text-muted d-none d-sm-inline">Manage technician availability, specializations and service bay allocation</small>
          </div>
        </div>

        <button class="btn btn-primary btn-sm fw-semibold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addMechanicModal">
          <i class="bi bi-person-plus me-1"></i> Add Mechanic
        </button>
      </header>

      <!-- Main Body -->
      <main class="dashboard-main-body">

        <?php if (!empty($mecMsg)): ?>
          <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($mecMsg) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($mecError)): ?>
          <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($mecError) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        
        <!-- Filter Bar -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="fw-bold mb-0 text-muted">Workshop Floor Crew (<?= count($mechanics) ?> Listed)</h6>
            <div class="d-flex align-items-center gap-2">
              <label class="form-label mb-0 small text-muted">Status:</label>
              <select id="mechanicStatusFilter" class="form-select form-select-sm" onchange="location = this.value;" style="width: 160px;">
                <option value="mechanics.php?status=All" <?= ($statusFilter === 'All') ? 'selected' : '' ?>>All Technicians</option>
                <option value="mechanics.php?status=Available" <?= ($statusFilter === 'Available') ? 'selected' : '' ?>>Available</option>
                <option value="mechanics.php?status=Busy" <?= ($statusFilter === 'Busy') ? 'selected' : '' ?>>Busy</option>
                <option value="mechanics.php?status=On Leave" <?= ($statusFilter === 'On Leave') ? 'selected' : '' ?>>On Leave</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Mechanics Grid -->
        <div class="row" id="mechanicsListContainer">
          <?php if (empty($mechanics)): ?>
            <div class="col-12 text-center py-5 text-muted">No technicians found.</div>
          <?php else: ?>
            <?php foreach ($mechanics as $m): ?>
              <?php
                $badgeClass = "status-available";
                if ($m['status'] === "Busy") $badgeClass = "status-busy";
                if ($m['status'] === "On Leave") $badgeClass = "status-on-leave";
              ?>
              <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm border-0 rounded-4">
                  <div class="card-body p-4 text-center">
                    <img src="<?= htmlspecialchars($m['avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80') ?>"
                         class="rounded-circle mb-3 shadow-sm" width="80" height="80" alt="<?= htmlspecialchars($m['name']) ?>" style="object-fit: cover;">
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($m['name']) ?></h5>
                    <div class="text-primary fw-semibold small mb-2"><?= htmlspecialchars($m['specialization']) ?></div>
                    <div class="text-muted small mb-3">
                      <i class="bi bi-clock-history me-1"></i> <?= htmlspecialchars($m['experience']) ?> Experience &bull; <i class="bi bi-telephone ms-1 me-1"></i> +91 <?= htmlspecialchars($m['phone']) ?>
                    </div>
                    <div class="mb-3">
                      <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($m['status']) ?></span>
                    </div>
                    <div class="d-flex justify-content-center gap-2">
                      <form method="POST" action="mechanics.php" class="d-inline">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="mechanic_id" value="<?= htmlspecialchars($m['mechanic_id']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Toggle Status</button>
                      </form>
                      <button class="btn btn-sm btn-outline-secondary" onclick="openEditMechanic(<?= htmlspecialchars(json_encode($m)) ?>)">
                        Edit
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </main>

      <!-- Admin Footer -->
      <footer class="p-3 bg-white border-top text-center text-muted small mt-auto">
        &copy; 2026 AutoCare Portal &bull; Enterprise Workshop Management
      </footer>

    </div>
  </div>

  <!-- Add Mechanic Modal -->
  <div class="modal fade" id="addMechanicModal" tabindex="-1" aria-labelledby="addMechanicModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="addMechanicModalLabel"><i class="bi bi-person-plus text-primary me-2"></i> Add Certified Technician</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="mechanics.php">
          <input type="hidden" name="action" value="add_mechanic">
          <div class="modal-body p-4">
            <div class="mb-3">
              <label for="addMecName" class="form-label">Technician Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="addMecName" class="form-control" placeholder="e.g. Ramesh V" required>
            </div>
            <div class="mb-3">
              <label for="addMecSpec" class="form-label">Specialization Area <span class="text-danger">*</span></label>
              <input type="text" name="specialization" id="addMecSpec" class="form-control" placeholder="e.g. Transmission & Gearbox" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label for="addMecExp" class="form-label">Experience</label>
                <input type="text" name="experience" id="addMecExp" class="form-control" placeholder="e.g. 7 Years">
              </div>
              <div class="col-6">
                <label for="addMecStatus" class="form-label">Initial Status</label>
                <select name="status" id="addMecStatus" class="form-select">
                  <option value="Available" selected>Available</option>
                  <option value="Busy">Busy</option>
                  <option value="On Leave">On Leave</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label for="addMecPhone" class="form-label">Mobile Number <span class="text-danger">*</span></label>
              <input type="tel" name="phone" id="addMecPhone" class="form-control" placeholder="9876543210" maxlength="10" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Save Technician</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Mechanic Modal -->
  <div class="modal fade" id="editMechanicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Technician</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="mechanics.php">
          <input type="hidden" name="action" value="edit_mechanic">
          <input type="hidden" name="mechanic_id" id="editMecId">
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" id="editMecName" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Specialization</label>
              <input type="text" name="specialization" id="editMecSpec" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" id="editMecPhone" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Update Technician</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/main.js"></script>
  <script>
    function openEditMechanic(m) {
      document.getElementById("editMecId").value = m.mechanic_id;
      document.getElementById("editMecName").value = m.name;
      document.getElementById("editMecSpec").value = m.specialization;
      document.getElementById("editMecPhone").value = m.phone;

      const modalEl = document.getElementById("editMechanicModal");
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  </script>
</body>
</html>
