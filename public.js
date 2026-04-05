// Shared public pages JS
const API = 'http://localhost/Barangay853';

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
