/* ============================================================
   CATEGORY.JS — Category/subcategory dropdown management
   ============================================================ */

/**
 * Populate a category <select> element with options from CATEGORIES.
 * @param {HTMLSelectElement} selectEl - the category dropdown
 * @param {string} [selected] - value to pre-select
 */
function populateCategoryDropdown(selectEl, selected = '') {
  selectEl.innerHTML = '<option value="">Select Category</option>';

  Object.keys(CATEGORIES).forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat;
    opt.textContent = cat;
    if (cat === selected) opt.selected = true;
    selectEl.appendChild(opt);
  });
}

/**
 * Populate a sub-category <select> based on selected category.
 * @param {HTMLSelectElement} subSelectEl - the subcategory dropdown
 * @param {string} category - selected category name
 * @param {string} [selected] - value to pre-select
 */
function populateSubcategoryDropdown(subSelectEl, category, selected = '') {
  subSelectEl.innerHTML = '<option value="">Select Sub-category</option>';

  if (!category || !CATEGORIES[category]) {
    subSelectEl.disabled = true;
    return;
  }

  subSelectEl.disabled = false;
  CATEGORIES[category].forEach(sub => {
    const opt = document.createElement('option');
    opt.value = sub;
    opt.textContent = sub;
    if (sub === selected) opt.selected = true;
    subSelectEl.appendChild(opt);
  });
}

/**
 * Populate a unit <select> element with all available units.
 * @param {HTMLSelectElement} selectEl - the unit dropdown
 * @param {string} [selected] - value to pre-select
 */
function populateUnitDropdown(selectEl, selected = '') {
  selectEl.innerHTML = '<option value="">Unit</option>';

  UNITS.forEach(unit => {
    const opt = document.createElement('option');
    opt.value = unit;
    opt.textContent = unit;
    if (unit === selected) opt.selected = true;
    selectEl.appendChild(opt);
  });
}

/**
 * Populate the filter category dropdown (for toolbar filtering).
 * Includes an "All Categories" option.
 * @param {HTMLSelectElement} selectEl
 */
function populateFilterCategoryDropdown(selectEl) {
  selectEl.innerHTML = '<option value="">All Categories</option>';
  Object.keys(CATEGORIES).forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat;
    opt.textContent = cat;
    selectEl.appendChild(opt);
  });
}

/**
 * Populate the filter subcategory dropdown.
 * @param {HTMLSelectElement} selectEl
 * @param {string} category - if empty, shows "All Sub-categories" only
 */
function populateFilterSubcategoryDropdown(selectEl, category = '') {
  selectEl.innerHTML = '<option value="">All Sub-categories</option>';
  if (category && CATEGORIES[category]) {
    CATEGORIES[category].forEach(sub => {
      const opt = document.createElement('option');
      opt.value = sub;
      opt.textContent = sub;
      selectEl.appendChild(opt);
    });
  }
}

/**
 * Wire up category→subcategory dependency for modal selects.
 * @param {HTMLSelectElement} catSelect
 * @param {HTMLSelectElement} subSelect
 */
function bindCategorySubcategoryLink(catSelect, subSelect) {
  catSelect.addEventListener('change', () => {
    populateSubcategoryDropdown(subSelect, catSelect.value);
  });
}

/**
 * Wire up category→subcategory dependency for filter toolbar.
 * @param {HTMLSelectElement} catSelect
 * @param {HTMLSelectElement} subSelect
 * @param {Function} onChangeCallback - called after subcategory is updated
 */
function bindFilterCategorySubcategoryLink(catSelect, subSelect, onChangeCallback) {
  catSelect.addEventListener('change', () => {
    populateFilterSubcategoryDropdown(subSelect, catSelect.value);
    subSelect.value = '';
    if (onChangeCallback) onChangeCallback();
  });

  subSelect.addEventListener('change', () => {
    if (onChangeCallback) onChangeCallback();
  });
}
