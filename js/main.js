/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Core Shared JavaScript Utilities (js/main.js)
 *
 * Stage 2: Database and Authentication are managed by MySQL & PHP.
 * LocalStorage is NOT used as the database.
 */

// ==========================================================================
// 1. Session & Navigation Helpers
// ==========================================================================
function logout() {
  const isInSubdir = window.location.pathname.includes('/customer/') || window.location.pathname.includes('/admin/');
  window.location.href = isInSubdir ? '../logout.php' : 'logout.php';
}

// ==========================================================================
// 2. Formatting & UI Helper Functions
// ==========================================================================
function formatCurrency(amount) {
  return "₹" + Number(amount).toLocaleString('en-IN');
}

function formatDate(dateString) {
  if (!dateString) return "N/A";
  const parts = dateString.split('-');
  if (parts.length === 3) {
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    if (!isNaN(d)) {
      return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    }
  }
  return dateString;
}

function getStatusBadge(status) {
  const normalized = (status || "").toLowerCase().replace(/[^a-zA-Z0-9]+/g, '-');
  return `<span class="status-badge status-${normalized}">${status}</span>`;
}

function showToast(message, type = "info") {
  let container = document.getElementById("toastContainer");
  if (!container) {
    container = document.createElement("div");
    container.id = "toastContainer";
    container.className = "toast-container-custom";
    document.body.appendChild(container);
  }

  const toast = document.createElement("div");
  toast.className = `custom-toast toast-${type}`;
  
  let iconClass = "bi-info-circle-fill text-primary";
  if (type === "success") iconClass = "bi-check-circle-fill text-success";
  if (type === "error") iconClass = "bi-exclamation-triangle-fill text-danger";
  if (type === "warning") iconClass = "bi-exclamation-circle-fill text-warning";

  toast.innerHTML = `
    <i class="bi ${iconClass} fs-5"></i>
    <div class="flex-grow-1 fs-6">${message}</div>
    <button type="button" class="btn-close btn-close-sm" aria-label="Close"></button>
  `;

  const closeBtn = toast.querySelector(".btn-close");
  closeBtn.addEventListener("click", () => toast.remove());

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = "0";
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// Generate Booking ID e.g., AC202610001
function generateBookingId() {
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `AC${yyyy}${mm}${dd}${rand}`;
}

// ==========================================================================
// 3. Global DOM Ready Listeners
// ==========================================================================
document.addEventListener("DOMContentLoaded", () => {
  // Sidebar Mobile Toggle
  const sidebarToggle = document.getElementById("sidebarToggle");
  const dashboardSidebar = document.querySelector(".dashboard-sidebar");
  const sidebarBackdrop = document.querySelector(".sidebar-backdrop");

  if (sidebarToggle && dashboardSidebar) {
    sidebarToggle.addEventListener("click", () => {
      dashboardSidebar.classList.toggle("show");
      if (sidebarBackdrop) sidebarBackdrop.classList.toggle("show");
    });
  }

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener("click", () => {
      dashboardSidebar.classList.remove("show");
      sidebarBackdrop.classList.remove("show");
    });
  }
});
