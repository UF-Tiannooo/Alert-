<?php
require_once 'config/db.php';
requireLogin();

$db       = getDB();
$parentId = $_SESSION['user_id'];

// ── Fetch children for selectors ──
$children = $db->query("SELECT * FROM children ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// ── Recent logs for mini table (dashboard view — last 3) ──
$miniLogs = $db->query("
    SELECT ll.*, c.name AS child_name
    FROM location_logs ll
    JOIN children c ON c.id = ll.child_id
    ORDER BY ll.received_at DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$db->close();

// ── Weather fetch (cached 10 min in session) ──
$weather = ['desc' => 'Loading...', 'icon' => '🌤️', 'day' => date('l'), 'time' => date('g:i A')];
if (!empty($_SESSION['weather_cache']) && time() - $_SESSION['weather_time'] < 600) {
    $weather = $_SESSION['weather_cache'];
} elseif (defined('OWM_API_KEY') && OWM_API_KEY !== 'YOUR_OPENWEATHERMAP_KEY') {
    $url = "https://api.openweathermap.org/data/2.5/weather?lat=".WEATHER_LAT."&lon=".WEATHER_LON."&appid=".OWM_API_KEY."&units=metric";
    $raw = @file_get_contents($url);
    if ($raw) {
        $wd = json_decode($raw, true);
        $desc = ucfirst($wd['weather'][0]['description'] ?? 'Clear');
        $iconMap = ['01'=>'☀️','02'=>'🌤️','03'=>'☁️','04'=>'☁️','09'=>'🌧️','10'=>'🌦️','11'=>'⛈️','13'=>'❄️','50'=>'🌫️'];
        $iconCode = substr($wd['weather'][0]['icon'] ?? '01d', 0, 2);
        $ico  = $iconMap[$iconCode] ?? '🌤️';
        $weather = ['desc' => $desc, 'icon' => $ico, 'day' => date('l'), 'time' => date('g:i A')];
        $_SESSION['weather_cache'] = $weather;
        $_SESSION['weather_time']  = time();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALERT+ | Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ══════════════════════════════════════
     SIDEBAR
══════════════════════════════════════ -->
<aside class="sidebar">
  <div class="sidebar-logo">ALERT +</div>

  <button class="nav-btn active" onclick="showPage('map',this)">Map</button>
  <button class="nav-btn"       onclick="showPage('logs',this)">Activity Logs</button>
  <button class="nav-btn"       onclick="showPage('users',this)">Users</button>
  <button class="nav-btn"       onclick="showPage('settings',this)">Settings</button>
  <button class="nav-btn"       onclick="confirmExit()">Exit</button>

  <div class="nav-spacer"></div>

  <!-- Connected indicator -->
  <div class="sidebar-connected" id="connStatus">✦ Connecting...</div>

  <!-- Weather widget -->
  <div class="sidebar-weather">
    <span class="weather-icon"><?=htmlspecialchars($weather['icon'])?></span>
    Weather<br>
    <?=htmlspecialchars($weather['day'])?> <?=htmlspecialchars($weather['time'])?><br>
    <?=htmlspecialchars($weather['desc'])?>
  </div>
</aside>

<!-- ══════════════════════════════════════
     CONTENT WRAPPER
══════════════════════════════════════ -->
<div class="content">

  <!-- ══════════ PAGE: MAP ══════════ -->
  <div class="page active" id="page-map">

    <!-- Live map -->
    <div class="map-wrap">
      <div id="map"></div>
    </div>

    <!-- Action bar -->
    <div class="action-bar">
      <button class="btn-ping" onclick="doCommand('ping')">Ping Location</button>
      <button class="btn-ring" onclick="doCommand('ring')">
        Ring User <span class="phone-icon">📞</span>
      </button>
      <!-- User selector -->
      <div class="user-select-wrap">
        <select class="user-select" id="selectedUser">
          <?php foreach($children as $c): ?>
          <option value="<?=htmlspecialchars($c['id'])?>"
                  data-color="<?=htmlspecialchars($c['color'])?>">
            <?=htmlspecialchars($c['name'])?>
          </option>
          <?php endforeach ?>
          <?php if(empty($children)): ?>
          <option value="">No users yet</option>
          <?php endif ?>
        </select>
      </div>
    </div>

    <!-- Mini recent log table -->
    <div class="mini-table-wrap">
      <table class="data-table" id="miniLogTable">
        <thead>
          <tr>
            <th>Time</th>
            <th>Date</th>
            <th>Location/Coordinates</th>
            <th>User</th>
          </tr>
        </thead>
        <tbody id="miniLogBody">
          <?php foreach($miniLogs as $log):
            $dms = decToDMS((float)$log['latitude'], (float)$log['longitude']);
          ?>
          <tr>
            <td><?=date('H:i', strtotime($log['received_at']))?></td>
            <td><?=date('F d, Y', strtotime($log['received_at']))?></td>
            <td style="cursor:pointer;color:#1e90ff"
                onclick="focusMap(<?= $log['latitude'] ?>, <?= $log['longitude'] ?>, <?= $log['child_id'] ?>)">
                <?=htmlspecialchars($dms)?>
            </td>
            <td><?=htmlspecialchars($log['child_name'])?></td>
          </tr>
          <?php endforeach ?>
          <?php if(empty($miniLogs)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:16px">No activity yet</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div><!-- /page-map -->


  <!-- ══════════ PAGE: LOGS ══════════ -->
  <div class="page" id="page-logs">
    <div class="page-toolbar">
      <input type="text" class="search-input" id="logSearch"
             placeholder="Search . . ." oninput="filterLogs()">
      <div class="sort-wrap">
        <select class="sort-select" id="logSort" onchange="filterLogs()">
          <option value="">Sort By</option>
          <option value="time_asc">Time ↑</option>
          <option value="time_desc">Time ↓</option>
          <option value="user">User A–Z</option>
          <option value="type">Type A–Z</option>
        </select>
      </div>
    </div>

    <div class="table-card">
      <table class="data-table" id="logsTable">
        <thead>
          <tr>
            <th>Time</th>
            <th>Date</th>
            <th>Type</th>
            <th>Location/Coordinates</th>
            <th>User</th>
          </tr>
        </thead>
        <tbody id="logsBody">
          <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--muted)">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div><!-- /page-logs -->


  <!-- ══════════ PAGE: USERS ══════════ -->
  <div class="page" id="page-users">
    <div class="page-toolbar">
      <input type="text" class="search-input" id="userSearch"
             placeholder="Search . . ." oninput="filterUsers()">
      <div class="sort-wrap">
        <select class="sort-select" id="userSort" onchange="filterUsers()">
          <option value="">Sort By</option>
          <option value="name">Name A–Z</option>
          <option value="name_desc">Name Z–A</option>
        </select>
      </div>
    </div>

    <div class="page-scroll">
      <!-- Add User Card -->
      <div class="card-block" style="margin-bottom:14px">
        <div class="card-block-title">Add User</div>
        <form id="addUserForm">
          <div class="form-row" style="align-items:flex-start;gap:20px;flex-wrap:wrap">
            <div>
              <div class="form-field" style="margin-bottom:10px">
                <label>Name</label>
                <input type="text" name="name" class="form-input" placeholder="" required>
              </div>
              <div class="form-field">
                <label>Number</label>
                <input type="text" name="phone" class="form-input" placeholder="+63912..." required>
              </div>
            </div>
            <div style="margin-left:auto;display:flex;flex-direction:column;align-items:flex-end;gap:6px">
              <span class="color-label">Assign Color (Marker/Indicator)</span>
              <div class="color-picker-wrap">
                <input type="color" name="color" class="color-input" value="#0000ff">
              </div>
            </div>
          </div>
          <button type="submit" class="btn-save">Save</button>
        </form>
      </div>

      <!-- Saved Users Card -->
      <div class="card-block">
        <div class="card-block-title">Saved Users</div>
        <div id="savedUsersList">
          <?php foreach($children as $c): ?>
          <div class="saved-user-row" data-id="<?=$c['id']?>"
               data-name="<?=htmlspecialchars($c['name'],ENT_QUOTES)?>"
               data-phone="<?=htmlspecialchars($c['phone'],ENT_QUOTES)?>"
               data-color="<?=htmlspecialchars($c['color'],ENT_QUOTES)?>">
            <span class="saved-user-name"><?=htmlspecialchars($c['name'])?></span>
            <span class="saved-user-phone"><?=htmlspecialchars($c['phone'])?></span>
            <div class="color-swatch" style="background:<?=htmlspecialchars($c['color'])?>"></div>
            <div style="position:relative">
              <button class="dots-btn" onclick="toggleCtx(this)">⋮</button>
              <div class="ctx-menu">
                <button class="ctx-item" onclick="editUser(this)">Edit</button>
                <button class="ctx-item" onclick="deleteUser(this)">Delete</button>
              </div>
            </div>
          </div>
          <?php endforeach ?>
          <?php if(empty($children)): ?>
          <p style="text-align:center;color:var(--muted);padding:14px;font-size:13px">No users registered yet.</p>
          <?php endif ?>
        </div>
      </div>
    </div>
  </div><!-- /page-users -->


  <!-- ══════════ PAGE: SETTINGS ══════════ -->
  <div class="page" id="page-settings">
    <div class="settings-card">
      <div class="settings-title">⚙️ System Settings</div>
      <form id="settingsForm">
        <div class="settings-field">
          <label>Admin Name</label>
          <input type="text" name="admin_name"
                 value="<?=htmlspecialchars($_SESSION['user_name'])?>">
        </div>
        <div class="settings-field">
          <label>Change Password</label>
          <input type="password" name="new_password" placeholder="Leave blank to keep current">
        </div>
        <div class="settings-field">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" placeholder="Repeat new password">
        </div>
        <div class="settings-field">
          <label>Map Auto-refresh Interval (seconds)</label>
          <input type="number" name="refresh_interval" min="5" max="120" value="10">
        </div>
        <button type="submit" class="btn-settings-save">Save Settings</button>
      </form>
    </div>
  </div><!-- /page-settings -->

</div><!-- /content -->

<!-- ══════════════ EDIT USER MODAL ══════════════ -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <h3>Edit User</h3>
    <form id="editUserForm">
      <input type="hidden" name="id" id="editId">
      <div class="settings-field">
        <label>Name</label>
        <input type="text" name="name" id="editName" class="settings-field input" required
               style="width:100%;padding:9px 12px;border:1.5px solid rgba(232,25,44,.25);border-radius:8px;background:#fff;font-family:var(--font);font-size:13px;outline:none">
      </div>
      <div class="settings-field">
        <label>Phone</label>
        <input type="text" name="phone" id="editPhone" required
               style="width:100%;padding:9px 12px;border:1.5px solid rgba(232,25,44,.25);border-radius:8px;background:#fff;font-family:var(--font);font-size:13px;outline:none">
      </div>
      <div class="settings-field">
        <label>Marker Color</label>
        <input type="color" name="color" id="editColor"
               style="width:58px;height:36px;border:none;border-radius:8px;cursor:pointer">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn-confirm">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Pass PHP data to JS -->
<script>
const CHILDREN     = <?=json_encode($children)?>;
const REFRESH_SECS = 10;
</script>

<script src="assets/js/dashboard.js"></script>
</body>
</html>