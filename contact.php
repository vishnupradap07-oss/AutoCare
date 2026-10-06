<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Contact Us (contact.php)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';

$currentUser = get_current_user_data();

$contactSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cName = trim($_POST['c_name'] ?? '');
    $cEmail = trim($_POST['c_email'] ?? '');
    $cMessage = trim($_POST['c_message'] ?? '');

    if (!empty($cName) && filter_var($cEmail, FILTER_VALIDATE_EMAIL) && !empty($cMessage)) {
        $contactSuccess = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact & Workshop Location – AutoCare</title>
  
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
          <li class="nav-item"><a class="nav-link" href="status.php">Service Status</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#why-choose">About</a></li>
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="contact.php">Contact</a></li>
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
  <section class="py-5 bg-dark text-white position-relative" style="background: linear-gradient(135deg, #0b132b 0%, #1e293b 100%);">
    <div class="container py-2 text-center">
      <span class="badge bg-primary px-3 py-2 rounded-pill mb-2">We're Here to Help</span>
      <h1 class="fw-bold text-white mb-2">Contact AutoCare Workshop</h1>
      <p class="text-white-50 mx-auto" style="max-width: 600px;">
        Have a question about a complex repair, custom quote, or corporate fleet maintenance? Get in touch with our team.
      </p>
    </div>
  </section>

  <!-- Contact Body -->
  <section class="py-5">
    <div class="container">
      <div class="row g-5">
        
        <!-- Left Column: Info Cards -->
        <div class="col-lg-5">
          <h4 class="fw-bold mb-4">Workshop Headquarters</h4>
          
          <div class="d-flex gap-3 mb-4">
            <div class="feature-icon-box flex-shrink-0" style="width: 50px; height: 50px;">
              <i class="bi bi-geo-alt-fill fs-5"></i>
            </div>
            <div>
              <h6 class="fw-bold mb-1">Service Center Address</h6>
              <p class="text-muted small mb-0">
                104, Auto Hub Road, SIDCO Industrial Estate,<br>
                Near Avinashi Road Flyover,<br>
                Coimbatore, Tamil Nadu – 641014
              </p>
            </div>
          </div>

          <div class="d-flex gap-3 mb-4">
            <div class="feature-icon-box flex-shrink-0" style="width: 50px; height: 50px;">
              <i class="bi bi-telephone-fill fs-5"></i>
            </div>
            <div>
              <h6 class="fw-bold mb-1">Direct Phone Support</h6>
              <p class="text-muted small mb-0">
                Workshop Desk: +91 98765 43210<br>
                24/7 Roadside Assistance: 1800 200 4567 (Toll Free)
              </p>
            </div>
          </div>

          <div class="d-flex gap-3 mb-4">
            <div class="feature-icon-box flex-shrink-0" style="width: 50px; height: 50px;">
              <i class="bi bi-envelope-fill fs-5"></i>
            </div>
            <div>
              <h6 class="fw-bold mb-1">Email Inquiries</h6>
              <p class="text-muted small mb-0">
                Support: support@autocare.com<br>
                Corporate Fleet: corporate@autocare.com
              </p>
            </div>
          </div>

          <div class="d-flex gap-3 mb-4">
            <div class="feature-icon-box flex-shrink-0" style="width: 50px; height: 50px;">
              <i class="bi bi-clock-fill fs-5"></i>
            </div>
            <div>
              <h6 class="fw-bold mb-1">Operational Hours</h6>
              <p class="text-muted small mb-0">
                Monday to Saturday: 8:00 AM – 7:30 PM<br>
                Sunday: Closed for maintenance
              </p>
            </div>
          </div>
        </div>

        <!-- Right Column: Contact Form -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
            <h4 class="fw-bold mb-2">Send Us a Direct Message</h4>
            <p class="text-muted small mb-4">Fill out the quick form below and our service desk team will get back to you within 2 working hours.</p>

            <?php if ($contactSuccess): ?>
              <div class="alert alert-success py-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Thank you! Your message has been received. Our workshop team will get back to you shortly.
              </div>
            <?php endif; ?>

            <form method="POST" action="contact.php">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Your Name <span class="text-danger">*</span></label>
                  <input type="text" name="c_name" class="form-control" placeholder="Rohan Sharma" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email Address <span class="text-danger">*</span></label>
                  <input type="email" name="c_email" class="form-control" placeholder="rohan@example.com" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Subject</label>
                  <input type="text" name="c_subject" class="form-control" placeholder="Inquiry regarding Brake System Check or Custom Service">
                </div>
                <div class="col-12">
                  <label class="form-label">Message Details <span class="text-danger">*</span></label>
                  <textarea name="c_message" class="form-control" rows="5" placeholder="Please provide details about your vehicle issue, preferred schedule, or service inquiries..." required></textarea>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm">
                    <i class="bi bi-send-fill me-1"></i> Send Message
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

      </div>
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
