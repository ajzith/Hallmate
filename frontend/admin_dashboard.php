<?php
// frontend/admin_dashboard.php
if (session_status() === PHP_SESSION_NONE) session_start();


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /frontend/login.html'); 
    exit;
}

require_once __DIR__ . '/../backend/db.php'; 

$stuCount = 0;
$roomCount = 0;
$facultyCount = 0;

if (isset($mysqli)) {
    $stuResult = $mysqli->query("SELECT COUNT(*) AS cnt FROM students");
    if ($stuResult) $stuCount = $stuResult->fetch_assoc()['cnt'];

 
    $roomResult = $mysqli->query("SELECT COUNT(*) AS cnt FROM room"); 
    if ($roomResult) $roomCount = $roomResult->fetch_assoc()['cnt'];

    $facultyResult = $mysqli->query("SELECT COUNT(*) AS cnt FROM faculty");
    if ($facultyResult) $facultyCount = $facultyResult->fetch_assoc()['cnt'];
    
} else {
    error_log("Database connection variable (\$mysqli) not available in admin_dashboard.php");
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hallmate — Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { font-family: Inter, system-ui; background:#f7fbff; }
    .card-tile {
      border-radius:16px;
      padding:1.5rem;
      color:#fff;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      transition: transform .2s ease;
      min-height: 150px; /* Ensure consistent height */
    }
    .card-tile:hover { transform:translateY(-3px); }
    .card-tile h5 { font-weight:600; margin-bottom:.5rem; }
    .card-tile p { margin:0; font-size:.9rem; opacity:.9; }
    .stats-card {
      border-radius:16px;
      padding:1.5rem;
      background:#fff;
      box-shadow:0 4px 12px rgba(0,0,0,.06);
      display:flex;
      align-items:center;
      justify-content:space-between;
    }
    .stats-card .count { font-size:2rem; font-weight:700; }
    footer { text-align:center; margin-top:3rem; font-size:.9rem; color:#555; }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="d-flex align-items-center mb-4">
      <img src="/assets/logo.png" style="height:56px;margin-right:12px" alt="logo"> <div>
        <h3 class="m-0">Hallmate</h3>
        <small class="text-muted">Admin Dashboard</small>
      </div>
      <button id="logoutBtn" class="btn btn-outline-secondary btn-sm ms-auto">Logout</button>
    </div>

    <div class="row g-4">
      <div class="col-md-6 col-lg-4">
        <a href="/frontend/manage_students.html" class="text-decoration-none"> <div class="card-tile bg-primary">
            <div><h5>Manage Students</h5><p>Add / edit / approve student records</p></div>
            <i class="bi bi-people-fill fs-2 align-self-end"></i>
          </div>
        </a>
      </div>

      <div class="col-md-6 col-lg-4">
        <a href="/frontend/manage_rooms.html" class="text-decoration-none"> <div class="card-tile bg-success">
            <div><h5>Manage Rooms</h5><p>Add halls & track capacity</p></div>
            <i class="bi bi-building fs-2 align-self-end"></i>
          </div>
        </a>
      </div>

      <div class="col-md-6 col-lg-4">
        <a href="/frontend/manage_exams.html" class="text-decoration-none"> <div class="card-tile bg-warning text-dark"> <div><h5>Manage Exams</h5><p>Schedule exam dates & times</p></div>
            <i class="bi bi-calendar-event fs-2 align-self-end"></i>
          </div>
        </a>
      </div>

      <div class="col-md-6 col-lg-4">
        <a href="/frontend/manage_faculty.html" class="text-decoration-none"> <div class="card-tile bg-info text-dark"> <div><h5>Manage Faculty</h5><p>Add / edit faculty records</p></div>
            <i class="bi bi-person-badge-fill fs-2 align-self-end"></i>
          </div>
        </a>
      </div>
      
      <div class="col-md-6 col-lg-4">
        <a href="/frontend/seating.html" class="text-decoration-none"> <div class="card-tile" style="background:#6f42c1"> <div><h5>Theory Seating</h5><p>Run automatic seat mapping</p></div>
            <i class="bi bi-grid-3x3-gap-fill fs-2 align-self-end"></i>
          </div>
        </a>
      </div>

      <div class="col-md-6 col-lg-4">
        <a href="/frontend/assign_lab.html" class="text-decoration-none">
          <div class="card-tile" style="background:#dc3545">
            <div><h5>Assign Lab Seating</h5><p>Manually assign labs to rooms</p></div>
            <i class="bi bi-person-bounding-box fs-2 align-self-end"></i>
          </div>
        </a>
      </div>
      <div class="col-md-6 col-lg-4">
        <a href="/frontend/manage_invigilation.html" class="text-decoration-none"> <div class="card-tile bg-secondary">
            <div><h5>Manage Invigilation</h5><p>Assign faculty to exams/rooms</p></div>
            <i class="bi bi-person-check-fill fs-2 align-self-end"></i> </div>
        </a>
      </div>
      
      <div class="col-md-6 col-lg-4">
        <div class="stats-card h-100"> <div><div class="text-muted">Students</div><div class="count text-primary"><?php echo $stuCount; ?></div></div>
          <i class="bi bi-person-lines-fill fs-1 text-primary"></i>
        </div>
      </div>

      <div class="col-md-6 col-lg-4">
        <div class="stats-card h-100">
          <div><div class="text-muted">Rooms</div><div class="count text-success"><?php echo $roomCount; ?></div></div>
          <i class="bi bi-door-open-fill fs-1 text-success"></i>
        </div>
      </div>

      <div class="col-md-6 col-lg-4">
        <div class="stats-card h-100">
          <div><div class="text-muted">Faculty</div><div class="count text-info"><?php echo $facultyCount; ?></div></div>
          <i class="bi bi-person-badge-fill fs-1 text-info"></i>
        </div>
      </div>
    </div>

    <footer>© <?php echo date("Y"); ?> Hallmate. All rights reserved.</footer>
  </div>

  <script>
  document.getElementById('logoutBtn').addEventListener('click', async () => {
    try {
      await fetch('/backend/logout.php', { method: 'POST', credentials: 'same-origin' });
    } catch(e){ console.error("Logout failed:", e); }
    window.location.href = '/frontend/login.html'; 
  });
  </script>
</body>
</html>