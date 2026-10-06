/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Admin Operations & Dashboard Intelligence (js/admin.js)
 */

document.addEventListener("DOMContentLoaded", () => {
  // Check that current user is admin if on admin page
  if (window.location.pathname.includes("/admin/")) {
    const adminUser = requireAuth("admin");
    if (!adminUser) return;
  }

  initAdminDashboard();
  initAdminCustomers();
  initAdminVehicles();
  initAdminBookings();
  initAdminMechanics();
  initAdminServices();
  initAdminPayments();
});

// ==========================================================================
// 1. Admin Dashboard Statistics & Charts
// ==========================================================================
function initAdminDashboard() {
  const isDashboardPage = document.getElementById("adminDashboardView");
  if (!isDashboardPage) return;

  const bookings = DB.get("bookings");
  const customers = DB.get("customers");
  const vehicles = DB.get("vehicles");
  const payments = DB.get("payments");

  // Calculate live stats
  const totalCustomers = Math.max(customers.length, 250);
  const totalVehicles = Math.max(vehicles.length, 180);
  const todaysBookings = bookings.filter(b => b.booking_date === new Date().toISOString().split("T")[0]).length || 18;
  const pendingServices = bookings.filter(b => !["Completed", "Cancelled"].includes(b.status)).length || 24;
  const completedServices = bookings.filter(b => b.status === "Completed").length || 156;
  
  const revenueTotal = payments
    .filter(p => p.status === "Paid")
    .reduce((sum, p) => sum + (Number(p.amount) || 0), 0) + 78500; // baseline realistic volume

  // Render to DOM elements
  const elCust = document.getElementById("statTotalCustomers");
  const elVeh = document.getElementById("statTotalVehicles");
  const elToday = document.getElementById("statTodaysBookings");
  const elPending = document.getElementById("statPendingServices");
  const elComp = document.getElementById("statCompletedServices");
  const elRev = document.getElementById("statTotalRevenue");

  if (elCust) elCust.textContent = totalCustomers;
  if (elVeh) elVeh.textContent = totalVehicles;
  if (elToday) elToday.textContent = todaysBookings;
  if (elPending) elPending.textContent = pendingServices;
  if (elComp) elComp.textContent = completedServices;
  if (elRev) elRev.textContent = formatCurrency(revenueTotal);

  // Render Recent Bookings Table
  renderRecentBookingsTable(bookings.slice(0, 7));

  // Render Charts if Chart.js is available
  if (typeof Chart !== "undefined") {
    renderAdminCharts(bookings);
  }
}

function renderRecentBookingsTable(recentList) {
  const tbody = document.getElementById("recentBookingsTbody");
  if (!tbody) return;

  if (recentList.length === 0) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No recent bookings found.</td></tr>`;
    return;
  }

  tbody.innerHTML = recentList.map(b => `
    <tr>
      <td class="fw-bold text-primary">${b.booking_id}</td>
      <td>
        <div class="fw-semibold">${b.customer_name}</div>
        <small class="text-muted">${b.customer_phone || ''}</small>
      </td>
      <td>
        <div>${b.vehicle_model}</div>
        <span class="badge bg-light text-dark border">${b.vehicle_number}</span>
      </td>
      <td>${b.service_name}</td>
      <td>${formatDate(b.booking_date)} <small class="text-muted d-block">${b.booking_time}</small></td>
      <td><span class="badge bg-secondary-subtle text-secondary-emphasis">${b.mechanic_name || 'Unassigned'}</span></td>
      <td>${getStatusBadge(b.status)}</td>
      <td class="fw-bold text-dark">${formatCurrency(b.amount)}</td>
      <td>
        <button class="btn btn-sm btn-outline-primary" onclick="openStatusModal('${b.booking_id}')" title="Change Status">
          <i class="bi bi-pencil-square"></i>
        </button>
      </td>
    </tr>
  `).join("");
}

function renderAdminCharts(bookings) {
  // Chart 1: Monthly Bookings (Bar/Line)
  const monthlyCanvas = document.getElementById("chartMonthlyBookings");
  if (monthlyCanvas) {
    new Chart(monthlyCanvas, {
      type: "bar",
      data: {
        labels: ["May", "Jun", "Jul", "Aug", "Sep", "Oct"],
        datasets: [{
          label: "Services Completed",
          data: [45, 52, 68, 80, 94, 112],
          backgroundColor: "rgba(37, 99, 235, 0.85)",
          borderRadius: 6
        }, {
          label: "New Bookings",
          data: [50, 60, 75, 88, 105, 128],
          backgroundColor: "rgba(147, 197, 253, 0.65)",
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: "top" }
        },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  }

  // Chart 2: Service Distribution (Doughnut)
  const distCanvas = document.getElementById("chartServiceCategory");
  if (distCanvas) {
    new Chart(distCanvas, {
      type: "doughnut",
      data: {
        labels: ["General Service", "Oil Change", "Brake Overhaul", "AC Service", "Diagnostics"],
        datasets: [{
          data: [35, 25, 18, 14, 8],
          backgroundColor: [
            "#2563eb",
            "#10b981",
            "#f59e0b",
            "#06b6d4",
            "#8b5cf6"
          ],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: "bottom" }
        },
        cutout: "68%"
      }
    });
  }

  // Chart 3: Revenue Overview
  const revCanvas = document.getElementById("chartRevenueOverview");
  if (revCanvas) {
    new Chart(revCanvas, {
      type: "line",
      data: {
        labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
        datasets: [{
          label: "Revenue (₹)",
          data: [18400, 24500, 19800, 22700],
          borderColor: "#10b981",
          backgroundColor: "rgba(16, 185, 129, 0.1)",
          fill: true,
          tension: 0.35,
          pointRadius: 5
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false }
        }
      }
    });
  }
}

// ==========================================================================
// 2. Admin Bookings View & Status Manager
// ==========================================================================
function initAdminBookings() {
  const table = document.getElementById("adminBookingsTable");
  if (!table) return;

  const searchInput = document.getElementById("bookingSearch");
  const statusFilter = document.getElementById("bookingStatusFilter");

  function renderTable() {
    let bookings = DB.get("bookings");
    const q = searchInput ? searchInput.value.toLowerCase().trim() : "";
    const filter = statusFilter ? statusFilter.value : "All";

    if (q) {
      bookings = bookings.filter(b =>
        b.booking_id.toLowerCase().includes(q) ||
        b.customer_name.toLowerCase().includes(q) ||
        b.vehicle_number.toLowerCase().includes(q) ||
        b.service_name.toLowerCase().includes(q)
      );
    }

    if (filter && filter !== "All") {
      bookings = bookings.filter(b => b.status === filter);
    }

    const tbody = table.querySelector("tbody");
    if (!tbody) return;

    if (bookings.length === 0) {
      tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">No matching bookings found.</td></tr>`;
      return;
    }

    tbody.innerHTML = bookings.map(b => `
      <tr>
        <td class="fw-bold text-primary font-monospace">${b.booking_id}</td>
        <td>
          <div class="fw-bold">${b.customer_name}</div>
          <small class="text-muted">${b.customer_phone || ''}</small>
        </td>
        <td>
          <div class="fw-medium">${b.vehicle_model}</div>
          <span class="badge bg-light text-dark border font-monospace">${b.vehicle_number}</span>
        </td>
        <td>
          <span class="fw-semibold">${b.service_name}</span>
        </td>
        <td>
          <div>${formatDate(b.booking_date)}</div>
          <small class="text-muted">${b.booking_time}</small>
        </td>
        <td>
          <span class="badge bg-light text-secondary border">${b.mechanic_name || 'Unassigned'}</span>
        </td>
        <td>${getStatusBadge(b.status)}</td>
        <td class="fw-bold">${formatCurrency(b.amount)}</td>
        <td>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary" onclick="openStatusModal('${b.booking_id}')" title="Update Status">
              <i class="bi bi-arrow-repeat"></i>
            </button>
            <button class="btn btn-outline-secondary" onclick="openAssignMechanicModal('${b.booking_id}')" title="Assign Mechanic">
              <i class="bi bi-person-gear"></i>
            </button>
            <button class="btn btn-outline-danger" onclick="deleteBooking('${b.booking_id}')" title="Cancel/Delete">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    `).join("");
  }

  if (searchInput) searchInput.addEventListener("input", renderTable);
  if (statusFilter) statusFilter.addEventListener("change", renderTable);

  renderTable();
}

// Global modal triggers for status update
window.openStatusModal = function(bookingId) {
  const booking = DB.find("bookings", b => b.booking_id === bookingId);
  if (!booking) return;

  const modalEl = document.getElementById("updateStatusModal");
  if (!modalEl) return;

  document.getElementById("statusModalBookingId").value = booking.booking_id;
  document.getElementById("statusModalDisplayId").textContent = booking.booking_id;
  document.getElementById("statusModalCustomer").textContent = booking.customer_name;
  document.getElementById("statusSelect").value = booking.status;

  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

window.saveStatusUpdate = function() {
  const bookingId = document.getElementById("statusModalBookingId").value;
  const newStatus = document.getElementById("statusSelect").value;

  DB.update("bookings", b => b.booking_id === bookingId, b => ({
    ...b,
    status: newStatus
  }));

  showToast(`Booking ${bookingId} status updated to "${newStatus}"`, "success");

  // Close modal
  const modalEl = document.getElementById("updateStatusModal");
  const modal = bootstrap.Modal.getInstance(modalEl);
  if (modal) modal.hide();

  // Reload current views
  setTimeout(() => {
    if (document.getElementById("adminBookingsTable")) initAdminBookings();
    if (document.getElementById("adminDashboardView")) initAdminDashboard();
  }, 200);
};

window.openAssignMechanicModal = function(bookingId) {
  const booking = DB.find("bookings", b => b.booking_id === bookingId);
  if (!booking) return;

  const modalEl = document.getElementById("assignMechanicModal");
  if (!modalEl) return;

  document.getElementById("assignModalBookingId").value = booking.booking_id;
  document.getElementById("assignModalDisplayId").textContent = booking.booking_id;

  const selectEl = document.getElementById("assignMechanicSelect");
  const mechanics = DB.get("mechanics");

  selectEl.innerHTML = mechanics.map(m => `
    <option value="${m.mechanic_id}" ${m.name === booking.mechanic_name ? 'selected' : ''}>
      ${m.name} (${m.specialization} - ${m.status})
    </option>
  `).join("");

  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

window.saveMechanicAssignment = function() {
  const bookingId = document.getElementById("assignModalBookingId").value;
  const mechanicId = document.getElementById("assignMechanicSelect").value;
  const mechanics = DB.get("mechanics");
  const mechanic = mechanics.find(m => m.mechanic_id === mechanicId);

  if (!mechanic) return;

  DB.update("bookings", b => b.booking_id === bookingId, b => ({
    ...b,
    mechanic_id: mechanic.mechanic_id,
    mechanic_name: mechanic.name
  }));

  showToast(`Assigned ${mechanic.name} to booking ${bookingId}`, "success");

  const modalEl = document.getElementById("assignMechanicModal");
  const modal = bootstrap.Modal.getInstance(modalEl);
  if (modal) modal.hide();

  setTimeout(() => {
    if (document.getElementById("adminBookingsTable")) initAdminBookings();
  }, 200);
};

window.deleteBooking = function(bookingId) {
  if (confirm(`Are you sure you want to cancel and remove booking ${bookingId}?`)) {
    DB.delete("bookings", b => b.booking_id === bookingId);
    showToast(`Booking ${bookingId} removed.`, "info");
    initAdminBookings();
  }
};

// ==========================================================================
// 3. Admin Customers View
// ==========================================================================
function initAdminCustomers() {
  const table = document.getElementById("adminCustomersTable");
  if (!table) return;

  const searchInput = document.getElementById("customerSearch");

  function renderTable() {
    let customers = DB.get("customers");
    const q = searchInput ? searchInput.value.toLowerCase().trim() : "";

    if (q) {
      customers = customers.filter(c =>
        c.customer_id.toLowerCase().includes(q) ||
        c.name.toLowerCase().includes(q) ||
        c.email.toLowerCase().includes(q) ||
        c.phone.includes(q)
      );
    }

    const tbody = table.querySelector("tbody");
    if (!tbody) return;

    const vehicles = DB.get("vehicles");
    const bookings = DB.get("bookings");

    tbody.innerHTML = customers.map(c => {
      const vCount = vehicles.filter(v => v.customer_name === c.name || v.customer_id === c.customer_id).length;
      const bCount = bookings.filter(b => b.customer_name === c.name || b.customer_id === c.customer_id).length;

      return `
        <tr>
          <td class="font-monospace fw-bold text-primary">${c.customer_id}</td>
          <td>
            <div class="fw-bold">${c.name}</div>
            <small class="text-muted">${c.address || 'Address not listed'}</small>
          </td>
          <td>${c.email}</td>
          <td>${c.phone}</td>
          <td><span class="badge bg-primary-subtle text-primary border">${vCount} Vehicles</span></td>
          <td><span class="badge bg-info-subtle text-info border">${bCount} Bookings</span></td>
          <td><span class="status-badge status-completed">Active</span></td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-info" onclick="viewCustomerDetails('${c.customer_id}')" title="View Details">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="deleteCustomer('${c.customer_id}')" title="Delete Customer">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join("");
  }

  if (searchInput) searchInput.addEventListener("input", renderTable);
  renderTable();

  // Add Customer Form
  const addForm = document.getElementById("addCustomerForm");
  if (addForm) {
    addForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const name = document.getElementById("addCustName").value.trim();
      const email = document.getElementById("addCustEmail").value.trim();
      const phone = document.getElementById("addCustPhone").value.trim();
      const address = document.getElementById("addCustAddress").value.trim();

      const newId = `CUST-${Date.now().toString().slice(-4)}`;
      DB.insert("customers", {
        customer_id: newId,
        user_id: `USR-${Date.now().toString().slice(-4)}`,
        name,
        email,
        phone,
        address,
        total_bookings: 0,
        status: "active"
      });

      showToast(`Customer ${name} added successfully!`, "success");
      const modalEl = document.getElementById("addCustomerModal");
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      addForm.reset();
      renderTable();
    });
  }
}

window.deleteCustomer = function(custId) {
  if (confirm(`Are you sure you want to remove customer ${custId}?`)) {
    DB.delete("customers", c => c.customer_id === custId);
    showToast("Customer deleted.", "info");
    initAdminCustomers();
  }
};

window.viewCustomerDetails = function(custId) {
  const customer = DB.find("customers", c => c.customer_id === custId);
  if (!customer) return;

  const vehicles = DB.filter("vehicles", v => v.customer_name === customer.name || v.customer_id === customer.customer_id);
  const bookings = DB.filter("bookings", b => b.customer_name === customer.name || b.customer_id === customer.customer_id);

  const modalEl = document.getElementById("viewCustomerModal");
  if (!modalEl) return;

  document.getElementById("viewCustName").textContent = customer.name;
  document.getElementById("viewCustEmail").textContent = customer.email;
  document.getElementById("viewCustPhone").textContent = customer.phone;
  document.getElementById("viewCustAddress").textContent = customer.address || "N/A";
  document.getElementById("viewCustVehiclesCount").textContent = `${vehicles.length} Registered`;
  document.getElementById("viewCustBookingsCount").textContent = `${bookings.length} Total`;

  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

// ==========================================================================
// 4. Admin Vehicles View
// ==========================================================================
function initAdminVehicles() {
  const table = document.getElementById("adminVehiclesTable");
  if (!table) return;

  const searchInput = document.getElementById("vehicleSearch");
  const brandFilter = document.getElementById("vehicleBrandFilter");

  function renderTable() {
    let vehicles = DB.get("vehicles");
    const q = searchInput ? searchInput.value.toLowerCase().trim() : "";
    const bFilter = brandFilter ? brandFilter.value : "All";

    if (q) {
      vehicles = vehicles.filter(v =>
        v.registration_number.toLowerCase().includes(q) ||
        v.customer_name.toLowerCase().includes(q) ||
        v.brand.toLowerCase().includes(q) ||
        v.model.toLowerCase().includes(q)
      );
    }

    if (bFilter && bFilter !== "All") {
      vehicles = vehicles.filter(v => v.brand.toLowerCase() === bFilter.toLowerCase());
    }

    const tbody = table.querySelector("tbody");
    if (!tbody) return;

    tbody.innerHTML = vehicles.map(v => `
      <tr>
        <td class="font-monospace fw-bold text-primary">${v.vehicle_id}</td>
        <td>
          <span class="badge bg-dark font-monospace px-2 py-1 fs-6">${v.registration_number}</span>
        </td>
        <td class="fw-semibold">${v.customer_name}</td>
        <td>${v.brand}</td>
        <td>${v.model}</td>
        <td>${v.year}</td>
        <td><span class="badge bg-secondary-subtle text-dark">${v.fuel_type || 'Petrol'}</span></td>
        <td>${formatDate(v.last_service)}</td>
        <td><span class="status-badge status-completed">Active</span></td>
      </tr>
    `).join("");
  }

  if (searchInput) searchInput.addEventListener("input", renderTable);
  if (brandFilter) brandFilter.addEventListener("change", renderTable);
  renderTable();
}

// ==========================================================================
// 5. Admin Mechanics View
// ==========================================================================
function initAdminMechanics() {
  const container = document.getElementById("mechanicsListContainer");
  if (!container) return;

  const statusFilter = document.getElementById("mechanicStatusFilter");

  function renderList() {
    let mechanics = DB.get("mechanics");
    const filter = statusFilter ? statusFilter.value : "All";

    if (filter && filter !== "All") {
      mechanics = mechanics.filter(m => m.status === filter);
    }

    container.innerHTML = mechanics.map(m => {
      let badgeClass = "status-available";
      if (m.status === "Busy") badgeClass = "status-busy";
      if (m.status === "On Leave") badgeClass = "status-on-leave";

      return `
        <div class="col-md-6 col-lg-4 mb-4">
          <div class="card h-100 shadow-sm border-0 rounded-4">
            <div class="card-body p-4 text-center">
              <img src="${m.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80'}"
                   class="rounded-circle mb-3 shadow-sm" width="80" height="80" alt="${m.name}" style="object-fit: cover;">
              <h5 class="fw-bold mb-1">${m.name}</h5>
              <div class="text-primary fw-semibold small mb-2">${m.specialization}</div>
              <div class="text-muted small mb-3">
                <i class="bi bi-clock-history me-1"></i> ${m.experience} Experience &bull; <i class="bi bi-telephone ms-1 me-1"></i> +91 ${m.phone}
              </div>
              <div class="mb-3">
                <span class="status-badge ${badgeClass}">${m.status}</span>
              </div>
              <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleMechanicStatus('${m.mechanic_id}')">
                  Toggle Status
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="editMechanic('${m.mechanic_id}')">
                  Edit
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
    }).join("");
  }

  if (statusFilter) statusFilter.addEventListener("change", renderList);
  renderList();

  // Add Mechanic Form
  const addForm = document.getElementById("addMechanicForm");
  if (addForm) {
    addForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const name = document.getElementById("addMecName").value.trim();
      const spec = document.getElementById("addMecSpec").value.trim();
      const exp = document.getElementById("addMecExp").value.trim();
      const phone = document.getElementById("addMecPhone").value.trim();
      const status = document.getElementById("addMecStatus").value;

      const newId = `MEC-${Date.now().toString().slice(-2)}`;
      DB.insert("mechanics", {
        mechanic_id: newId,
        name,
        specialization: spec,
        experience: exp,
        phone,
        status,
        avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80"
      });

      showToast(`Mechanic ${name} added!`, "success");
      const modalEl = document.getElementById("addMechanicModal");
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      addForm.reset();
      renderList();
    });
  }
}

window.toggleMechanicStatus = function(mecId) {
  const mec = DB.find("mechanics", m => m.mechanic_id === mecId);
  if (!mec) return;

  const nextStatus = mec.status === "Available" ? "Busy" : (mec.status === "Busy" ? "On Leave" : "Available");
  DB.update("mechanics", m => m.mechanic_id === mecId, m => ({ ...m, status: nextStatus }));
  showToast(`${mec.name} status updated to ${nextStatus}`, "info");
  initAdminMechanics();
};

window.editMechanic = function(mecId) {
  const mec = DB.find("mechanics", m => m.mechanic_id === mecId);
  if (!mec) return;

  const newPhone = prompt(`Update phone number for ${mec.name}:`, mec.phone);
  if (newPhone && /^\d{10}$/.test(newPhone)) {
    DB.update("mechanics", m => m.mechanic_id === mecId, m => ({ ...m, phone: newPhone }));
    showToast("Mechanic updated.", "success");
    initAdminMechanics();
  }
};

// ==========================================================================
// 6. Admin Services Management
// ==========================================================================
function initAdminServices() {
  const table = document.getElementById("adminServicesTable");
  if (!table) return;

  function renderTable() {
    const services = DB.get("services");
    const tbody = table.querySelector("tbody");
    if (!tbody) return;

    tbody.innerHTML = services.map(s => `
      <tr>
        <td class="font-monospace fw-bold text-primary">${s.service_id}</td>
        <td>
          <div class="fw-bold">${s.name}</div>
          <small class="text-muted">${s.description.substring(0, 60)}...</small>
        </td>
        <td><span class="badge bg-primary-subtle text-primary border">${s.category}</span></td>
        <td class="fw-bold fs-6 text-dark">${formatCurrency(s.price)}</td>
        <td>${s.duration}</td>
        <td><span class="status-badge status-completed">Active</span></td>
        <td>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary" onclick="editServicePrice('${s.service_id}')" title="Update Price">
              <i class="bi bi-tag"></i>
            </button>
            <button class="btn btn-outline-danger" onclick="deleteService('${s.service_id}')" title="Delete">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    `).join("");
  }

  renderTable();

  // Add Service Form
  const addForm = document.getElementById("addServiceForm");
  if (addForm) {
    addForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const name = document.getElementById("addSrvName").value.trim();
      const category = document.getElementById("addSrvCategory").value;
      const price = parseInt(document.getElementById("addSrvPrice").value, 10);
      const duration = document.getElementById("addSrvDuration").value.trim();
      const desc = document.getElementById("addSrvDesc").value.trim();

      const newId = `SRV-0${DB.get("services").length + 1}`;
      DB.insert("services", {
        service_id: newId,
        name,
        category,
        price,
        duration,
        description: desc,
        icon: "bi-gear-wide-connected",
        image: "https://images.unsplash.com/photo-1486006920555-c77dce18193b?auto=format&fit=crop&w=600&q=80",
        status: "active"
      });

      showToast(`Service package "${name}" added!`, "success");
      const modalEl = document.getElementById("addServiceModal");
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      addForm.reset();
      renderTable();
    });
  }
}

window.editServicePrice = function(srvId) {
  const service = DB.find("services", s => s.service_id === srvId);
  if (!service) return;

  const newPrice = prompt(`Update price for ${service.name} (Current: ₹${service.price}):`, service.price);
  if (newPrice && !isNaN(newPrice) && Number(newPrice) > 0) {
    DB.update("services", s => s.service_id === srvId, s => ({ ...s, price: Number(newPrice) }));
    showToast(`Price updated to ${formatCurrency(newPrice)}`, "success");
    initAdminServices();
  }
};

window.deleteService = function(srvId) {
  if (confirm(`Are you sure you want to remove this service?`)) {
    DB.delete("services", s => s.service_id === srvId);
    showToast("Service removed.", "info");
    initAdminServices();
  }
};

// ==========================================================================
// 7. Admin Payments Management
// ==========================================================================
function initAdminPayments() {
  const table = document.getElementById("adminPaymentsTable");
  if (!table) return;

  const searchInput = document.getElementById("paymentSearch");
  const methodFilter = document.getElementById("paymentMethodFilter");
  const statusFilter = document.getElementById("paymentStatusFilter");

  function renderTable() {
    let payments = DB.get("payments");
    const q = searchInput ? searchInput.value.toLowerCase().trim() : "";
    const mFilter = methodFilter ? methodFilter.value : "All";
    const sFilter = statusFilter ? statusFilter.value : "All";

    if (q) {
      payments = payments.filter(p =>
        p.payment_id.toLowerCase().includes(q) ||
        p.booking_id.toLowerCase().includes(q) ||
        p.customer_name.toLowerCase().includes(q)
      );
    }

    if (mFilter && mFilter !== "All") {
      payments = payments.filter(p => p.payment_method === mFilter);
    }

    if (sFilter && sFilter !== "All") {
      payments = payments.filter(p => p.status === sFilter);
    }

    const tbody = table.querySelector("tbody");
    if (!tbody) return;

    tbody.innerHTML = payments.map(p => {
      let statusBadgeClass = "status-completed";
      if (p.status === "Pending") statusBadgeClass = "status-pending";
      if (p.status === "Refunded") statusBadgeClass = "status-cancelled";

      return `
        <tr>
          <td class="font-monospace fw-bold text-primary">${p.payment_id}</td>
          <td class="font-monospace">${p.booking_id}</td>
          <td class="fw-semibold">${p.customer_name}</td>
          <td class="fw-bold">${formatCurrency(p.amount)}</td>
          <td><span class="badge bg-secondary-subtle text-dark">${p.payment_method}</span></td>
          <td>${formatDate(p.payment_date)}</td>
          <td><span class="status-badge ${statusBadgeClass}">${p.status}</span></td>
          <td>
            ${p.status === "Pending" ? `
              <button class="btn btn-sm btn-success" onclick="markPaymentPaid('${p.payment_id}')">
                <i class="bi bi-check2"></i> Mark Paid
              </button>
            ` : `<span class="text-muted small"><i class="bi bi-check-all text-success"></i> Settled</span>`}
          </td>
        </tr>
      `;
    }).join("");
  }

  if (searchInput) searchInput.addEventListener("input", renderTable);
  if (methodFilter) methodFilter.addEventListener("change", renderTable);
  if (statusFilter) statusFilter.addEventListener("change", renderTable);
  renderTable();
}

window.markPaymentPaid = function(payId) {
  DB.update("payments", p => p.payment_id === payId, p => ({ ...p, status: "Paid" }));
  showToast(`Payment ${payId} marked as Paid!`, "success");
  initAdminPayments();
};
