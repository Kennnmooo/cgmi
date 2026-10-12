(function () {
  'use strict';
  const api = window.CGMI_INCIDENTS_API || {};
  async function handleSubmit() {
    const category = document.getElementById('report-category')?.value.trim() || '';
    const title = document.getElementById('report-title').value.trim();
    const narrative = document.getElementById('report-narrative').value.trim();
    const file = window.Upload?.getFile?.() || null;
    const hasImage = !!file || !!window.Upload?.getCapturedDataUrl?.();
    if (!hasImage) return window.showToast('icon-incident', 'Image Required', 'Please upload or capture an incident image.', 'var(--accent-warning)');
    if (!category) return window.showToast('icon-incident', 'Category Required', 'Please select the category this report is about.', 'var(--accent-warning)');
    if (!title) return window.showToast('icon-incident', 'Title Required', 'Please enter a title for the incident.', 'var(--accent-warning)');
    if (narrative.length < 20) return window.showToast('icon-incident', 'Narrative Too Short', 'Please enter at least 20 characters.', 'var(--accent-warning)');
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) loadingOverlay.classList.add('active');
    try {
      const formData = new FormData();
      formData.append('category', category);
      formData.append('title', title);
      formData.append('narrative', narrative);
      if (file) {
        formData.append('image', file);
      } else {
        const blob = await (await fetch(window.Upload.getCapturedDataUrl())).blob();
        formData.append('image', new File([blob], 'incident-photo.png', { type: blob.type || 'image/png' }));
      }
      const response = await fetch(api.submit, { method: 'POST', credentials: 'same-origin', body: formData });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Unable to submit incident report.');
      resetForm();
      window.showToast('icon-check', 'Report Submitted', data.message || 'Your incident report has been sent to the admin.', 'var(--accent-success)');
    } catch (error) {
      console.error(error);
      window.showToast('icon-incident', 'Submission Failed', error.message || 'Unable to submit report.', 'var(--accent-danger)');
    } finally {
      if (loadingOverlay) loadingOverlay.classList.remove('active');
    }
  }
  function resetForm() { window.Upload?.clearImage?.(); const cat = document.getElementById('report-category'); if (cat) cat.value = ''; document.getElementById('report-title').value = ''; document.getElementById('report-narrative').value = ''; }
  document.getElementById('submit-report-btn')?.addEventListener('click', handleSubmit);
  document.getElementById('clear-report-btn')?.addEventListener('click', resetForm);
  ['add-report-btn', 'add-report-btn-2'].forEach(function (id) { const btn = document.getElementById(id); if (!btn) return; btn.addEventListener('click', function () { const panel = document.getElementById('add-report-panel'); if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }); });
})();
