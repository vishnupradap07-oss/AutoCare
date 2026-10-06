<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Live Service Status Tracker (status.php)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';

$currentUser = get_current_user_data();

$searchId = trim($_GET['id'] ?? ($_POST['id'] ?? ''));
$bookingFound = null;
$searched = false;

if (!empty($searchId)) {
    $searched = true;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                b.*,
                v.registration_number,
                v.brand,
                v.model,
                s.service_name,
                s.duration,
                m.name AS mechanic_name,
                c.name AS customer_name,
                c.phone AS customer_phone
            FROM bookings b
            JOIN vehicles v ON b.vehicle_id = v.vehicle_id
            JOIN services s ON b.service_id = s.service_id
            LEFT JOIN mechanics m ON b.mechanic_id = m.mechanic_id
            JOIN customers c ON b.customer_id = c.customer_id
            WHERE UPPER(b.booking_id) = UPPER(?)
            LIMIT 1
        ");
        $stmt->execute([$searchId]);
        $bookingFound = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Status track error: " . $e->getMessage());
        $bookingFound = null;
    }
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
  <title>Vehicle Service Status Tracker – AutoCare</title>
  
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Custom Styles -->
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <!-- Navigation Bar -->
  <nav class="navbar navbar-expand-lg navbar-dark autocare-navbar">
    <div class="container">
      <a class="navbar-brand" href="index.php">
        <span class="brand-badge"><i class="bi bi-shield-shaded"></i></span>
        <span>Auto<span class="text-brand-accent">Care</span></span>
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="publicNavbar">
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
          <li class="nav-item"><a class="nav-link" href="booking.php">Book Service</a></li>
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="status.php">Service Status</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#why-choose">About</a></li>
          <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
        </ul>

        <div class="d-flex align-items-center gap-2" id="navAuthArea">
          <?php if ($currentUser): ?>
            <?php $dashUrl = ($currentUser['role'] === 'admin') ? 'admin/dashboard.php' : 'customer/dashboard.php'; ?>
            <a href="<?= $dashUrl ?>" class="btn btn-outline-light btn-sm px-3">
              <i class="bi bi-speedometer2 me-1"></i> Dashboard (<?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?>)
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm px-2" title="Sign Out">
              <i class="bi bi-box-arrow-right"></i>
            </a>
          <?php else: ?>
            <a href="login.php" class="btn btn-outline-light btn-sm px-3">
              <i class="bi bi-person me-1"></i> Login
            </a>
            <a href="register.php" class="btn btn-nav-book btn-sm px-3">
              <i class="bi bi-person-plus me-1"></i> Register
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Tracker Header -->
  <section class="py-5 bg-dark text-white position-relative" style="background: linear-gradient(135deg, #0b132b 0%, #1e3a8a 100%);">
    <div class="container py-2 text-center">
      <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-2">Live Digital Tracking</span>
      <h1 class="fw-bold text-white mb-2">Real-Time Vehicle Service Tracker</h1>
      <p class="text-white-50 mx-auto" style="max-width: 600px;">
        Track your car's exact servicing milestone from workshop reception through computerized inspection, mechanic servicing, quality assurance, and delivery.
      </p>

      <!-- Booking Lookup Box -->
      <div class="row justify-content-center mt-4">
        <div class="col-md-8 col-lg-6">
          <div class="card p-2 rounded-4 shadow-lg border-0 bg-white">
            <form action="status.php" method="GET" class="d-flex gap-2">
              <div class="input-group">
                <span class="input-group-text bg-transparent border-0 text-muted">
                  <i class="bi bi-search"></i>
                </span>
                <input type="text" name="id" class="form-control border-0 font-monospace text-uppercase" placeholder="Enter Booking ID (e.g. AC202610001)" value="<?= htmlspecialchars($searchId) ?>" required>
              </div>
              <button type="submit" class="btn btn-primary fw-bold px-4 rounded-3 text-nowrap">
                Track Vehicle
              </button>
            </form>
          </div>

          <!-- Quick Test Demo IDs -->
          <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mt-3 small text-white-50">
            <span>Quick Test IDs:</span>
            <a href="status.php?id=AC202610001" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill font-monospace">AC202610001 (In Progress)</a>
            <a href="status.php?id=AC202610004" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill font-monospace">AC202610004 (Inspection)</a>
            <a href="status.php?id=AC202610003" class="btn btn-sm btn-outline-light py-0 px-2 rounded-pill font-monospace">AC202610003 (Completed)</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Live Status Result Section -->
  <section class="py-5" id="statusResultSection">
    <div class="container">
      
      <?php if (!$searched): ?>
        <!-- Empty State Default -->
        <div id="statusEmptyPrompt" class="text-center py-5">
          <div class="display-3 text-muted mb-3"><i class="bi bi-speedometer2"></i></div>
          <h4 class="fw-bold text-dark">Enter your Booking ID above to track status</h4>
          <p class="text-muted">You can find your Booking ID on your confirmation receipt or in your customer dashboard.</p>
        </div>
      <?php elseif ($searched && !$bookingFound): ?>
        <!-- Not Found Prompt -->
        <div class="text-center py-5">
          <div class="display-3 text-danger mb-3"><i class="bi bi-x-circle"></i></div>
          <h4 class="fw-bold text-dark">Booking ID "<?= htmlspecialchars($searchId) ?>" not found</h4>
          <p class="text-muted">Please check your ID and try again, or click one of the quick test IDs above.</p>
        </div>
      <?php else: ?>
        <!-- Result Card Populated Directly from MySQL -->
        <?php
          $currentStatus = $bookingFound['status'];
          $currentIdx = array_search($currentStatus, $stages);
          $activeIdx = ($currentIdx !== false) ? $currentIdx : 0;
        ?>
        <div id="statusResultCard" class="card border-0 shadow-sm rounded-4 overflow-hidden">
          
          <!-- Header Banner -->
          <div class="p-4 bg-light border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fs-4 fw-extrabold text-primary font-monospace"><?= htmlspecialchars($bookingFound['booking_id']) ?></span>
                <?= render_status_badge($bookingFound['status']) ?>
              </div>
              <p class="text-muted small mb-0">Booked for <?= format_date($bookingFound['booking_date']) ?> (<?= htmlspecialchars($bookingFound['booking_time']) ?>)</p>
            </div>

            <div class="text-md-end">
              <span class="text-muted small d-block">Estimated Billing:</span>
              <span class="fs-4 fw-bold text-dark"><?= format_currency($bookingFound['amount']) ?></span>
              <span class="badge bg-secondary-subtle text-dark border ms-1"><?= htmlspecialchars($bookingFound['payment_status']) ?></span>
            </div>
          </div>

          <div class="card-body p-4 p-md-5">

            <!-- 6-Stage Visual Stepper Timeline -->
            <h5 class="fw-bold mb-4 text-center">Service Lifecycle Timeline</h5>
            
            <div class="position-relative">
              <div class="stepper-timeline" id="stepperContainer">
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

            <hr class="my-5">

            <!-- Vehicle & Customer Details Grid -->
            <div class="row g-4">
              <div class="col-md-6 col-lg-3">
                <div class="p-3 bg-light rounded-3 h-100">
                  <small class="text-muted fw-bold text-uppercase d-block mb-1">Customer</small>
                  <div class="fw-bold text-dark"><?= htmlspecialchars($bookingFound['customer_name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($bookingFound['customer_phone'] ?? 'Phone not listed') ?></small>
                </div>
              </div>

              <div class="col-md-6 col-lg-3">
                <div class="p-3 bg-light rounded-3 h-100">
                  <small class="text-muted fw-bold text-uppercase d-block mb-1">Vehicle</small>
                  <div class="fw-bold text-dark"><?= htmlspecialchars($bookingFound['brand'] . ' ' . $bookingFound['model']) ?></div>
                  <span class="badge bg-dark font-monospace text-uppercase"><?= htmlspecialchars($bookingFound['registration_number']) ?></span>
                </div>
              </div>

              <div class="col-md-6 col-lg-3">
                <div class="p-3 bg-light rounded-3 h-100">
                  <small class="text-muted fw-bold text-uppercase d-block mb-1">Service Package</small>
                  <div class="fw-bold text-primary"><?= htmlspecialchars($bookingFound['service_name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($bookingFound['booking_time']) ?> Slot</small>
                </div>
              </div>

              <div class="col-md-6 col-lg-3">
                <div class="p-3 bg-light rounded-3 h-100">
                  <small class="text-muted fw-bold text-uppercase d-block mb-1">Assigned Specialist</small>
                  <div class="fw-bold text-dark"><?= htmlspecialchars($bookingFound['mechanic_name'] ?? 'Workshop Technician') ?></div>
                  <small class="text-success"><i class="bi bi-shield-check"></i> Certified Master Tech</small>
                </div>
              </div>
            </div>

            <!-- Notes / Description -->
            <div class="mt-4 p-3 border rounded-3 bg-white">
              <small class="text-muted fw-bold text-uppercase d-block mb-1">Problem / Work Notes</small>
              <p class="mb-0 text-secondary"><?= nl2br(htmlspecialchars($bookingFound['problem_description'] ?? 'Standard maintenance inspection.')) ?></p>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
              <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Print Service Sheet
              </button>
              <a href="booking.php" class="btn btn-primary fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> Book Another Service
              </a>
            </div>

          </div>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <!-- Public Footer -->
  <footer class="autocare-footer">
    <div class="container">
      <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div>&copy; 2026 AutoCare Service Management System. Developed for Academic Project.</div>
        <div class="mt-2 mt-md-0">
          <a href="login.php" class="text-muted me-3">Admin Portal</a>
          <a href="login.php" class="text-muted">Customer Login</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bootstrap 5.3 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
