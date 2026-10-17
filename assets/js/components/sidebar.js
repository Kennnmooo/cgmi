/* ── Sidebar toggle (all screen sizes) ─────────────────────── */
const menuToggle = document.getElementById('menu-toggle');
const sidebar    = document.getElementById('sidebar');
const overlay    = document.getElementById('sidebar-overlay');

const MOBILE_BREAKPOINT = 1376; // treat tablets (≤ 1376px) as mobile: drawer + overlay
const STORAGE_KEY = 'cgmi_sidebar_collapsed';

/* Transition values applied inline so they win over any CSS cascade */
const SIDEBAR_TRANSITION  = 'transform 0.32s cubic-bezier(0.4,0,0.2,1), box-shadow 0.32s cubic-bezier(0.4,0,0.2,1)';
const CONTENT_TRANSITION  = 'margin-left 0.32s cubic-bezier(0.4,0,0.2,1)';
const OVERLAY_TRANSITION  = 'opacity 0.28s ease';

function getMainContent() {
  return document.querySelector('.main-content');
}

/* Apply inline transitions once so they are guaranteed to be active */
function ensureTransitions() {
  if (sidebar) {
    sidebar.style.transition  = SIDEBAR_TRANSITION;
    sidebar.style.willChange  = 'transform';
  }
  const mc = getMainContent();
  if (mc) mc.style.transition = CONTENT_TRANSITION;
  if (overlay) overlay.style.transition = OVERLAY_TRANSITION;
}

function isMobile() {
  return window.innerWidth <= MOBILE_BREAKPOINT;
}

function openSidebar() {
  ensureTransitions();
  if (isMobile()) {
    /* Make overlay visible before animating opacity */
    overlay.style.display    = 'block';
    overlay.style.opacity    = '0';
    overlay.style.pointerEvents = 'auto';
    /* Force reflow so the opacity transition fires */
    overlay.getBoundingClientRect();
    overlay.style.opacity    = '1';

    sidebar.classList.add('open');
    document.body.style.overflow = 'hidden';
  } else {
    document.body.classList.remove('sidebar-collapsed');
    localStorage.setItem(STORAGE_KEY, '0');
  }
  menuToggle?.setAttribute('aria-expanded', 'true');
}

function closeSidebar() {
  ensureTransitions();
  if (isMobile()) {
    /* Fade out overlay, then hide */
    overlay.style.opacity = '0';
    overlay.style.pointerEvents = 'none';
    overlay.addEventListener('transitionend', () => {
      if (overlay.style.opacity === '0') overlay.style.display = 'none';
    }, { once: true });

    sidebar.classList.remove('open');
    document.body.style.overflow = '';
  } else {
    document.body.classList.add('sidebar-collapsed');
    localStorage.setItem(STORAGE_KEY, '1');
  }
  menuToggle?.setAttribute('aria-expanded', 'false');
}

function toggleSidebar() {
  if (isMobile()) {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  } else {
    document.body.classList.contains('sidebar-collapsed') ? openSidebar() : closeSidebar();
  }
}

/* Restore desktop preference on load; mobile always starts closed */
function initSidebar() {
  ensureTransitions();
  if (!isMobile()) {
    const collapsed = localStorage.getItem(STORAGE_KEY);
    if (collapsed === '1') {
      document.body.classList.add('sidebar-collapsed');
      menuToggle?.setAttribute('aria-expanded', 'false');
    } else {
      menuToggle?.setAttribute('aria-expanded', 'true');
    }
  } else {
    document.body.style.overflow = '';
    menuToggle?.setAttribute('aria-expanded', 'false');
    /* Ensure overlay starts hidden */
    if (overlay) {
      overlay.style.display    = 'none';
      overlay.style.opacity    = '0';
      overlay.style.pointerEvents = 'none';
    }
  }
}

/* Re-evaluate on resize (e.g. rotating tablet) */
let resizeTimer;
window.addEventListener('resize', () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => {
    ensureTransitions();
    if (!isMobile()) {
      sidebar.classList.remove('open');
      if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.display = 'none';
        overlay.style.pointerEvents = 'none';
      }
      document.body.style.overflow = '';
    } else {
      document.body.classList.remove('sidebar-collapsed');
    }
  }, 150);
});

menuToggle?.addEventListener('click', toggleSidebar);
overlay?.addEventListener('click', closeSidebar);

initSidebar();
