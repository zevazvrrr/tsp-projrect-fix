<?php
require_once 'db.php';
check_auth();
$page_title = 'Laporan — Village InviteMail Express';
require_once 'includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Delivery Reports</h1>
    <p class="page-sub">Laporan distribusi dan performa pengantaran undangan</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost" onclick="exportCSV()"><i class="fas fa-file-csv"></i> Export CSV</button>
    <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print PDF</button>
  </div>
</div>

<!-- TOP STATS -->
<div class="stats-grid" id="laporanStats">
  <div class="stat-card stat-blue"><div class="stat-icon"><i class="fas fa-envelope"></i></div>
    <div class="stat-info"><span id="lTotal" class="stat-val">—</span><span class="stat-label">Total Undangan</span></div></div>
  <div class="stat-card stat-green"><div class="stat-icon"><i class="fas fa-check-circle"></i></div>
    <div class="stat-info"><span id="lCompleted" class="stat-val">—</span><span class="stat-label">Terkirim</span></div></div>
  <div class="stat-card stat-yellow"><div class="stat-icon"><i class="fas fa-clock"></i></div>
    <div class="stat-info"><span id="lPending" class="stat-val">—</span><span class="stat-label">Pending</span></div></div>
  <div class="stat-card stat-red"><div class="stat-icon"><i class="fas fa-times-circle"></i></div>
    <div class="stat-info"><span id="lFailed" class="stat-val">—</span><span class="stat-label">Gagal</span></div></div>
</div>

<div class="dashboard-grid">
  <!-- DONUT -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><i class="fas fa-chart-pie"></i> Distribution Progress</h2>
    </div>
    <div class="card-body donut-wrap">
      <canvas id="laporanDonut" width="200" height="200"></canvas>
      <div class="donut-legend" id="donutLegend"></div>
    </div>
  </div>

  <!-- BAR CHART per Kurir -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><i class="fas fa-chart-bar"></i> Performance by Courier</h2>
    </div>
    <div class="card-body" id="kurirChartWrap">
      <div style="color:#475569;text-align:center;padding:20px;">
        <i class="fas fa-spinner fa-spin"></i> Loading...
      </div>
    </div>
  </div>

  <!-- DELIVERY TABLE -->
  <div class="card card-wide">
    <div class="card-header">
      <h2 class="card-title"><i class="fas fa-table"></i> Detail Pengiriman</h2>
      <div class="filter-inline">
        <select id="lapFilterStatus" onchange="renderTable()" class="filter-select-sm">
          <option value="">Semua</option>
          <option value="PENDING">Pending</option>
          <option value="COMPLETED">Completed</option>
          <option value="FAILED">Failed</option>
        </select>
      </div>
    </div>
    <div class="card-body p0">
      <table class="data-table" id="lapTable">
        <thead>
          <tr>
            <th>Invitation-ID</th>
            <th>Penerima</th>
            <th>Lokasi</th>
            <th>Kurir Assigned</th>
            <th>Delivery Status</th>
            <th>Time Stamp</th>
          </tr>
        </thead>
        <tbody id="lapBody">
          <tr><td colspan="6" class="loading-row"><i class="fas fa-spinner fa-spin"></i> Memuat...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
let allDeliveries = [];

async function loadLaporan() {
  const res = await fetch('api.php?action=get_laporan');
  const data = await res.json();
  const { stats, kurir_stats, deliveries } = data;
  allDeliveries = deliveries;

  // stats
  document.getElementById('lTotal').textContent     = stats.total;
  document.getElementById('lCompleted').textContent = stats.completed;
  document.getElementById('lPending').textContent   = stats.pending;
  document.getElementById('lFailed').textContent    = stats.failed;

  drawDonut(stats);
  drawKurirBars(kurir_stats);
  renderTable();
}

function drawDonut(stats) {
  const canvas = document.getElementById('laporanDonut');
  const ctx = canvas.getContext('2d');
  ctx.clearRect(0,0,200,200);
  const vals   = [stats.completed, stats.failed, stats.pending];
  const colors = ['#22c55e','#ef4444','#f59e0b'];
  const labels = ['Completed','Failed','Pending'];
  const total  = vals.reduce((a,b)=>a+b,0) || 1;

  let start = -Math.PI/2;
  vals.forEach((v,i) => {
    const slice = (v/total)*Math.PI*2;
    ctx.beginPath(); ctx.moveTo(100,100);
    ctx.arc(100,100,80,start,start+slice);
    ctx.closePath(); ctx.fillStyle=colors[i]; ctx.fill();
    start += slice;
  });
  ctx.beginPath(); ctx.arc(100,100,52,0,Math.PI*2);
  ctx.fillStyle='#1e293b'; ctx.fill();
  ctx.fillStyle='#f1f5f9'; ctx.font='bold 22px Inter';
  ctx.textAlign='center'; ctx.textBaseline='middle';
  ctx.fillText(Math.round((stats.completed/total)*100)+'%',100,92);
  ctx.font='11px Inter'; ctx.fillStyle='#64748b';
  ctx.fillText('Completed',100,112);

  const leg = document.getElementById('donutLegend');
  leg.innerHTML = vals.map((v,i)=>`
    <div class="legend-item">
      <span class="dot" style="background:${colors[i]}"></span>
      ${labels[i]} <strong>${v}</strong>
    </div>
  `).join('');
}

function drawKurirBars(kurir_stats) {
  const wrap = document.getElementById('kurirChartWrap');
  const maxTotal = Math.max(...kurir_stats.map(k=>k.total), 1);
  wrap.innerHTML = kurir_stats.map(k => `
    <div class="bar-row">
      <span class="bar-label">${k.nama}</span>
      <div class="bar-track">
        <div class="bar-fill bar-blue"  style="width:${(k.completed/maxTotal)*100}%" title="Completed: ${k.completed}"></div>
        <div class="bar-fill bar-yellow" style="width:${(k.pending/maxTotal)*100}%"  title="Pending: ${k.pending}"></div>
        <div class="bar-fill bar-red"   style="width:${(k.failed/maxTotal)*100}%"   title="Failed: ${k.failed}"></div>
      </div>
      <span class="bar-total">${k.total}</span>
    </div>
  `).join('') || '<p style="color:#475569;text-align:center">Tidak ada data</p>';
}

function renderTable() {
  const filter = document.getElementById('lapFilterStatus').value;
  let rows = filter ? allDeliveries.filter(d=>d.status===filter) : allDeliveries;
  const tbody = document.getElementById('lapBody');
  if (!rows.length) {
    tbody.innerHTML='<tr><td colspan="6" class="empty-row"><i class="fas fa-inbox"></i> Tidak ada data</td></tr>'; return;
  }
  tbody.innerHTML = rows.map(d => `
    <tr>
      <td><span class="kode-badge">${d.kode}</span></td>
      <td>
        <div class="user-cell">
          <div class="user-mini-avatar"><i class="fas fa-user"></i></div>
          ${d.nama_penerima}
        </div>
      </td>
      <td><i class="fas fa-map-marker-alt" style="color:#60a5fa;margin-right:4px;"></i>${d.node_name}</td>
      <td>${d.kurir_nama || '—'}</td>
      <td>${statusBadge(d.status)}</td>
      <td style="color:#64748b;font-size:11px;">${d.updated_at ? d.updated_at.slice(0,16).replace('T',' ') : '—'}</td>
    </tr>
  `).join('');
}

function statusBadge(s) {
  const map = {COMPLETED:'badge-completed',PENDING:'badge-pending',FAILED:'badge-failed'};
  return `<span class="badge ${map[s]||''}">${s}</span>`;
}

function exportCSV() {
  const rows = [['Kode','Penerima','Lokasi','Kurir','Status','Tanggal']];
  allDeliveries.forEach(d => rows.push([d.kode, d.nama_penerima, d.node_name, d.kurir_nama||'', d.status, d.updated_at||'']));
  const csv = rows.map(r => r.map(v=>`"${v}"`).join(',')).join('\n');
  const blob = new Blob([csv],{type:'text/csv'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `laporan_${new Date().toISOString().slice(0,10)}.csv`;
  a.click();
  showToast('CSV berhasil diexport!','success');
}

setTimeout(loadLaporan, 100);
</script>

<?php require_once 'includes/footer.php'; ?>
