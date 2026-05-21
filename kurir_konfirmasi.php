<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kurir') {
    header("Location: login.php");
    exit;
}
$username = strtoupper($_SESSION['username']);
$kurir_id = $_SESSION['kurir_id'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Konfirmasi Pengantaran</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        margin: 0; padding: 0; font-family: 'Inter', sans-serif;
        background-color: #0f172a; color: #f1f5f9;
        display: flex; justify-content: center;
        min-height: 100vh;
    }
    .mobile-container {
        width: 100%; max-width: 420px; min-height: 100vh;
        background: #0f172a; position: relative;
        display: flex; flex-direction: column;
        overflow: hidden;
    }
    .header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .header a.btn-back {
        background: rgba(255,255,255,0.08); color: #fff;
        padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 600;
        text-decoration: none; display: flex; align-items: center; gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: 0.2s;
    }
    .header a.btn-back:hover {
        background: rgba(255,255,255,0.15);
    }
    
    .content { padding: 24px 20px; text-align: center; flex: 1; display: flex; flex-direction: column; }
    h2 { color: #fff; font-weight: 800; font-size: 20px; margin-top: 0; margin-bottom: 24px; text-align: left; }
    
    .envelope-icon {
        font-size: 32px; color: #60a5fa; margin: 0 auto 28px;
        background: rgba(96,165,250,0.15); width: 72px; height: 72px; border-radius: 24px;
        display: flex; align-items: center; justify-content: center;
        border: 1px solid rgba(96,165,250,0.25);
        box-shadow: 0 8px 24px rgba(96,165,250,0.1);
    }
    
    .list-container {
        display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px;
    }
    
    .card-item {
        display: flex; align-items: center; justify-content: space-between;
        background: #1e293b; padding: 16px 18px; border-radius: 18px;
        border: 1px solid rgba(255,255,255,0.06);
        transition: all 0.2s ease;
    }
    .card-item:hover {
        border-color: rgba(255,255,255,0.12);
    }
    .card-left { display: flex; align-items: center; gap: 14px; text-align: left; }
    .avatar { 
        width: 42px; height: 42px; border-radius: 14px; background: rgba(96,165,250,0.1);
        display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 16px;
        border: 1px solid rgba(96,165,250,0.15);
    }
    .info h4 { margin: 0; font-size: 14px; color: #f1f5f9; font-weight: 700; }
    .info p { margin: 4px 0 0; font-size: 12px; color: #94a3b8; }
    
    .btn {
        padding: 10px 16px; border-radius: 10px; font-size: 11px; font-weight: 700;
        border: none; cursor: pointer; transition: 0.2s;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .btn-konfirmasi {
        background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff;
        box-shadow: 0 4px 12px rgba(37,99,235,0.25);
    }
    .btn-konfirmasi:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37,99,235,0.35);
    }
    .btn-selesai {
        background: rgba(34,197,94,0.12); color: #4ade80;
        border: 1px solid rgba(34,197,94,0.2);
        cursor: default;
    }
    
    .logout-link {
        color: #94a3b8; font-size: 13px; font-weight: 600; text-decoration: none; margin-top: auto; padding-bottom: 24px;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        transition: color 0.2s;
    }
    .logout-link:hover { color: #f87171; }
  </style>
</head>
<body>
<div class="mobile-container">
    <div class="header">
        <a href="kurir_dashboard.php" class="btn-back"><i class="fas fa-chevron-left"></i> KEMBALI</a>
        <div class="profile" style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:#94a3b8;">
            <i class="fas fa-user-circle" style="color:#60a5fa;font-size:16px;"></i> <?= htmlspecialchars($username) ?> - KURIR
        </div>
    </div>
    
    <div class="content">
        <h2>Konfirmasi Pengantaran</h2>
        <div class="envelope-icon"><i class="fas fa-envelope-open-text"></i></div>
        
        <div class="list-container" id="invitationList">
            <div style="font-size:13px;color:#64748b;">Memuat data...</div>
        </div>
        
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> LogOut</a>
    </div>
</div>

<script>
    async function loadInvitations() {
        try {
            const res = await fetch('api.php?action=get_kurir_undangan');
            const data = await res.json();
            
            const container = document.getElementById('invitationList');
            if (data.length === 0) {
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">Tidak ada undangan untuk Anda.</div>';
                return;
            }
            
            container.innerHTML = data.map(item => `
                <div class="card-item">
                    <div class="card-left">
                        <div class="avatar"><i class="fas fa-user"></i></div>
                        <div class="info">
                            <h4>${item.nama_penerima}</h4>
                            <p>${item.node_name}</p>
                        </div>
                    </div>
                    ${item.status === 'COMPLETED' 
                        ? `<button class="btn btn-selesai">SELESAI</button>`
                        : `<button class="btn btn-konfirmasi" onclick="konfirmasi(${item.id})">KONFIRMASI</button>`
                    }
                </div>
            `).join('');
            
        } catch(e) {
            document.getElementById('invitationList').innerHTML = '<div style="color:#ef4444;font-size:13px;">Gagal memuat data.</div>';
        }
    }
    
    async function konfirmasi(id) {
        // Hapus konfirmasi agar langsung memproses
        const fd = new URLSearchParams();
        fd.append('action', 'update_undangan_status');
        fd.append('id', id);
        fd.append('status', 'COMPLETED');
        
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: fd.toString()
            });
            const data = await res.json();
            if(data.success) {
                loadInvitations(); // reload list
            } else {
                alert('Gagal mengupdate status!');
            }
        } catch(e) {
            alert('Terjadi kesalahan!');
        }
    }
    
    window.onload = loadInvitations;
</script>
</body>
</html>
