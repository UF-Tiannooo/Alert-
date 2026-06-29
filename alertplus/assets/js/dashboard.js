// ─────────────────────────────
// ALERT+ DASHBOARD JS (FIXED)
// ─────────────────────────────

// PAGE NAVIGATION
function showPage(page, btn) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const target = document.getElementById('page-' + page);
    if (target) target.classList.add('active');

    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
}

function confirmExit() {
    if (confirm("Exit dashboard?")) {
        window.location.href = "logout.php";
    }
}

// ─────────────────────────────
// MAP INIT
// ─────────────────────────────
const map = L.map('map', {
    zoomControl: true
}).setView([6.9214, 122.0790], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

// STATE
const markers = {};
const colorMap = {};

CHILDREN.forEach(c => {
    colorMap[c.id] = c.color;
});

// ─────────────────────────────
// ICON (LARGE + CLEAR MARKER)
// ─────────────────────────────
function makeIcon(color, label) {
    return L.divIcon({
        className: '',
        html: `
        <div style="
            width:42px;height:42px;
            background:${color};
            border:3px solid #fff;
            border-radius:50% 50% 50% 0;
            transform:rotate(-45deg);
            display:flex;
            align-items:center;
            justify-content:center;
            box-shadow:0 3px 10px rgba(0,0,0,0.35);
        ">
            <span style="
                transform:rotate(45deg);
                color:#fff;
                font-weight:900;
                font-size:13px;
            ">${label}</span>
        </div>`,
        iconSize: [42, 42],
        iconAnchor: [21, 42],
        popupAnchor: [0, -40]
    });
}

// ─────────────────────────────
// CLICKABLE ZOOM FUNCTION
// ─────────────────────────────
function zoomTo(lat, lng) {
    map.setView([lat, lng], 18, {
        animate: true
    });
}

// ─────────────────────────────
// FETCH LOCATIONS (FIXED + STABLE)
// ─────────────────────────────
async function fetchLocations() {
    try {
        const res = await fetch('api/get_locations.php');
        const data = await res.json();

        const bounds = [];

        data.forEach(loc => {
            const cid = loc.child_id;
            const lat = parseFloat(loc.latitude);
            const lng = parseFloat(loc.longitude);

            bounds.push([lat, lng]);

            const icon = makeIcon(
                colorMap[cid] || '#e74c3c',
                (loc.child_name || 'U').charAt(0)
            );

            const popup = `
                <b>${loc.child_name}</b><br>
                ${loc.source}<br>
                ${lat}, ${lng}
            `;

            if (markers[cid]) {
                markers[cid]
                    .setLatLng([lat, lng])
                    .unbindPopup()
                    .bindPopup(popup);
            } else {
                markers[cid] = L.marker([lat, lng], { icon })
                    .addTo(map)
                    .bindPopup(popup);
            }
        });

    } catch (e) {
        console.error('fetchLocations error:', e);
    }
}

// ─────────────────────────────
// COMMAND SYSTEM (FIXED)
// ─────────────────────────────
async function doCommand(type) {
    const childId = document.getElementById('selectedUser').value;

    if (!childId) return showToast('Select a user first', 'error');

    if (!['ping', 'ring'].includes(type)) {
        return showToast('Invalid command', 'error');
    }

    try {
        const res = await fetch('api/send_command.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                child_id: childId,
                command_type: type
            })
        });

        const data = await res.json();

        if (data.success) {
            showToast(type === 'ping'
                ? '📍 Ping sent'
                : '📞 Ring sent', 'success');
        } else {
            showToast(data.error || 'Failed', 'error');
        }

    } catch (e) {
        showToast('Network error', 'error');
    }
}

// ─────────────────────────────
// ADD USER (FIXED ID)
// ─────────────────────────────
document.getElementById('addUserForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = new FormData(this);

    try {
        const res = await fetch('api/add_child.php', {
            method: 'POST',
            body: form
        });

        const data = await res.json();

        if (data.success) {
            showToast('User added', 'success');
            this.reset();
        } else {
            showToast(data.error || 'Failed', 'error');
        }

    } catch (e) {
        showToast('Network error', 'error');
    }
});

// ─────────────────────────────
// TOAST
// ─────────────────────────────
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast show ${type}`;
    setTimeout(() => t.className = 'toast', 3000);
}

// ─────────────────────────────
// AUTO REFRESH
// ─────────────────────────────
fetchLocations();
fetchLogs();
setInterval(() => {
    fetchLocations();
    fetchLogs();
}, 10000);

function focusMap(lat, lng, childId) {
    // switch to map page
    showPage('map');

    // zoom map
    map.setView([lat, lng], 18, {
        animate: true
    });

    // open marker popup if exists
    if (markers[childId]) {
        markers[childId].openPopup();
        flashMarker(childId);
    }
}

function flashMarker(childId) {
    if (!markers[childId]) return;

    const m = markers[childId];
    const el = m.getElement();

    if (el) {
        el.style.transform = "scale(1.3)";
        setTimeout(() => {
            el.style.transform = "scale(1)";
        }, 300);
    }
}