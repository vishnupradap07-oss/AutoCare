-- ============================================================================
-- AutoCare – Vehicle Service & Maintenance Management System
-- Stage 2 Database Schema & Initial Seed Data
-- Compatible with: MySQL 5.7+ / MySQL 8.0+ / MariaDB / phpMyAdmin (XAMPP)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `autocare_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `autocare_db`;

-- ----------------------------------------------------------------------------
-- 1. Table structure for table `users`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` VARCHAR(30) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
  `phone` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 2. Table structure for table `customers`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` VARCHAR(30) NOT NULL,
  `user_id` VARCHAR(30) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 3. Table structure for table `vehicles`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicles` (
  `vehicle_id` VARCHAR(30) NOT NULL,
  `customer_id` VARCHAR(30) NOT NULL,
  `registration_number` VARCHAR(20) NOT NULL UNIQUE,
  `brand` VARCHAR(60) NOT NULL,
  `model` VARCHAR(60) NOT NULL,
  `year` INT(4) NOT NULL,
  `fuel_type` ENUM('Petrol', 'Diesel', 'Electric', 'CNG', 'Hybrid') DEFAULT 'Petrol',
  `last_service` DATE DEFAULT NULL,
  `next_service` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vehicle_id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4. Table structure for table `services`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
  `service_id` VARCHAR(30) NOT NULL,
  `service_name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `duration` VARCHAR(50) NOT NULL,
  `icon` VARCHAR(50) DEFAULT 'bi-wrench',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5. Table structure for table `mechanics`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mechanics` (
  `mechanic_id` VARCHAR(30) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `specialization` VARCHAR(100) NOT NULL,
  `experience` VARCHAR(50) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `status` ENUM('Available', 'Busy', 'On Leave') DEFAULT 'Available',
  `avatar` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`mechanic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 6. Table structure for table `bookings`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` VARCHAR(30) NOT NULL,
  `customer_id` VARCHAR(30) NOT NULL,
  `vehicle_id` VARCHAR(30) NOT NULL,
  `service_id` VARCHAR(30) NOT NULL,
  `mechanic_id` VARCHAR(30) DEFAULT NULL,
  `booking_date` DATE NOT NULL,
  `booking_time` VARCHAR(30) NOT NULL,
  `problem_description` TEXT DEFAULT NULL,
  `status` ENUM(
    'Pending',
    'Confirmed',
    'Vehicle Received',
    'Inspection',
    'Service In Progress',
    'Ready for Delivery',
    'Completed',
    'Cancelled'
  ) DEFAULT 'Confirmed',
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('Pending', 'Paid', 'Refunded') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`customer_id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`vehicle_id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`service_id`) ON DELETE CASCADE,
  FOREIGN KEY (`mechanic_id`) REFERENCES `mechanics`(`mechanic_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 7. Table structure for table `payments`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` VARCHAR(30) NOT NULL,
  `booking_id` VARCHAR(30) NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('Cash', 'UPI', 'Card') NOT NULL DEFAULT 'UPI',
  `payment_date` DATE NOT NULL,
  `status` ENUM('Paid', 'Pending', 'Refunded') DEFAULT 'Paid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 8. Table structure for table `service_history`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_history` (
  `history_id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` VARCHAR(30) NOT NULL,
  `service_date` DATE NOT NULL,
  `description` TEXT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- SEED DATA (Aligns 1:1 with Stage 1 LocalStorage Structure)
-- ============================================================================

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `phone`) VALUES
('USR-001', 'Rohan Sharma', 'customer@autocare.com', 'customer123', 'customer', '9876501234'),
('USR-002', 'Rajesh Kumar (Service Head)', 'admin@autocare.com', 'admin123', 'admin', '9876599999');

INSERT INTO `customers` (`customer_id`, `user_id`, `name`, `email`, `phone`, `address`, `status`) VALUES
('CUST-001', 'USR-001', 'Rohan Sharma', 'customer@autocare.com', '9876501234', '42, Green Park Avenue, Coimbatore, TN', 'active');

INSERT INTO `vehicles` (`vehicle_id`, `customer_id`, `registration_number`, `brand`, `model`, `year`, `fuel_type`, `last_service`, `next_service`) VALUES
('VEH-001', 'CUST-001', 'TN38AB1234', 'Maruti Suzuki', 'Swift VXI', 2021, 'Petrol', '2026-09-20', '2027-03-20'),
('VEH-002', 'CUST-001', 'TN38CD5678', 'Hyundai', 'Creta SX', 2023, 'Diesel', '2026-08-10', '2027-02-10');

INSERT INTO `services` (`service_id`, `service_name`, `category`, `description`, `price`, `duration`, `icon`, `status`) VALUES
('SRV-01', 'General Service', 'Maintenance', 'Complete 40-point vehicle inspection, engine oil top-up, filter clean, spark plug check, fluids & coolant level check.', 1499.00, '3 - 4 Hours', 'bi-wrench-adjustable-circle', 'active'),
('SRV-02', 'Oil Change', 'Engine', 'Synthetic engine oil replacement, premium oil filter change, and sludge clearance with engine health inspection.', 999.00, '1 - 2 Hours', 'bi-droplet-half', 'active'),
('SRV-03', 'Brake Service', 'Brakes', 'Brake pad replacement, disc rotor inspection and cleaning, brake caliper lubrication, and hydraulic fluid top-up.', 1999.00, '2 - 3 Hours', 'bi-disc', 'active'),
('SRV-04', 'AC Service', 'AC', 'AC gas leak check & refrigerant recharge, cabin pollen filter cleaning, condenser coil wash, and antibacterial spray.', 1499.00, '2 - 3 Hours', 'bi-snow', 'active'),
('SRV-05', 'Wheel Alignment', 'Maintenance', 'Computerized 3D laser wheel alignment, tire pressure optimization, camber and toe angle adjustments, tire wear check.', 799.00, '1 Hour', 'bi-compass', 'active'),
('SRV-06', 'Battery Replacement', 'Electrical', 'Testing of battery health, alternator output test, terminal corrosion cleanup, and branded battery installation.', 5999.00, '1 Hour', 'bi-battery-charging', 'active'),
('SRV-07', 'Full Car Service', 'Maintenance', 'Exhaustive bumper-to-bumper 65-point checkup, engine tuning, brake overhaul, electrical scan, underbody wash & detailing.', 3999.00, '5 - 6 Hours', 'bi-shield-check', 'active'),
('SRV-08', 'Engine Diagnostics', 'Engine', 'OBD-II scanner code extraction, live sensor data analysis, fuel trim evaluation, and ECU diagnostic health report.', 1299.00, '2 Hours', 'bi-cpu', 'active');

INSERT INTO `mechanics` (`mechanic_id`, `name`, `specialization`, `experience`, `phone`, `status`, `avatar`) VALUES
('MEC-01', 'Arun Kumar', 'Engine Specialist', '8 Years', '9876543210', 'Busy', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80'),
('MEC-02', 'Rahul Sharma', 'Brake Specialist', '6 Years', '9876543211', 'Available', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80'),
('MEC-03', 'Karthik Raja', 'AC Specialist', '7 Years', '9876543212', 'Busy', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80'),
('MEC-04', 'Vijay Anand', 'General Service', '5 Years', '9876543213', 'Available', 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=200&q=80'),
('MEC-05', 'Suresh Babu', 'Electrical Specialist', '9 Years', '9876543214', 'On Leave', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=80');

INSERT INTO `bookings` (`booking_id`, `customer_id`, `vehicle_id`, `service_id`, `mechanic_id`, `booking_date`, `booking_time`, `problem_description`, `status`, `amount`, `payment_status`) VALUES
('AC202610001', 'CUST-001', 'VEH-001', 'SRV-01', 'MEC-01', '2026-10-10', '10:30', 'Periodic 30,000km scheduled maintenance. Check engine vibrations.', 'Service In Progress', 1499.00, 'Paid'),
('AC202610002', 'CUST-001', 'VEH-002', 'SRV-02', 'MEC-02', '2026-10-12', '14:00', 'Routine synthetic engine oil replacement and new oil filter installation.', 'Confirmed', 999.00, 'Pending');

INSERT INTO `payments` (`payment_id`, `booking_id`, `customer_name`, `amount`, `payment_method`, `payment_date`, `status`) VALUES
('PAY20261001', 'AC202610001', 'Rohan Sharma', 1499.00, 'UPI', '2026-10-06', 'Paid'),
('PAY20261002', 'AC202610002', 'Rohan Sharma', 999.00, 'UPI', '2026-10-12', 'Pending');
