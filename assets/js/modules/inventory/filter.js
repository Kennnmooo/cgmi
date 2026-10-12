/* ============================================================
   FILTER.JS — Filtering, searching, and sorting logic
   ============================================================ */

const FilterManager = (() => {
  const state = {
    search:        '',
    searchMode:    'name', // 'name' or 'sku'
    statusFilter:  'Active',
    category:      '',
    subcategory:   '',
    shelfTracking: '',
    sort:          null,
  };

  let dtRef = null;

  /* ── Init: bind UI controls to state ───────────────────── */
  function init(dt, elements) {
    dtRef = dt;

    if (elements.searchInput) {
      elements.searchInput.addEventListener('input', (e) => {
        state.search = e.target.value;
        applyFilters();
      });
    }

    // Search mode toggle (Name / SKU buttons)
    if (elements.searchModeBtns) {
      elements.searchModeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          elements.searchModeBtns.forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
          });
          btn.classList.add('active');
          btn.setAttribute('aria-pressed', 'true');
          state.searchMode = btn.dataset.mode;
          // Update placeholder
          if (elements.searchInput) {
            elements.searchInput.placeholder = state.searchMode === 'sku' ? 'Search by SKU…' : 'Search by name…';
          }
          applyFilters();
        });
      });
    }

    if (elements.statusTabs) {
      elements.statusTabs.forEach(tab => {
        tab.addEventListener('click', () => {
          elements.statusTabs.forEach(t => {
            t.classList.remove('active');
            t.setAttribute('aria-pressed', 'false');
          });
          tab.classList.add('active');
          tab.setAttribute('aria-pressed', 'true');
          state.statusFilter = tab.dataset.filter;
          applyFilters();
        });
      });
    }

    if (elements.catSelect) {
      elements.catSelect.addEventListener('change', () => {
        state.category    = elements.catSelect.value;
        state.subcategory = '';
        applyFilters();
      });
    }

    if (elements.subSelect) {
      elements.subSelect.addEventListener('change', () => {
        state.subcategory = elements.subSelect.value;
        applyFilters();
      });
    }

    if (elements.shelfSelect) {
      elements.shelfSelect.addEventListener('change', () => {
        state.shelfTracking = elements.shelfSelect.value;
        applyFilters();
      });
    }
  }

  /* ── Called after table is rebuilt on resize ────────────── */
  function reattach(dt) {
    dtRef = dt;
  }

  /* ── Push the search string into DataTables (triggers custom search fn) */
  function applyFilters() {
    if (!dtRef) return;
    // Use empty string for DT native search; our custom search filter handles the actual filtering
    dtRef.search('').draw();
  }

  /* ── External sort ───────────────────────────────────────── */
  function setSort(col, dir) {
    state.sort = { col, dir };
    if (!dtRef) return;
    /* Full-mode column indices (incl. hidden CatRaw/SubRaw):
         0=image 1=name 2=category 3=warehouseQty 4=storeQty 5=iotShelfQty
         6=overallQty 7=weightCheck 8=CatRaw 9=SubRaw 10=unitPrice 11=status 12=actions
       Compact-mode column indices:
         0=image 1=name 2=overallQty 3=CatRaw 4=SubRaw 5=status 6=details 7=update 8=statusBtn
       Compact mode has no price column, so price falls back to name (index 1). */
    const colMap = isCompactMode
      ? { name: 1, price: 1, stock: 2 }
      : { name: 1, price: 10, stock: 6 };
    dtRef.order([colMap[col] || 1, dir]).draw();
  }

  function clearSort() {
    state.sort = null;
    if (dtRef) dtRef.order([1, 'asc']).draw();
  }

  function getState()  { return { ...state }; }
  function reset()     {
    state.search = ''; state.statusFilter = 'Active';
    state.category = ''; state.subcategory = ''; state.shelfTracking = ''; state.sort = null;
    if (dtRef) dtRef.search('').order([1, 'asc']).draw();
  }

  return { init, reattach, applyFilters, setSort, clearSort, getState, reset };
})();


/* ============================================================
   SORT DROPDOWN CONTROLLER
   ============================================================ */
function initSortDropdown(sortBtn, sortDropdown, sortOptions) {
  sortBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = sortDropdown.classList.toggle('open');
    sortBtn.classList.toggle('active', open);
    sortBtn.setAttribute('aria-expanded', String(open));
  });

  document.addEventListener('click', (e) => {
    if (!sortDropdown.contains(e.target) && e.target !== sortBtn) {
      sortDropdown.classList.remove('open');
      sortBtn.classList.remove('active');
      sortBtn.setAttribute('aria-expanded', 'false');
    }
  });

  sortOptions.forEach(opt => {
    opt.addEventListener('click', () => {
      sortOptions.forEach(o => o.classList.remove('active'));
      opt.classList.add('active');
      sortBtn.querySelector('.sort-label').textContent = opt.dataset.label || opt.textContent.trim();
      sortBtn.classList.add('active');
      FilterManager.setSort(opt.dataset.col, opt.dataset.dir);
      sortDropdown.classList.remove('open');
      sortBtn.setAttribute('aria-expanded', 'false');
    });
  });
}
