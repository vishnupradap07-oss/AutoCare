/**
 * AutoCare – Vehicle Service & Maintenance Management System
 * Authentication & Registration Logic (js/auth.js)
 */

document.addEventListener("DOMContentLoaded", () => {
  initLoginForm();
  initRegisterForm();
  initProfileForm();
});

// ==========================================================================
// 1. Login Logic
// ==========================================================================
function initLoginForm() {
  const loginForm = document.getElementById("loginForm");
  if (!loginForm) return;

  loginForm.addEventListener("submit", (e) => {
    e.preventDefault();

    const email = document.getElementById("loginEmail").value.trim().toLowerCase();
    const password = document.getElementById("loginPassword").value;
    const loginAlert = document.getElementById("loginAlert");

    if (loginAlert) loginAlert.classList.add("d-none");

    if (!email || !password) {
      showLoginError("Please enter both email and password.");
      return;
    }

    const users = DB.get("users");
    const matchedUser = users.find(u => u.email.toLowerCase() === email && u.password === password);

    if (matchedUser) {
      // Save session
      setCurrentUser({
        user_id: matchedUser.user_id,
        name: matchedUser.name,
        email: matchedUser.email,
        role: matchedUser.role,
        phone: matchedUser.phone || ""
      });

      showToast(`Welcome back, ${matchedUser.name}!`, "success");

      // Redirect based on role
      setTimeout(() => {
        if (matchedUser.role === "admin") {
          window.location.href = "admin/dashboard.html";
        } else {
          window.location.href = "customer/dashboard.html";
        }
      }, 600);
    } else {
      showLoginError("Invalid email or password. Please check your credentials or click a demo account below.");
    }
  });
}

function showLoginError(msg) {
  const loginAlert = document.getElementById("loginAlert");
  if (loginAlert) {
    loginAlert.textContent = msg;
    loginAlert.classList.remove("d-none");
  } else {
    showToast(msg, "error");
  }
}

// Quick Autofill for Demo Roles
function fillDemo(role) {
  const emailInput = document.getElementById("loginEmail");
  const passwordInput = document.getElementById("loginPassword");
  if (!emailInput || !passwordInput) return;

  if (role === "customer") {
    emailInput.value = "customer@autocare.com";
    passwordInput.value = "customer123";
    showToast("Filled Customer Demo Credentials", "info");
  } else if (role === "admin") {
    emailInput.value = "admin@autocare.com";
    passwordInput.value = "admin123";
    showToast("Filled Admin Demo Credentials", "info");
  }
}

// ==========================================================================
// 2. Registration Logic
// ==========================================================================
function initRegisterForm() {
  const registerForm = document.getElementById("registerForm");
  if (!registerForm) return;

  registerForm.addEventListener("submit", (e) => {
    e.preventDefault();

    const fullName = document.getElementById("regFullName").value.trim();
    const email = document.getElementById("regEmail").value.trim().toLowerCase();
    const mobile = document.getElementById("regMobile").value.trim();
    const password = document.getElementById("regPassword").value;
    const confirmPassword = document.getElementById("regConfirmPassword").value;

    const vehicleNumber = document.getElementById("regVehicleNumber").value.trim().toUpperCase();
    const vehicleBrand = document.getElementById("regVehicleBrand").value.trim();
    const vehicleModel = document.getElementById("regVehicleModel").value.trim();
    const vehicleYear = parseInt(document.getElementById("regVehicleYear").value, 10);
    const fuelType = document.getElementById("regFuelType") ? document.getElementById("regFuelType").value : "Petrol";

    const alertBox = document.getElementById("registerAlert");
    if (alertBox) alertBox.classList.add("d-none");

    // Client Validation
    if (!fullName || fullName.length < 3) {
      showRegisterError("Please enter your full name (minimum 3 characters).");
      return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showRegisterError("Please enter a valid email address.");
      return;
    }

    const mobileRegex = /^[6-9]\d{9}$/;
    if (!mobileRegex.test(mobile)) {
      showRegisterError("Please enter a valid 10-digit Indian mobile number starting with 6-9.");
      return;
    }

    if (!password || password.length < 8) {
      showRegisterError("Password must be at least 8 characters long.");
      return;
    }

    if (password !== confirmPassword) {
      showRegisterError("Passwords do not match. Please verify.");
      return;
    }

    // Vehicle validation
    const regNoClean = vehicleNumber.replace(/\s+/g, '');
    const vehicleRegex = /^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/;
    if (!vehicleRegex.test(regNoClean)) {
      showRegisterError("Vehicle number format is invalid. Example format: TN38AB1234 or DL01A1234");
      return;
    }

    if (!vehicleBrand || !vehicleModel) {
      showRegisterError("Please specify your vehicle brand and model.");
      return;
    }

    const currentYear = new Date().getFullYear();
    if (isNaN(vehicleYear) || vehicleYear < 1995 || vehicleYear > currentYear + 1) {
      showRegisterError(`Manufacturing year must be between 1995 and ${currentYear + 1}.`);
      return;
    }

    // Check existing email
    const users = DB.get("users");
    if (users.some(u => u.email.toLowerCase() === email)) {
      showRegisterError("An account with this email address already exists. Please login instead.");
      return;
    }

    // Create record IDs
    const newUserId = `USR-${Date.now().toString().slice(-4)}`;
    const newCustId = `CUST-${Date.now().toString().slice(-4)}`;
    const newVehId = `VEH-${Date.now().toString().slice(-4)}`;

    const newUser = {
      user_id: newUserId,
      name: fullName,
      email: email,
      password: password,
      role: "customer",
      phone: mobile,
      created_at: new Date().toISOString().split("T")[0]
    };

    const newCustomer = {
      customer_id: newCustId,
      user_id: newUserId,
      name: fullName,
      email: email,
      phone: mobile,
      address: "Registered Online Customer",
      total_bookings: 0,
      status: "active"
    };

    const newVehicle = {
      vehicle_id: newVehId,
      customer_id: newCustId,
      customer_name: fullName,
      registration_number: regNoClean,
      brand: vehicleBrand,
      model: vehicleModel,
      year: vehicleYear,
      fuel_type: fuelType,
      last_service: "New Registration",
      next_service: "Pending First Booking"
    };

    // Save to localStorage tables
    DB.insert("users", newUser);
    DB.insert("customers", newCustomer);
    DB.insert("vehicles", newVehicle);

    // Auto-login newly registered customer
    setCurrentUser({
      user_id: newUser.user_id,
      name: newUser.name,
      email: newUser.email,
      role: "customer",
      phone: newUser.phone
    });

    showToast("Account & Vehicle registered successfully!", "success");

    setTimeout(() => {
      window.location.href = "customer/dashboard.html";
    }, 1000);
  });
}

function showRegisterError(msg) {
  const alertBox = document.getElementById("registerAlert");
  if (alertBox) {
    alertBox.textContent = msg;
    alertBox.classList.remove("d-none");
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  } else {
    showToast(msg, "error");
  }
}

// ==========================================================================
// 3. Customer Profile Form Handling
// ==========================================================================
function initProfileForm() {
  const profileForm = document.getElementById("profileForm");
  if (!profileForm) return;

  const user = requireAuth("customer");
  if (!user) return;

  // Pre-fill profile
  const custNameInput = document.getElementById("profName");
  const custEmailInput = document.getElementById("profEmail");
  const custPhoneInput = document.getElementById("profPhone");
  const custAddressInput = document.getElementById("profAddress");

  const customerRecord = DB.find("customers", c => c.email.toLowerCase() === user.email.toLowerCase()) || {
    name: user.name,
    email: user.email,
    phone: user.phone || "",
    address: ""
  };

  if (custNameInput) custNameInput.value = customerRecord.name || user.name;
  if (custEmailInput) custEmailInput.value = customerRecord.email || user.email;
  if (custPhoneInput) custPhoneInput.value = customerRecord.phone || user.phone || "";
  if (custAddressInput) custAddressInput.value = customerRecord.address || "";

  profileForm.addEventListener("submit", (e) => {
    e.preventDefault();

    const updatedName = custNameInput.value.trim();
    const updatedPhone = custPhoneInput.value.trim();
    const updatedAddress = custAddressInput ? custAddressInput.value.trim() : "";

    if (!updatedName || updatedName.length < 3) {
      showToast("Full name must be at least 3 characters.", "error");
      return;
    }

    // Update customer table
    DB.update("customers", c => c.email.toLowerCase() === user.email.toLowerCase(), (c) => ({
      ...c,
      name: updatedName,
      phone: updatedPhone,
      address: updatedAddress
    }));

    // Update users table
    DB.update("users", u => u.email.toLowerCase() === user.email.toLowerCase(), (u) => ({
      ...u,
      name: updatedName,
      phone: updatedPhone
    }));

    // Update session
    user.name = updatedName;
    user.phone = updatedPhone;
    setCurrentUser(user);

    showToast("Profile details updated successfully!", "success");
  });

  // Password Change Form
  const passwordForm = document.getElementById("passwordChangeForm");
  if (passwordForm) {
    passwordForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const currentPass = document.getElementById("currentPass").value;
      const newPass = document.getElementById("newPass").value;
      const confirmNewPass = document.getElementById("confirmNewPass").value;

      const userRec = DB.find("users", u => u.email.toLowerCase() === user.email.toLowerCase());

      if (userRec && userRec.password !== currentPass) {
        showToast("Current password does not match.", "error");
        return;
      }

      if (newPass.length < 8) {
        showToast("New password must be at least 8 characters.", "error");
        return;
      }

      if (newPass !== confirmNewPass) {
        showToast("New password confirmation does not match.", "error");
        return;
      }

      DB.update("users", u => u.email.toLowerCase() === user.email.toLowerCase(), (u) => ({
        ...u,
        password: newPass
      }));

      passwordForm.reset();
      showToast("Password updated successfully!", "success");
    });
  }
}
