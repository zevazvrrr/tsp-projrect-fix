// ═══════════════════════════════════════════════════════════
//  MAIN.JS — Global UI utilities
// ═══════════════════════════════════════════════════════════

/* ── TOAST ─────────────────────────────────────────────────── */
let toastTimer = null;
function showToast(msg, type = 'success') {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = msg;
  toast.className = `toast toast-${type} show`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
}

/* ── MODAL ──────────────────────────────────────────────────── */
function openModal(title, bodyHTML) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML = bodyHTML;
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal(e) {
  if (e.target === document.getElementById('modalOverlay')) closeModalDirect();
}

function closeModalDirect() {
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

/* ── SIDEBAR TOGGLE ──────────────────────────────────────── */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

/* ── ESCAPE KEY ─────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModalDirect();
});

/* ── GLOBAL SEARCH HINT ──────────────────────────────────── */
const gs = document.getElementById('globalSearch');
if (gs) {
  gs.addEventListener('keydown', e => {
    if (e.key === 'Enter' && gs.value.trim()) {
      // Navigate to undangan page with search param
      window.location.href = `undangan.php?search=${encodeURIComponent(gs.value.trim())}`;
    }
  });
}
