<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Regular Employee Dashboard</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <!-- EMPLOYEE TOPBAR / SIDEBAR / RIGHTBAR -->
    <div id="topbar-placeholder"></div>
    <div id="sidebar-placeholder"></div>
    <div id="rightbar-placeholder"></div>

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
