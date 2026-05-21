<?php
require_once 'db.php';
check_auth();
$page_title = 'Rute TSP — Village InviteMail Express';
require_once 'includes/header.php';

$kurir_list = $pdo->query("SELECT * FROM kurir")->fetchAll();
$node_list = ['Rumah A', 'Rumah B', 'Rumah C', 'Rumah D', 'Rumah E'];
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-route" style="color:#818cf8"></i> Optimasi Rute TSP</h1>
    <p class="page-sub">Nearest Neighbor Algorithm — Temukan rute pengantaran terpendek</p>
  </div>
</div>

<div class="tsp-layout">
  <!-- LEFT: INPUT PANEL -->
  <div class="tsp-panel">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title"><i class="fas fa-sliders-h"></i> Konfigurasi Rute</h2>
      </div>
      <div class="card-body">

        <div class="form-group">
          <label><i class="fas fa-flag" style="color:#f59e0b"></i> Titik Awal (Kurir)</label>
          <select id="startNode" class="form-control-lg">
            <option value="Kurir">Petugas Kurir (Start)</option>
          </select>
        </div>

        <div class="form-group">
          <label><i class="fas fa-motorcycle" style="color:#60a5fa"></i> Pilih Kurir</label>
          <select id="selectedKurir" class="form-control-lg">
            <option value="">-- Pilih Kurir --</option>
            <?php foreach ($kurir_list as $k): ?>
            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama']) ?> (<?= htmlspecialchars($k['plat']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btn btn-primary btn-full btn-lg" onclick="calculateTSP()" id="calcBtn">
          <i class="fas fa-calculator"></i> Hitung Rute Optimal
        </button>
        <button class="btn btn-ghost btn-full" style="margin-top:8px;" onclick="resetAll()">
          <i class="fas fa-redo"></i> Reset
        </button>
      </div>
    </div>
  </div>

  <!-- RIGHT: CANVAS MAP -->
  <div class="tsp-map-wrap card">
    <div class="card-header">
      <h2 class="card-title"><i class="fas fa-map-marked-alt"></i> Peta Graf TSP</h2>
      <div class="map-legend">
        <span><span class="leg-dot" style="background:#334155"></span> Semua Jalur</span>
        <span><span class="leg-dot" style="background:#22c55e"></span> Rute Optimal</span>
      </div>
    </div>
    <div class="canvas-container">
      <canvas id="tspCanvas"></canvas>
    </div>
  </div>
</div>

<!-- RESULT PANEL -->
<div class="card" id="resultCard" style="display:none; margin-top:24px;">
  <div class="card-header">
    <h2 class="card-title"><i class="fas fa-trophy" style="color:#f59e0b"></i> Hasil Optimal</h2>
  </div>
  <div class="card-body">
    <div class="result-summary">
      <div class="result-dist">
        <span id="totalDist">0</span>
        <small>km total jarak</small>
      </div>
      <div class="result-stops">
        <span id="totalStops">0</span>
        <small>titik dikunjungi</small>
      </div>
    </div>

    <div class="route-flow" id="routeFlow"></div>

    <div class="step-table-wrap">
      <h4 style="font-size:13px;color:#94a3b8;margin-bottom:10px;font-weight:600;">
        <i class="fas fa-list-ol"></i> Detail Langkah
      </h4>
      <table class="data-table" id="stepTable">
        <thead>
          <tr><th>#</th><th>Dari</th><th>Ke</th><th>Jarak</th></tr>
        </thead>
        <tbody id="stepBody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- HISTORY -->
<div class="card" style="margin-top:24px;">
  <div class="card-header">
    <h2 class="card-title"><i class="fas fa-history"></i> Riwayat Kalkulasi TSP</h2>
    <button class="btn btn-ghost btn-sm" onclick="loadHistory()"><i class="fas fa-sync"></i></button>
  </div>
  <div class="card-body p0">
    <table class="data-table">
      <thead>
        <tr><th>Waktu</th><th>Kurir</th><th>Titik Awal</th><th>Rumah Dikunjungi</th><th>Rute Optimal</th><th>Total Jarak</th><th>Aksi</th></tr>
      </thead>
      <tbody id="historyBody">
        <tr><td colspan="6" class="loading-row"><i class="fas fa-spinner fa-spin"></i> Memuat...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script src="static/js/tsp_canvas.js"></script>
<script>
let graphData = null;
const allNodes = <?= json_encode($node_list) ?>;

async function initGraph() {
  const res = await fetch('api.php?action=get_graph');
  graphData = await res.json();
  drawCanvas(graphData.nodes, graphData.edges, allNodes, []);
}

async function calculateTSP() {
  const checked = allNodes;

  const btn = document.getElementById('calcBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghitung...';
  btn.disabled = true;

  const payload = {
    action:         'tsp_calculate',
    start_node:     'Kurir',
    selected_nodes: checked,
    kurir_id:       document.getElementById('selectedKurir').value || null
  };

  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  
  const data = await res.json();

  btn.innerHTML = '<i class="fas fa-calculator"></i> Hitung Rute Optimal';
  btn.disabled = false;

  if (data.route) {
    showResult(data);
    drawCanvas(data.nodes, data.edges, checked, data.route);
    loadHistory();
    showToast('Rute optimal berhasil dihitung!','success');
  } else {
    showToast('Gagal menghitung rute','error');
  }
}

function showResult(data) {
  document.getElementById('resultCard').style.display = 'block';
  document.getElementById('totalDist').textContent = data.total_distance;
  document.getElementById('totalStops').textContent = data.route.length - 2;

  const flow = document.getElementById('routeFlow');
  flow.innerHTML = data.route.map((node,i) => {
    const isFirst = i === 0;
    const isLast  = i === data.route.length - 1;
    const label   = isFirst ? 'START' : (isLast ? 'END' : '');
    return `
      <div class="rf-step ${isFirst||isLast ? 'rf-endpoint' : ''}">
        ${label ? `<span class="rf-label">${label}</span>` : ''}
        <div class="rf-node">${node}</div>
        ${i < data.route.length-1 ? '<div class="rf-arrow"><i class="fas fa-chevron-right"></i></div>' : ''}
      </div>
    `;
  }).join('');

  const tbody = document.getElementById('stepBody');
  tbody.innerHTML = data.route_details.map((d,i) => `
    <tr>
      <td>${i+1}</td>
      <td>${d.from}</td>
      <td><i class="fas fa-arrow-right" style="color:#818cf8;margin:0 4px;"></i>${d.to}</td>
      <td><span class="dist-badge">${d.distance} km</span></td>
    </tr>
  `).join('');
}

async function loadHistory() {
  const res = await fetch('api.php?action=tsp_history');
  const data = await res.json();
  const tbody = document.getElementById('historyBody');
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-row">Belum ada riwayat</td></tr>'; return;
  }
  tbody.innerHTML = data.map(h => `
    <tr>
      <td style="font-size:12px;color:#64748b">${h.created_at ? h.created_at.slice(0,16).replace('T',' ') : '—'}</td>
      <td>${h.kurir_nama || '—'}</td>
      <td><span class="kode-badge">${h.start_node}</span></td>
      <td style="color:#94a3b8">${h.selected_nodes.join(' → ')}</td>
      <td style="font-size:12px;">${h.optimal_route.join(' → ')}</td>
      <td><span class="dist-badge">${h.total_distance} km</span></td>
      <td>
        <button class="btn-icon btn-delete" onclick="deleteHistory(${h.id})" title="Hapus Riwayat">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    </tr>
  `).join('');
}

async function deleteHistory(id) {
  if (!confirm('Yakin ingin menghapus riwayat ini?')) return;
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'delete_tsp_history', id: id })
  });
  const data = await res.json();
  if (data.success) {
    showToast('Riwayat berhasil dihapus!', 'success');
    loadHistory();
  } else {
    showToast('Gagal menghapus riwayat.', 'error');
  }
}

function resetAll() {
  document.getElementById('resultCard').style.display = 'none';
  if (graphData) drawCanvas(graphData.nodes, graphData.edges, allNodes, []);
}

setTimeout(() => {
  initGraph();
  loadHistory();
}, 100);
</script>

<?php require_once 'includes/footer.php'; ?>
