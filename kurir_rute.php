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
  <title>Route Overview</title>
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
    
    .stats-box {
        display: flex; gap: 12px; margin-bottom: 24px;
    }
    .stat-card {
        flex: 1; background: #1e293b; border: 1px solid rgba(255,255,255,0.06); border-radius: 14px;
        padding: 14px; text-align: left; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .stat-card span { display: block; font-size: 11px; color: #64748b; font-weight: 600; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-card strong { font-size: 15px; color: #f1f5f9; font-weight: 800; }
    
    #tspCanvas { 
        width: 100%; max-width: 380px; height: 260px; 
        border: 1px solid rgba(255,255,255,0.06); border-radius: 18px; 
        background: #0a0f1e; 
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    
    .route-info {
        background: #1e293b; border: 1px solid rgba(255,255,255,0.06); border-radius: 16px;
        padding: 18px; margin-top: 24px; text-align: left;
    }
    .route-info h3 { font-size: 12px; margin: 0 0 10px; color: #f87171; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
    .route-path { font-size: 13px; color: #f1f5f9; line-height: 1.6; font-weight: 600; }
    
    .btn-confirm {
        margin-top: 24px; display: block; width: 100%; padding: 16px;
        background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; text-align: center;
        border-radius: 14px; font-weight: 700; text-decoration: none;
        box-shadow: 0 8px 24px rgba(37,99,235,0.25); transition: all 0.2s ease;
        text-transform: uppercase; letter-spacing: 0.5px; font-size: 13px;
    }
    .btn-confirm:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 32px rgba(37,99,235,0.35);
    }
    .btn-confirm:active {
        transform: translateY(0);
    }
  </style>
</head>
<body>
<div class="mobile-container">
    <div class="header">
        <a href="kurir_dashboard.php" class="btn-back"><i class="fas fa-chevron-left"></i> KEMBALI</a>
        <div style="font-weight:600;font-size:12px;color:#94a3b8; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-user-circle" style="color:#22c55e;font-size:16px;"></i> <?= htmlspecialchars($username) ?> - KURIR
        </div>
    </div>
    <div class="content">
        <h2>Rute Pengantaran</h2>
        
        <div class="stats-box">
            <div class="stat-card">
                <span>Jarak Tempuh</span>
                <strong id="jarakTempuh">- KM</strong>
            </div>
            <div class="stat-card">
                <span>Estimasi Waktu</span>
                <strong id="estimasiWaktu">- Menit</strong>
            </div>
        </div>
        
        <canvas id="tspCanvas" width="360" height="300"></canvas>
        
        <div class="route-info">
            <h3>Rute Merah</h3>
            <div class="route-path" id="routePath">Memuat...</div>
        </div>
        
        <a href="kurir_konfirmasi.php" class="btn-confirm">KONFIRMASI PENGANTARAN</a>
    </div>
</div>

<script src="static/js/tsp_canvas.js"></script>
<script>
    const KURIR_ID = <?= $kurir_id ?>;
    let graphData = null;

    async function loadData() {
        try {
            // Get graph
            const resG = await fetch('api.php?action=get_graph');
            graphData = await resG.json();

            // Get history
            const resH = await fetch('api.php?action=tsp_history');
            const history = await resH.json();
            
            // Find latest for this kurir
            const myRoute = history.find(h => parseInt(h.kurir_id) === KURIR_ID);
            
            if (myRoute) {
                document.getElementById('jarakTempuh').textContent = myRoute.total_distance + ' KM';
                document.getElementById('estimasiWaktu').textContent = (myRoute.total_distance * 4) + ' Menit'; 
                document.getElementById('routePath').innerHTML = myRoute.optimal_route.join(' <i class="fas fa-arrow-right" style="font-size:10px;color:#94a3b8;margin:0 4px;"></i> ');
                
                // Draw graph
                initCanvas();
                drawCanvas(graphData.nodes, graphData.edges, myRoute.selected_nodes, myRoute.optimal_route);
            } else {
                document.getElementById('routePath').textContent = "Belum ada rute yang dihitung oleh Admin untuk Anda.";
                initCanvas();
                drawCanvas(graphData.nodes, graphData.edges, [], []);
            }
        } catch (e) {
            console.error(e);
            document.getElementById('routePath').textContent = "Gagal memuat data.";
        }
    }
    
    window.onload = loadData;
</script>
</body>
</html>
