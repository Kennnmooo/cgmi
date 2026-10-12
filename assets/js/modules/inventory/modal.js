/* ============================================================
   MODAL.JS — Add/Edit Item modal management
   ============================================================ */

const ModalManager = (() => {
  // DOM references (set during init)
  let backdrop, modal, form;
  let titleEl, subtitleEl, iconEl;
  let imgInput, imgPreviewWrap, imgPreview, imgRemoveBtn, uploadZone;
  let nameInput, qtyWarehouseInput, qtyStoreInput, qtyIotInput, unitSelect, catSelect, subCatSelect, priceInput;
  let saveBtn, cancelBtn, closeBtn;

  // Modal mode: 'add' | 'edit'
  let currentMode = 'add';
  // ID of the item being edited
  let editingItemId = null;
  // Current image preview URL / existing image URL
  let currentImageData = null;
  let currentImageFile = null;
  let removeExistingImage = false;

  /**
   * Initialize the modal by binding DOM elements and events.
   */
  function init() {
    backdrop      = document.getElementById('item-modal-backdrop');
    modal         = document.getElementById('item-modal');
    form          = document.getElementById('item-form');

    titleEl       = document.getElementById('modal-title');
    subtitleEl    = document.getElementById('modal-subtitle');
    iconEl        = document.getElementById('modal-icon');

    uploadZone    = document.getElementById('img-upload-zone');
    imgInput      = document.getElementById('img-file-input');
    imgPreviewWrap = document.getElementById('img-preview-wrap');
    imgPreview    = document.getElementById('img-preview');
    imgRemoveBtn  = document.getElementById('img-remove-btn');

    nameInput     = document.getElementById('item-name');
    qtyWarehouseInput = document.getElementById('item-qty-warehouse');
    qtyStoreInput = document.getElementById('item-qty-store');
    qtyIotInput   = document.getElementById('item-qty-iot');
    unitSelect    = document.getElementById('item-unit');
    catSelect     = document.getElementById('item-category');
    subCatSelect  = document.getElementById('item-subcategory');
    priceInput    = document.getElementById('item-price');

    saveBtn       = document.getElementById('modal-save-btn');
    cancelBtn     = document.getElementById('modal-cancel-btn');
    closeBtn      = document.getElementById('modal-close-btn');

    // Populate static dropdowns
    populateUnitDropdown(unitSelect);
    populateCategoryDropdown(catSelect);
    populateSubcategoryDropdown(subCatSelect, '');
    bindCategorySubcategoryLink(catSelect, subCatSelect);

    // Image upload
    imgInput.addEventListener('change', handleImageUpload);

    // Drag and drop
    uploadZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      uploadZone.classList.add('drag-over');
    });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
    uploadZone.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadZone.classList.remove('drag-over');
      if (e.dataTransfer.files.length) {
        handleImageFile(e.dataTransfer.files[0]);
      }
    });

    // Remove image
    imgRemoveBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      clearImagePreview();
    });

    // Close modal events
    closeBtn.addEventListener('click', close);
    cancelBtn.addEventListener('click', close);
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) close();
    });

    // Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && backdrop.classList.contains('active')) close();
    });

    // Save button
    saveBtn.addEventListener('click', handleSave);

    // Clear errors on input
    [nameInput, qtyWarehouseInput, qtyStoreInput, qtyIotInput, priceInput, catSelect, subCatSelect, unitSelect].filter(Boolean).forEach(el => {
      el.addEventListener('change', () => clearFieldError(el));
      el.addEventListener('input', () => clearFieldError(el));
    });
  }

  /**
   * Open the modal in "Add Item" mode.
   */
  function openAdd() {
    currentMode = 'add';
    editingItemId = null;
    currentImageData = null;
    currentImageFile = null;
    removeExistingImage = false;

    titleEl.textContent = 'Add New Item';
    subtitleEl.textContent = 'Fill in the details for the new inventory item';
    iconEl.className = 'modal-icon ui-icon icon-add';
    iconEl.textContent = '';
    iconEl.setAttribute('aria-hidden','true');
    saveBtn.textContent = 'Save Item';
    saveBtn.className = 'btn btn-primary';

    resetForm();

    // New items don't have a shelf assigned yet — disable IoT input
    qtyIotInput.disabled = true;
    qtyIotInput.value = 0;
    qtyIotInput.title = 'Assign this item to a shelf first to set IoT quantity';
    qtyIotInput.closest('.qty-field')?.classList.add('qty-field-disabled');

    // Lock quantity fields for roles that may not edit stock (inventory).
    applyQuantityPermission();

    backdrop.classList.add('active');
    nameInput.focus();
  }

  /**
   * Determine whether the current user may edit stock quantities.
   * Only super-admin and admin can; the inventory role cannot.
   */
  function canEditQuantity() {
    const role = ((window.CGMI_USER || {}).role || '').toString().toLowerCase();
    return role === 'super-admin' || role === 'admin';
  }

  /**
   * Disable and visually lock the warehouse / store / IoT quantity inputs when
   * the current user is not allowed to change stock. When allowed, warehouse
   * and store inputs are re-enabled (IoT stays governed by shelf assignment).
   */
  function applyQuantityPermission() {
    const locked = !canEditQuantity();
    const lockTitle = 'Only an admin or super admin can change stock quantities.';

    // Use a dedicated "locked" class (not "qty-field-disabled") so we don't
    // trigger the shelf-specific "No shelf assigned" hint on warehouse/store.
    [qtyWarehouseInput, qtyStoreInput].forEach(input => {
      if (!input) return;
      input.disabled = locked;
      input.title = locked ? lockTitle : '';
      input.closest('.qty-field')?.classList.toggle('qty-field-locked', locked);
    });

    if (locked && qtyIotInput) {
      qtyIotInput.disabled = true;
      qtyIotInput.title = lockTitle;
      qtyIotInput.closest('.qty-field')?.classList.add('qty-field-locked');
    }
  }

  /**
   * Open the modal in "Edit Item" mode with pre-filled data.
   * @param {object} item - the inventory item object
   */
  function openEdit(item) {
    currentMode = 'edit';
    editingItemId = item.dbId || item.id;
    currentImageData = item.image || null;
    currentImageFile = null;
    removeExistingImage = false;

    titleEl.textContent = 'Update Item';
    subtitleEl.textContent = `Editing: ${item.id}`;
    iconEl.className = 'modal-icon ui-icon icon-edit';
    iconEl.textContent = '';
    iconEl.setAttribute('aria-hidden','true');
    saveBtn.textContent = 'Save Changes';
    saveBtn.className = 'btn btn-primary';

    resetForm();

    // Pre-fill fields
    nameInput.value = item.name || '';
    qtyWarehouseInput.value = item.warehouseQty !== undefined ? item.warehouseQty : 0;
    qtyStoreInput.value = item.storeQty !== undefined ? item.storeQty : 0;
    qtyIotInput.value = item.iotShelfQty !== undefined ? item.iotShelfQty : 0;
    priceInput.value = item.unitPrice !== undefined ? item.unitPrice : '';

    // Enable/disable IoT input based on shelf assignment
    if (item.hasShelf) {
      qtyIotInput.disabled = false;
      qtyIotInput.title = '';
      qtyIotInput.closest('.qty-field')?.classList.remove('qty-field-disabled');
    } else {
      qtyIotInput.disabled = true;
      qtyIotInput.value = 0;
      qtyIotInput.title = 'Assign this item to a shelf first to set IoT quantity';
      qtyIotInput.closest('.qty-field')?.classList.add('qty-field-disabled');
    }

    // Lock quantity fields for roles that may not edit stock (inventory).
    applyQuantityPermission();

    // Populate and set category
    populateCategoryDropdown(catSelect, item.category);
    populateSubcategoryDropdown(subCatSelect, item.category, item.subcategory);
    populateUnitDropdown(unitSelect, item.unit);

    // Set image preview
    if (item.image) {
      showImagePreview(item.image);
    } else {
      clearImagePreview();
    }

    backdrop.classList.add('active');
    nameInput.focus();
  }

  /**
   * Close the modal.
   */
  function close() {
    backdrop.classList.remove('active');
    editingItemId = null;
    currentMode = 'add';
    currentImageData = null;
    currentImageFile = null;
    removeExistingImage = false;
    clearAllErrors();
  }

  /**
   * Handle image file input change.
   */
  function handleImageUpload(e) {
    const file = e.target.files[0];
    if (file) handleImageFile(file);
  }

  /**
   * Process an image file and show preview.
   * @param {File} file
   */
  function handleImageFile(file) {
    if (!file.type.startsWith('image/')) {
      showToast('Invalid File', 'Please upload an image file (JPG, PNG, etc.).', 'warning');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      showToast('File Too Large', 'Image must be under 5MB.', 'warning');
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      currentImageData = e.target.result;
      currentImageFile = file;
      removeExistingImage = false;
      showImagePreview(currentImageData);
    };
    reader.readAsDataURL(file);
  }

  /**
   * Show image in the preview area.
   * @param {string} src - image data URL or URL
   */
  function showImagePreview(src) {
    imgPreview.src = src;
    imgPreviewWrap.classList.add('has-img');
  }

  /**
   * Clear the image preview.
   */
  function clearImagePreview() {
    imgPreview.src = '';
    imgPreviewWrap.classList.remove('has-img');
    imgInput.value = '';
    currentImageData = null;
    currentImageFile = null;
    removeExistingImage = true;
    currentImageFile = null;
    removeExistingImage = false;
  }

  /**
   * Reset all form fields to blank state.
   */
  function resetForm() {
    form.reset();
    clearImagePreview();
    populateCategoryDropdown(catSelect);
    populateSubcategoryDropdown(subCatSelect, '');
    populateUnitDropdown(unitSelect);
    clearAllErrors();
  }

  /**
   * Handle save button click — validate and call InventoryManager.
   */
  async function handleSave() {
    if (!validateForm()) return;

    const itemData = {
      name: nameInput.value.trim(),
      category: catSelect.value,
      subcategory: subCatSelect.value,
      shelfTracking: 'not_shelves',
      warehouseQty: parseInt(qtyWarehouseInput.value, 10) || 0,
      storeQty: parseInt(qtyStoreInput.value, 10) || 0,
      iotShelfQty: parseInt(qtyIotInput.value, 10) || 0,
      unit: unitSelect.value,
      unitPrice: parseFloat(priceInput.value),
      imageFile: currentImageFile,
      removeImage: removeExistingImage ? 1 : 0,
    };

    showLoading();

    try {
      if (currentMode === 'add') {
        await InventoryManager.addItem(itemData);
        showToast('Item Added', `"${itemData.name}" has been added to inventory.`, 'success');
      } else {
        await InventoryManager.updateItem(editingItemId, itemData);
        showToast('Item Updated', `"${itemData.name}" has been successfully updated.`, 'success');
      }

      close();
    } catch (error) {
      console.error('[ModalManager] Save failed.', error);
      const message = error.message || 'Unable to save item.';
      // Highlight the name field when the server reports a duplicate item.
      if (/already exists/i.test(message)) {
        setFieldError(nameInput, message);
        nameInput.focus();
      }
      showToast('Save Failed', message, 'error', 5000);
    } finally {
      hideLoading();
    }
  }

  /**
   * Validate the form fields.
   * @returns {boolean} - true if valid
   */
  function validateForm() {
    let valid = true;

    if (!nameInput.value.trim()) {
      setFieldError(nameInput, 'Item name is required.');
      valid = false;
    }

    // Quantities are optional — a blank field defaults to 0. Only reject
    // values that are non-numeric or negative when something is entered.
    if (qtyWarehouseInput.value !== '' && (isNaN(parseInt(qtyWarehouseInput.value, 10)) || parseInt(qtyWarehouseInput.value, 10) < 0)) {
      setFieldError(qtyWarehouseInput, 'Please enter a valid warehouse quantity (0 or more).');
      valid = false;
    }

    if (qtyStoreInput.value !== '' && (isNaN(parseInt(qtyStoreInput.value, 10)) || parseInt(qtyStoreInput.value, 10) < 0)) {
      setFieldError(qtyStoreInput, 'Please enter a valid store quantity (0 or more).');
      valid = false;
    }

    if (qtyIotInput.value !== '' && (isNaN(parseInt(qtyIotInput.value, 10)) || parseInt(qtyIotInput.value, 10) < 0)) {
      setFieldError(qtyIotInput, 'Please enter a valid IoT shelves quantity (0 or more).');
      valid = false;
    }

    if (!unitSelect.value) {
      setFieldError(unitSelect, 'Please select a unit.');
      valid = false;
    }

    if (!catSelect.value) {
      setFieldError(catSelect, 'Please select a category.');
      valid = false;
    }

    if (!subCatSelect.value) {
      setFieldError(subCatSelect, 'Please select a sub-category.');
      valid = false;
    }

    if (priceInput.value === '' || isNaN(parseFloat(priceInput.value)) || parseFloat(priceInput.value) < 0) {
      setFieldError(priceInput, 'Please enter a valid price.');
      valid = false;
    }

    return valid;
  }

  /**
   * Set error state on a field.
   * @param {HTMLElement} el
   * @param {string} message
   */
  function setFieldError(el, message) {
    el.classList.add('error');
    // Find or create error message element
    let errEl = el.parentElement.querySelector('.field-error');
    if (!errEl) {
      errEl = document.createElement('span');
      errEl.className = 'field-error';
      el.parentElement.appendChild(errEl);
    }
    errEl.textContent = message;
  }

  /**
   * Clear error on a specific field.
   * @param {HTMLElement} el
   */
  function clearFieldError(el) {
    el.classList.remove('error');
    const errEl = el.parentElement ? el.parentElement.querySelector('.field-error') : null;
    if (errEl) errEl.remove();
  }

  /**
   * Clear all form field errors.
   */
  function clearAllErrors() {
    if (!form) return;
    form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
    form.querySelectorAll('.field-error').forEach(el => el.remove());
  }

  return { init, openAdd, openEdit, close };
})();
