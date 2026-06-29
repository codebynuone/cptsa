/* ==================== nav.js — shared navigation ==================== */

(function () {
    // Determine current page to highlight active link
    var page = window.location.pathname.split('/').pop() || 'index.html';

    var NAV_HTML = `
    <div class="bg-navy text-white py-1 text-sm" style="font-size:0.8rem;">
        <div class="max-w-7xl mx-auto px-4 flex" style="justify-content:space-between;align-items:center;">
            <span>Government of Sri Lanka - Central Province</span>
            <span id="navTopRight" style="font-size:0.78rem;"></span>
        </div>
    </div>
    <div class="flag-stripe"></div>
    <nav id="mainNav">
        <div class="inner">
            <div class="nav-logo">
                <img src="https://tsa.cp.gov.lk/live_tracking/img/cptsa_logo.png" alt="CPTSA Logo" style="height:45px;width:auto;object-fit:contain;" onerror="this.style.display='none'">
                <div>
                    <div style="font-weight:700;font-size:0.9rem;color:var(--navy);line-height:1.2;">Transport Services Authority</div>
                    <div style="font-size:0.72rem;color:var(--sage);">Central Province Driving School</div>
                </div>
            </div>
            <div class="nav-links" id="desktopLinks">
                <a href="index.html"         class="nav-link" data-page="index.html">Home</a>
                <a href="services.html"      class="nav-link" data-page="services.html">Services</a>
                <a href="admin.html"         class="nav-link" data-page="admin.html">Admin Portal</a>
                <a href="instructors.html"   class="nav-link" data-page="instructors.html">Instructors</a>
                <a href="announcements.html" class="nav-link" data-page="announcements.html">Announcements</a>
                <a href="contact.html"       class="nav-link" data-page="contact.html">Contact Us</a>
                <a href="about.html"         class="nav-link" data-page="about.html">About</a>
            </div>
            <div class="nav-actions">
                <button id="navSignInBtn" class="btn btn-secondary" onclick="navShowLogin('student')" style="display:none;">Sign In</button>
                <button id="navAdminBtn" class="btn btn-primary" onclick="navShowLogin('admin')" style="display:none;">Admin Sign In</button>
                <button id="navSignOutBtn" class="btn btn-danger" onclick="navSignOut()" style="display:none;">Sign Out</button>
                <button class="hamburger" onclick="toggleMobileMenu()" aria-label="Menu">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
        <div id="mobileMenu">
            <a href="index.html">Home</a>
            <a href="services.html">Services</a>
            <a href="admin.html">Admin Portal</a>
            <a href="instructors.html">Instructors</a>
            <a href="announcements.html">Announcements</a>
            <a href="contact.html">Contact Us</a>
            <a href="about.html">About</a>
            <a href="students.html">Go to Student Portal</a>
        </div>
    </nav>`;

    // Inject nav at top of body
    document.body.insertAdjacentHTML('afterbegin', NAV_HTML);

    // Highlight active link
    document.querySelectorAll('.nav-link[data-page]').forEach(function (a) {
        if (a.getAttribute('data-page') === page) a.classList.add('active');
    });

    function toggleMobileMenu() {
        document.getElementById('mobileMenu').classList.toggle('open');
    }
    window.toggleMobileMenu = toggleMobileMenu;

    // ===== AUTH BUTTONS =====
    function updateNavAuthButtons() {
        var session = null;
        try { session = JSON.parse(localStorage.getItem('currentSession')); } catch (e) {}
        var signInBtn = document.getElementById('navSignInBtn');
        var adminBtn = document.getElementById('navAdminBtn');
        var signOutBtn = document.getElementById('navSignOutBtn');
        var topRight = document.getElementById('navTopRight');
        if (session) {
            if (signInBtn) signInBtn.style.display = 'none';
            if (adminBtn) adminBtn.style.display = 'none';
            if (signOutBtn) signOutBtn.style.display = '';
            if (topRight) topRight.textContent = 'Signed in as: ' + (session.name || session.userId || session.role);
        } else {
            if (signInBtn) signInBtn.style.display = '';
            if (adminBtn) adminBtn.style.display = '';
            if (signOutBtn) signOutBtn.style.display = 'none';
            if (topRight) topRight.textContent = 'ශ්‍රී ලංකා රජය - මධ්‍යම පළාත';
        }
    }

    window.navShowLogin = function (role) {
        // If on index page with modal support
        if (typeof showLoginModal === 'function') {
            showLoginModal(role);
        } else {
            window.location.href = 'student_portal.html';
        }
    };

    window.navSignOut = function () {
        localStorage.removeItem('currentSession');
        sessionStorage.clear();
        updateNavAuthButtons();
        showToast && showToast('Signed out successfully');
        setTimeout(function () { window.location.href = 'index.html'; }, 800);
    };

    function showToast(msg) {
        var t = document.getElementById('toast');
        if (!t) return;
        var m = document.getElementById('toastMessage');
        if (m) m.textContent = msg;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 3000);
    }

    // Listen for storage changes (login/logout from another tab)
    window.addEventListener('storage', function (e) {
        if (e.key === 'currentSession') updateNavAuthButtons();
    });

    // On load
    document.addEventListener('DOMContentLoaded', function () {
        updateNavAuthButtons();
    });

    // Export for main pages
    window.updateNavAuthButtons = updateNavAuthButtons;

})();
