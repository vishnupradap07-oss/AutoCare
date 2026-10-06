<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Services Catalog (services.php)
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';

$currentUser = get_current_user_data();

try {
    $stmt = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY price ASC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch services: " . $e->getMessage());
    $services = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Service Packages & Pricing – AutoCare</title>
  
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
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="services.php">Services</a></li>
          <li class="nav-item"><a class="nav-link" href="booking.php">Book Service</a></li>
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

  <!-- Page Banner -->
  <section class="py-5 bg-dark text-white position-relative" style="background: linear-gradient(135deg, #0b132b 0%, #1e293b 100%);">
    <div class="container py-3">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <span class="badge bg-primary px-3 py-2 rounded-pill mb-2">Transparent & Guaranteed</span>
          <h1 class="text-white fw-bold display-5 mb-2">Our Service Packages</h1>
          <p class="text-white-50 lead mb-0">Dealer-grade equipment, OEM warranty-backed parts, and fixed transparent pricing with no surprises.</p>
        </div>
        <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
          <a href="booking.php" class="btn btn-warning btn-lg px-4 py-2 fw-bold rounded-pill">
            <i class="bi bi-calendar2-check me-2"></i> Book Appointment
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Filters & Search Toolbar -->
  <section class="py-4 bg-light border-bottom sticky-top" style="top: 65px; z-index: 1010; backdrop-filter: blur(10px); background-color: rgba(248, 250, 252, 0.95) !important;">
    <div class="container">
      <div class="row g-3 align-items-center">
        <!-- Category Filter Tabs -->
        <div class="col-lg-7">
          <div class="d-flex flex-wrap gap-2" id="categoryFilterTabs">
            <button class="btn btn-primary btn-sm px-3 rounded-pill active-category-btn" data-category="All">All Services</button>
            <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill" data-category="Maintenance">Maintenance</button>
            <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill" data-category="Engine">Engine</button>
            <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill" data-category="Brakes">Brakes</button>
            <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill" data-category="AC">AC</button>
            <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill" data-category="Electrical">Electrical</button>
          </div>
        </div>

        <!-- Search Input -->
        <div class="col-lg-5">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="serviceSearchInput" class="form-control border-start-0" placeholder="Search service e.g. Oil change, Brake, AC...">
            <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn">Clear</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Services Grid Section -->
  <section class="py-5">
    <div class="container">
      <div class="mb-4">
        <span class="text-muted small fw-semibold" id="servicesCounter">Showing <?= count($services) ?> service packages</span>
      </div>

      <div class="row g-4" id="servicesGrid">
        <?php foreach ($services as $s): ?>
          <?php
            $img = 'https://images.unsplash.com/photo-1486006920555-c77dce18193b?auto=format&fit=crop&w=600&q=80';
            if (stripos($s['service_name'], 'Oil') !== false) {
                $img = 'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($s['service_name'], 'Brake') !== false) {
                $img = 'https://images.unsplash.com/photo-1578844251758-2f71da64c96f?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($s['service_name'], 'AC') !== false) {
                $img = 'https://images.unsplash.com/photo-1580273916550-e323be2ae537?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($s['service_name'], 'Wheel') !== false) {
                $img = 'https://images.unsplash.com/photo-1580273916550-e323be2ae537?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($s['service_name'], 'Battery') !== false) {
                $img = 'https://images.unsplash.com/photo-1517524008697-84bbe3c3fd98?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($s['service_name'], 'Full Car') !== false) {
                $img = 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=600&q=80';
            } elseif (stripos($s['service_name'], 'Diagnostics') !== false) {
                $img = 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=600&q=80';
            }
          ?>
          <div class="col-md-6 col-lg-4 col-xl-3 service-item" 
               data-category="<?= htmlspecialchars($s['category']) ?>" 
               data-name="<?= htmlspecialchars(strtolower($s['service_name'])) ?>" 
               data-desc="<?= htmlspecialchars(strtolower($s['description'])) ?>">
            <div class="service-card shadow-sm h-100 d-flex flex-column">
              <div class="service-card-header">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($s['service_name']) ?>">
                <span class="service-badge"><?= htmlspecialchars($s['category']) ?></span>
              </div>
              <div class="service-card-body d-flex flex-column flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi <?= htmlspecialchars($s['icon'] ?? 'bi-wrench') ?> text-primary fs-5"></i>
                  <h5 class="fw-bold mb-0"><?= htmlspecialchars($s['service_name']) ?></h5>
                </div>
                <p class="text-muted small mb-3 flex-grow-1"><?= htmlspecialchars($s['description']) ?></p>
                
                <div class="pt-3 border-top mt-auto">
                  <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="service-duration text-muted small"><i class="bi bi-clock me-1"></i> <?= htmlspecialchars($s['duration']) ?></span>
                    <span class="service-price"><?= format_currency($s['price']) ?></span>
                  </div>
                  <a href="booking.php?serviceId=<?= urlencode($s['service_id']) ?>" class="btn btn-primary w-100 fw-semibold rounded-pill py-2">
                    <i class="bi bi-calendar2-plus me-1"></i> Book Now
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
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
          <h5>Workshop Hours</h5>
          <ul class="text-muted small">
            <li class="mb-2">Monday – Saturday: 8:00 AM – 7:30 PM</li>
            <li class="mb-2">Sunday: By Appointment Only</li>
            <li class="mb-2">Emergency Breakdown Assistance: 24/7</li>
          </ul>
        </div>

        <div class="col-md-6 col-lg-3">
          <h5>Help & Support</h5>
          <ul class="text-muted small">
            <li class="mb-2"><i class="bi bi-telephone text-primary me-2"></i> +91 98765 43210</li>
            <li class="mb-2"><i class="bi bi-envelope text-primary me-2"></i> support@autocare.com</li>
            <li class="mb-2"><i class="bi bi-geo-alt text-primary me-2"></i> SIDCO Industrial Estate, Coimbatore</li>
          </ul>
        </div>
      </div>

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

  <!-- Interactive Search & Filtering on MySQL-Rendered Service Grid -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const searchInput = document.getElementById("serviceSearchInput");
      const clearBtn = document.getElementById("clearSearchBtn");
      const filterButtons = document.querySelectorAll("#categoryFilterTabs button");
      const counterEl = document.getElementById("servicesCounter");
      const serviceItems = document.querySelectorAll(".service-item");

      let currentCategory = "All";

      function filterServices() {
        const query = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        serviceItems.forEach(item => {
          const itemCat = item.dataset.category;
          const itemName = item.dataset.name;
          const itemDesc = item.dataset.desc;

          const matchCat = (currentCategory === "All" || itemCat.toLowerCase() === currentCategory.toLowerCase());
          const matchQuery = (!query || itemName.includes(query) || itemDesc.includes(query) || itemCat.toLowerCase().includes(query));

          if (matchCat && matchQuery) {
            item.style.display = "";
            visibleCount++;
          } else {
            item.style.display = "none";
          }
        });

        if (counterEl) {
          counterEl.textContent = `Showing ${visibleCount} service packages${currentCategory !== 'All' ? ' in ' + currentCategory : ''}`;
        }
      }

      filterButtons.forEach(btn => {
        btn.addEventListener("click", () => {
          filterButtons.forEach(b => {
            b.classList.remove("btn-primary");
            b.classList.add("btn-outline-secondary");
          });
          btn.classList.remove("btn-outline-secondary");
          btn.classList.add("btn-primary");
          currentCategory = btn.dataset.category;
          filterServices();
        });
      });

      searchInput.addEventListener("input", filterServices);
      clearBtn.addEventListener("click", () => {
        searchInput.value = "";
        filterServices();
      });
    });
  </script>
</body>
</html>
