<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kurir') {
    header("Location: login.php");
    exit;
}
$username  = $_SESSION['username'] ?? 'Kurir';
$kurir_id  = $_SESSION['kurir_id'] ?? null;

// Fetch stats for this courier
$total     = 0;
$pending   = 0;
$completed = 0;
$kurir_nama = $username;

if ($kurir_id) {
    $total     = $pdo->prepare("SELECT COUNT(*) FROM undangan WHERE kurir_id=?");
    $total->execute([$kurir_id]);
    $total     = (int)$total->fetchColumn();

    $pending   = $pdo->prepare("SELECT COUNT(*) FROM undangan WHERE kurir_id=? AND status='PENDING'");
    $pending->execute([$kurir_id]);
    $pending   = (int)$pending->fetchColumn();

    $completed = $pdo->prepare("SELECT COUNT(*) FROM undangan WHERE kurir_id=? AND status='COMPLETED'");
    $completed->execute([$kurir_id]);
    $completed = (int)$completed->fetchColumn();

    $kRow = $pdo->prepare("SELECT nama FROM kurir WHERE id=?");
    $kRow->execute([$kurir_id]);
    $kRow = $kRow->fetch();
    if ($kRow) $kurir_nama = $kRow['nama'];
}
$initials = strtoupper(substr($kurir_nama, 0, 2));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kurir Dashboard — Village InviteMail</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', sans-serif;
      background: #0f172a;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .app {
      width: 100%;
      max-width: 420px;
      min-height: 100vh;
      background: #0f172a;
      position: relative;
      overflow: hidden;
    }

    /* ── HERO HEADER ─────────────────────────────── */
    .hero {
      background: linear-gradient(135deg, #1e40af 0%, #7c3aed 60%, #0f172a 100%);
      padding: 48px 24px 80px;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      top: -60px; right: -60px;
      width: 220px; height: 220px;
      background: rgba(255,255,255,0.06);
      border-radius: 50%;
    }
    .hero::after {
      content: '';
      position: absolute;
      bottom: -40px; left: -40px;
      width: 160px; height: 160px;
      background: rgba(255,255,255,0.04);
      border-radius: 50%;
    }

    .hero-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
      position: relative; z-index: 1;
    }
    .brand {
      display: flex; align-items: center; gap: 8px;
    }
    .brand-icon {
      width: 36px; height: 36px;
      background: rgba(255,255,255,0.15);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      backdrop-filter: blur(4px);
    }
    .brand-icon i { color: #fff; font-size: 16px; }
    .brand-name {
      font-size: 13px; font-weight: 700;
      color: rgba(255,255,255,0.9);
      letter-spacing: 0.5px;
    }
    .brand-sub {
      font-size: 10px; font-weight: 400;
      color: rgba(255,255,255,0.55);
      display: block;
    }

    .logout-btn {
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 10px;
      padding: 8px 14px;
      color: #fff;
      font-size: 12px; font-weight: 600;
      text-decoration: none;
      display: flex; align-items: center; gap: 6px;
      backdrop-filter: blur(4px);
      transition: 0.2s;
    }
    .logout-btn:hover { background: rgba(255,255,255,0.2); }

    /* Avatar + greeting */
    .hero-profile { position: relative; z-index: 1; }
    .avatar-row { display: flex; align-items: center; gap: 16px; }
    .avatar {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, #60a5fa, #818cf8);
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; font-weight: 800; color: #fff;
      box-shadow: 0 8px 24px rgba(96,165,250,0.4);
      flex-shrink: 0;
    }
    .greeting { }
    .greeting-hello {
      font-size: 13px; color: rgba(255,255,255,0.6);
      font-weight: 400; margin-bottom: 2px;
    }
    .greeting-name {
      font-size: 20px; font-weight: 800; color: #fff;
      letter-spacing: 0.2px;
    }
    .greeting-role {
      display: inline-flex; align-items: center; gap: 5px;
      margin-top: 6px;
      background: rgba(255,255,255,0.12);
      border-radius: 20px;
      padding: 3px 10px;
      font-size: 11px; font-weight: 600; color: #a5f3fc;
    }
    .greeting-role i { font-size: 10px; }

    /* ── STAT CARDS ─────────────────────────────── */
    .stats-float {
      margin: -40px 20px 0;
      position: relative; z-index: 10;
      background: #1e293b;
      border-radius: 20px;
      padding: 20px;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 4px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.4);
      border: 1px solid rgba(255,255,255,0.06);
    }
    .stat-item {
      display: flex; flex-direction: column; align-items: center;
      padding: 8px 4px;
    }
    .stat-item + .stat-item {
      border-left: 1px solid rgba(255,255,255,0.07);
    }
    .stat-num {
      font-size: 28px; font-weight: 800;
      line-height: 1;
      background: linear-gradient(135deg, #60a5fa, #818cf8);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .stat-num.green {
      background: linear-gradient(135deg, #4ade80, #22c55e);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .stat-num.yellow {
      background: linear-gradient(135deg, #fbbf24, #f59e0b);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .stat-label {
      font-size: 10px; font-weight: 500;
      color: #64748b;
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      text-align: center;
    }

    /* ── MENU SECTION ───────────────────────────── */
    .section { padding: 28px 20px 0; }
    .section-title {
      font-size: 11px; font-weight: 700;
      color: #475569;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-bottom: 16px;
    }

    .menu-grid {
      display: flex; flex-direction: column; gap: 12px;
    }

    .menu-card {
      display: flex; align-items: center; gap: 16px;
      background: #1e293b;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 18px;
      padding: 18px 20px;
      text-decoration: none;
      transition: all 0.2s ease;
      position: relative;
      overflow: hidden;
    }
    .menu-card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, transparent, rgba(255,255,255,0.02));
      opacity: 0;
      transition: opacity 0.2s;
    }
    .menu-card:active { transform: scale(0.98); }
    .menu-card:hover::before { opacity: 1; }
    .menu-card:hover { border-color: rgba(255,255,255,0.12); }

    .menu-card.blue  { --accent: #3b82f6; --glow: rgba(59,130,246,0.15); }
    .menu-card.green { --accent: #22c55e; --glow: rgba(34,197,94,0.15); }
    .menu-card.red   { --accent: #ef4444; --glow: rgba(239,68,68,0.12); }

    .menu-icon {
      width: 48px; height: 48px;
      border-radius: 14px;
      background: var(--glow);
      border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .menu-icon i {
      font-size: 20px;
      color: var(--accent);
    }

    .menu-text { flex: 1; }
    .menu-title {
      font-size: 15px; font-weight: 700;
      color: #f1f5f9;
      margin-bottom: 3px;
    }
    .menu-desc {
      font-size: 12px;
      color: #64748b;
      font-weight: 400;
    }

    .menu-arrow {
      color: #334155;
      font-size: 14px;
    }

    /* ── DATE FOOTER ────────────────────────────── */
    .date-footer {
      padding: 28px 20px 36px;
      display: flex; align-items: center; gap: 8px;
    }
    .date-dot {
      width: 6px; height: 6px;
      background: #22c55e;
      border-radius: 50%;
      animation: blink 2s infinite;
    }
    @keyframes blink {
      0%,100% { opacity: 1; }
      50% { opacity: 0.3; }
    }
    .date-text {
      font-size: 12px; color: #475569; font-weight: 500;
    }
  </style>
</head>
<body>
<div class="app">

  <!-- HERO -->
  <div class="hero">
    <div class="hero-top">
      <div class="brand">
        <div class="brand-icon"><i class="fas fa-envelope-open-text"></i></div>
        <div>
          <span class="brand-name">InviteMail</span>
          <span class="brand-sub">Village Express</span>
        </div>
      </div>
      <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Keluar
      </a>
    </div>

    <div class="hero-profile">
      <div class="avatar-row">
        <div class="avatar"><?= $initials ?></div>
        <div class="greeting">
          <div class="greeting-hello">Selamat datang,</div>
          <div class="greeting-name"><?= htmlspecialchars($kurir_nama) ?></div>
          <div class="greeting-role">
            <i class="fas fa-motorcycle"></i> Petugas Kurir
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- FLOATING STATS -->
  <div class="stats-float">
    <div class="stat-item">
      <div class="stat-num"><?= $total ?></div>
      <div class="stat-label">Total</div>
    </div>
    <div class="stat-item">
      <div class="stat-num yellow"><?= $pending ?></div>
      <div class="stat-label">Pending</div>
    </div>
    <div class="stat-item">
      <div class="stat-num green"><?= $completed ?></div>
      <div class="stat-label">Selesai</div>
    </div>
  </div>

  <!-- MENU -->
  <div class="section">
    <div class="section-title">Menu Utama</div>
    <div class="menu-grid">

      <a href="kurir_konfirmasi.php" class="menu-card blue">
        <div class="menu-icon"><i class="fas fa-envelope-open"></i></div>
        <div class="menu-text">
          <div class="menu-title">Informasi Undangan</div>
          <div class="menu-desc">Lihat & konfirmasi pengantaran</div>
        </div>
        <i class="fas fa-chevron-right menu-arrow"></i>
      </a>

      <a href="kurir_rute.php" class="menu-card green">
        <div class="menu-icon"><i class="fas fa-map-marked-alt"></i></div>
        <div class="menu-text">
          <div class="menu-title">Rute Pengantaran</div>
          <div class="menu-desc">Lihat peta rute optimal TSP</div>
        </div>
        <i class="fas fa-chevron-right menu-arrow"></i>
      </a>

    </div>
  </div>

  <!-- DATE FOOTER -->
  <div class="date-footer">
    <div class="date-dot"></div>
    <div class="date-text" id="dateNow"></div>
  </div>

</div>

<script>
  const d = new Date();
  document.getElementById('dateNow').textContent =
    d.toLocaleDateString('id-ID', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
</script>
</body>
</html>
