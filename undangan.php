<?php
require_once 'db.php';
check_auth();
$page_title = 'Undangan — Village InviteMail Express';
require_once 'includes/header.php';

$kurir_list = $pdo->query("SELECT * FROM kurir")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Invitations Data</h1>
    <p class="page-sub">Manajemen surat undangan acara desa</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="openAddModal()">
      <i class="fas fa-plus"></i> Tambah Undangan
    </button>
  </div>
</div>

<!-- STAT ROW -->
<div class="mini-stats">
  <div class="mini-stat">
    <i class="fas fa-envelope" style="color:#60a5fa"></i>
    <div><span id="statTotal">—</span><small>Total</small></div>
  </div>
  <div class="mini-stat">
    <i class="fas fa-check-circle" style="color:#4ade80"></i>
    <div><span id="statCompleted">—</span><small>Delivered</small></div>
  </div>
  <div class="mini-stat">
    <i class="fas fa-clock" style="color:#fbbf24"></i>
    <div><span id="statPending">—</span><small>Pending</small></div>
  </div>
  <div class="mini-stat">
    <i class="fas fa-times-circle" style="color:#f87171"></i>
    <div><span id="statFailed">—</span><small>Failed</small></div>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card">
  <div class="filter-bar">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" id="searchInput" placeholder="Cari nama penerima, kode..." oninput="loadUndangan()"/>
    </div>
    <select id="filterStatus" onchange="loadUndangan()" class="filter-select">
      <option value="">Semua Status</option>
      <option value="PENDING">Pending</option>
      <option value="COMPLETED">Completed</option>
      <option value="FAILED">Failed</option>
    </select>
  </div>

  <div class="card-body p0">
    <table class="data-table" id="undanganTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Kode</th>
          <th>Penerima</th>
          <th>Alamat</th>
          <th>Lokasi (Node)</th>
          <th>Kurir</th>
          <th>Status</th>
          <th>Tanggal</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="undanganBody">
        <tr><td colspan="9" class="loading-row"><i class="fas fa-spinner fa-spin"></i> Memuat data...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD / EDIT MODAL CONTENT -->
<script>
const kurirList = <?= json_encode($kurir_list) ?>;

async function loadUndangan() {
  const search = document.getElementById('searchInput').value;
  const status = document.getElementById('filterStatus').value;
  
  const res = await fetch(`api.php?action=get_undangan&search=${encodeURIComponent(search)}`);
  const data = await res.json();

  let filtered = data;
  if (status) filtered = data.filter(d => d.status === status);

  // update mini stats
  document.getElementById('statTotal').textContent = data.length;
  document.getElementById('statCompleted').textContent = data.filter(d=>d.status==='COMPLETED').length;
  document.getElementById('statPending').textContent = data.filter(d=>d.status==='PENDING').length;
  document.getElementById('statFailed').textContent = data.filter(d=>d.status==='FAILED').length;

  const tbody = document.getElementById('undanganBody');
  if (!filtered.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="empty-row"><i class="fas fa-inbox"></i> Tidak ada data</td></tr>';
    return;
  }

  tbody.innerHTML = filtered.map((u,i) => `
    <tr>
      <td style="color:#475569">${i+1}</td>
      <td><span class="kode-badge">${u.kode}</span></td>
      <td>
        <div class="user-cell">
          <div class="user-mini-avatar"><i class="fas fa-user"></i></div>
          <span>${u.nama_penerima}</span>
        </div>
      </td>
      <td style="color:#94a3b8;font-size:13px;">${u.alamat}</td>
      <td><i class="fas fa-map-marker-alt" style="color:#60a5fa;margin-right:4px;"></i>${u.node_name}</td>
      <td>${u.kurir_nama || '<span style="color:#475569">—</span>'}</td>
      <td>${statusBadge(u.status)}</td>
      <td style="color:#64748b;font-size:12px;">${u.created_at ? u.created_at.slice(0,10) : '—'}</td>
      <td>
        <div class="action-btns">
          <button class="btn-icon btn-edit" onclick='openEditModal(${JSON.stringify(u).replace(/'/g, "&#39;")})' title="Edit">
            <i class="fas fa-pencil-alt"></i>
          </button>
          <button class="btn-icon btn-delete" onclick="deleteUndangan(${u.id},'${u.nama_penerima}')" title="Hapus">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function statusBadge(s) {
  const map = {COMPLETED:'badge-completed',PENDING:'badge-pending',FAILED:'badge-failed'};
  return `<span class="badge ${map[s]||''}">${s}</span>`;
}

function getFormHTML(u={}) {
  const kurirOpts = kurirList.map(k =>
    `<option value="${k.id}" ${u.kurir_id==k.id?'selected':''}>${k.nama}</option>`
  ).join('');
  const nodes = ['Rumah A','Rumah B','Rumah C','Rumah D','Rumah E'];
  const nodeOpts = nodes.map(n =>
    `<option value="${n}" ${u.node_name===n?'selected':''}>${n}</option>`
  ).join('');
  return `
    <div class="form-grid">
      <div class="form-group">
        <label>Nama Penerima</label>
        <input type="text" id="fNama" value="${u.nama_penerima||''}" placeholder="Nama lengkap penerima"/>
      </div>
      <div class="form-group">
        <label>Lokasi (Node)</label>
        <select id="fNode">${nodeOpts}</select>
      </div>
      <div class="form-group fg-full">
        <label>Alamat Lengkap</label>
        <input type="text" id="fAlamat" value="${u.alamat||''}" placeholder="Jl. Contoh No.1, RT/RW..."/>
      </div>
      <div class="form-group">
        <label>Kurir</label>
        <select id="fKurir"><option value="">-- Pilih Kurir --</option>${kurirOpts}</select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select id="fStatus">
          <option value="PENDING" ${u.status==='PENDING'?'selected':''}>PENDING</option>
          <option value="COMPLETED" ${u.status==='COMPLETED'?'selected':''}>COMPLETED</option>
          <option value="FAILED" ${u.status==='FAILED'?'selected':''}>FAILED</option>
        </select>
      </div>
      <div class="form-group fg-full">
        <label>Catatan</label>
        <input type="text" id="fCatatan" value="${u.catatan||''}" placeholder="Catatan tambahan..."/>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModalDirect()">Batal</button>
      <button class="btn btn-primary" onclick="saveUndangan(${u.id||0})">
        <i class="fas fa-save"></i> Simpan
      </button>
    </div>
  `;
}

function openAddModal() { openModal('Tambah Undangan Baru', getFormHTML()); }
function openEditModal(u) { openModal('Edit Undangan — ' + u.kode, getFormHTML(u)); }

async function saveUndangan(id) {
  const payload = {
    action:        id ? 'update_undangan' : 'create_undangan',
    id:            id,
    nama_penerima: document.getElementById('fNama').value.trim(),
    node_name:     document.getElementById('fNode').value,
    alamat:        document.getElementById('fAlamat').value.trim(),
    kurir_id:      document.getElementById('fKurir').value || null,
    status:        document.getElementById('fStatus').value,
    catatan:       document.getElementById('fCatatan').value,
  };
  
  if (!payload.nama_penerima || !payload.alamat) {
    showToast('Nama dan alamat wajib diisi!', 'error'); return;
  }
  
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  
  const data = await res.json();
  if (data.success || data.kode) {
    showToast(id ? 'Data berhasil diperbarui!' : `Undangan berhasil ditambah!`, 'success');
    closeModalDirect();
    loadUndangan();
  } else {
    showToast('Gagal menyimpan: ' + (data.error||''), 'error');
  }
}

async function deleteUndangan(id, nama) {
  if (!confirm(`Hapus undangan untuk "${nama}"?`)) return;
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'delete_undangan', id: id })
  });
  const data = await res.json();
  if (data.success) {
    showToast('Undangan berhasil dihapus!', 'success');
    loadUndangan();
  }
}

// Initial load
const urlParams = new URLSearchParams(window.location.search);
const searchParam = urlParams.get('search');
if (searchParam) {
  document.getElementById('searchInput').value = searchParam;
}
setTimeout(loadUndangan, 100);
</script>

<?php require_once 'includes/footer.php'; ?>
