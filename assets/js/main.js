// Main JavaScript file for Lagusan Coffee Skydeck

// Format currency
function formatCurrency(amount) {
  return (
    "₱" +
    Number.parseFloat(amount)
      .toFixed(2)
      .replace(/\d(?=(\d{3})+\.)/g, "$&,")
  )
}

// Show notification
function showNotification(message, type = "success") {
  const notification = document.createElement("div")
  notification.className = `notification notification-${type}`
  notification.textContent = message
  document.body.appendChild(notification)

  setTimeout(() => {
    notification.classList.add("show")
  }, 100)

  setTimeout(() => {
    notification.classList.remove("show")
    setTimeout(() => notification.remove(), 300)
  }, 3000)
}

// Confirm dialog
function confirmAction(message) {
  return confirm(message)
}

// Password visibility toggle
function togglePasswordVisibility() {
  const passwordInput = document.getElementById('password');
  const eyeIcon = document.querySelector('#toggle-password svg path');

  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    eyeIcon.setAttribute('d', 'M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z M14.707 9.293a1 1 0 010 1.414l-1.414-1.414a1 1 0 011.414 0z M18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1z M7 10a1 1 0 011-1h1a1 1 0 110 2H8a1 1 0 01-1-1z');
  } else {
    passwordInput.type = 'password';
    eyeIcon.setAttribute('d', 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z M12 9a3 3 0 100 6 3 3 0 000-6z');
  }
}

// Initialize password toggle on page load
document.addEventListener('DOMContentLoaded', function() {
  const toggleButton = document.getElementById('toggle-password');
  if (toggleButton) {
    toggleButton.addEventListener('click', togglePasswordVisibility);
  }
});

// AJAX helper
async function fetchAPI(url, options = {}) {
  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        ...options.headers,
      },
    })
    return await response.json()
  } catch (error) {
    console.error("API Error:", error)
    showNotification("An error occurred. Please try again.", "error")
    return null
  }
}
