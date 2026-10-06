<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Admin Services Management (admin/services.php)
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../auth_helper.php';

require_auth('admin', '../');

$currentUser = get_current_user_data();

$srvMsg = '';
$srvError = '';

// Handle Service Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_service') {
        $name     = trim($_POST['service_name'] ?? '');
        $category = trim($_POST['category'] ?? 'Maintenance');
        $price    = floatval($_POST['price'] ?? 0);
        $duration = trim($_POST['duration'] ?? '1 - 2 Hours');
        $desc     = trim($_POST['description'] ?? '');

        if (empty($name) || $price <= 0) {
            $srvError = "Package name and a valid standard price are required.";
        } else {
            try {
                $count = ($pdo->query("SELECT COUNT(*) FROM services")->fetchColumn() ?: 0) + 1;
                $newId = 'SRV-' . str_pad((string)$count, 2, '0', STR_PAD_LEFT);

                $stmtIns = $pdo->prepare("INSERT INTO services (service_id, service_name, category, description, price, duration, icon, status) VALUES (?, ?, ?, ?, ?, ?, 'bi-wrench-adjustable-circle', 'active')");
                $stmtIns->execute([$newId, $name, $category, $desc, $price, $duration]);
                $srvMsg = "Service package \"$name\" ($newId) created successfully at " . format_currency($price) . "!";
            } catch (PDOException $e) {
                error_log("Add service error: " . $e->getMessage());
                $srvError = "Failed to add service package.";
            }
        }
    } elseif ($action === 'update_price') {
        $srvId    = trim($_POST['service_id'] ?? '');
        $newPrice = floatval($_POST['new_price'] ?? 0);

        if (!empty($srvId) && $newPrice > 0) {
            try {
                $stmtUpd = $pdo->prepare("UPDATE services SET price = ? WHERE service_id = ?");
                $stmtUpd->execute([$newPrice, $srvId]);
                $srvMsg = "Price for $srvId updated to " . format_currency($newPrice) . " in MySQL database.";
            } catch (PDOException $e) {
                error_log("Update service price error: " . $e->getMessage());
                $srvError = "Unable to update service price.";
            }
        } else {
            $srvError = "Please enter a valid positive price.";
        }
    } elseif ($action === 'delete_service') {
        $srvId = trim($_POST['service_id'] ?? '');

        if (!empty($srvId)) {
            try {
                // Check if bookings use this service
                $chkBook = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE service_id = ?");
                $chkBook->execute([$srvId]);
                $bCount = $chkBook->fetchColumn();

                if ($bCount > 0) {
                    $srvError = "Cannot delete service $srvId because $bCount bookings reference it. You may set its status to inactive instead.";
                } else {
                    $stmtDel = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
                    $stmtDel->execute([$srvId]);
                    $srvMsg = "Service $srvId removed from catalog.";
                }
            } catch (PDOException $e) {
                error_log("Delete service error: " . $e->getMessage());
                $srvError = "Error removing service.";
            }
        }
    }
}

// Fetch all services from MySQL
try {
    $stmt = $pdo->query("SELECT * FROM services ORDER BY price ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch services error: " . $e->getMessage());
    $services = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Service Catalog Management – AutoCare Admin</title>
  
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
        <li><a href="mechanics.php"><i class="bi bi-tools"></i> Mechanics</a></li>
        
        <div class="sidebar-heading mt-3">Catalog & Finance</div>
        <li><a href="services.php" class="active"><i class="bi bi-gear-wide-connected"></i> Services</a></li>
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
            <h5 class="fw-bold mb-0">Service Catalog & Package Pricing</h5>
            <small class="text-muted d-none d-sm-inline">Configure workshop offerings, standard labor hours and package rates stored in MySQL</small>
          </div>
        </div>

        <button class="btn btn-primary btn-sm fw-semibold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addServiceModal">
          <i class="bi bi-plus-lg me-1"></i> Add Service Package
        </button>
      </header>

      <!-- Main Body -->
      <main class="dashboard-main-body">

        <?php if (!empty($srvMsg)): ?>
          <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($srvMsg) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($srvError)): ?>
          <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($srvError) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        
        <!-- Services Table Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="adminServicesTable">
              <thead>
                <tr>
                  <th>Service Code</th>
                  <th>Package Name & Scope</th>
                  <th>Category</th>
                  <th>Standard Price</th>
                  <th>Duration</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($services)): ?>
                  <tr><td colspan="7" class="text-center py-4 text-muted">No service packages registered.</td></tr>
                <?php else: ?>
                  <?php foreach ($services as $s): ?>
                    <tr>
                      <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($s['service_id']) ?></td>
                      <td>
                        <div class="fw-bold"><?= htmlspecialchars($s['service_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars(substr($s['description'], 0, 75)) ?>...</small>
                      </td>
                      <td><span class="badge bg-primary-subtle text-primary border"><?= htmlspecialchars($s['category']) ?></span></td>
                      <td class="fw-bold fs-6 text-dark"><?= format_currency($s['price']) ?></td>
                      <td><?= htmlspecialchars($s['duration']) ?></td>
                      <td><span class="status-badge status-completed"><?= htmlspecialchars(ucfirst($s['status'])) ?></span></td>
                      <td>
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-primary" onclick="openUpdatePriceModal('<?= htmlspecialchars($s['service_id']) ?>', '<?= htmlspecialchars($s['service_name']) ?>', '<?= htmlspecialchars($s['price']) ?>')" title="Update Price">
                            <i class="bi bi-tag"></i>
                          </button>
                          <form method="POST" action="services.php" onsubmit="return confirm('Delete service <?= htmlspecialchars($s['service_id']) ?>?');" class="d-inline">
                            <input type="hidden" name="action" value="delete_service">
                            <input type="hidden" name="service_id" value="<?= htmlspecialchars($s['service_id']) ?>">
                            <button type="submit" class="btn btn-outline-danger" title="Delete">
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

  <!-- Add Service Modal -->
  <div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="addServiceModalLabel"><i class="bi bi-plus-circle text-primary me-2"></i> Add Service Package</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="services.php">
          <input type="hidden" name="action" value="add_service">
          <div class="modal-body p-4">
            <div class="mb-3">
              <label for="addSrvName" class="form-label">Service Package Name <span class="text-danger">*</span></label>
              <input type="text" name="service_name" id="addSrvName" class="form-control" placeholder="e.g. Complete Ceramic Coating" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label for="addSrvCategory" class="form-label">Category</label>
                <select name="category" id="addSrvCategory" class="form-select">
                  <option value="Maintenance">Maintenance</option>
                  <option value="Engine">Engine</option>
                  <option value="Brakes">Brakes</option>
                  <option value="AC">AC</option>
                  <option value="Electrical">Electrical</option>
                  <option value="Detailing">Detailing</option>
                </select>
              </div>
              <div class="col-6">
                <label for="addSrvPrice" class="form-label">Standard Price (₹) <span class="text-danger">*</span></label>
                <input type="number" name="price" id="addSrvPrice" class="form-control" placeholder="2499" min="100" required>
              </div>
            </div>
            <div class="mb-3">
              <label for="addSrvDuration" class="form-label">Standard Turnaround Duration</label>
              <input type="text" name="duration" id="addSrvDuration" class="form-control" placeholder="e.g. 2 - 3 Hours" required>
            </div>
            <div class="mb-3">
              <label for="addSrvDesc" class="form-label">Service Description</label>
              <textarea name="description" id="addSrvDesc" class="form-control" rows="3" placeholder="Provide checklist items covered in this package..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Publish Service</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Update Price Modal -->
  <div class="modal fade" id="updatePriceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-tag text-primary me-2"></i> Update Service Price</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="services.php">
          <input type="hidden" name="action" value="update_price">
          <input type="hidden" name="service_id" id="priceSrvId">
          <div class="modal-body p-4">
            <div class="mb-2">
              <span class="text-muted small">Package:</span>
              <strong id="priceSrvName" class="d-block text-dark"></strong>
            </div>
            <div class="mb-3">
              <label for="priceInput" class="form-label">New Standard Rate (₹) <span class="text-danger">*</span></label>
              <input type="number" name="new_price" id="priceInput" class="form-control form-control-lg font-monospace" min="50" step="1" required>
              <small class="text-muted">This price will be stored in MySQL and used for all future bookings.</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Save Price</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/main.js"></script>
  <script>
    function openUpdatePriceModal(id, name, currentPrice) {
      document.getElementById("priceSrvId").value = id;
      document.getElementById("priceSrvName").textContent = name + " (" + id + ")";
      document.getElementById("priceInput").value = currentPrice;

      const modalEl = document.getElementById("updatePriceModal");
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  </script>
</body>
</html>
