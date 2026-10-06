<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Book a Service (booking.php)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';

$currentUser = get_current_user_data();
$customerId = null;
$customerVehicles = [];

if ($currentUser) {
    $customerId = get_or_set_customer_id($pdo, $currentUser['user_id']);
    if ($customerId) {
        $stmtVeh = $pdo->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC");
        $stmtVeh->execute([$customerId]);
        $customerVehicles = $stmtVeh->fetchAll();
    }
}

// Fetch all active services from MySQL
try {
    $stmtSrv = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY price ASC");
    $servicesList = $stmtSrv->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch services: " . $e->getMessage());
    $servicesList = [];
}

$preselectedServiceId = trim($_GET['serviceId'] ?? '');
$bookingError = '';
$confirmedBooking = null;

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Session & Customer validation
    if (!$currentUser) {
        // Redirect guest to login with message
        header("Location: login.php?msg=login_required");
        exit;
    }

    $cName    = trim($_POST['customer_name'] ?? '');
    $cEmail   = trim(strtolower($_POST['customer_email'] ?? ''));
    $cPhone   = trim($_POST['customer_phone'] ?? '');

    $regNo    = strtoupper(trim(preg_replace('/\s+/', '', $_POST['vehicle_number'] ?? '')));
    $vType    = trim($_POST['vehicle_type'] ?? 'Hatchback');
    $vBrand   = trim($_POST['vehicle_brand'] ?? '');
    $vModel   = trim($_POST['vehicle_model'] ?? '');

    $serviceId = trim($_POST['service_id'] ?? '');
    $bookDate  = trim($_POST['booking_date'] ?? '');
    $bookTime  = trim($_POST['booking_time'] ?? '');
    $problem   = trim($_POST['problem_desc'] ?? '');

    // Validations
    if (empty($cName) || empty($cEmail) || empty($cPhone)) {
        $bookingError = "Customer name, email and phone number are required.";
    } elseif (!filter_var($cEmail, FILTER_VALIDATE_EMAIL)) {
        $bookingError = "Please enter a valid email address.";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $cPhone)) {
        $bookingError = "Please enter a valid 10-digit mobile number.";
    } elseif (empty($regNo) || !preg_match('/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/', $regNo)) {
        $bookingError = "Please enter a valid vehicle registration number (e.g. TN38AB1234).";
    } elseif (empty($vBrand) || empty($vModel)) {
        $bookingError = "Please specify vehicle brand and model.";
    } elseif (empty($serviceId)) {
        $bookingError = "Please select a service package.";
    } elseif (empty($bookDate) || empty($bookTime)) {
        $bookingError = "Please select your preferred service date and time slot.";
    } elseif (strtotime($bookDate) < strtotime(date('Y-m-d'))) {
        $bookingError = "Service date cannot be in the past.";
    } else {
        try {
            // 2. Query MySQL for actual service and database price (never trust browser)
            $stmtSrvCheck = $pdo->prepare("SELECT service_id, service_name, price, duration FROM services WHERE service_id = ? AND status = 'active' LIMIT 1");
            $stmtSrvCheck->execute([$serviceId]);
            $serviceData = $stmtSrvCheck->fetch();

            if (!$serviceData) {
                $bookingError = "The selected service package is invalid or currently inactive.";
            } else {
                $actualPrice = floatval($serviceData['price']);

                // 3. Validate Vehicle & Ownership
                $stmtVehCheck = $pdo->prepare("SELECT * FROM vehicles WHERE registration_number = ? LIMIT 1");
                $stmtVehCheck->execute([$regNo]);
                $existingVeh = $stmtVehCheck->fetch();

                $targetVehicleId = null;
                if ($existingVeh) {
                    // Check ownership
                    if ($existingVeh['customer_id'] !== $customerId) {
                        $bookingError = "This vehicle registration number is already registered to another customer account.";
                    } else {
                        $targetVehicleId = $existingVeh['vehicle_id'];
                    }
                } else {
                    // Register vehicle under current customer
                    $targetVehicleId = 'VEH-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
                    $insVeh = $pdo->prepare("INSERT INTO vehicles (vehicle_id, customer_id, registration_number, brand, model, year, fuel_type, last_service, next_service) VALUES (?, ?, ?, ?, ?, ?, 'Petrol', NULL, ?)");
                    $insVeh->execute([$targetVehicleId, $customerId, $regNo, $vBrand, $vModel, intval(date('Y')), $bookDate]);
                }

                if (!$bookingError && $targetVehicleId) {
                    // 4. Assign an available mechanic
                    $stmtMec = $pdo->query("SELECT mechanic_id, name FROM mechanics WHERE status = 'Available' LIMIT 1");
                    $assignedMec = $stmtMec->fetch();
                    if (!$assignedMec) {
                        $stmtMecDef = $pdo->query("SELECT mechanic_id, name FROM mechanics LIMIT 1");
                        $assignedMec = $stmtMecDef->fetch();
                    }
                    $mechanicId = $assignedMec ? $assignedMec['mechanic_id'] : null;
                    $mechanicName = $assignedMec ? $assignedMec['name'] : 'Master Technician';

                    // 5. Generate Booking ID
                    $bookingId = 'AC' . date('Ymd') . rand(1000, 9999);

                    $pdo->beginTransaction();

                    // 6. Insert booking with database price & initial status 'Pending'
                    $stmtInsBook = $pdo->prepare("INSERT INTO bookings (booking_id, customer_id, vehicle_id, service_id, mechanic_id, booking_date, booking_time, problem_description, status, amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, 'Pending')");
                    $stmtInsBook->execute([
                        $bookingId,
                        $customerId,
                        $targetVehicleId,
                        $serviceData['service_id'],
                        $mechanicId,
                        $bookDate,
                        $bookTime,
                        $problem ?: 'Periodic maintenance booking',
                        $actualPrice
                    ]);

                    // 7. Insert payment record
                    $paymentId = 'PAY' . date('Ymd') . rand(1000, 9999);
                    $stmtInsPay = $pdo->prepare("INSERT INTO payments (payment_id, booking_id, customer_name, amount, payment_method, payment_date, status) VALUES (?, ?, ?, ?, 'UPI', ?, 'Pending')");
                    $stmtInsPay->execute([
                        $paymentId,
                        $bookingId,
                        $cName,
                        $actualPrice,
                        $bookDate
                    ]);

                    $pdo->commit();

                    $confirmedBooking = [
                        'booking_id'    => $bookingId,
                        'customer_name' => $cName,
                        'vehicle'       => "$vBrand $vModel ($regNo)",
                        'service_name'  => $serviceData['service_name'],
                        'date_time'     => date('d M Y', strtotime($bookDate)) . " at " . $bookTime,
                        'mechanic_name' => $mechanicName,
                        'amount'        => $actualPrice
                    ];
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Booking error: " . $e->getMessage());
            $bookingError = "Unable to complete booking. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book a Vehicle Service – AutoCare</title>
  
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
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="booking.php">Book Service</a></li>
          <li class="nav-item"><a class="nav-link" href="status.php">Service Status</a></li>
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

  <!-- Page Header -->
  <section class="py-4 bg-light border-bottom">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h2 class="fw-bold mb-1">Book an Automotive Service</h2>
          <p class="text-muted small mb-0">Fill out your vehicle information and schedule a verified workshop appointment.</p>
        </div>
        <div class="col-md-4 text-md-end mt-2 mt-md-0">
          <span class="badge bg-primary-subtle text-primary border px-3 py-2">
            <i class="bi bi-shield-check me-1"></i> 100% Genuine Parts Guarantee
          </span>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Booking Area -->
  <section class="py-5">
    <div class="container">
      <div class="row g-4">
        
        <!-- Left Side: Booking Form -->
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">

            <?php if (!empty($bookingError)): ?>
              <div class="alert alert-danger py-2 small mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($bookingError) ?>
              </div>
            <?php endif; ?>

            <?php if (!$currentUser): ?>
              <div class="alert alert-warning py-3 mb-4 d-flex align-items-center justify-content-between">
                <div>
                  <i class="bi bi-info-circle-fill me-2"></i>
                  <strong>Please Sign In:</strong> Log into your customer account to choose from your registered garage or auto-track status.
                </div>
                <a href="login.php" class="btn btn-dark btn-sm px-3">Sign In</a>
              </div>
            <?php endif; ?>

            <form id="bookingForm" method="POST" action="booking.php" novalidate>
              
              <!-- Registered Customer Fast Select -->
              <?php if (!empty($customerVehicles)): ?>
                <div id="savedVehicleWrapper" class="mb-4 p-3 bg-light rounded-3 border">
                  <label class="form-label text-primary fw-bold">
                    <i class="bi bi-car-front-fill me-1"></i> Select from Your Registered Vehicles
                  </label>
                  <select id="userVehicleSelect" class="form-select">
                    <option value="">-- Choose registered vehicle --</option>
                    <?php foreach ($customerVehicles as $v): ?>
                      <option value="<?= htmlspecialchars($v['registration_number']) ?>" 
                              data-brand="<?= htmlspecialchars($v['brand']) ?>" 
                              data-model="<?= htmlspecialchars($v['model']) ?>"
                              data-fuel="<?= htmlspecialchars($v['fuel_type']) ?>">
                        <?= htmlspecialchars($v['registration_number']) ?> - <?= htmlspecialchars($v['brand'] . ' ' . $v['model'] . ' (' . $v['year'] . ')') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <small class="text-muted">Selecting your car auto-fills vehicle details below.</small>
                </div>
              <?php endif; ?>

              <!-- Section 1: Customer Contact Details -->
              <h5 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                <i class="bi bi-person-vcard"></i> 1. Customer Information
              </h5>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label for="bookCustomerName" class="form-label">Customer Name <span class="text-danger">*</span></label>
                  <input type="text" name="customer_name" id="bookCustomerName" class="form-control" placeholder="e.g. Rohan Sharma" value="<?= htmlspecialchars($_POST['customer_name'] ?? ($currentUser['name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-6">
                  <label for="bookCustomerPhone" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="tel" name="customer_phone" id="bookCustomerPhone" class="form-control" placeholder="9876501234" maxlength="10" value="<?= htmlspecialchars($_POST['customer_phone'] ?? ($currentUser['phone'] ?? '')) ?>" required>
                  </div>
                </div>
                <div class="col-12">
                  <label for="bookCustomerEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
                  <input type="email" name="customer_email" id="bookCustomerEmail" class="form-control" placeholder="rohan@example.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? ($currentUser['email'] ?? '')) ?>" required>
                </div>
              </div>

              <hr class="my-4">

              <!-- Section 2: Vehicle Details -->
              <h5 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                <i class="bi bi-truck-front"></i> 2. Vehicle Information
              </h5>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label for="bookVehicleNumber" class="form-label">Vehicle Registration Number <span class="text-danger">*</span></label>
                  <input type="text" name="vehicle_number" id="bookVehicleNumber" class="form-control font-monospace text-uppercase" placeholder="e.g. TN38AB1234" value="<?= htmlspecialchars($_POST['vehicle_number'] ?? ($customerVehicles[0]['registration_number'] ?? '')) ?>" required>
                  <small class="text-muted">Standard Indian plate format</small>
                </div>
                <div class="col-md-6">
                  <label for="bookVehicleType" class="form-label">Vehicle Body Type <span class="text-danger">*</span></label>
                  <select name="vehicle_type" id="bookVehicleType" class="form-select" required>
                    <option value="Hatchback">Hatchback (Swift, i20, etc.)</option>
                    <option value="Sedan">Sedan (City, Verna, Ciaz, etc.)</option>
                    <option value="Compact SUV">Compact SUV (Brezza, Venue, etc.)</option>
                    <option value="SUV">Mid/Full Size SUV (Creta, Harrier, etc.)</option>
                    <option value="Luxury">Luxury / German Sedan</option>
                    <option value="Electric">Electric Vehicle (EV)</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="bookVehicleBrand" class="form-label">Vehicle Brand <span class="text-danger">*</span></label>
                  <input type="text" name="vehicle_brand" id="bookVehicleBrand" class="form-control" placeholder="e.g. Maruti Suzuki, Hyundai, Honda" value="<?= htmlspecialchars($_POST['vehicle_brand'] ?? ($customerVehicles[0]['brand'] ?? '')) ?>" required>
                </div>
                <div class="col-md-6">
                  <label for="bookVehicleModel" class="form-label">Vehicle Model & Variant <span class="text-danger">*</span></label>
                  <input type="text" name="vehicle_model" id="bookVehicleModel" class="form-control" placeholder="e.g. Swift VXI, Creta SX" value="<?= htmlspecialchars($_POST['vehicle_model'] ?? ($customerVehicles[0]['model'] ?? '')) ?>" required>
                </div>
              </div>

              <hr class="my-4">

              <!-- Section 3: Service & Scheduling -->
              <h5 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                <i class="bi bi-wrench-adjustable"></i> 3. Service Package & Slot
              </h5>
              <div class="row g-3 mb-4">
                <div class="col-12">
                  <label for="bookService" class="form-label">Select Service Package <span class="text-danger">*</span></label>
                  <select name="service_id" id="bookService" class="form-select form-select-lg" required>
                    <option value="">-- Choose a Service Package --</option>
                    <?php foreach ($servicesList as $s): ?>
                      <?php 
                        $isSelected = ($preselectedServiceId === $s['service_id'] || ($_POST['service_id'] ?? '') === $s['service_id']); 
                      ?>
                      <option value="<?= htmlspecialchars($s['service_id']) ?>"
                              data-price="<?= htmlspecialchars($s['price']) ?>"
                              data-name="<?= htmlspecialchars($s['service_name']) ?>"
                              data-duration="<?= htmlspecialchars($s['duration']) ?>"
                              <?= $isSelected ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['service_name']) ?> – <?= format_currency($s['price']) ?> (<?= htmlspecialchars($s['duration']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="bookDate" class="form-label">Preferred Date <span class="text-danger">*</span></label>
                  <input type="date" name="booking_date" id="bookDate" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['booking_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>" required>
                </div>
                <div class="col-md-6">
                  <label for="bookTime" class="form-label">Preferred Time Slot <span class="text-danger">*</span></label>
                  <select name="booking_time" id="bookTime" class="form-select" required>
                    <option value="">-- Select Time Slot --</option>
                    <?php 
                      $slots = ["09:00 AM", "10:30 AM", "12:00 PM", "02:30 PM", "04:00 PM", "05:30 PM"];
                      $selectedSlot = $_POST['booking_time'] ?? '10:30 AM';
                      foreach ($slots as $slot) {
                          $sel = ($selectedSlot === $slot) ? 'selected' : '';
                          echo "<option value=\"$slot\" $sel>$slot Slot</option>";
                      }
                    ?>
                  </select>
                </div>
                <div class="col-12">
                  <label for="bookProblemDesc" class="form-label">Additional Problem Description (Optional)</label>
                  <textarea name="problem_desc" id="bookProblemDesc" class="form-control" rows="3" placeholder="Describe any specific issues, squeaks, noises, AC issues or custom requests..."><?= htmlspecialchars($_POST['problem_desc'] ?? '') ?></textarea>
                </div>
              </div>

              <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg fw-bold py-3 rounded-3 shadow">
                  <i class="bi bi-check2-circle me-2"></i> Confirm Booking
                </button>
              </div>

            </form>
          </div>
        </div>

        <!-- Right Side: Live Booking Summary Card -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 100px;">
            <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
              <h5 class="fw-bold mb-0">Booking Summary</h5>
              <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i> Live Preview</span>
            </div>

            <div class="mb-3">
              <small class="text-muted d-block text-uppercase fw-semibold">Target Vehicle</small>
              <div class="fw-bold text-dark fs-6" id="sumVehicle">Vehicle not specified yet</div>
            </div>

            <div class="mb-3">
              <small class="text-muted d-block text-uppercase fw-semibold">Selected Package</small>
              <div class="fw-bold text-primary fs-6" id="sumService">Select a service package</div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <small class="text-muted d-block text-uppercase fw-semibold">Date</small>
                <div class="fw-bold text-dark small" id="sumDate">Not selected</div>
              </div>
              <div class="col-6">
                <small class="text-muted d-block text-uppercase fw-semibold">Time Slot</small>
                <div class="fw-bold text-dark small" id="sumTime">Not selected</div>
              </div>
            </div>

            <hr class="my-3">

            <!-- Cost Breakdown (Calculated from MySQL service price) -->
            <div class="d-flex justify-content-between mb-2 small text-muted">
              <span>Service Subtotal:</span>
              <span class="fw-semibold text-dark" id="sumSubtotal">₹0</span>
            </div>
            <div class="d-flex justify-content-between mb-3 small text-muted">
              <span>Estimated GST (Inclusive):</span>
              <span class="fw-semibold text-dark" id="sumTaxes">Included</span>
            </div>

            <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center mb-4">
              <div>
                <span class="d-block text-muted small fw-semibold">TOTAL ESTIMATED</span>
                <span class="fs-4 fw-extrabold text-primary" id="sumTotal">₹0</span>
              </div>
              <span class="badge bg-success-subtle text-success border">Fixed Price</span>
            </div>

            <div class="small text-muted">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-check-circle-fill text-success"></i> Free 40-Point Safety Check included
              </div>
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-check-circle-fill text-success"></i> No cancellation fee up to 2 hours prior
              </div>
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill text-success"></i> Pay online or at service center pickup
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Booking Confirmation Modal (Rendered after successful PHP insertion) -->
  <?php if ($confirmedBooking): ?>
    <div class="modal fade show d-block" id="bookingConfirmationModal" tabindex="-1" style="background-color: rgba(0,0,0,0.6);" aria-modal="true" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-check-circle-fill me-2"></i> Service Booking Confirmed!
            </h5>
          </div>
          <div class="modal-body p-4 text-center">
            <div class="display-6 text-success mb-3"><i class="bi bi-patch-check"></i></div>
            <h4 class="fw-bold mb-1">Your appointment is scheduled!</h4>
            <p class="text-muted small mb-4">Please keep this Booking ID for vehicle tracking and workshop verification.</p>

            <div class="p-3 bg-light rounded-3 border mb-4 text-start">
              <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <span class="text-muted small">Booking ID:</span>
                <span class="fw-bold font-monospace text-primary fs-5"><?= htmlspecialchars($confirmedBooking['booking_id']) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-muted">Customer:</span>
                <span class="fw-semibold"><?= htmlspecialchars($confirmedBooking['customer_name']) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-muted">Vehicle:</span>
                <span class="fw-semibold"><?= htmlspecialchars($confirmedBooking['vehicle']) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-muted">Service Package:</span>
                <span class="fw-semibold text-primary"><?= htmlspecialchars($confirmedBooking['service_name']) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-muted">Scheduled Slot:</span>
                <span class="fw-semibold"><?= htmlspecialchars($confirmedBooking['date_time']) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-muted">Assigned Specialist:</span>
                <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($confirmedBooking['mechanic_name']) ?></span>
              </div>
              <div class="d-flex justify-content-between pt-2 border-top fw-bold">
                <span>Total Estimated Cost:</span>
                <span class="text-success"><?= format_currency($confirmedBooking['amount']) ?></span>
              </div>
            </div>

            <div class="d-flex flex-column gap-2">
              <a href="status.php?id=<?= urlencode($confirmedBooking['booking_id']) ?>" class="btn btn-primary fw-bold py-2 rounded-3">
                <i class="bi bi-geo-alt me-1"></i> Track Service Live Status
              </a>
              <a href="customer/bookings.php" class="btn btn-outline-secondary btn-sm py-2">
                <i class="bi bi-journal-text me-1"></i> View in My Bookings
              </a>
              <a href="index.php" class="btn btn-link text-muted btn-sm">Return to Home</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

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

  <!-- Live Summary Calculator & Registered Vehicle Autofill JS -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const vehicleSelect = document.getElementById("userVehicleSelect");
      const regNoInput = document.getElementById("bookVehicleNumber");
      const brandInput = document.getElementById("bookVehicleBrand");
      const modelInput = document.getElementById("bookVehicleModel");

      const serviceSelect = document.getElementById("bookService");
      const dateInput = document.getElementById("bookDate");
      const timeSelect = document.getElementById("bookTime");

      const sumVehicle = document.getElementById("sumVehicle");
      const sumService = document.getElementById("sumService");
      const sumDate = document.getElementById("sumDate");
      const sumTime = document.getElementById("sumTime");
      const sumSubtotal = document.getElementById("sumSubtotal");
      const sumTotal = document.getElementById("sumTotal");

      // Handle registered vehicle selection
      if (vehicleSelect) {
        vehicleSelect.addEventListener("change", () => {
          const selected = vehicleSelect.options[vehicleSelect.selectedIndex];
          if (vehicleSelect.value) {
            regNoInput.value = vehicleSelect.value;
            brandInput.value = selected.dataset.brand || "";
            modelInput.value = selected.dataset.model || "";
            updateSummary();
          }
        });
      }

      function updateSummary() {
        const reg = regNoInput.value.trim().toUpperCase();
        const brand = brandInput.value.trim();
        const model = modelInput.value.trim();

        if (brand || model) {
          sumVehicle.textContent = `${brand} ${model} ${reg ? '(' + reg + ')' : ''}`;
        } else {
          sumVehicle.textContent = "Vehicle not specified yet";
        }

        if (serviceSelect.selectedIndex > 0) {
          const opt = serviceSelect.options[serviceSelect.selectedIndex];
          const price = Number(opt.dataset.price) || 0;
          sumService.textContent = opt.dataset.name || opt.text;
          sumSubtotal.textContent = "₹" + price.toLocaleString('en-IN');
          sumTotal.textContent = "₹" + price.toLocaleString('en-IN');
        } else {
          sumService.textContent = "Select a service package";
          sumSubtotal.textContent = "₹0";
          sumTotal.textContent = "₹0";
        }

        if (dateInput.value) {
          const d = new Date(dateInput.value + 'T00:00:00');
          sumDate.textContent = isNaN(d) ? dateInput.value : d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
        } else {
          sumDate.textContent = "Not selected";
        }

        if (timeSelect.value) {
          sumTime.textContent = timeSelect.value;
        } else {
          sumTime.textContent = "Not selected";
        }
      }

      [regNoInput, brandInput, modelInput, serviceSelect, dateInput, timeSelect].forEach(el => {
        if (el) {
          el.addEventListener("input", updateSummary);
          el.addEventListener("change", updateSummary);
        }
      });

      // Initial update
      updateSummary();
    });
  </script>
</body>
</html>
