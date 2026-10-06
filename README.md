# AutoCare – Vehicle Service & Maintenance Management System

> **BE Computer Science Engineering – Individual Academic Web Application Project**  
> **Stage 1 Implementation:** Client-Side / Front-End Web Application  
> **Stage 2 Prepared:** Structured for seamless migration to PHP, MySQL, and XAMPP/phpMyAdmin.

---

## 1. Project Overview

**AutoCare** is a vehicle service and maintenance management web application designed for modern multi-brand automotive service centers and dealership workshops. It bridges vehicle owners and workshop service advisors through transparent fixed-package pricing, computerized job scheduling, real-time 6-stage milestone tracking, customer fleet management, and workshop administration.

### Technology Stack (Stage 1)
- **HTML5:** Semantic architecture, accessible forms, validation attributes.
- **CSS3 & Bootstrap 5.3:** Custom automotive dark/navy/white design system, responsive grid, mobile sidebars, custom stepper progress bars, and badges.
- **JavaScript (ES6+):** Relational-like `localStorage` data store (`DB` abstraction), dynamic price calculator, unique booking ID generator (`AC` + `YYYYMMDD` + `rand`), authentication session emulator, and Chart.js analytics.
- **Bootstrap Icons:** Visual indicators for diagnostics, engines, brakes, status states, and user actions.

---

## 2. Directory & Folder Structure

```
AutoCare/
│
├── index.html                  # Landing page (Hero, Stats, Why Choose Us, Services preview, Testimonials)
├── login.html                  # Authentication portal with 1-click Demo auto-fill
├── register.html               # Registration form with vehicle onboarding & client validations
├── services.html               # Service catalog with category tabs & live search
├── booking.html                # Booking wizard with live pricing & summary card
├── status.html                 # 6-Stage visual timeline service tracker
├── contact.html                # Service center contacts, hours, address, and inquiry form
│
├── customer/                   # Customer Dashboard Portal
│   ├── dashboard.html          # Overview KPI cards, active service stepper, recent jobs
│   ├── vehicles.html           # My Garage (Add, view, edit, remove vehicles)
│   ├── bookings.html           # All bookings table with filters & detail modals
│   └── profile.html            # Profile info & password update simulation
│
├── admin/                      # Workshop Administration Portal
│   ├── dashboard.html          # Floor KPIs, Chart.js charts, and live job queue
│   ├── customers.html          # Master customer directory & view details modal
│   ├── vehicles.html           # Master vehicle fleet registry with brand filter
│   ├── bookings.html           # Full bookings list with status updater & mechanic assigner
│   ├── mechanics.html          # Technician crew manager & status toggle
│   ├── services.html           # Package catalog, pricing updates & duration settings
│   └── payments.html           # Invoice receipts table with Cash/UPI/Card filter
│
├── css/
│   └── style.css               # Comprehensive custom styling & automotive theme
│
├── js/
│   ├── main.js                 # Data seeds, localStorage DB abstraction, toasts, session helpers
│   ├── auth.js                 # Login/registration validation, session handling, profile updates
│   ├── booking.js              # Booking calculator, date validation, and confirmation modal
│   └── admin.js                # Floor metrics, Chart.js graphs, status transitions, CRUD operations
│
├── sql/
│   └── schema.sql              # MySQL schema & relational tables ready for Stage 2 XAMPP import
│
└── assets/
    ├── images/                 # Automobile and component assets
    └── icons/                  # Custom vectors and badges
```

---

## 3. How to Run the Project

Since Stage 1 is fully front-end and browser-native, it requires **no Node.js, npm, or server installations**.

### Option A: Direct Browser Launch
1. Navigate to the `AutoCare/` folder in your file explorer.
2. Double-click `index.html` to open it in any modern browser (Google Chrome, Microsoft Edge, Mozilla Firefox, Safari).

### Option B: Local Development Server (Recommended for Academic Evaluation)
Using Python (pre-installed on macOS/Linux/Windows):
```bash
cd AutoCare
python3 -m http.server 8000
```
Then visit:
```
http://localhost:8000
```

---

## 4. Demo Credentials

The application comes pre-loaded with simulated session data. You can log in using either role, or click the **"Auto-fill"** buttons on the login page:

| Role | Email Address | Password | Landing Page |
| :--- | :--- | :--- | :--- |
| **Customer** | `customer@autocare.com` | `customer123` | `customer/dashboard.html` |
| **Admin (Service Head)** | `admin@autocare.com` | `admin123` | `admin/dashboard.html` |

---

## 5. Main Functional Features

### 1. Landing Page (`index.html`)
- Professional hero banner: *"Your Car. Our Care."*
- Verified operational statistics: **5,000+ Vehicles Serviced**, **25+ Certified Mechanics**, **10+ Years Experience**, **98% Customer Satisfaction**.
- Feature value propositions: Certified Mechanics, Genuine Parts, Transparent Pricing, Digital Service Tracking.
- Interactive service quick-track input directly navigating to live tracking.
- Testimonials and responsive footer.

### 2. Service Catalog & Filter (`services.html`)
- 8 standard service packages with fixed prices:
  1. General Service – ₹1,499
  2. Oil Change – ₹999
  3. Brake Service – ₹1,999
  4. AC Service – ₹1,499
  5. Wheel Alignment – ₹799
  6. Battery Replacement – ₹5,999
  7. Full Car Service – ₹3,999
  8. Engine Diagnostics – ₹1,299
- Instant real-time category filtering: **All**, **Maintenance**, **Engine**, **Brakes**, **AC**, **Electrical**.
- Live keyword search as you type.

### 3. Service Booking Wizard (`booking.html`)
- Dynamic service selection that automatically recalculates labor and taxes (18% GST).
- Live right-side summary card updated dynamically on input changes.
- Logged-in customer auto-population with registered vehicles dropdown.
- Unique Booking ID generation: Format `AC` + `YYYYMMDD` + `4-digit sequence` (e.g. `AC202610001`).
- Confirmation modal with print receipt option and direct link to tracker.

### 4. Real-Time Service Status Tracker (`status.html`)
- Search any Booking ID or auto-load via URL query parameter (`?id=AC202610001`).
- Quick-test buttons to inspect different service states.
- Attractive 6-stage visual timeline stepper:
  1. `Confirmed`
  2. `Vehicle Received`
  3. `Inspection`
  4. `Service In Progress` (with animated active pulse)
  5. `Ready for Delivery`
  6. `Completed`

### 5. Customer Dashboard & Garage Management (`customer/`)
- Summary cards: Registered Vehicles, Active Bookings, Completed Services, Pending Payments.
- Active booking card displaying live stepper progress and assigned mechanic.
- Vehicle garage (`vehicles.html`): View registered cars, add new vehicles with year/fuel validation, edit models, and delete entries.
- Booking history (`bookings.html`): Filter by active, completed, or cancelled with detailed inspection modals.
- Profile settings (`profile.html`): Update customer address, phone, and simulate password changes.

### 6. Admin Control Center (`admin/`)
- Real-time statistics: Total Customers (250), Total Vehicles (180), Today's Bookings (18), Pending Services (24), Completed Services (156), Total Revenue (₹85,400).
- Chart.js visual analytics:
  - Monthly Bookings & Work Volume (Bar chart)
  - Service Category Share (Doughnut chart)
  - Weekly Revenue Overview (Line chart)
- Live floor queue with instant status updates (`admin/bookings.html`). Updating a status updates both admin and customer portals simultaneously!
- Specialist assignment modal to assign available mechanics to specific bookings.
- Full Customer directory (`admin/customers.html`), Vehicles fleet (`admin/vehicles.html`), Mechanics crew (`admin/mechanics.html`), Package management (`admin/services.html`), and Payments ledger (`admin/payments.html`).

---

## 6. How Stage 1 Works (Architecture)

Stage 1 uses a dedicated storage abstraction layer located in `js/main.js` called `DB`:
- It stores collections under distinct keys in `localStorage`:
  - `autocare_users`
  - `autocare_customers`
  - `autocare_vehicles`
  - `autocare_services`
  - `autocare_mechanics`
  - `autocare_bookings`
  - `autocare_payments`
- Methods like `DB.get()`, `DB.set()`, `DB.find()`, `DB.insert()`, `DB.update()`, and `DB.delete()` mimic database CRUD queries.
- When an admin updates a booking status in `admin/bookings.html`, `DB.update()` persists the change to `localStorage`, so when checking `status.html` or `customer/dashboard.html`, the status stepper immediately reflects the updated state!

---

## 7. Stage 2 Transition Guide (PHP & MySQL on XAMPP)

The entire UI and data model were purposefully designed so that **no HTML restructuring or CSS modifications are required** when upgrading to Stage 2.

### Step 1: Database Setup
1. Launch **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Click the **Import** tab and upload `sql/schema.sql` (located in `AutoCare/sql/schema.sql`).
4. This creates the database `autocare_db` and all 8 tables with relational foreign keys and seed data.

### Step 2: Database Connection (`db_connect.php`)
Create a single database connection file:
```php
<?php
$host = "localhost";
$dbname = "autocare_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
```

### Step 3: Converting HTML Files to PHP
Rename files from `.html` to `.php`:
- Include authentication check at top of protected pages:
  ```php
  <?php
  session_start();
  if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
      header("Location: ../login.php");
      exit();
  }
  ?>
  ```
- Replace JavaScript `localStorage` queries with PHP PDO prepared statements:
  ```php
  $stmt = $pdo->prepare("SELECT * FROM bookings ORDER BY booking_date DESC");
  $stmt->execute();
  $bookings = $stmt->fetchAll();
  ```
- Secure password hashing:
  ```php
  // Registration:
  $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
  
  // Login:
  if (password_verify($inputPassword, $user['password'])) {
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['role'] = $user['role'];
  }
  ```

---

## 8. LWP Review 01 Presentation Checklist

During **LWP (Lab Work in Progress) Review 01**, you can demonstrate:

1. **System Architecture & Theme Design:**
   - Demonstrate the modern automotive UI (custom navy/slate palette, rounded cards, hover animations, responsive navbar).
   - Show how CSS and JS are strictly decoupled from HTML.

2. **Public User Journey:**
   - Navigate from `index.html` to `services.html`. Filter by category (e.g. *Brakes* or *AC*), then search for a service.
   - Click **Book Now** on a service card and show how it automatically transfers to `booking.html` with price pre-calculation.

3. **Service Booking & Calculation:**
   - Select a service, select a date and time slot, and show the live reactive calculation in the right-side summary.
   - Click **Confirm Booking** to demonstrate unique ID generation (`AC202610...`), modal receipt, and storage into `localStorage`.

4. **Live Milestone Status Tracker:**
   - Open `status.html`, input the generated Booking ID (or a demo ID), and showcase the 6-stage visual timeline stepper.

5. **Customer Dashboard & Garage:**
   - Log in using `customer@autocare.com` / `customer123`.
   - Show customer KPI statistics, active booking tracker, and the garage (`vehicles.html`) where new cars can be added and persisted.

6. **Admin Operations & Multi-role Separation:**
   - Log in using `admin@autocare.com` / `admin123`.
   - Present the 6 floor metrics and 3 Chart.js graphs.
   - Open `admin/bookings.html`, update a booking status (e.g., from *Confirmed* to *Service In Progress*), assign a mechanic, and show how the change immediately updates the customer's tracking view.

7. **Stage 2 Preparedness:**
   - Present `sql/schema.sql` and explain how the tables map 1:1 with the current `localStorage` entity relationships.
