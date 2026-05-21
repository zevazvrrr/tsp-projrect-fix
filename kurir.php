<?php
require_once 'db.php';
check_auth();
$page_title = 'Kurir — Village InviteMail Express';
require_once 'includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Courier Information</h1>
    <p class="page-sub">Manajemen data kurir pengantaran</p>
  </div>
  <div class="page-actions">
    <!-- Tombol tambah kurir dinonaktifkan sesuai permintaan (maks 3 kurir) -->
  </div>
</div>

<!-- KURIR STATS -->
<div class="mini-stats" id="kurirStats">
  <div class="mini-stat">
    <i class="fas fa-motorcycle" style="color:#818cf8"></i>
    <div><span id="kTotal">—</span><small>Total Kurir</small></div>
  </div>
  <div class="mini-stat">
    <i class="fas fa-circle" style="color:#4ade80"></i>
    <div><span id="kFree">—</span><small>Tersedia</small></div>
  </div>
  <div class="mini-stat">
    <i class="fas fa-circle" style="color:#f59e0b"></i>
    <div><span id="kBusy">—</span><small>Bertugas</small></div>
  </div>
</div>

<div class="card">
  <div class="filter-bar">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" id="kurirSearch" placeholder="Cari nama kurir, plat..." oninput="loadKurir()"/>
    </div>
  </div>

  <div class="card-body p0">
    <table class="data-table" id="kurirTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Kurir</th>
          <th>No. Plat</th>
          <th>Telepon</th>
          <th>Status</th>
          <th>Bergabung</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="kurirBody">
        <tr><td colspan="7" class="loading-row"><i class="fas fa-spinner fa-spin"></i> Memuat data...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
async function loadKurir() {
  const search = document.getElementById('kurirSearch').value.toLowerCase();
  const res = await fetch('api.php?action=get_kurir');
  let data = await res.json();
  
  if (search) data = data.filter(k => k.nama.toLowerCase().includes(search) || k.plat.toLowerCase().includes(search));

  document.getElementById('kTotal').textContent = data.length;
  document.getElementById('kFree').textContent = data.filter(k=>k.status==='FREE').length;
  document.getElementById('kBusy').textContent = data.filter(k=>k.status==='SEDANG BERTUGAS').length;

  const tbody = document.getElementById('kurirBody');
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-row"><i class="fas fa-inbox"></i> Tidak ada data</td></tr>';
    return;
  }

  tbody.innerHTML = data.map((k,i) => `
    <tr>
      <td style="color:#475569">${i+1}</td>
      <td>
        <div class="user-cell">
          <div class="user-mini-avatar kurir-av"><i class="fas fa-user"></i></div>
          <div>
            <span style="font-weight:600;color:#f1f5f9">${k.nama}</span>
          </div>
        </div>
      </td>
      <td><span class="plat-badge">${k.plat}</span></td>
      <td style="color:#94a3b8">${k.telepon || '—'}</td>
      <td>${k.status==='SEDANG BERTUGAS'
        ? '<span class="badge badge-busy">SEDANG BERTUGAS</span>'
        : '<span class="badge badge-free">FREE</span>'}</td>
      <td style="color:#64748b;font-size:12px;">${k.created_at ? k.created_at.slice(0,10) : '—'}</td>
      <td>
        <div class="action-btns">
          <button class="btn-icon btn-edit" onclick='openEditKurir(${JSON.stringify(k)})' title="Edit">
            <i class="fas fa-pencil-alt"></i>
          </button>
          <button class="btn-icon btn-delete" onclick="deleteKurir(${k.id},'${k.nama}')" title="Hapus">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function getKurirForm(k={}) {
  return `
    <div class="form-grid">
      <div class="form-group">
        <label>Nama Kurir</label>
        <input type="text" id="kNama" value="${k.nama||''}" placeholder="Nama lengkap..."/>
      </div>
      <div class="form-group">
        <label>No. Plat Kendaraan</label>
        <input type="text" id="kPlat" value="${k.plat||''}" placeholder="AE 1234 XY"/>
      </div>
      <div class="form-group">
        <label>No. Telepon</label>
        <input type="text" id="kTelepon" value="${k.telepon||''}" placeholder="08xxxxxxxxxx"/>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select id="kStatus">
          <option value="FREE" ${k.status==='FREE'?'selected':''}>FREE</option>
          <option value="SEDANG BERTUGAS" ${k.status==='SEDANG BERTUGAS'?'selected':''}>SEDANG BERTUGAS</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModalDirect()">Batal</button>
      <button class="btn btn-primary" onclick="saveKurir(${k.id||0})">
        <i class="fas fa-save"></i> Simpan
      </button>
    </div>
  `;
}

function openAddKurir() { openModal('Tambah Kurir Baru', getKurirForm()); }
function openEditKurir(k) { openModal('Edit Kurir — ' + k.nama, getKurirForm(k)); }

async function saveKurir(id) {
  const payload = {
    action:  id ? 'update_kurir' : 'create_kurir',
    id:      id,
    nama:    document.getElementById('kNama').value.trim(),
    plat:    document.getElementById('kPlat').value.trim(),
    telepon: document.getElementById('kTelepon').value.trim(),
    status:  document.getElementById('kStatus').value,
  };
  
  if (!payload.nama || !payload.plat) { showToast('Nama dan plat wajib diisi!','error'); return; }
  
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  
  const data = await res.json();
  if (data.success) {
    showToast(id ? 'Data kurir diperbarui!' : 'Kurir baru ditambahkan!','success');
    closeModalDirect(); 
    loadKurir();
  } else {
    showToast('Gagal: ' + (data.error||''),'error');
  }
}

async function deleteKurir(id, nama) {
  if (!confirm(`Hapus kurir "${nama}"?`)) return;
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'delete_kurir', id: id })
  });
  const data = await res.json();
  if (data.success) { showToast('Kurir dihapus!','success'); loadKurir(); }
}

setTimeout(loadKurir, 100);
</script>

<?php require_once 'includes/footer.php'; ?>
