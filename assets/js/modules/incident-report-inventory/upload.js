/* ============================================================
   UPLOAD.JS — Image upload & preview handler
   Depends on: toast.js  (window.showToast)
   Exposes:    window.Upload.getCapturedDataUrl()
               window.Upload.clearImage()
               window.Upload.showPreviewFromDataUrl(dataUrl)
   ============================================================ */

(function () {
  'use strict';

  /* ── DOM refs ── */
  var fileInputUpload = document.getElementById('report-image-upload');
  var preview         = document.getElementById('upload-preview');
  var placeholder     = document.getElementById('upload-placeholder');
  var previewImg      = document.getElementById('preview-img');
  var removeBtn       = document.getElementById('remove-img-btn');

  /* ── State ── */
  var capturedDataUrl = null; // set by camera.js when a photo is taken

  /* ── Internal helpers ── */

  /**
   * Show the preview card with a data URL (used by camera.js).
   * @param {string} dataUrl
   */
  function showPreviewFromDataUrl(dataUrl) {
    capturedDataUrl     = dataUrl;
    previewImg.src      = dataUrl;
    preview.style.display     = 'flex';
    placeholder.style.display = 'none';
  }

  /**
   * Load a File object, validate it, and show the preview.
   * @param {File} file
   */
  function setPreviewFromFile(file) {
    if (!file || !file.type.startsWith('image/')) {
      window.showToast('icon-incident', 'Invalid File', 'Please select a valid image file.', 'var(--accent-warning)');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      window.showToast('icon-incident', 'File Too Large', 'Image must be under 10 MB.', 'var(--accent-warning)');
      return;
    }

    capturedDataUrl = null; // clear any camera snapshot
    var reader = new FileReader();
    reader.onload = function (e) {
      previewImg.src            = e.target.result;
      preview.style.display     = 'flex';
      placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
  }

  /**
   * Reset the upload area back to the empty state.
   */
  function clearImage() {
    fileInputUpload.value     = '';
    capturedDataUrl           = null;
    previewImg.src            = '';
    preview.style.display     = 'none';
    placeholder.style.display = 'flex';
  }

  /* ── Event listeners ── */

  /* "Upload Photo" button → open file picker */
  document.getElementById('btn-upload-file').addEventListener('click', function () {
    fileInputUpload.click();
  });

  /* File picker → show preview */
  fileInputUpload.addEventListener('change', function () {
    if (fileInputUpload.files && fileInputUpload.files[0]) {
      setPreviewFromFile(fileInputUpload.files[0]);
    }
  });

  /* Remove button → clear */
  removeBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    clearImage();
  });

  /* ── Public API ── */
  window.Upload = {
    /** Returns the camera-captured data URL, or null if a file was used instead */
    getCapturedDataUrl: function () { return capturedDataUrl; },
    /** Returns the raw FileList from the file input */
    getFile: function () { return fileInputUpload.files && fileInputUpload.files[0]; },
    /** Clears the upload area (called by report.js on submit / clear) */
    clearImage: clearImage,
    /** Shows a preview from a data URL (called by camera.js after capture) */
    showPreviewFromDataUrl: showPreviewFromDataUrl
  };

})();
