<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Customer Garage & Vehicles (customer/vehicles.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

require_auth('customer', '../');

$currentUser = get_current_user_data();
$customerId = get_or_set_customer_id($pdo, $currentUser['user_id']);

$vehSuccess = '';
$vehError = '';

// Handle Vehicle Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_vehicle') {
        $reg   = strtoupper(trim(preg_replace('/\s+/', '', $_POST['registration_number'] ?? '')));
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $year  = intval($_POST['year'] ?? 0);
        $fuel  = trim($_POST['fuel_type'] ?? 'Petrol');

        if (empty($reg) || !preg_match('/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/', $reg)) {
            $vehError = "Invalid registration number format (e.g. TN38AB1234).";
        } elseif (empty($brand) || empty($model)) {
            $vehError = "Please specify vehicle brand and model.";
        } elseif ($year < 1995 || $year > intval(date('Y')) + 1) {
            $vehError = "Manufacturing year must be between 1995 and " . (intval(date('Y')) + 1) . ".";
        } else {
            try {
                // Check if vehicle registration number already exists
                $stmtChk = $pdo->prepare("SELECT vehicle_id FROM vehicles WHERE registration_number = ? LIMIT 1");
                $stmtChk->execute([$reg]);
                if ($stmtChk->fetch()) {
                    $vehError = "A vehicle with registration number $reg is already registered.";
                } else {
                    $newVehId = 'VEH-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                    $stmtIns = $pdo->prepare("INSERT INTO vehicles (vehicle_id, customer_id, registration_number, brand, model, year, fuel_type, last_service, next_service) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
                    $stmtIns->execute([$newVehId, $customerId, $reg, $brand, $model, $year, $fuel]);
                    $vehSuccess = "Vehicle $brand $model ($reg) added to your garage successfully!";
                }
            } catch (PDOException $e) {
                error_log("Add vehicle error: " . $e->getMessage());
                $vehError = "Something went wrong while saving your vehicle.";
            }
        }
    } elseif ($action === 'edit_vehicle') {
        $vId   = trim($_POST['vehicle_id'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $year  = intval($_POST['year'] ?? 0);
        $fuel  = trim($_POST['fuel_type'] ?? 'Petrol');

        if (empty($vId) || empty($brand) || empty($model)) {
            $vehError = "Brand and model cannot be empty.";
        } else {
            try {
                // Enforce ownership: WHERE vehicle_id = ? AND customer_id = ?
                $stmtUpd = $pdo->prepare("UPDATE vehicles SET brand = ?, model = ?, year = ?, fuel_type = ? WHERE vehicle_id = ? AND customer_id = ?");
                $stmtUpd->execute([$brand, $model, $year, $fuel, $vId, $customerId]);

                if ($stmtUpd->rowCount() > 0) {
                    $vehSuccess = "Vehicle details updated successfully!";
                } else {
                    $vehError = "Vehicle could not be updated or ownership check failed.";
                }
            } catch (PDOException $e) {
                error_log("Edit vehicle error: " . $e->getMessage());
                $vehError = "Unable to update vehicle.";
            }
        }
    } elseif ($action === 'delete_vehicle') {
        $vId = trim($_POST['vehicle_id'] ?? '');

        if (!empty($vId)) {
            try {
                // Check if bookings exist for this vehicle
                $stmtChkBook = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE vehicle_id = ?");
                $stmtChkBook->execute([$vId]);
                $bookingCount = $stmtChkBook->fetchColumn();

                if ($bookingCount > 0) {
                    $vehError = "Cannot remove vehicle because it is linked to active or past service orders.";
                } else {
                    // Enforce ownership: WHERE vehicle_id = ? AND customer_id = ?
                    $stmtDel = $pdo->prepare("DELETE FROM vehicles WHERE vehicle_id = ? AND customer_id = ?");
                    $stmtDel->execute([$vId, $customerId]);

                    if ($stmtDel->rowCount() > 0) {
                        $vehSuccess = "Vehicle removed from your garage.";
                    } else {
                        $vehError = "Unable to remove vehicle or permission denied.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Delete vehicle error: " . $e->getMessage());
                $vehError = "Error removing vehicle.";
            }
        }
    }
}

// Fetch all customer vehicles with strict ownership check
try {
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$customerId]);
    $vehicles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch vehicles error: " . $e->getMessage());
    $vehicles = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Garage & Vehicles – AutoCare</title>
  
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
        <li><a href="vehicles.php" class="active"><i class="bi bi-car-front-fill"></i> My Vehicles</a></li>
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
            <h5 class="fw-bold mb-0">My Registered Vehicles</h5>
            <small class="text-muted d-none d-sm-inline">Manage and track maintenance intervals for your personal fleet</small>
          </div>
        </div>

        <button class="btn btn-primary btn-sm fw-semibold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
          <i class="bi bi-plus-lg me-1"></i> Add New Vehicle
        </button>
      </header>

      <!-- Main Body -->
      <main class="dashboard-main-body">

        <?php if (!empty($vehSuccess)): ?>
          <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($vehSuccess) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($vehError)): ?>
          <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($vehError) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        
        <div class="row g-4" id="vehiclesCardGrid">
          <?php if (empty($vehicles)): ?>
            <div class="col-12 text-center py-5">
              <div class="display-4 text-muted mb-3"><i class="bi bi-car-front"></i></div>
              <h5 class="fw-bold">No vehicles registered yet</h5>
              <p class="text-muted">Add your car to track service milestones and schedule maintenance.</p>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                <i class="bi bi-plus-lg me-1"></i> Add First Vehicle
              </button>
            </div>
          <?php else: ?>
            <?php foreach ($vehicles as $v): ?>
              <div class="col-md-6 col-xl-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                  <div class="p-4 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                      <h5 class="fw-bold mb-0"><?= htmlspecialchars($v['brand'] . ' ' . $v['model']) ?></h5>
                      <span class="badge bg-light text-dark border font-monospace mt-1"><?= htmlspecialchars($v['registration_number']) ?></span>
                    </div>
                    <div class="icon-wrapper icon-blue rounded-3 p-2">
                      <i class="bi bi-car-front-fill fs-4"></i>
                    </div>
                  </div>
                  
                  <div class="card-body p-4">
                    <div class="row g-2 mb-3 small">
                      <div class="col-6">
                        <span class="text-muted d-block">Year:</span>
                        <strong><?= htmlspecialchars($v['year']) ?></strong>
                      </div>
                      <div class="col-6">
                        <span class="text-muted d-block">Fuel:</span>
                        <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($v['fuel_type'] ?? 'Petrol') ?></span>
                      </div>
                      <div class="col-6 mt-2">
                        <span class="text-muted d-block">Last Service:</span>
                        <span class="text-secondary"><?= format_date($v['last_service']) ?></span>
                      </div>
                      <div class="col-6 mt-2">
                        <span class="text-muted d-block">Next Service:</span>
                        <span class="text-primary fw-semibold"><?= format_date($v['next_service']) ?></span>
                      </div>
                    </div>

                    <div class="pt-3 border-top d-flex gap-2">
                      <a href="../booking.php?serviceId=SRV-01" class="btn btn-primary btn-sm flex-grow-1 fw-semibold">
                        <i class="bi bi-calendar2-plus me-1"></i> Book Service
                      </a>
                      <button class="btn btn-outline-secondary btn-sm" onclick="openEditVehicleModal(<?= htmlspecialchars(json_encode($v)) ?>)" title="Edit Vehicle">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <form method="POST" action="vehicles.php" onsubmit="return confirm('Are you sure you want to remove this vehicle?');" class="d-inline">
                        <input type="hidden" name="action" value="delete_vehicle">
                        <input type="hidden" name="vehicle_id" value="<?= htmlspecialchars($v['vehicle_id']) ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </main>

      <!-- Customer Footer -->
      <footer class="p-3 bg-white border-top text-center text-muted small mt-auto">
        &copy; 2026 AutoCare Portal &bull; Customer Management Area
      </footer>

    </div>
  </div>

  <!-- Add Vehicle Modal -->
  <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="addVehicleModalLabel"><i class="bi bi-car-front text-primary me-2"></i> Register New Vehicle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="vehicles.php">
          <input type="hidden" name="action" value="add_vehicle">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Registration Number <span class="text-danger">*</span></label>
              <input type="text" name="registration_number" class="form-control font-monospace text-uppercase" placeholder="e.g. TN38KL9999" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Brand <span class="text-danger">*</span></label>
                <input type="text" name="brand" class="form-control" placeholder="e.g. Volkswagen" required>
              </div>
              <div class="col-6">
                <label class="form-label">Model <span class="text-danger">*</span></label>
                <input type="text" name="model" class="form-control" placeholder="e.g. Taigun GT" required>
              </div>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Manufacturing Year <span class="text-danger">*</span></label>
                <input type="number" name="year" class="form-control" min="1995" max="<?= date('Y') + 1 ?>" value="2023" required>
              </div>
              <div class="col-6">
                <label class="form-label">Fuel Type <span class="text-danger">*</span></label>
                <select name="fuel_type" class="form-select" required>
                  <option value="Petrol" selected>Petrol</option>
                  <option value="Diesel">Diesel</option>
                  <option value="Electric">Electric</option>
                  <option value="CNG">CNG</option>
                  <option value="Hybrid">Hybrid</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Save Vehicle</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Vehicle Modal -->
  <div class="modal fade" id="editVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i> Update Vehicle Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="vehicles.php">
          <input type="hidden" name="action" value="edit_vehicle">
          <input type="hidden" name="vehicle_id" id="editVehId">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Registration Number</label>
              <input type="text" id="editVehReg" class="form-control font-monospace" readonly disabled>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Brand <span class="text-danger">*</span></label>
                <input type="text" name="brand" id="editVehBrand" class="form-control" required>
              </div>
              <div class="col-6">
                <label class="form-label">Model <span class="text-danger">*</span></label>
                <input type="text" name="model" id="editVehModel" class="form-control" required>
              </div>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Manufacturing Year <span class="text-danger">*</span></label>
                <input type="number" name="year" id="editVehYear" class="form-control" min="1995" max="<?= date('Y') + 1 ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label">Fuel Type <span class="text-danger">*</span></label>
                <select name="fuel_type" id="editVehFuel" class="form-select" required>
                  <option value="Petrol">Petrol</option>
                  <option value="Diesel">Diesel</option>
                  <option value="Electric">Electric</option>
                  <option value="CNG">CNG</option>
                  <option value="Hybrid">Hybrid</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Update Details</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/main.js"></script>
  <script>
    function openEditVehicleModal(v) {
      document.getElementById("editVehId").value = v.vehicle_id;
      document.getElementById("editVehReg").value = v.registration_number;
      document.getElementById("editVehBrand").value = v.brand;
      document.getElementById("editVehModel").value = v.model;
      document.getElementById("editVehYear").value = v.year;
      document.getElementById("editVehFuel").value = v.fuel_type || "Petrol";

      const modalEl = document.getElementById("editVehicleModal");
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  </script>
</body>
</html>
