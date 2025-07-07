<?php 
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
  header('Location: login.php'); exit();
}
$conn = new mysqli("localhost", "root", "", "dvine_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Logout
if (isset($_POST['logout'])) {
  session_destroy();
  header("Location: login.php"); exit();
}

// Add user
if (isset($_POST['add_user'])) {
  $username = $_POST['username'];
  $email = $_POST['email'];
  $role = $_POST['role'];
  $phone = $_POST['phone'];
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

  $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
  $check->bind_param("s", $email);
  $check->execute(); $check->store_result();

  if ($check->num_rows > 0) {
    echo "<script>alert('Email already exists!');</script>";
  } else {
    $stmt = $conn->prepare("INSERT INTO users (username, email, role, phone, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $email, $role, $phone, $password);
    $stmt->execute();
  }
}

// Edit user
if (isset($_POST['edit_user'])) {
  $id = $_POST['user_id'];
  $username = $_POST['username'];
  $email = $_POST['email'];
  $role = $_POST['role'];
  $phone = $_POST['phone'];
  $new_password = $_POST['password'];

  if (!empty($new_password)) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, phone=?, password=? WHERE id=?");
    $stmt->bind_param("sssssi", $username, $email, $role, $phone, $hashed, $id);
  } else {
    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, phone=? WHERE id=?");
    $stmt->bind_param("ssssi", $username, $email, $role, $phone, $id);
  }
  $stmt->execute();
}

// Delete user
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  $conn->query("DELETE FROM users WHERE id=$id");
}

// Excel Import
if (isset($_FILES['excel_file']['name']) && $_FILES['excel_file']['tmp_name']) {
  require 'vendor/autoload.php';
  $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['excel_file']['tmp_name']);
  $sheet = $spreadsheet->getActiveSheet()->toArray();

  $added = 0; $skipped = 0; $duplicateEmails = [];

  for ($i = 1; $i < count($sheet); $i++) {
    $username = $sheet[$i][0];
    $email = $sheet[$i][1];
    $role = $sheet[$i][2];
    $rawPassword = $sheet[$i][3];
    $phone = $sheet[$i][4];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }
    if (!in_array(strtolower($role), ['admin', 'member', 'guest'])) { $skipped++; continue; }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute(); $check->store_result();

    if ($check->num_rows > 0) {
      $skipped++; $duplicateEmails[] = htmlspecialchars($email);
    } else {
      $password = password_hash($rawPassword, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO users (username, email, role, phone, password) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param("sssss", $username, $email, $role, $phone, $password);
      $stmt->execute(); $added++;
    }
  }

  $_SESSION['import_feedback'] = [
    'added' => $added,
    'skipped' => $skipped,
    'duplicates' => $duplicateEmails
  ];
  header("Location: admin-dashboard.php"); exit();
}

// AJAX fetch
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
  header('Content-Type: application/json');
  $users = $conn->query("SELECT * FROM users");
  $results = $conn->query("SELECT * FROM quiz_results WHERE user_email IN (SELECT email FROM users WHERE role='guest')");
  $data = ['users' => [], 'results' => []];
  while ($row = $users->fetch_assoc()) $data['users'][] = $row;
  while ($res = $results->fetch_assoc()) $data['results'][] = $res;
  echo json_encode($data); exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
  <style>
    body { font-family: Arial; background: #f4f6f9; padding: 30px; }
    h2, h3 { margin-bottom: 20px; }
    input, select, button { padding: 8px; margin: 5px; width: 200px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #eee; }
    .actions a { margin-right: 10px; color: red; text-decoration: none; }
    .form-section { background: white; padding: 20px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 0 10px #ccc; }
    .feedback-box { background: #ffe0e0; border: 1px solid red; padding: 15px; margin-bottom: 20px; border-radius: 10px; }
  </style>
</head>
<body>

<?php if (isset($_SESSION['import_feedback'])): $f = $_SESSION['import_feedback']; unset($_SESSION['import_feedback']); ?>
<div class="feedback-box">
  <strong>Import Summary:</strong><br>
  ✅ <?= $f['added'] ?> users added<br>
  ⚠️ <?= $f['skipped'] ?> duplicates skipped<br>
  <?php if (!empty($f['duplicates'])): ?>
    <div><strong>Duplicate Emails:</strong>
      <ul style="margin:5px 0 0 20px; color:red;">
        <?php foreach ($f['duplicates'] as $dup): ?><li><?= $dup ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<h2>Welcome, Admin</h2>
<div style="text-align:right;">
  <form method="POST" style="display:inline;"><button name="logout">Logout</button></form>
</div>

<div class="form-section">
  <h3>Add New User</h3>
  <form method="POST">
    <input type="text" name="username" placeholder="Username" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="phone" placeholder="Phone Number" required>
    <select name="role" required>
      <option value="">Role</option><option value="admin">Admin</option><option value="member">Member</option><option value="guest">Guest</option>
    </select>
    <input type="text" name="password" placeholder="Password" required>
    <button type="submit" name="add_user">Add User</button>
  </form>
</div>

<div class="form-section">
  <h3>Import Users from Excel</h3>
  <form method="POST" enctype="multipart/form-data">
    <input type="file" name="excel_file" accept=".xlsx,.xls" required>
    <button type="submit">Import Excel</button>
    <button type="button" onclick="downloadSample()">Download Sample Sheet</button>
  </form>
</div>

<div class="form-section">
  <h3>User List</h3>
  <select id="roleFilter" onchange="filterTable()">
    <option value="all">All Roles</option><option value="admin">Admin</option><option value="member">Member</option><option value="guest">Guest</option>
  </select>
  <input type="text" id="search" placeholder="Search..." onkeyup="filterTable()">
  <br>
  <button onclick="exportFilteredTableToExcel('userTable', 'FilteredUsers')">Export Filtered Users</button>
  <button onclick="exportAllTableToExcel('userTable', 'AllUsers')">Export All Users</button>

  <table id="userTable">
    <thead>
      <tr><th>ID</th><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Actions</th></tr>
    </thead>
    <tbody id="userTableBody"></tbody>
  </table>
</div>

<div class="form-section">
  <h3>Guest Quiz Results</h3>
  <button onclick="exportFilteredTableToExcel('resultsTable', 'FilteredResults')">Export Filtered Results</button>
  <button onclick="exportAllTableToExcel('resultsTable', 'AllResults')">Export All Results</button>
  <table id="resultsTable">
    <thead>
      <tr><th>Email</th><th>Test</th><th>Score</th><th>Percentage</th><th>Date</th></tr>
    </thead>
    <tbody id="resultsTableBody"></tbody>
  </table>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none; position:fixed; top:20%; left:50%; transform:translateX(-50%); background:white; padding:20px; border:1px solid #ccc; box-shadow:0 0 10px #aaa; border-radius:10px; z-index:10;">
  <h3>Edit User</h3>
  <form method="POST">
    <input type="hidden" name="user_id" id="edit_id">
    <input type="text" name="username" id="edit_username" required>
    <input type="email" name="email" id="edit_email" required>
    <input type="text" name="phone" id="edit_phone" required>
    <select name="role" id="edit_role" required>
      <option value="admin">Admin</option><option value="member">Member</option><option value="guest">Guest</option>
    </select>
    <input type="password" name="password" placeholder="New Password (optional)">
    <div style="margin-top:10px;">
      <button type="submit" name="edit_user">Save</button>
      <button type="button" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
    </div>
  </form>
</div>

<script>
function exportAllTableToExcel(tableId, sheetName) {
  const table = document.getElementById(tableId);
  const wb = XLSX.utils.table_to_book(table, { sheet: sheetName });
  XLSX.writeFile(wb, sheetName + ".xlsx");
}
function exportFilteredTableToExcel(tableId, sheetName) {
  const table = document.getElementById(tableId);
  const rows = Array.from(table.querySelectorAll("tbody tr"));
  const exportTable = document.createElement("table");
  const thead = table.querySelector("thead").cloneNode(true);
  const tbody = document.createElement("tbody");

  rows.forEach(row => {
    if (row.style.display !== "none") tbody.appendChild(row.cloneNode(true));
  });
  exportTable.appendChild(thead); exportTable.appendChild(tbody);
  const wb = XLSX.utils.table_to_book(exportTable, { sheet: sheetName });
  XLSX.writeFile(wb, sheetName + ".xlsx");
}
function filterTable() {
  const role = document.getElementById("roleFilter").value;
  const search = document.getElementById("search").value.toLowerCase();
  const rows = document.querySelectorAll("#userTableBody tr");
  rows.forEach(row => {
    const matchRole = (role === "all" || row.cells[4].textContent.toLowerCase() === role);
    const matchSearch = row.innerText.toLowerCase().includes(search);
    row.style.display = (matchRole && matchSearch) ? "" : "none";
  });
}
function editUser(id, username, email, phone, role) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_username').value = username;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_phone').value = phone;
  document.getElementById('edit_role').value = role;
  document.getElementById('editModal').style.display = 'block';
}
function loadData() {
  fetch('admin-dashboard.php?action=fetch')
    .then(res => res.json())
    .then(data => {
      const userBody = document.getElementById("userTableBody");
      const resultBody = document.getElementById("resultsTableBody");
      userBody.innerHTML = ""; resultBody.innerHTML = "";
      data.users.forEach(u => {
        userBody.innerHTML += `
          <tr>
            <td>${u.id}</td>
            <td>${u.username}</td>
            <td>${u.email}</td>
            <td>${u.phone}</td>
            <td>${u.role}</td>
            <td class="actions">
              <a href="#" onclick="editUser(${u.id}, '${u.username}', '${u.email}', '${u.phone}', '${u.role}')">Edit</a> |
              <a href="?delete=${u.id}" onclick="return confirm('Delete user?')">Delete</a>
            </td>
          </tr>`;
      });
      data.results.forEach(r => {
        resultBody.innerHTML += `
          <tr>
            <td>${r.user_email}</td>
            <td>${r.test_name}</td>
            <td>${r.score}</td>
            <td>${r.percentage}%</td>
            <td>${r.date}</td>
          </tr>`;
      });
      filterTable();
    });
}
function downloadSample() {
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.aoa_to_sheet([
    ["Username", "Email", "Role", "Password", "Phone"],
    ["john", "john@example.com", "member", "123456", "9876543210"]
  ]);
  XLSX.utils.book_append_sheet(wb, ws, "Sample");
  XLSX.writeFile(wb, "sample_users.xlsx");
}
setInterval(loadData, 5000);
window.onload = loadData;
</script>



<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
function cleanTableForExport(tableId, filteredOnly = false) {
  const table = document.getElementById(tableId);
  const exportTable = document.createElement("table");
  const thead = document.createElement("thead");
  const tbody = document.createElement("tbody");

  // Copy header row excluding last column (Actions)
  const headerRow = table.querySelector("thead tr");
  const newHeaderRow = document.createElement("tr");
  Array.from(headerRow.cells).forEach((cell, index) => {
    if (cell.textContent.trim() !== "Actions") {
      newHeaderRow.appendChild(cell.cloneNode(true));
    }
  });
  thead.appendChild(newHeaderRow);

  // Copy rows (filtered or all), exclude Actions
  table.querySelectorAll("tbody tr").forEach(row => {
    if (!filteredOnly || row.style.display !== "none") {
      const newRow = document.createElement("tr");
      Array.from(row.cells).forEach((cell, i) => {
        if (i !== row.cells.length - 1) {
          newRow.appendChild(cell.cloneNode(true));
        }
      });
      tbody.appendChild(newRow);
    }
  });

  exportTable.appendChild(thead);
  exportTable.appendChild(tbody);
  return exportTable;
}

function exportFilteredTableToExcel(tableId, sheetName) {
  const exportTable = cleanTableForExport(tableId, true);
  const wb = XLSX.utils.table_to_book(exportTable, { sheet: sheetName });
  XLSX.writeFile(wb, sheetName + ".xlsx");
}

function exportAllTableToExcel(tableId, sheetName) {
  const exportTable = cleanTableForExport(tableId, false);
  const wb = XLSX.utils.table_to_book(exportTable, { sheet: sheetName });
  XLSX.writeFile(wb, sheetName + ".xlsx");
}

function filterTable() {
  const role = document.getElementById("roleFilter").value;
  const search = document.getElementById("search").value.toLowerCase();
  const rows = document.querySelectorAll("#userTableBody tr");
  rows.forEach(row => {
    const matchRole = (role === "all" || row.cells[4].textContent.toLowerCase() === role);
    const matchSearch = row.innerText.toLowerCase().includes(search);
    row.style.display = (matchRole && matchSearch) ? "" : "none";
  });
}

function editUser(id, username, email, phone, role) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_username').value = username;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_phone').value = phone;
  document.getElementById('edit_role').value = role;
  document.getElementById('editModal').style.display = 'block';
}

function loadData() {
  fetch('admin-dashboard.php?action=fetch')
    .then(res => res.json())
    .then(data => {
      const userBody = document.getElementById("userTableBody");
      const resultBody = document.getElementById("resultsTableBody");
      userBody.innerHTML = ""; resultBody.innerHTML = "";
      data.users.forEach(u => {
        userBody.innerHTML += `
          <tr>
            <td>${u.id}</td>
            <td>${u.username}</td>
            <td>${u.email}</td>
            <td>${u.phone}</td>
            <td>${u.role}</td>
            <td class="actions">
              <a href="#" onclick="editUser(${u.id}, '${u.username.replace(/'/g, "\\'")}', '${u.email}', '${u.phone}', '${u.role}')">Edit</a> |
              <a href="?delete=${u.id}" onclick="return confirm('Delete user?')">Delete</a>
            </td>
          </tr>`;
      });
      data.results.forEach(r => {
        resultBody.innerHTML += `
          <tr>
            <td>${r.user_email}</td>
            <td>${r.test_name}</td>
            <td>${r.score}</td>
            <td>${r.percentage}%</td>
            <td>${r.date}</td>
          </tr>`;
      });
      filterTable();
    });
}

function downloadSample() {
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.aoa_to_sheet([
    ["Username", "Email", "Role", "Password", "Phone"],
    ["john", "john@example.com", "member", "123456", "9876543210"]
  ]);
  XLSX.utils.book_append_sheet(wb, ws, "Sample");
  XLSX.writeFile(wb, "sample_users.xlsx");
}

setInterval(loadData, 5000);
window.onload = loadData;
</script>



</body>
</html>



