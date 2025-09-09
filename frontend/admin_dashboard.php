<?php
// frontend/admin_dashboard.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /Hallmate/frontend/login.html');
    exit;
}
?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hallmate — Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Inter, system-ui; padding:2rem; background:#f7fbff; }
    .card { border-radius:12px; box-shadow:0 8px 24px rgba(16,24,40,0.06); }
    .logout { float:right; }
  </style>
</head>
<body>
  <div class="container">
    <div class="d-flex align-items-center mb-4">
      <img src="/Hallmate/assets/logo.png" style="height:56px;margin-right:12px" alt="logo">
      <h3 class="m-0">Hallmate — Admin</h3>
      <button id="logoutBtn" class="btn btn-outline-secondary btn-sm ms-auto">Logout</button>
    </div>

    <div class="row g-4">
      <div class="col-md-6">
        <div class="card p-4">
          <h5>Quick Actions</h5>
          <ul>
              <li><a href="/Hallmate/frontend/manage_students.html">Manage Students</a></li>
              <li><a href="/Hallmate/frontend/manage_rooms.html">Manage Rooms</a></li>
              <li><a href="/Hallmate/frontend/manage_exams.html">Create Exams</a></li>
              <li><a href="/Hallmate/frontend/seating.html">Run Seat Allocation</a></li>
          </ul>

        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-4">
          <h5>Stats</h5>
          <p class="small-muted">Students: <strong id="stuCount">—</strong></p>
          <p class="small-muted">Rooms: <strong id="roomCount">—</strong></p>
        </div>
      </div>
    </div>
  </div>

<script>
document.getElementById('logoutBtn').addEventListener('click', async () => {
  try {
    await fetch('/Hallmate/backend/logout.php', { method: 'POST', credentials: 'same-origin' });
  } catch(e){}
  window.location.href = '/Hallmate/frontend/login.html';
});
</script>
</body>
</html>
