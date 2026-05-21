// ============================================================
//  tsp_canvas.js — Visualisasi Peta Graf TSP dengan Canvas
// ============================================================
//  File ini bertanggung jawab menggambar peta interaktif TSP
//  menggunakan HTML5 Canvas API.
//
//  Fungsi utama yang bisa dipanggil dari luar:
//  - initCanvas()  : Inisialisasi canvas saat halaman dimuat
//  - drawCanvas(nodes, edges, checkedNodes, routeNodes)
//                  : Gambar ulang seluruh peta
//
//  Cara kerja umum:
//  1. Canvas disesuaikan ukurannya dengan lebar container-nya
//  2. Koordinat node di database (x,y) dikonversi ke koordinat canvas
//     menggunakan fungsi scaleCoord()
//  3. Gambar semua edge (jalur) lebih dulu, baru node (titik)
//  4. Jika ada rute optimal, edge rute digambar hijau + animasi
// ============================================================


// ── VARIABEL GLOBAL ANIMASI ──────────────────────────────────
// Variabel-variabel ini digunakan untuk menyimpan state animasi
// sehingga canvas bisa digambar ulang saat window di-resize

let animFrame   = null;  // ID dari requestAnimationFrame (untuk bisa dibatalkan)
let animProgress = 0;    // Belum dipakai saat ini (cadangan untuk animasi masa depan)
let animRoute   = [];    // Rute optimal yang sedang dianimasikan
let animNodes   = {};    // Objek node yang sedang ditampilkan
let animEdges   = [];    // Array edge yang sedang ditampilkan
let animChecked = [];    // Daftar node yang dipilih admin


// ── PALET WARNA ──────────────────────────────────────────────
// Semua warna yang dipakai di canvas dikumpulkan di sini
// agar mudah diubah tanpa harus mencari di banyak tempat
const COLORS = {
  bg:              '#0f172a',              // Warna latar belakang canvas
  gridLine:        'rgba(255,255,255,0.04)', // Warna garis grid (sangat transparan)
  edgeDefault:     'rgba(71,85,105,0.7)',  // Warna jalur biasa (abu-abu)
  edgeChecked:     'rgba(99,102,241,0.5)', // Warna jalur antar node yang dipilih (ungu)
  edgeRoute:       '#22c55e',              // Warna jalur rute optimal (hijau)
  edgeGlow:        'rgba(34,197,94,0.4)',  // Efek cahaya di jalur rute (hijau transparan)
  nodeDefault:     '#1e293b',              // Warna node biasa
  nodeBorder:      '#334155',              // Border node biasa
  nodeChecked:     '#312e81',              // Warna node yang dipilih (ungu gelap)
  nodeCheckBorder: '#818cf8',              // Border node yang dipilih (ungu terang)
  nodeStart:       '#14532d',              // Warna node titik awal/kurir (hijau gelap)
  nodeStartBorder: '#22c55e',              // Border titik awal (hijau)
  nodeRoute:       '#1e3a5f',              // Warna node yang masuk rute (biru gelap)
  nodeRouteBorder: '#3b82f6',              // Border node rute (biru)
  textDefault:     '#94a3b8',              // Warna teks label node biasa
  textRoute:       '#f1f5f9',              // Warna teks label node di rute
  distLabel:       '#64748b',              // Warna teks jarak di edge biasa
  distRoute:       '#ffffff',              // Warna teks jarak di edge rute
  arrow:           '#22c55e',              // Warna panah arah perjalanan
};


// ── VARIABEL CANVAS ──────────────────────────────────────────
let canvas, ctx, dpr = 1;
// canvas : elemen <canvas> di HTML
// ctx    : "kuas" untuk menggambar (2D rendering context)
// dpr    : Device Pixel Ratio — untuk layar retina/HiDPI agar tidak buram


// ============================================================
//  FUNGSI: initCanvas()
//  Inisialisasi canvas — cari elemen canvas di halaman,
//  sesuaikan ukurannya, dan pasang event listener untuk resize.
//  Dipanggil otomatis saat DOMContentLoaded.
// ============================================================
function initCanvas() {
  canvas = document.getElementById('tspCanvas');
  if (!canvas) return; // Jika elemen tidak ada, hentikan

  // Ambil rasio piksel perangkat (penting untuk layar retina)
  dpr = window.devicePixelRatio || 1;

  resizeCanvas(); // Sesuaikan ukuran canvas pertama kali

  // Setiap kali jendela browser diubah ukurannya, gambar ulang
  window.addEventListener('resize', () => {
    resizeCanvas();
    // Gambar ulang dengan data yang sama sebelum resize
    if (animNodes && animEdges) {
      drawCanvas(animNodes, animEdges, animChecked, animRoute);
    }
  });
}


// ============================================================
//  FUNGSI: resizeCanvas()
//  Menyesuaikan ukuran canvas dengan ukuran container-nya.
//  Rasio tinggi:lebar = 0.6 (misal lebar 700px → tinggi 420px)
//  Menggunakan DPR agar tampilan tetap tajam di layar HiDPI.
// ============================================================
function resizeCanvas() {
  if (!canvas) return;

  // Ambil ukuran elemen induk (parent) dari canvas
  const rect = canvas.parentElement.getBoundingClientRect();
  const W = rect.width || 700;     // Lebar canvas mengikuti container
  const H = Math.round(W * 0.6);   // Tinggi = 60% dari lebar

  // Ukuran piksel nyata (dikalikan DPR untuk layar retina)
  canvas.width  = W * dpr;
  canvas.height = H * dpr;

  // Ukuran tampilan CSS (tetap W x H secara visual)
  canvas.style.width  = W + 'px';
  canvas.style.height = H + 'px';

  // Ambil context dan skala agar gambar tajam di retina
  ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);
}


// ── KONVERSI KOORDINAT ────────────────────────────────────────
// Koordinat node di database menggunakan ruang referensi 640x500
// (koordinat desain, bukan piksel nyata).
// Fungsi scaleCoord() mengkonversi ke koordinat canvas aktual
// dengan memperhitungkan ukuran canvas dan padding tepi.

const REF_W = 640; // Lebar ruang koordinat referensi
const REF_H = 500; // Tinggi ruang koordinat referensi

function scaleCoord(x, y) {
  const cw  = canvas.width  / dpr; // Lebar canvas aktual (piksel CSS)
  const ch  = canvas.height / dpr; // Tinggi canvas aktual (piksel CSS)
  const pad = 70; // Jarak tepi agar node tidak menempel ke pinggir canvas

  // Hitung posisi dengan mempertimbangkan padding di kiri-kanan dan atas-bawah
  const sx = pad + (x / REF_W) * (cw - pad * 2);
  const sy = pad + (y / REF_H) * (ch - pad * 2);

  return { x: sx, y: sy };
}


// ============================================================
//  FUNGSI UTAMA: drawCanvas(nodes, edges, checkedNodes, routeNodes)
//
//  Parameter:
//  - nodes       : Objek berisi semua node {nama: {x, y, label}}
//  - edges       : Array semua jalur [{from, to, distance}]
//  - checkedNodes: Array nama node yang dipilih admin
//  - routeNodes  : Array urutan kunjungan hasil TSP (rute optimal)
//
//  Urutan menggambar:
//  1. Bersihkan canvas → gambar latar → gambar grid
//  2. Tentukan edge mana yang termasuk rute optimal
//  3. Gambar semua edge (jalur) — rute optimal berwarna hijau
//  4. Gambar semua node (titik/lingkaran) + label
//  5. Jika ada rute, jalankan animasi berulang (requestAnimationFrame)
// ============================================================
function drawCanvas(nodes, edges, checkedNodes = [], routeNodes = []) {

  // Jika canvas belum diinisialisasi, inisialisasi dulu
  if (!canvas) { initCanvas(); }
  if (!ctx) return;

  // Simpan semua parameter untuk keperluan resize & animasi ulang
  animNodes   = nodes;
  animEdges   = edges;
  animChecked = checkedNodes;
  animRoute   = routeNodes;

  const cw = canvas.width  / dpr; // Lebar canvas
  const ch = canvas.height / dpr; // Tinggi canvas

  // Batalkan animasi sebelumnya jika masih berjalan
  if (animFrame) { cancelAnimationFrame(animFrame); animFrame = null; }

  // ── 1. BERSIHKAN DAN GAMBAR LATAR ──────────────────────────
  ctx.clearRect(0, 0, cw, ch);
  ctx.fillStyle = '#0a0f1e'; // Warna latar gelap (biru tua hampir hitam)
  ctx.fillRect(0, 0, cw, ch);

  // ── GAMBAR TITIK-TITIK GRID (dekorasi) ─────────────────────
  ctx.fillStyle = 'rgba(255,255,255,0.035)'; // Sangat transparan
  const gridStep = 32; // Jarak antar titik grid
  for (let x = gridStep; x < cw; x += gridStep) {
    for (let y = gridStep; y < ch; y += gridStep) {
      ctx.beginPath();
      ctx.arc(x, y, 1, 0, Math.PI * 2); // Titik kecil radius 1px
      ctx.fill();
    }
  }

  // ── 2. TENTUKAN EDGE YANG MASUK RUTE OPTIMAL ───────────────
  // Buat Set berisi pasangan node rute agar mudah dicek
  // Contoh: "Kurir|Rumah B" berarti edge Kurir↔Rumah B adalah bagian rute
  const routeEdgeSet = new Set();
  if (routeNodes && routeNodes.length > 1) {
    for (let i = 0; i < routeNodes.length - 1; i++) {
      // Sort agar "A|B" dan "B|A" dianggap sama (undirected)
      const key = [routeNodes[i], routeNodes[i+1]].sort().join('|');
      routeEdgeSet.add(key);
    }
  }

  // ── 3. GAMBAR SEMUA EDGE (JALUR) ───────────────────────────
  edges.forEach(edge => {
    const from = nodes[edge.from];
    const to   = nodes[edge.to];
    if (!from || !to) return; // Lewati jika node tidak ditemukan

    // Konversi koordinat referensi ke koordinat canvas
    const pFrom = scaleCoord(from.x, from.y);
    const pTo   = scaleCoord(to.x,   to.y);

    const key       = [edge.from, edge.to].sort().join('|');
    const isRoute   = routeEdgeSet.has(key); // Apakah edge ini bagian rute?
    const isChecked = checkedNodes.includes(edge.from) && checkedNodes.includes(edge.to);

    if (isRoute) {
      // ── Gambar efek cahaya (glow) di belakang jalur rute ──
      ctx.save();
      ctx.strokeStyle = COLORS.edgeGlow;
      ctx.lineWidth   = 8;
      ctx.lineCap     = 'round';
      ctx.shadowColor = '#22c55e';
      ctx.shadowBlur  = 16;
      ctx.beginPath();
      ctx.moveTo(pFrom.x, pFrom.y);
      ctx.lineTo(pTo.x,   pTo.y);
      ctx.stroke();
      ctx.restore();

      // Garis utama rute (hijau solid)
      ctx.strokeStyle = COLORS.edgeRoute;
      ctx.lineWidth   = 3;
      ctx.setLineDash([]); // Garis penuh (tidak putus-putus)

    } else if (isChecked) {
      // Jalur antar node yang dipilih tapi bukan rute (ungu putus-putus)
      ctx.strokeStyle = COLORS.edgeChecked;
      ctx.lineWidth   = 1.5;
      ctx.setLineDash([6, 4]); // Pattern: 6px garis, 4px kosong

    } else {
      // Jalur biasa yang tidak terlibat (abu-abu)
      ctx.strokeStyle = COLORS.edgeDefault;
      ctx.lineWidth   = 1.5;
      ctx.setLineDash([]);
    }

    // Gambar garis edge
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(pFrom.x, pFrom.y);
    ctx.lineTo(pTo.x,   pTo.y);
    ctx.stroke();
    ctx.setLineDash([]); // Reset pattern setelah menggambar

    // ── Gambar panah arah di jalur rute ─────────────────────
    if (isRoute) {
      // Cek urutan arah dari routeNodes untuk menentukan arah panah
      const routeIdx = routeNodes.indexOf(edge.from);
      if (routeIdx >= 0 && routeNodes[routeIdx+1] === edge.to) {
        drawArrow(pFrom, pTo); // Arah: from → to
      } else {
        const rIdx2 = routeNodes.indexOf(edge.to);
        if (rIdx2 >= 0 && routeNodes[rIdx2+1] === edge.from) {
          drawArrow(pTo, pFrom); // Arah: to → from
        }
      }
    }

    // ── Gambar label jarak di tengah edge ───────────────────
    const mx = (pFrom.x + pTo.x) / 2; // Titik tengah X
    const my = (pFrom.y + pTo.y) / 2; // Titik tengah Y

    // Hitung sudut edge untuk menempatkan label sedikit di atas garis
    const angle    = Math.atan2(pTo.y - pFrom.y, pTo.x - pFrom.x);
    const labelOff = 14; // Jarak label dari garis (piksel)
    const lx = mx + Math.cos(angle - Math.PI/2) * labelOff;
    const ly = my + Math.sin(angle - Math.PI/2) * labelOff;

    // Gambar kotak latar label jarak
    const labelText = edge.distance + ' km';
    ctx.font = `bold ${isRoute ? 12 : 11}px Inter, sans-serif`;
    const tw = ctx.measureText(labelText).width;
    ctx.fillStyle = isRoute ? 'rgba(34,197,94,0.2)' : 'rgba(15,23,42,0.85)';
    roundRect(ctx, lx - tw/2 - 5, ly - 9, tw + 10, 18, 5);
    ctx.fill();

    // Gambar teks jarak
    ctx.fillStyle     = isRoute ? COLORS.distRoute : COLORS.distLabel;
    ctx.textAlign     = 'center';
    ctx.textBaseline  = 'middle';
    ctx.fillText(labelText, lx, ly);
  });

  // Faktor skala berdasarkan lebar canvas
  // Di layar kecil (mobile), node & teks dibuat lebih kecil
  const scaleRatio = Math.min(1, cw / 550);

  // ── 4. GAMBAR SEMUA NODE (TITIK LOKASI) ────────────────────
  Object.entries(nodes).forEach(([name, pos]) => {
    const p         = scaleCoord(pos.x, pos.y); // Konversi ke koordinat canvas
    const isStart   = name === 'Kurir';           // Apakah ini titik awal?
    const isChecked = checkedNodes.includes(name) || isStart; // Dipilih atau titik awal
    const inRoute   = routeNodes.includes(name);  // Apakah masuk rute optimal?

    // Ukuran radius node — titik awal (Kurir) lebih besar
    const r = (isStart ? 32 : 24) * scaleRatio;

    // ── Efek pulsa/animasi untuk node yang masuk rute ───────
    if (inRoute || isStart) {
      // pulseT adalah counter yang terus bertambah setiap frame
      // Math.sin menghasilkan gelombang 0-1 untuk efek berdenyut
      const pulse = 0.5 + 0.5 * Math.sin(pulseT * 0.06);

      ctx.save();
      ctx.shadowColor  = isStart ? '#22c55e' : (inRoute ? '#3b82f6' : '#818cf8');
      ctx.shadowBlur   = 20 + pulse * 10;
      ctx.beginPath();
      ctx.arc(p.x, p.y, r + 4 + pulse * 4, 0, Math.PI * 2);
      ctx.fillStyle    = 'transparent';
      ctx.strokeStyle  = isStart
        ? `rgba(34,197,94,${0.3 + pulse*0.2})`
        : `rgba(59,130,246,${0.3 + pulse*0.2})`;
      ctx.lineWidth    = 2 + pulse;
      ctx.stroke();

      // Efek blob biru di node yang masuk rute (bukan titik awal)
      if (inRoute && !isStart) {
        ctx.globalAlpha = 0.08 + pulse * 0.12;
        ctx.beginPath();
        ctx.arc(p.x, p.y, (32 + pulse * 8) * scaleRatio, 0, Math.PI * 2);
        ctx.fillStyle = '#3b82f6';
        ctx.fill();
      }
      ctx.restore();
    }

    // ── Gambar lingkaran node dengan gradient radial ─────────
    const grad = ctx.createRadialGradient(
      p.x - r*0.3, p.y - r*0.3, r*0.1, // Titik awal gradient (highlight)
      p.x,         p.y,         r        // Titik akhir gradient (tepi)
    );

    // Pilih warna gradient berdasarkan status node
    if (isStart) {
      grad.addColorStop(0, '#166534'); // Hijau gelap untuk kurir
      grad.addColorStop(1, '#052e16');
    } else if (inRoute) {
      grad.addColorStop(0, '#1e3a8a'); // Biru untuk node di rute
      grad.addColorStop(1, '#0f172a');
    } else if (isChecked) {
      grad.addColorStop(0, '#312e81'); // Ungu untuk node dipilih
      grad.addColorStop(1, '#1e1b4b');
    } else {
      grad.addColorStop(0, '#1e293b'); // Abu-abu gelap untuk node biasa
      grad.addColorStop(1, '#0f172a');
    }

    // Isi lingkaran dengan gradient
    ctx.beginPath();
    ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
    ctx.fillStyle = grad;
    ctx.fill();

    // Gambar border lingkaran
    ctx.beginPath();
    ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
    ctx.strokeStyle = isStart   ? COLORS.nodeStartBorder
                    : inRoute   ? COLORS.nodeRouteBorder
                    : isChecked ? COLORS.nodeCheckBorder
                    :             COLORS.nodeBorder;
    ctx.lineWidth = isStart || inRoute ? 2.5 : 1.5;
    ctx.stroke();

    // ── Gambar ikon emoji di dalam node ─────────────────────
    ctx.fillStyle = isStart   ? '#4ade80'
                  : inRoute   ? '#60a5fa'
                  : isChecked ? '#818cf8'
                  :             COLORS.textDefault;
    ctx.font          = `${(isStart ? 16 : 12) * scaleRatio}px "Font Awesome 6 Free"`;
    ctx.textAlign     = 'center';
    ctx.textBaseline  = 'middle';
    ctx.fillText(isStart ? '📍' : '🏠', p.x, p.y - 2);

    // ── Gambar nomor urutan kunjungan di sudut node ──────────
    // Angka muncul jika node ini ada di rute optimal (bukan start/end)
    const stepNum = routeNodes.indexOf(name);
    if (stepNum > 0 && stepNum < routeNodes.length - 1) {
      const bx = p.x + r * 0.7;  // Posisi X badge (kanan atas node)
      const by = p.y - r * 0.7;  // Posisi Y badge (kanan atas node)

      // Gambar lingkaran badge biru
      ctx.beginPath();
      ctx.arc(bx, by, 9 * scaleRatio, 0, Math.PI * 2);
      ctx.fillStyle = '#2563eb';
      ctx.fill();

      // Gambar angka di dalam badge
      ctx.fillStyle     = '#fff';
      ctx.font          = `bold ${10 * scaleRatio}px Inter`;
      ctx.textAlign     = 'center';
      ctx.textBaseline  = 'middle';
      ctx.fillText(stepNum, bx, by);
    }

    // ── Gambar label nama node di bawah lingkaran ───────────
    const labelY = p.y + r + (12 * scaleRatio);
    ctx.font          = `bold ${(isStart ? 13 : 11) * scaleRatio}px Inter, sans-serif`;
    ctx.textAlign     = 'center';
    ctx.textBaseline  = 'middle';
    ctx.fillStyle     = isStart   ? '#4ade80'
                      : inRoute   ? '#60a5fa'
                      : isChecked ? '#a5b4fc'
                      :             COLORS.textDefault;
    ctx.fillText(isStart ? 'Petugas Kurir' : name, p.x, labelY);
  });

  // ── 5. ANIMASI BERULANG (hanya jika ada rute yang ditampilkan) ─
  if (routeNodes.length > 1) {
    animFrame = requestAnimationFrame(() => {
      pulseT++; // Naikkan counter animasi setiap frame (~60x per detik)
      drawCanvas(nodes, edges, checkedNodes, routeNodes); // Gambar ulang
    });
  }
}


// ============================================================
//  FUNGSI PEMBANTU
// ============================================================

// ── drawArrow(from, to) ──────────────────────────────────────
// Menggambar panah kecil di tengah-tengah edge rute
// untuk menunjukkan arah perjalanan kurir.
// from, to : objek {x, y} berupa koordinat canvas
function drawArrow(from, to) {
  const angle = Math.atan2(to.y - from.y, to.x - from.x); // Sudut arah panah
  const mx  = (from.x + to.x) / 2; // Titik tengah X
  const my  = (from.y + to.y) / 2; // Titik tengah Y
  const len = 10; // Panjang kepala panah

  ctx.save();
  ctx.translate(mx, my);
  ctx.rotate(angle);
  ctx.beginPath();
  ctx.moveTo(-len, -len/2); // Sayap kiri panah
  ctx.lineTo(0,    0);       // Ujung panah
  ctx.lineTo(-len,  len/2); // Sayap kanan panah
  ctx.strokeStyle = COLORS.arrow;
  ctx.lineWidth   = 2;
  ctx.lineCap     = 'round';
  ctx.lineJoin    = 'round';
  ctx.stroke();
  ctx.restore();
}


// ── roundRect(ctx, x, y, w, h, r) ───────────────────────────
// Menggambar kotak/persegi panjang dengan sudut membulat.
// Digunakan untuk latar belakang label jarak di edge.
// x, y = posisi kiri atas; w, h = lebar & tinggi; r = radius sudut
function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.lineTo(x + w - r, y);
  ctx.quadraticCurveTo(x + w, y,     x + w, y + r);
  ctx.lineTo(x + w, y + h - r);
  ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
  ctx.lineTo(x + r, y + h);
  ctx.quadraticCurveTo(x,     y + h, x,     y + h - r);
  ctx.lineTo(x, y + r);
  ctx.quadraticCurveTo(x,     y,     x + r, y);
  ctx.closePath();
}


// ── Counter Animasi Pulsa ────────────────────────────────────
// pulseT bertambah setiap frame animasi (~60x per detik).
// Digunakan di dalam Math.sin() untuk membuat efek berdenyut
// yang halus pada node yang masuk rute.
let pulseT = 0;


// ── INISIALISASI OTOMATIS ────────────────────────────────────
// Jalankan initCanvas() begitu seluruh HTML selesai dimuat.
window.addEventListener('DOMContentLoaded', () => {
  initCanvas();
});
