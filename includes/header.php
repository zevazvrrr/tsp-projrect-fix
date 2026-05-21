<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $page_title ?? 'Village InviteMail Express' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="static/css/style.css"/>
</head>
<body>
  <div class="app-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-envelope-open-text"></i></div>
        <div class="brand-text">
          <span class="brand-title">Village</span>
          <span class="brand-subtitle">InviteMail Express</span>
          <span class="brand-desc">PENGANTARAN SURAT UNDANGAN ACARA DESA</span>
        </div>
      </div>

      <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
      <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
        <a href="undangan.php" class="nav-item <?= $current_page == 'undangan.php' ? 'active' : '' ?>">
          <i class="fas fa-envelope"></i>
          <span>Undangan</span>
        </a>
        <a href="kurir.php" class="nav-item <?= $current_page == 'kurir.php' ? 'active' : '' ?>">
          <i class="fas fa-motorcycle"></i>
          <span>Kurir</span>
        </a>
        <a href="tsp.php" class="nav-item <?= $current_page == 'tsp.php' ? 'active' : '' ?>">
          <i class="fas fa-route"></i>
          <span>Rute TSP</span>
        </a>
        <a href="laporan.php" class="nav-item <?= $current_page == 'laporan.php' ? 'active' : '' ?>">
          <i class="fas fa-chart-bar"></i>
          <span>Laporan</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-wrapper">
      <!-- TOPBAR -->
      <header class="topbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
          <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-search">
          <i class="fas fa-search"></i>
          <input type="text" placeholder="Cari data..." id="globalSearch"/>
        </div>
        <div class="topbar-right">
          <div class="user-avatar-wrap">
            <div class="user-avatar">
              <i class="fas fa-user-shield"></i>
            </div>
            <span class="user-name"><?= strtoupper($_SESSION['username'] ?? 'ADMIN') ?></span>
          </div>
        </div>
      </header>

      <!-- PAGE CONTENT -->
      <main class="page-content">
