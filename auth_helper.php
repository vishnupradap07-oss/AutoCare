<?php
/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Authentication & Session Helper (auth_helper.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Returns currently logged-in user details
 */
function get_current_user_data() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'user_id'     => $_SESSION['user_id'] ?? '',
        'name'        => $_SESSION['name'] ?? '',
        'email'       => $_SESSION['email'] ?? '',
        'role'        => $_SESSION['role'] ?? '',
        'phone'       => $_SESSION['phone'] ?? '',
        'customer_id' => $_SESSION['customer_id'] ?? ''
    ];
}

/**
 * Enforces role-based access control.
 *
 * @param string|null $requiredRole 'admin' or 'customer' or null for any logged-in user
 * @param string $redirectRelativePath Path back to root folder (e.g. '../' when in customer/ or admin/)
 */
function require_auth($requiredRole = null, $redirectRelativePath = '') {
    if (!is_logged_in()) {
        header("Location: " . $redirectRelativePath . "login.php");
        exit;
    }

    $currentRole = $_SESSION['role'] ?? '';

    if ($requiredRole !== null && $currentRole !== $requiredRole) {
        if ($currentRole === 'admin') {
            header("Location: " . $redirectRelativePath . "admin/dashboard.php");
        } else {
            header("Location: " . $redirectRelativePath . "customer/dashboard.php");
        }
        exit;
    }
}

/**
 * Retrieves the customer_id associated with a user_id from the database
 */
function get_or_set_customer_id($pdo, $userId) {
    if (!empty($_SESSION['customer_id'])) {
        return $_SESSION['customer_id'];
    }

    $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $custId = $stmt->fetchColumn();

    if ($custId) {
        $_SESSION['customer_id'] = $custId;
        return $custId;
    }

    return null;
}

/**
 * Format currency helper
 */
function format_currency($amount) {
    return '₹' . number_format((float)$amount, 0, '.', ',');
}

/**
 * Format date helper
 */
function format_date($dateStr) {
    if (empty($dateStr)) return 'N/A';
    $time = strtotime($dateStr);
    return $time ? date('d M Y', $time) : htmlspecialchars($dateStr);
}

/**
 * Returns HTML for status badge
 */
function render_status_badge($status) {
    $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $status));
    return '<span class="status-badge status-' . htmlspecialchars($normalized) . '">' . htmlspecialchars($status) . '</span>';
}
?>
