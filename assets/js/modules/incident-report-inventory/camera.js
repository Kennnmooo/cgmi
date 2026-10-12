/* ============================================================
   CAMERA.JS — Live camera modal (getUserMedia)
   Depends on: toast.js   (window.showToast)
               upload.js  (window.Upload.showPreviewFromDataUrl)
   ============================================================ */

(function () {
  'use strict';

  /* ── DOM refs ── */
  var cameraOverlay        = document.getElementById('camera-overlay');
  var cameraVideo          = document.getElementById('camera-video');
  var cameraCanvas         = document.getElementById('camera-canvas');
  var cameraSnapPreview    = document.getElementById('camera-snap-preview');
  var cameraSnapImg        = document.getElementById('camera-snap-img');
  var cameraLiveControls   = document.getElementById('camera-live-controls');
  var cameraRetakeControls = document.getElementById('camera-retake-controls');

  /* ── State ── */
  var currentStream = null;
  var facingMode    = 'environment'; // start with rear camera

  /* ── Stream helpers ── */

  /** Stop all active media tracks. */
  function stopStream() {
    if (currentStream) {
      currentStream.getTracks().forEach(function (track) { track.stop(); });
      currentStream = null;
    }
  }

  /**
   * Request camera access and start the live feed.
   * @param {string} [facing] - 'user' | 'environment'
   */
  function startCamera(facing) {
    stopStream();
    if (facing) facingMode = facing;

    navigator.mediaDevices
      .getUserMedia({ video: { facingMode: facingMode }, audio: false })
      .then(function (stream) {
        currentStream = stream;

        cameraVideo.srcObject         = stream;
        cameraVideo.style.display     = 'block';
        cameraSnapPreview.style.display    = 'none';
        cameraLiveControls.style.display   = 'flex';
        cameraRetakeControls.style.display = 'none';
      })
      .catch(function (err) {
        closeCameraModal();
        var msg = err.name === 'NotAllowedError'
          ? 'Camera permission denied. Please allow access and try again.'
          : 'Could not access camera: ' + err.message;
        window.showToast('icon-incident', 'Camera Error', msg, 'var(--accent-danger)');
      });
  }

  /* ── Modal open / close ── */

  function openCameraModal() {
    cameraOverlay.style.display  = 'flex';
    document.body.style.overflow = 'hidden';
    startCamera();
  }

  function closeCameraModal() {
    stopStream();
    cameraOverlay.style.display  = 'none';
    document.body.style.overflow = '';
    cameraVideo.srcObject             = null;
    cameraSnapPreview.style.display        = 'none';
    cameraLiveControls.style.display       = 'flex';
    cameraRetakeControls.style.display     = 'none';
  }

  /* ── Event listeners ── */

  /* "Take Photo" button → open modal */
  document.getElementById('btn-take-photo').addEventListener('click', openCameraModal);

  /* Close button & backdrop click → close modal */
  document.getElementById('camera-close-btn').addEventListener('click', closeCameraModal);
  cameraOverlay.addEventListener('click', function (e) {
    if (e.target === cameraOverlay) closeCameraModal();
  });

  /* Shutter → capture frame onto canvas */
  document.getElementById('camera-shutter-btn').addEventListener('click', function () {
    var w = cameraVideo.videoWidth;
    var h = cameraVideo.videoHeight;

    cameraCanvas.width  = w;
    cameraCanvas.height = h;
    cameraCanvas.getContext('2d').drawImage(cameraVideo, 0, 0, w, h);

    var dataUrl = cameraCanvas.toDataURL('image/jpeg', 0.92);
    cameraSnapImg.src = dataUrl;

    /* Switch to snapshot review UI */
    cameraSnapPreview.style.display    = 'flex';
    cameraVideo.style.display          = 'none';
    cameraLiveControls.style.display   = 'none';
    cameraRetakeControls.style.display = 'flex';

    stopStream();
  });

  /* Retake → discard snapshot, restart live feed */
  document.getElementById('camera-retake-btn').addEventListener('click', function () {
    cameraSnapPreview.style.display = 'none';
    cameraVideo.style.display       = 'block';
    startCamera();
  });

  /* Use Photo → hand data URL to upload module */
  document.getElementById('camera-use-btn').addEventListener('click', function () {
    var dataUrl = cameraSnapImg.src;
    closeCameraModal();
    window.Upload.showPreviewFromDataUrl(dataUrl);
    window.showToast('icon-check', 'Photo Captured', 'Camera photo added to the report.', 'var(--accent-success)');
  });

  /* Flip → toggle front / rear camera */
  document.getElementById('camera-flip-btn').addEventListener('click', function () {
    startCamera(facingMode === 'environment' ? 'user' : 'environment');
  });

})();
