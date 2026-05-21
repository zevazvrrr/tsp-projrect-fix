<?php
require_once 'db.php';
check_auth();
$page_title = 'Dashboard — Village InviteMail Express';
require_once 'includes/header.php';

$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM undangan")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM undangan WHERE status='COMPLETED'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM undangan WHERE status='PENDING'")->fetchColumn(),
    'failed' => $pdo->query("SELECT COUNT(*) FROM undangan WHERE status='FAILED'")->fetchColumn(),
    'kurir' => $pdo->query("SELECT COUNT(*) FROM kurir")->fetchColumn(),
    'warga' => $pdo->query("SELECT COUNT(DISTINCT nama_penerima) FROM undangan")->fetchColumn(),
];

$recent = $pdo->query("SELECT u.*, k.nama as kurir_nama FROM undangan u LEFT JOIN kurir k ON u.kurir_id=k.id ORDER BY u.created_at DESC LIMIT 5")->fetchAll();
$kurir_list = $pdo->query("SELECT * FROM kurir")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-sub">Selamat datang kembali, <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong> 👋</p>
  </div>
  <div class="page-actions">
    <span class="date-badge"><i class="fas fa-calendar-alt"></i> <span id="currentDate"></span></span>
  </div>
</div>

<!-- STAT CARDS -->
<div class="stats-grid">
  <div class="stat-card stat-blue">
    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
    <div class="stat-info">
      <span class="stat-val"><?= $stats['total'] ?></span>
      <span class="stat-label">Total Undangan</span>
    </div>
    <div class="stat-sparkline"></div>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
    <div class="stat-info">
      <span class="stat-val"><?= $stats['completed'] ?></span>
      <span class="stat-label">Terkirim</span>
    </div>
  </div>
  <div class="stat-card stat-yellow">
    <div class="stat-icon"><i class="fas fa-clock"></i></div>
    <div class="stat-info">
      <span class="stat-val"><?= $stats['pending'] ?></span>
      <span class="stat-label">Pending</span>
    </div>
  </div>
  <div class="stat-card stat-red">
    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
    <div class="stat-info">
      <span class="stat-val"><?= $stats['failed'] ?></span>
      <span class="stat-label">Gagal</span>
    </div>
  </div>
  <div class="stat-card stat-purple">
    <div class="stat-icon"><i class="fas fa-motorcycle"></i></div>
    <div class="stat-info">
      <span class="stat-val"><?= $stats['kurir'] ?></span>
      <span class="stat-label">Total Kurir</span>
    </div>
  </div>
  <div class="stat-card stat-indigo">
    <div class="stat-icon"><i class="fas fa-users"></i></div>
    <div class="stat-info">
      <span class="stat-val"><?= $stats['warga'] ?></span>
      <span class="stat-label">Total Warga</span>
    </div>
  </div>
</div>

<div class="dashboard-grid">
  <!-- DONUT CHART -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><i class="fas fa-chart-pie"></i> Distribusi Status</h2>
    </div>
    <div class="card-body donut-wrap">
      <canvas id="donutChart" width="200" height="200"></canvas>
      <div class="donut-legend">
        <div class="legend-item"><span class="dot dot-green"></span> Completed <strong><?= $stats['completed'] ?></strong></div>
        <div class="legend-item"><span class="dot dot-red"></span> Failed <strong><?= $stats['failed'] ?></strong></div>
        <div class="legend-item"><span class="dot dot-yellow"></span> Pending <strong><?= $stats['pending'] ?></strong></div>
      </div>
    </div>
  </div>

  <!-- KURIR STATUS -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><i class="fas fa-motorcycle"></i> Status Kurir</h2>
      <a href="kurir.php" class="card-link">Lihat Semua</a>
    </div>
    <div class="card-body">
      <?php foreach ($kurir_list as $k): ?>
      <div class="kurir-row">
        <div class="kurir-avatar"><i class="fas fa-user"></i></div>
        <div class="kurir-info">
          <span class="kurir-name"><?= htmlspecialchars($k['nama']) ?></span>
          <span class="kurir-plat"><?= htmlspecialchars($k['plat']) ?></span>
        </div>
        <span class="badge-status <?= $k['status'] == 'SEDANG BERTUGAS' ? 'badge-busy' : 'badge-free' ?>">
          <?= htmlspecialchars($k['status']) ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RECENT UNDANGAN -->
  <div class="card card-wide">
    <div class="card-header">
      <h2 class="card-title"><i class="fas fa-history"></i> Undangan Terbaru</h2>
      <a href="undangan.php" class="card-link">Lihat Semua</a>
    </div>
    <div class="card-body p0">
      <table class="data-table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Penerima</th>
            <th>Lokasi</th>
            <th>Kurir</th>
            <th>Status</th>
            <th>Tanggal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $u): ?>
          <tr>
            <td><span class="kode-badge"><?= htmlspecialchars($u['kode']) ?></span></td>
            <td>
              <div class="user-cell">
                <div class="user-mini-avatar"><i class="fas fa-user"></i></div>
                <?= htmlspecialchars($u['nama_penerima']) ?>
              </div>
            </td>
            <td><i class="fas fa-map-marker-alt" style="color:#60a5fa;margin-right:4px;"></i><?= htmlspecialchars($u['node_name']) ?></td>
            <td><?= htmlspecialchars($u['kurir_nama'] ?: '—') ?></td>
            <td>
              <span class="badge 
                <?php
                if ($u['status'] == 'COMPLETED') echo 'badge-completed';
                elseif ($u['status'] == 'PENDING') echo 'badge-pending';
                elseif ($u['status'] == 'FAILED') echo 'badge-failed';
                ?>">
                <?= htmlspecialchars($u['status']) ?>
              </span>
            </td>
            <td style="color:#64748b;font-size:12px;"><?= substr($u['created_at'], 0, 10) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- QUICK ACTIONS -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
    </div>
    <div class="card-body quick-actions">
      <a href="undangan.php" class="qa-btn qa-blue">
        <i class="fas fa-plus-circle"></i>
        <span>Tambah Undangan</span>
      </a>
      <a href="tsp.php" class="qa-btn qa-purple">
        <i class="fas fa-route"></i>
        <span>Hitung Rute TSP</span>
      </a>
      <a href="kurir.php" class="qa-btn qa-green">
        <i class="fas fa-motorcycle"></i>
        <span>Kelola Kurir</span>
      </a>
      <a href="laporan.php" class="qa-btn qa-orange">
        <i class="fas fa-file-alt"></i>
        <span>Lihat Laporan</span>
      </a>
    </div>
  </div>
</div>

<script>
  document.getElementById('currentDate').textContent = new Date().toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

  const canvas = document.getElementById('donutChart');
  const ctx = canvas.getContext('2d');
  const data = [<?= $stats['completed'] ?>, <?= $stats['failed'] ?>, <?= $stats['pending'] ?>];
  const colors = ['#22c55e','#ef4444','#f59e0b'];
  const total = data.reduce((a,b)=>a+b,0) || 1;

  let start = -Math.PI/2;
  data.forEach((val,i) => {
    const slice = (val/total) * Math.PI * 2;
    ctx.beginPath();
    ctx.moveTo(100,100);
    ctx.arc(100,100,80,start,start+slice);
    ctx.closePath();
    ctx.fillStyle = colors[i];
    ctx.fill();
    start += slice;
  });
  
  ctx.beginPath();
  ctx.arc(100,100,52,0,Math.PI*2);
  ctx.fillStyle = getComputedStyle(document.body).getPropertyValue('--card-bg') || '#1e293b';
  ctx.fill();
  
  ctx.fillStyle = '#f1f5f9';
  ctx.font = 'bold 22px Inter';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  const pct = total > 0 ? Math.round((data[0]/total)*100) : 0;
  ctx.fillText(pct+'%', 100, 95);
  ctx.font = '11px Inter';
  ctx.fillStyle = '#64748b';
  ctx.fillText('Selesai', 100, 115);
</script>

<?php require_once 'includes/footer.php'; ?>
