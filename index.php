<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Homepage (index.php)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';

$currentUser = get_current_user_data();

// Fetch featured services from MySQL
try {
    $stmtServices = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY price ASC LIMIT 6");
    $featuredServices = $stmtServices->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch services: " . $e->getMessage());
    $featuredServices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare – Vehicle Service & Maintenance Management System</title>
  
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
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="services.php">Services</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="booking.php">Book Service</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="status.php">Service Status</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#why-choose">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="contact.php">Contact</a>
          </li>
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

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="container position-relative">
      <div class="row align-items-center g-5">
        <div class="col-lg-6">
          <div class="hero-pill">
            <i class="bi bi-patch-check-fill text-warning"></i>
            <span>#1 Rated Multi-Brand Auto Service Network</span>
          </div>
          <h1 class="hero-title">
            Your Car. <br>
            <span class="gradient-text">Our Care.</span>
          </h1>
          <p class="hero-subtitle">
            Professional vehicle servicing made simple, transparent and reliable. Experience hassle-free maintenance with genuine parts, certified technicians, and real-time status tracking.
          </p>

          <div class="d-flex flex-wrap gap-3">
            <a href="booking.php" class="btn btn-primary btn-lg px-4 py-3 fw-bold rounded-3 shadow">
              <i class="bi bi-calendar2-check me-2"></i> Book a Service
            </a>
            <a href="services.php" class="btn btn-outline-light btn-lg px-4 py-3 fw-bold rounded-3">
              <i class="bi bi-grid-fill me-2"></i> Explore Services
            </a>
          </div>

          <!-- Quick Service Track Input -->
          <div class="mt-4 pt-3">
            <div class="bg-white bg-opacity-10 p-2 rounded-4 border border-white border-opacity-25" style="max-width: 480px;">
              <form action="status.php" method="GET" class="d-flex gap-2">
                <div class="input-group">
                  <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-search"></i></span>
                  <input type="text" name="id" class="form-control bg-transparent border-0 text-white placeholder-light" placeholder="Track with Booking ID (e.g. AC202610001)" required>
                </div>
                <button type="submit" class="btn btn-warning fw-bold px-3 text-nowrap rounded-3">
                  Track
                </button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="hero-graphic-card">
            <div class="hero-image-wrapper">
              <img src="https://images.unsplash.com/photo-1613214149922-f1809c99b414?auto=format&fit=crop&w=1000&q=80" alt="Modern automobile service center diagnostics">
              <div class="floating-glass-badge">
                <div class="bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                  <i class="bi bi-shield-check fs-5"></i>
                </div>
                <div>
                  <div class="fw-bold text-white small">Comprehensive 60-Point Check</div>
                  <div class="text-white-50 extra-small">OEM Genuine Warranty Protection</div>
                </div>
                <span class="badge bg-primary ms-auto">Verified</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Live Statistics Bar -->
      <div class="hero-stats-banner">
        <div class="row g-4">
          <div class="col-6 col-md-3">
            <div class="stat-item">
              <div class="stat-number">5000+</div>
              <div class="stat-label">Vehicles Serviced</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-item">
              <div class="stat-number">25+</div>
              <div class="stat-label">Certified Mechanics</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-item">
              <div class="stat-number">10+</div>
              <div class="stat-label">Years Experience</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-item">
              <div class="stat-number">98%</div>
              <div class="stat-label">Customer Satisfaction</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- Why Choose AutoCare Section -->
  <section class="py-5 my-4" id="why-choose">
    <div class="container">
      <div class="text-center mb-5">
        <span class="section-tag">Quality & Assurance</span>
        <h2 class="section-title">WHY CHOOSE AUTOCARE</h2>
        <p class="section-subtitle">We combine state-of-the-art diagnostic technology with master certified technicians to deliver dealer-grade service at honest prices.</p>
      </div>

      <div class="row g-4">
        <div class="col-md-6 col-lg-3">
          <div class="feature-card">
            <div class="feature-icon-box">
              <i class="bi bi-award"></i>
            </div>
            <h5 class="fw-bold mb-2">Certified Mechanics</h5>
            <p class="text-muted small mb-0">ASE and factory-certified automobile specialists with hands-on multi-brand experience.</p>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="feature-card">
            <div class="feature-icon-box">
              <i class="bi bi-patch-check"></i>
            </div>
            <h5 class="fw-bold mb-2">Genuine Parts</h5>
            <p class="text-muted small mb-0">100% OEM and OES certified spare parts guaranteed with warranty protection on all repairs.</p>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="feature-card">
            <div class="feature-icon-box">
              <i class="bi bi-tag"></i>
            </div>
            <h5 class="fw-bold mb-2">Transparent Pricing</h5>
            <p class="text-muted small mb-0">Upfront estimates with zero hidden costs. Review and approve all part replacements beforehand.</p>
          </div>
        </div>

        <div class="col-md-6 col-lg-3">
          <div class="feature-card">
            <div class="feature-icon-box">
              <i class="bi bi-phone"></i>
            </div>
            <h5 class="fw-bold mb-2">Digital Service Tracking</h5>
            <p class="text-muted small mb-0">Track your vehicle from arrival through inspection, service, and test drive in real-time online.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Popular Services Preview (Loaded from MySQL) -->
  <section class="py-5 bg-white border-top border-bottom">
    <div class="container">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-5">
        <div>
          <span class="section-tag">Popular Packages</span>
          <h2 class="section-title mb-0">Featured Automotive Services</h2>
        </div>
        <a href="services.php" class="btn btn-outline-primary fw-semibold mt-3 mt-md-0">
          View All Services <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>

      <div class="row g-4" id="homeServicesGrid">
        <?php foreach ($featuredServices as $srv): ?>
          <?php
            // Sample image fallback matching categories
            $srvImg = 'https://images.unsplash.com/photo-1486006920555-c77dce18193b?auto=format&fit=crop&w=600&q=80';
            if (stripos($srv['service_name'], 'Oil') !== false) {
                $srvImg = 'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($srv['service_name'], 'Brake') !== false) {
                $srvImg = 'https://images.unsplash.com/photo-1578844251758-2f71da64c96f?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($srv['service_name'], 'AC') !== false) {
                $srvImg = 'https://images.unsplash.com/photo-1580273916550-e323be2ae537?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($srv['service_name'], 'Wheel') !== false) {
                $srvImg = 'https://images.unsplash.com/photo-1580273916550-e323be2ae537?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($srv['service_name'], 'Diagnostics') !== false) {
                $srvImg = 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=600&q=80';
            }
          ?>
          <div class="col-md-6 col-lg-4">
            <div class="service-card h-100 d-flex flex-column">
              <div class="service-card-header">
                <img src="<?= htmlspecialchars($srvImg) ?>" alt="<?= htmlspecialchars($srv['service_name']) ?>">
                <span class="service-badge"><?= htmlspecialchars($srv['category']) ?></span>
              </div>
              <div class="service-card-body d-flex flex-column flex-grow-1">
                <h5 class="fw-bold mb-2"><?= htmlspecialchars($srv['service_name']) ?></h5>
                <p class="text-muted small mb-3 flex-grow-1"><?= htmlspecialchars($srv['description']) ?></p>
                <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top">
                  <div>
                    <div class="service-price"><?= format_currency($srv['price']) ?></div>
                    <span class="service-duration"><i class="bi bi-clock me-1"></i> <?= htmlspecialchars($srv['duration']) ?></span>
                  </div>
                  <a href="booking.php?serviceId=<?= urlencode($srv['service_id']) ?>" class="btn btn-primary btn-sm px-3 fw-semibold rounded-pill">
                    Book Now
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- How It Works Section -->
  <section class="py-5 my-4">
    <div class="container">
      <div class="text-center mb-5">
        <span class="section-tag">Simplified Process</span>
        <h2 class="section-title">HOW IT WORKS</h2>
        <p class="section-subtitle">Servicing your vehicle is simple and effortless with our structured 4-step digital workflow.</p>
      </div>

      <div class="row g-4">
        <div class="col-6 col-md-3">
          <div class="step-card">
            <div class="step-number">1</div>
            <h5 class="fw-bold mb-2">Book</h5>
            <p class="text-muted small">Choose your desired service package and schedule your preferred date and time online.</p>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="step-card">
            <div class="step-number">2</div>
            <h5 class="fw-bold mb-2">Inspect</h5>
            <p class="text-muted small">Bring in your car or opt for pickup. Our master technicians perform an initial digital audit.</p>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="step-card">
            <div class="step-number">3</div>
            <h5 class="fw-bold mb-2">Service</h5>
            <p class="text-muted small">Vehicle repairs and maintenance are carried out with genuine parts and live status updates.</p>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="step-card">
            <div class="step-number">4</div>
            <h5 class="fw-bold mb-2">Deliver</h5>
            <p class="text-muted small">Road-tested, washed, sanitized, and delivered back to you with comprehensive warranty coverage.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Customer Testimonials -->
  <section class="py-5 bg-light border-top">
    <div class="container">
      <div class="text-center mb-5">
        <span class="section-tag">User Reviews</span>
        <h2 class="section-title">What Our Customers Say</h2>
        <p class="section-subtitle">Real experiences from vehicle owners who rely on AutoCare every day.</p>
      </div>

      <div class="row g-4">
        <div class="col-md-4">
          <div class="testimonial-card">
            <div class="rating-stars">
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
            </div>
            <p class="text-secondary small mb-4">
              "The live status tracking feature is phenomenal! I could see exactly when my Creta was in inspection and when the brake pads were being installed. Completely transparent."
            </p>
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                RS
              </div>
              <div>
                <h6 class="fw-bold mb-0">Rohan Sharma</h6>
                <small class="text-muted">Hyundai Creta Owner</small>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="testimonial-card">
            <div class="rating-stars">
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
            </div>
            <p class="text-secondary small mb-4">
              "Very reasonable pricing compared to authorized dealership service centers. Genuine parts used, and the car drives like brand new after the general service."
            </p>
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-success text-white fw-bold d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                PV
              </div>
              <div>
                <h6 class="fw-bold mb-0">Priya Venkat</h6>
                <small class="text-muted">Tata Nexon EV Owner</small>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="testimonial-card">
            <div class="rating-stars">
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-half"></i>
            </div>
            <p class="text-secondary small mb-4">
              "Arun Kumar, the engine specialist, diagnosed a strange vibration issue in my Honda City that two other garages couldn't identify. Top notch workmanship!"
            </p>
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-warning text-white fw-bold d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                AP
              </div>
              <div>
                <h6 class="fw-bold mb-0">Anand Prakash</h6>
                <small class="text-muted">Honda City Owner</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Banner -->
  <section class="py-5" style="background: linear-gradient(135deg, #0b132b, #1d4ed8); color: white;">
    <div class="container text-center py-4">
      <h2 class="fw-bold text-white mb-3">Ready to Experience Hassle-Free Vehicle Care?</h2>
      <p class="text-white-50 mb-4 max-w-600 mx-auto">Book your slot online in under 2 minutes and get instant upfront pricing with digital status tracking.</p>
      <div class="d-flex justify-content-center gap-3">
        <a href="booking.php" class="btn btn-warning btn-lg px-4 py-3 fw-bold rounded-pill shadow">
          <i class="bi bi-calendar-check me-2"></i> Book Appointment Now
        </a>
        <a href="contact.php" class="btn btn-outline-light btn-lg px-4 py-3 fw-semibold rounded-pill">
          Contact Service Center
        </a>
      </div>
    </div>
  </section>

  <!-- Public Footer -->
  <footer class="autocare-footer">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4">
          <a class="navbar-brand mb-3 d-inline-flex" href="index.php">
            <span class="brand-badge"><i class="bi bi-shield-shaded"></i></span>
            <span>Auto<span class="text-brand-accent">Care</span></span>
          </a>
          <p class="small text-muted mb-4">
            Professional automobile service & maintenance management system. Setting the gold standard in certified mechanical repairs, transparent pricing, and digital tracking.
          </p>
          <div class="social-links">
            <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
            <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>

        <div class="col-6 col-md-3 col-lg-2">
          <h5>Quick Links</h5>
          <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="services.php">Services</a></li>
            <li><a href="booking.php">Book Service</a></li>
            <li><a href="status.php">Track Status</a></li>
            <li><a href="contact.php">Contact Us</a></li>
          </ul>
        </div>

        <div class="col-6 col-md-3 col-lg-3">
          <h5>Our Services</h5>
          <ul>
            <li><a href="services.php">General Maintenance</a></li>
            <li><a href="services.php">Synthetic Oil Change</a></li>
            <li><a href="services.php">Brake Overhaul</a></li>
            <li><a href="services.php">AC Leak Check & Gas</a></li>
            <li><a href="services.php">3D Wheel Alignment</a></li>
            <li><a href="services.php">OBD-II Engine Diagnostics</a></li>
          </ul>
        </div>

        <div class="col-md-6 col-lg-3">
          <h5>Contact Info</h5>
          <ul class="text-muted small">
            <li class="mb-2"><i class="bi bi-geo-alt text-primary me-2"></i> 104, Auto Hub Road, Industrial Estate, Coimbatore, TN - 641014</li>
            <li class="mb-2"><i class="bi bi-telephone text-primary me-2"></i> +91 98765 43210</li>
            <li class="mb-2"><i class="bi bi-envelope text-primary me-2"></i> support@autocare.com</li>
            <li class="mb-2"><i class="bi bi-clock text-primary me-2"></i> Mon – Sat: 8:00 AM – 7:30 PM</li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div>
          &copy; 2026 AutoCare Service Management System. Developed for Academic Project.
        </div>
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
