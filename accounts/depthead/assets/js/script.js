// Function to load PHP/HTML components
async function includeHTML(id, file) {
    const element = document.getElementById(id);
    if (!element) return;

    try {
        const response = await fetch(file);
        if (response.ok) {
            element.innerHTML = await response.text();
        } else {
            element.innerHTML = "Error loading " + file;
        }
    } catch (err) {
        console.error("Fetch error:", err);
    }
}

// Function to update the real-time clock
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

// Initialize layout components and events
async function init() {
    // Load layouts from the backend folder
    await includeHTML('topbar-placeholder', 'backend/topbar.php');
    await includeHTML('sidebar-placeholder', 'backend/sidebar.php');
    await includeHTML('rightbar-placeholder', 'backend/rightbar.php');

    // Sidebar Collapse Logic
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

    // Start Clock
    setInterval(updateClock, 1000);
    updateClock();
}

// Run initialization
init();