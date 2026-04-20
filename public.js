// Shared public pages JS
window.API = window.API || window.location.origin + '/Barangay853';

// Form Validation Utilities (For strict Regex and UI/UX feedback)
const Validation = {
  patterns: {
    // Forces exactly 11 digits starting with 09
    phone: /^09\d{9}$/,
    // Forces proper structure with @ and domain
    email: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
  },
  
  setupLiveValidation: function(inputId, warningId, patternType) {
    const input = document.getElementById(inputId);
    const warning = document.getElementById(warningId);
    if (!input || !warning) return;

    input.addEventListener('input', (e) => {
      const value = e.target.value;
      const isValid = this.patterns[patternType].test(value);

      if (value.length === 0) {
        // Reset if empty
        input.classList.remove('input-error', 'input-success');
        warning.style.display = 'none';
      } else if (!isValid) {
        // Show Red
        input.classList.add('input-error');
        input.classList.remove('input-success');
        warning.style.display = 'block';
      } else {
        // Show Green
        input.classList.add('input-success');
        input.classList.remove('input-error');
        warning.style.display = 'none';
      }
    });
  }
};

// Set nav auth state on every page
function initNav() {
  const user = JSON.parse(localStorage.getItem('user') || 'null');
  const token = localStorage.getItem('auth_token');
  const navAuth = document.getElementById('nav-auth');
  if (!navAuth) return;

  if (user && token) {
    const initial = (user.name || 'U').charAt(0).toUpperCase();
    navAuth.innerHTML = `
      <div class="nav-user-menu">
        <div class="user-avatar-nav">${initial}</div>
        <div class="user-dropdown">
          <div class="user-dropdown-header">
            <div class="user-avatar-nav">${initial}</div>
            <div>
              <p class="user-name-nav">${user.name || 'User'}</p>
              <p class="user-role-nav">${user.role || 'user'}</p>
            </div>
          </div>
          <div class="user-dropdown-menu">
            <button class="dropdown-item" onclick="goToDashboard()">Dashboard</button>
            <button class="dropdown-item logout-item" onclick="handleLogout()">Logout</button>
          </div>
        </div>
      </div>`;
  } else {
    navAuth.innerHTML = `<a href="login.html" class="btn-login">Login</a>`;
  }
}

function goToDashboard() {
  const user = JSON.parse(localStorage.getItem('user') || 'null');
  if (user?.role === 'resident') {
    window.location.href = 'resident-dashboard.html';
  } else {
    window.location.href = 'dashboard.html';
  }
}

async function handleLogout() {
  const token = localStorage.getItem('auth_token');
  if (token) {
    await fetch(`${API}/auth/logout.php`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` }
    }).catch(() => {});
  }
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  window.location.href = 'index.html';
}

document.addEventListener('DOMContentLoaded', initNav);

// Dynamic Authentication Button (RBAC Navigation)
document.addEventListener('DOMContentLoaded', () => {
  const navAuth = document.getElementById('nav-auth');
  
  // If the navbar element doesn't exist on the page, skip
  if (!navAuth) return;
  
  const token = localStorage.getItem('auth_token');
  const userStr = localStorage.getItem('user');
  
  if (token && userStr) {
    const user = JSON.parse(userStr);
    
    // Determine the correct dashboard link and label based on their role
    let dashboardLink = 'resident-dashboard.html';
    let label = 'My Dashboard';
    
    // If Admin or Staff, send them to the main admin panel
    if (user.role === 'admin' || user.role === 'staff') {
      dashboardLink = 'dashboard.html';
      label = 'Admin Panel';
    }
    
    // Create the personalized profile button
    const initial = (user.name || user.email || 'U').charAt(0).toUpperCase();
    navAuth.innerHTML = `
      <a href="${dashboardLink}" style="background: #0b2250; color: #fff; text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; transition: background 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="width: 24px; height: 24px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">${initial}</div>
        ${label}
      </a>
    `;
  } else {
    // User is NOT logged in, show the default Login button
    navAuth.innerHTML = `
      <a href="login.html" style="background: #3b82f6; color: #fff; text-decoration: none; padding: 0.5rem 1.25rem; border-radius: 0.5rem; font-weight: 600; display: inline-block; transition: background 0.2s;">Login</a>
    `;
  }
});