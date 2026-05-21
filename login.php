<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — Village InviteMail Express</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    /* ... (CSS is exactly the same as the python version, inline in login.html) ... */
    *{margin:0;padding:0;box-sizing:border-box;}
    body{
      font-family:'Inter',sans-serif;
      min-height:100vh;
      background: linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0f172a 100%);
      display:flex;align-items:center;justify-content:center;
      position:relative;overflow:hidden;
    }
    .blob{position:absolute;border-radius:50%;filter:blur(80px);opacity:.25;animation:float 8s ease-in-out infinite;}
    .blob1{width:500px;height:500px;background:#6366f1;top:-150px;left:-150px;animation-delay:0s;}
    .blob2{width:400px;height:400px;background:#2563eb;bottom:-100px;right:-100px;animation-delay:-3s;}
    .blob3{width:300px;height:300px;background:#7c3aed;top:50%;left:50%;transform:translate(-50%,-50%);animation-delay:-6s;}
    @keyframes float{0%,100%{transform:translateY(0) scale(1);}50%{transform:translateY(-30px) scale(1.05);}}

    body::before{
      content:'';position:absolute;inset:0;
      background-image:radial-gradient(circle,rgba(255,255,255,.06) 1px,transparent 1px);
      background-size:32px 32px;
    }

    .login-card{
      position:relative;z-index:10;
      background:rgba(30,41,59,.85);
      backdrop-filter:blur(20px);
      border:1px solid rgba(255,255,255,.12);
      border-radius:24px;
      padding:48px 40px;
      width:100%;max-width:420px;
      box-shadow:0 32px 80px rgba(0,0,0,.5);
      animation:slideUp .6s cubic-bezier(.16,1,.3,1);
    }
    @keyframes slideUp{from{opacity:0;transform:translateY(40px);}to{opacity:1;transform:translateY(0);}}

    .login-logo{
      width:72px;height:72px;border-radius:20px;
      background:linear-gradient(135deg,#2563eb,#7c3aed);
      display:flex;align-items:center;justify-content:center;
      margin:0 auto 20px;
      box-shadow:0 12px 32px rgba(99,102,241,.4);
    }
    .login-logo i{font-size:30px;color:#fff;}
    .login-brand{text-align:center;margin-bottom:8px;}
    .login-brand h1{font-size:22px;font-weight:800;color:#f1f5f9;letter-spacing:-.3px;}
    .login-brand p{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-top:4px;}

    .tab-group{display:flex;background:rgba(15,23,42,.5);border-radius:10px;padding:4px;margin:24px 0 28px;}
    .tab-btn{flex:1;padding:8px;font-size:13px;font-weight:600;color:#64748b;background:transparent;border:none;border-radius:8px;cursor:pointer;transition:.2s;}
    .tab-btn.active{background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;}

    .form-group{margin-bottom:18px;}
    .form-group label{display:block;font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
    .input-wrap{position:relative;}
    .input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#475569;font-size:14px;}
    .input-wrap input{
      width:100%;padding:12px 14px 12px 40px;
      background:rgba(15,23,42,.6);border:1px solid rgba(255,255,255,.1);
      border-radius:10px;color:#f1f5f9;font-size:14px;font-family:'Inter',sans-serif;
      transition:.2s;outline:none;
    }
    .input-wrap input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.2);}
    .input-wrap input::placeholder{color:#475569;}

    .forgot{font-size:12px;color:#2563eb;text-decoration:none;float:right;margin-top:-14px;margin-bottom:20px;display:block;}

    .remember{display:flex;align-items:center;gap:8px;margin-bottom:24px;}
    .remember input[type=checkbox]{accent-color:#2563eb;}
    .remember label{font-size:13px;color:#64748b;}

    .btn-login{
      width:100%;padding:14px;border:none;border-radius:12px;
      background:linear-gradient(135deg,#2563eb,#7c3aed);
      color:#fff;font-size:15px;font-weight:700;cursor:pointer;
      box-shadow:0 8px 24px rgba(37,99,235,.35);
      transition:.2s;font-family:'Inter',sans-serif;
    }
    .btn-login:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(37,99,235,.45);}
    .btn-login:active{transform:translateY(0);}
    .btn-login.loading{opacity:.7;cursor:not-allowed;}

    .error-msg{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;
      padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;display:none;}
    .error-msg.show{display:block;animation:shake .4s;}
    @keyframes shake{0%,100%{transform:translateX(0);}25%{transform:translateX(-6px);}75%{transform:translateX(6px);}}
  </style>
</head>
<body>
  <div class="blob blob1"></div>
  <div class="blob blob2"></div>
  <div class="blob blob3"></div>

  <div class="login-card">
    <div class="login-logo"><i class="fas fa-envelope-open-text"></i></div>
    <div class="login-brand">
      <h1>Village InviteMail Express</h1>
      <p>Pengantaran Surat Undangan Acara Desa</p>
    </div>

    <div class="tab-group">
      <button class="tab-btn active" onclick="setTab('admin',this)">Admin</button>
      <button class="tab-btn" onclick="setTab('kurir',this)">Kurir</button>
    </div>

    <div id="errMsg" class="error-msg"></div>

    <div class="form-group">
      <label>Username or Email</label>
      <div class="input-wrap">
        <i class="fas fa-user"></i>
        <input type="text" id="username" placeholder="Masukkan username..." autocomplete="off"/>
      </div>
    </div>

    <div class="form-group">
      <label>Password</label>
      <div class="input-wrap">
        <i class="fas fa-lock"></i>
        <input type="password" id="password" placeholder="••••••••" autocomplete="off"/>
      </div>
    </div>

    <a href="#" class="forgot">Lupa password?</a>
    <div style="clear:both;height:8px;"></div>

    <div class="remember">
      <input type="checkbox" id="remember"/>
      <label for="remember">Remember this device</label>
    </div>

    <button class="btn-login" id="loginBtn" onclick="doLogin()">
      <i class="fas fa-sign-in-alt"></i> Login
    </button>


  </div>

  <script>
    let currentRole = 'admin';
    function setTab(role, btn) {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentRole = role;
    }

    document.getElementById('password').addEventListener('keydown', e => {
      if (e.key === 'Enter') doLogin();
    });

    async function doLogin() {
      const btn = document.getElementById('loginBtn');
      const err = document.getElementById('errMsg');
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;
      
      if (!username || !password) {
        showErr('Username dan password wajib diisi!'); return;
      }
      
      btn.classList.add('loading');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
      
      try {
        const formData = new URLSearchParams();
        formData.append('action', 'login');
        formData.append('username', username);
        formData.append('password', password);
        formData.append('role', currentRole);

        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: formData.toString()
        });
        
        const data = await res.json();
        
        if (data.success) {
          btn.innerHTML = '<i class="fas fa-check"></i> Berhasil!';
          setTimeout(() => {
             if (data.role === 'kurir') {
                 window.location.href = 'kurir_dashboard.php';
             } else {
                 window.location.href = 'index.php';
             }
          }, 500);
        } else {
          showErr(data.message || 'Login gagal!');
          btn.classList.remove('loading');
          btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
        }
      } catch(e) {
        showErr('Server error. Pastikan backend berjalan.');
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
      }
    }

    function showErr(msg) {
      const el = document.getElementById('errMsg');
      el.textContent = msg; el.className = 'error-msg show';
      setTimeout(() => el.classList.remove('show'), 3000);
    }
  </script>
</body>
</html>
