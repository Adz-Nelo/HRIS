<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Regular Employee Dashboard</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- MOBILE CSS INLINE - Only applies below 768px -->
    <style>
        @media screen and (max-width: 768px) {
            /* Root variables adjustment for mobile */
            :root {
                --topbar-height: 60px;
            }

            /* TOP BAR MOBILE */
            .topbar {
                padding: 0 15px;
                height: 60px;
            }

            .logo-text {
                display: none; /* Hide logo text on mobile */
            }

            .logo {
                width: 32px;
                height: 32px;
            }

            .menu-toggle {
                padding: 8px;
                font-size: 18px;
            }

            .topbar-right {
                gap: 8px;
            }

            .email-icon-top {
                font-size: 20px;
            }

            .profile-trigger,
            .topbar-profile {
                width: 34px;
                height: 34px;
            }

            /* SIDEBAR MOBILE - Hidden by default */
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
                z-index: 1300; /* Higher z-index on mobile */
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            }

            .sidebar.active {
                transform: translateX(0); /* Show sidebar when active */
            }

            /* Mobile overlay for sidebar */
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1250;
                display: none;
            }

            .sidebar.active ~ .sidebar-overlay {
                display: block;
            }

            /* MAIN CONTENT MOBILE */
            .main-content {
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
                padding: 20px 15px;
                margin-top: 60px; /* Adjusted for mobile topbar */
            }

            /* Hide right sidebar completely on mobile */
            .right-sidebar {
                display: none;
            }

            /* DASHBOARD MOBILE ADJUSTMENTS */
            .welcome-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                padding: 15px;
            }

            .date-time-widget {
                text-align: left;
                padding-left: 0;
                border-left: none;
                border-top: 1px solid #f1f5f9;
                padding-top: 15px;
                width: 100%;
            }

            .date-time-widget .time {
                font-size: 20px;
                min-width: auto;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
                padding: 15px;
            }

            .main-dashboard-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .content-card {
                padding: 15px;
            }

            /* FORM STYLES MOBILE */
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: span 1;
            }

            .toggle-btn {
                font-size: 14px;
                padding: 8px 12px;
            }

            /* TABLE STYLES MOBILE */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .accounts-table {
                font-size: 13px;
                min-width: 600px; /* Minimum width for scrolling */
            }

            .accounts-table th,
            .accounts-table td {
                padding: 10px 8px;
            }

            .filter-bar {
                flex-direction: column;
                gap: 10px;
            }

            .filter-group {
                width: 100%;
            }

            .filter-actions {
                width: 100%;
                justify-content: space-between;
            }

            .filter-actions .btn-primary,
            .filter-actions .btn-secondary {
                flex: 1;
                text-align: center;
                justify-content: center;
            }

            /* PAGINATION MOBILE */
            .pagination-container {
                justify-content: center;
            }

            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }

            /* BUILDER LAYOUT MOBILE */
            .builder-layout {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .builder-sidebar.right {
                display: none; /* Hide right sidebar in builder on mobile */
            }

            .builder-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-left,
            .toolbar-right {
                width: 100%;
                justify-content: space-between;
            }

            /* MODAL MOBILE */
            .modal-overlay {
                padding: 15px;
            }

            .modal-content {
                width: 100%;
                max-width: 100%;
                margin: 0;
            }

            .edit-grid {
                grid-template-columns: 1fr;
                max-height: 70vh;
            }

            /* FLYOUT SUBMENU MOBILE FIX */
            .flyout-submenu {
                position: fixed;
                left: 0;
                top: 60px;
                width: 100%;
                height: calc(100vh - 60px);
                z-index: 1400;
                border-radius: 0;
                border-left: none;
                border-top: 4px solid var(--primary-color);
            }

            .has-flyout .flyout-submenu {
                display: none;
            }

            .has-flyout.active .flyout-submenu {
                display: flex;
                opacity: 1;
                visibility: visible;
                transform: translateX(0);
            }

            /* LOGOUT MODAL MOBILE */
            .modal-card {
                width: 90%;
                padding: 20px;
            }

            .modal-buttons {
                flex-direction: column;
            }

            .btn-stay, .btn-logout {
                width: 100%;
            }

            /* FLOATING ACTION BUTTON FOR MOBILE */
            .mobile-fab {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: var(--primary-color);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                z-index: 1000;
                border: none;
                cursor: pointer;
                display: none; /* Hidden by default, shown via JS */
            }

            /* TOUCH DEVICE OPTIMIZATIONS */
            @media (hover: none) and (pointer: coarse) {
                .menu-item:hover {
                    background: transparent;
                }

                .menu-item:active {
                    background: var(--hover-bg);
                }

                /* Increase touch target sizes */
                .menu-item,
                .submenu-item,
                .icon-btn,
                .btn {
                    min-height: 44px; /* Apple's recommended minimum touch target */
                }
            }
        }

        /* Additional small mobile adjustments */
        @media screen and (max-width: 480px) {
            .main-content {
                padding: 15px 10px;
            }

            .content-card {
                padding: 12px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .card-header h2 {
                font-size: 16px;
            }

            /* Announcements */
            .announcement-item {
                flex-direction: column;
                gap: 10px;
            }

            .ann-date {
                align-self: flex-start;
            }

            /* Form inputs */
            .form-group input,
            .form-group select,
            .filter-group input,
            .filter-group select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            /* Table improvements */
            .icon-btn {
                padding: 4px 6px;
                font-size: 12px;
                margin-right: 2px;
            }

            .status {
                font-size: 10px;
                padding: 3px 8px;
            }

            /* Dashboard stats */
            .stat-info h3 {
                font-size: 20px;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
        }

        /* Landscape mode adjustments */
        @media screen and (max-height: 600px) and (orientation: landscape) {
            .topbar {
                height: 50px;
            }

            .sidebar {
                height: calc(100vh - 50px);
            }

            .main-content {
                margin-top: 50px;
                min-height: calc(100vh - 50px);
            }

            .menu-section {
                max-height: 60vh;
                overflow-y: auto;
            }
        }
    </style>
</head>

<body>
    <!-- EMPLOYEE TOPBAR / SIDEBAR / RIGHTBAR -->
    <div id="topbar-placeholder"></div>
    <div id="sidebar-placeholder"></div>
    <div id="rightbar-placeholder"></div>

    <!-- MOBILE FLOATING ACTION BUTTON (Will be added by JS) -->
    <div id="mobile-fab-container"></div>

    <!-- EMPLOYEE MAIN CONTENT -->
    <main class="main-content" id="main-content">
        <div class="content-container" id="contents-placeholder"></div>
    </main>

    <script>
        async function includeHTML(id, file) {
            const element = document.getElementById(id);
            if (!element) return;

            try {
                const response = await fetch(file);
                element.innerHTML = response.ok ? await response.text() : "Error loading " + file;
            } catch (err) {
                console.error("Fetch error:", err);
            }
        }

        async function init() {
            await includeHTML('topbar-placeholder', 'backend/topbar.php');
            await includeHTML('sidebar-placeholder', 'backend/sidebar.php');
            await includeHTML('rightbar-placeholder', 'backend/rightbar.php');
            await includeHTML('contents-placeholder', 'backend/main.php');

            const sidebar = document.getElementById('sidebar');
            const collapseBtn = document.getElementById('collapseBtn') || document.getElementById('menuToggle');
            const body = document.body;

            if (collapseBtn && sidebar) {
                collapseBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    body.classList.toggle('sidebar-collapsed');

                    const logoText = document.getElementById('logoText');
                    if (logoText) {
                        logoText.style.display = sidebar.classList.contains('collapsed') ? "none" : "block";
                    }
                });
            }

            // MOBILE SPECIFIC FUNCTIONALITY
            if (window.innerWidth <= 768) {
                setupMobileFunctionality();
            }

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    setupMobileFunctionality();
                } else {
                    // Reset mobile styles when resizing to desktop
                    const sidebar = document.querySelector('.sidebar');
                    const sidebarOverlay = document.querySelector('.sidebar-overlay');
                    const fab = document.querySelector('.mobile-fab');
                    
                    if (sidebar) sidebar.classList.remove('active');
                    if (sidebarOverlay) sidebarOverlay.style.display = 'none';
                    if (fab) fab.style.display = 'none';
                }
            });
        }

        function setupMobileFunctionality() {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (!sidebar || !menuToggle) return;

            // Create mobile overlay
            let sidebarOverlay = document.querySelector('.sidebar-overlay');
            if (!sidebarOverlay) {
                sidebarOverlay = document.createElement('div');
                sidebarOverlay.className = 'sidebar-overlay';
                document.body.appendChild(sidebarOverlay);
            }

            // Create mobile FAB if it doesn't exist
            let mobileFab = document.querySelector('.mobile-fab');
            if (!mobileFab) {
                mobileFab = document.createElement('button');
                mobileFab.className = 'mobile-fab';
                mobileFab.innerHTML = '<i class="fas fa-bars"></i>';
                document.getElementById('mobile-fab-container').appendChild(mobileFab);
            }
            mobileFab.style.display = 'flex';

            // Mobile menu toggle
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
            }

            // Add event listeners
            if (menuToggle) {
                menuToggle.addEventListener('click', toggleSidebar);
            }
            
            mobileFab.addEventListener('click', toggleSidebar);
            
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.style.display = 'none';
            });

            // Close sidebar when clicking a menu item
            const menuItems = document.querySelectorAll('.menu-item, .submenu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.style.display = 'none';
                });
            });

            // Flyout menu toggle for mobile
            const flyoutLinks = document.querySelectorAll('.has-flyout > a');
            flyoutLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const parent = this.parentElement;
                    
                    // Close other flyouts
                    flyoutLinks.forEach(otherLink => {
                        if (otherLink !== this) {
                            otherLink.parentElement.classList.remove('active');
                        }
                    });
                    
                    parent.classList.toggle('active');
                });
            });

            // Show/hide FAB based on scroll
            let lastScroll = 0;
            window.addEventListener('scroll', function() {
                const currentScroll = window.pageYOffset;
                if (mobileFab) {
                    if (currentScroll > lastScroll) {
                        mobileFab.style.transform = 'translateY(100px)';
                    } else {
                        mobileFab.style.transform = 'translateY(0)';
                    }
                }
                lastScroll = currentScroll;
            });

            // Set initial sidebar state for mobile
            sidebar.classList.remove('collapsed');
            document.body.classList.remove('sidebar-collapsed');
        }

        init();
    </script>

    <!-- REAL-TIME CLOCK -->
    <script>
        (function () {
            function updateClock() {
                const timeEl = document.getElementById('real-time');
                const dateEl = document.getElementById('real-date');
                if (!timeEl || !dateEl) return;

                const now = new Date();
                let h = now.getHours();
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;

                timeEl.textContent = `${h}:${m}:${s} ${ampm}`;
                dateEl.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'long', month: 'short', day: 'numeric', year: 'numeric'
                });
            }

            if (window.clockInterval) clearInterval(window.clockInterval);
            window.clockInterval = setInterval(updateClock, 1000);
            updateClock();
        })();
    </script>

    <!-- EMPLOYEE NAVIGATION + ROUTING -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {

            document.querySelectorAll('.has-flyout').forEach(item => {
                item.querySelector('.flyout-toggle')?.addEventListener('click', e => {
                    e.preventDefault();
                    item.classList.toggle('active');
                });
            });

            document.addEventListener("click", function (e) {
                const link = e.target.closest(".nav-link");
                if (!link) return;

                e.preventDefault();
                const page = link.getAttribute("href");
                const file = link.getAttribute("data-page");

                window.history.pushState({ file }, "", page);
                loadPage(file);
            });

            async function loadPage(file) {
                const content = document.getElementById("main-content");
                if (!content) return;

                try {
                    const res = await fetch(file);
                    if (!res.ok) throw new Error();
                    content.innerHTML = await res.text();
                } catch {
                    content.innerHTML = `<div style="padding:20px;color:red;">Page not found.</div>`;
                }
            }

            window.onpopstate = e => e.state?.file && loadPage(e.state.file);
        });
    </script>

</body>
</html>