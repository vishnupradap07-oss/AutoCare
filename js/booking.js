/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Booking Wizard & Price Calculation Logic (js/booking.js)
 */

document.addEventListener("DOMContentLoaded", () => {
  initBookingPage();
});

function initBookingPage() {
  const bookingForm = document.getElementById("bookingForm");
  if (!bookingForm) return;

  const serviceSelect = document.getElementById("bookService");
  const dateInput = document.getElementById("bookDate");
  const timeSelect = document.getElementById("bookTime");
  const vehicleSelect = document.getElementById("userVehicleSelect");
  const vehicleNumberInput = document.getElementById("bookVehicleNumber");
  const vehicleBrandInput = document.getElementById("bookVehicleBrand");
  const vehicleModelInput = document.getElementById("bookVehicleModel");
  const vehicleTypeSelect = document.getElementById("bookVehicleType");

  const nameInput = document.getElementById("bookCustomerName");
  const emailInput = document.getElementById("bookCustomerEmail");
  const phoneInput = document.getElementById("bookCustomerPhone");

  // Prevent past dates
  if (dateInput) {
    const today = new Date().toISOString().split("T")[0];
    dateInput.min = today;
    if (!dateInput.value) {
      dateInput.value = today;
    }
  }

  // Load services into dropdown
  const services = DB.get("services");
  if (serviceSelect && services.length > 0) {
    serviceSelect.innerHTML = `<option value="">-- Choose a Service Package --</option>` +
      services.map(s => `<option value="${s.service_id}" data-price="${s.price}" data-name="${s.name}" data-duration="${s.duration}">${s.name} – ${formatCurrency(s.price)} (${s.duration})</option>`).join("");
  }

  // Check URL query parameters for preselected service
  const urlParams = new URLSearchParams(window.location.search);
  const preselectedServiceId = urlParams.get("serviceId");
  const preselectedServiceName = urlParams.get("service");

  if (serviceSelect) {
    if (preselectedServiceId) {
      serviceSelect.value = preselectedServiceId;
    } else if (preselectedServiceName) {
      const match = services.find(s => s.name.toLowerCase() === preselectedServiceName.toLowerCase());
      if (match) serviceSelect.value = match.service_id;
    }
  }

  // If customer is logged in, auto populate customer & vehicles
  const currentUser = getCurrentUser();
  if (currentUser && currentUser.role === "customer") {
    if (nameInput) nameInput.value = currentUser.name;
    if (emailInput) emailInput.value = currentUser.email;
    if (phoneInput && currentUser.phone) phoneInput.value = currentUser.phone;

    // Load registered vehicles for this customer
    const userVehicles = DB.filter("vehicles", v => v.customer_name === currentUser.name || (v.customer_id && currentUser.user_id && v.customer_id.includes(currentUser.user_id.slice(-3))));
    
    if (vehicleSelect && userVehicles.length > 0) {
      const vehicleWrapper = document.getElementById("savedVehicleWrapper");
      if (vehicleWrapper) vehicleWrapper.classList.remove("d-none");

      vehicleSelect.innerHTML = `<option value="">-- Select from your registered vehicles --</option>` +
        userVehicles.map(v => `<option value="${v.registration_number}" data-brand="${v.brand}" data-model="${v.model}" data-fuel="${v.fuel_type}">${v.registration_number} - ${v.brand} ${v.model} (${v.year})</option>`).join("");

      vehicleSelect.addEventListener("change", () => {
        const selectedOpt = vehicleSelect.options[vehicleSelect.selectedIndex];
        if (vehicleSelect.value) {
          vehicleNumberInput.value = vehicleSelect.value;
          vehicleBrandInput.value = selectedOpt.dataset.brand || "";
          vehicleModelInput.value = selectedOpt.dataset.model || "";
          updateBookingSummary();
        }
      });

      // Default select first vehicle
      if (userVehicles[0]) {
        vehicleSelect.selectedIndex = 1;
        vehicleNumberInput.value = userVehicles[0].registration_number;
        vehicleBrandInput.value = userVehicles[0].brand;
        vehicleModelInput.value = userVehicles[0].model;
      }
    }
  }

  // Bind live change events for summary card
  const summaryTriggerElements = [
    serviceSelect, dateInput, timeSelect,
    vehicleNumberInput, vehicleBrandInput, vehicleModelInput, vehicleTypeSelect
  ];

  summaryTriggerElements.forEach(el => {
    if (el) {
      el.addEventListener("input", updateBookingSummary);
      el.addEventListener("change", updateBookingSummary);
    }
  });

  // Initial Summary Render
  updateBookingSummary();

  // Booking Form Submission
  bookingForm.addEventListener("submit", (e) => {
    e.preventDefault();

    const customerName = nameInput ? nameInput.value.trim() : "";
    const customerEmail = emailInput ? emailInput.value.trim().toLowerCase() : "";
    const customerPhone = phoneInput ? phoneInput.value.trim() : "";
    const vehicleNumber = vehicleNumberInput ? vehicleNumberInput.value.trim().toUpperCase() : "";
    const vehicleBrand = vehicleBrandInput ? vehicleBrandInput.value.trim() : "";
    const vehicleModel = vehicleModelInput ? vehicleModelInput.value.trim() : "";
    const vehicleType = vehicleTypeSelect ? vehicleTypeSelect.value : "Hatchback";
    const selectedServiceId = serviceSelect ? serviceSelect.value : "";
    const bookingDate = dateInput ? dateInput.value : "";
    const bookingTime = timeSelect ? timeSelect.value : "";
    const problemDesc = document.getElementById("bookProblemDesc") ? document.getElementById("bookProblemDesc").value.trim() : "";

    // Form Validations
    if (!customerName || customerName.length < 3) {
      showToast("Please enter customer name (at least 3 characters).", "error");
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(customerEmail)) {
      showToast("Please enter a valid email address.", "error");
      return;
    }

    if (!/^[6-9]\d{9}$/.test(customerPhone)) {
      showToast("Please enter a valid 10-digit mobile number.", "error");
      return;
    }

    const cleanRegNo = vehicleNumber.replace(/\s+/g, '');
    if (!/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/.test(cleanRegNo)) {
      showToast("Please enter a valid vehicle registration number (e.g. TN38AB1234).", "error");
      return;
    }

    if (!vehicleBrand || !vehicleModel) {
      showToast("Please specify the vehicle brand and model.", "error");
      return;
    }

    if (!selectedServiceId) {
      showToast("Please select a service package.", "error");
      return;
    }

    if (!bookingDate || !bookingTime) {
      showToast("Please select your preferred service date and time slot.", "error");
      return;
    }

    // Find service details
    const serviceObj = services.find(s => s.service_id === selectedServiceId);
    if (!serviceObj) {
      showToast("Invalid service selection.", "error");
      return;
    }

    // Find or assign mechanic
    const mechanics = DB.get("mechanics");
    const availableMechanic = mechanics.find(m => m.status === "Available") || mechanics[0];

    // Generate unique ID: AC + YYYYMMDD + 4 digits
    const bookingId = generateBookingId();

    const newBooking = {
      booking_id: bookingId,
      customer_id: currentUser ? currentUser.user_id : `GUEST-${Date.now().toString().slice(-4)}`,
      customer_name: customerName,
      customer_email: customerEmail,
      customer_phone: customerPhone,
      vehicle_id: `VEH-${Date.now().toString().slice(-4)}`,
      vehicle_number: cleanRegNo,
      vehicle_model: `${vehicleBrand} ${vehicleModel}`,
      vehicle_type: vehicleType,
      service_id: serviceObj.service_id,
      service_name: serviceObj.name,
      booking_date: bookingDate,
      booking_time: bookingTime,
      problem_description: problemDesc || "Standard service request",
      mechanic_id: availableMechanic ? availableMechanic.mechanic_id : "MEC-01",
      mechanic_name: availableMechanic ? availableMechanic.name : "Arun Kumar",
      amount: serviceObj.price,
      status: "Confirmed",
      payment_status: "Pending",
      created_at: new Date().toISOString().replace("T", " ").substring(0, 16)
    };

    // Save Booking
    DB.insert("bookings", newBooking);

    // Save Payment Entry
    const paymentId = `PAY${new Date().getFullYear()}${Math.floor(1000 + Math.random() * 9000)}`;
    DB.insert("payments", {
      payment_id: paymentId,
      booking_id: bookingId,
      customer_name: customerName,
      amount: serviceObj.price,
      payment_method: "UPI",
      payment_date: bookingDate,
      status: "Pending"
    });

    // Also auto-save vehicle if not present
    const existingVehicles = DB.get("vehicles");
    if (!existingVehicles.some(v => v.registration_number === cleanRegNo)) {
      DB.insert("vehicles", {
        vehicle_id: newBooking.vehicle_id,
        customer_id: newBooking.customer_id,
        customer_name: customerName,
        registration_number: cleanRegNo,
        brand: vehicleBrand,
        model: vehicleModel,
        year: new Date().getFullYear(),
        fuel_type: "Petrol",
        last_service: "New Booking Scheduled",
        next_service: bookingDate
      });
    }

    // Display confirmation modal
    showBookingConfirmation(newBooking);
  });
}

// Live Summary Calculator
function updateBookingSummary() {
  const serviceSelect = document.getElementById("bookService");
  const dateInput = document.getElementById("bookDate");
  const timeSelect = document.getElementById("bookTime");
  const vehicleNumberInput = document.getElementById("bookVehicleNumber");
  const vehicleBrandInput = document.getElementById("bookVehicleBrand");
  const vehicleModelInput = document.getElementById("bookVehicleModel");

  const sumVehicle = document.getElementById("sumVehicle");
  const sumService = document.getElementById("sumService");
  const sumDate = document.getElementById("sumDate");
  const sumTime = document.getElementById("sumTime");
  const sumSubtotal = document.getElementById("sumSubtotal");
  const sumTaxes = document.getElementById("sumTaxes");
  const sumTotal = document.getElementById("sumTotal");

  if (!sumTotal) return;

  // Vehicle info
  const brand = vehicleBrandInput ? vehicleBrandInput.value.trim() : "";
  const model = vehicleModelInput ? vehicleModelInput.value.trim() : "";
  const regNo = vehicleNumberInput ? vehicleNumberInput.value.trim().toUpperCase() : "";

  if (brand || model || regNo) {
    sumVehicle.textContent = `${brand} ${model} ${regNo ? `(${regNo})` : ''}`.trim();
  } else {
    sumVehicle.textContent = "Vehicle not specified yet";
  }

  // Service & Price
  let price = 0;
  let serviceName = "Select a service package";

  if (serviceSelect && serviceSelect.selectedIndex > 0) {
    const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
    price = parseInt(selectedOption.dataset.price, 10) || 0;
    serviceName = selectedOption.dataset.name || selectedOption.text;
  }

  if (sumService) sumService.textContent = serviceName;

  // Date and Time
  if (sumDate) {
    sumDate.textContent = dateInput && dateInput.value ? formatDate(dateInput.value) : "Not selected";
  }
  if (sumTime) {
    sumTime.textContent = timeSelect && timeSelect.value ? timeSelect.value : "Not selected";
  }

  // Calculations
  const tax = Math.round(price * 0.18); // 18% GST estimate
  const finalTotal = price > 0 ? (price + tax) : 0;

  if (sumSubtotal) sumSubtotal.textContent = formatCurrency(price);
  if (sumTaxes) sumTaxes.textContent = formatCurrency(tax);
  if (sumTotal) sumTotal.textContent = formatCurrency(finalTotal);
}

// Show Confirmation Modal
function showBookingConfirmation(booking) {
  const modalEl = document.getElementById("bookingConfirmationModal");
  if (!modalEl) {
    alert(`Booking Confirmed!\nBooking ID: ${booking.booking_id}\nAmount: ${formatCurrency(booking.amount)}`);
    window.location.href = `status.html?id=${booking.booking_id}`;
    return;
  }

  document.getElementById("modalBookingId").textContent = booking.booking_id;
  document.getElementById("modalCustomer").textContent = booking.customer_name;
  document.getElementById("modalVehicle").textContent = `${booking.vehicle_model} (${booking.vehicle_number})`;
  document.getElementById("modalService").textContent = booking.service_name;
  document.getElementById("modalDateTime").textContent = `${formatDate(booking.booking_date)} at ${booking.booking_time}`;
  document.getElementById("modalAmount").textContent = formatCurrency(booking.amount);
  document.getElementById("modalMechanic").textContent = booking.mechanic_name;

  const trackBtn = document.getElementById("modalTrackBtn");
  if (trackBtn) {
    trackBtn.href = `status.html?id=${booking.booking_id}`;
  }

  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}
