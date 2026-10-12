/* ============================================================
   DATA.JS — Dynamic category/unit data loaded from database
   ============================================================ */

/**
 * CATEGORIES — loaded from API, keyed by category name → subcategory array.
 * Populated by loadCategoriesFromAPI().
 */
let CATEGORIES = {};

/**
 * UNITS — loaded from API, flat array of unit name strings.
 * Populated by loadUnitsFromAPI().
 */
let UNITS = [];

/**
 * Load categories and subcategories from the database API.
 * Returns the CATEGORIES object.
 */
async function loadCategoriesFromAPI() {
  try {
    const url = (window.CGMI_INVENTORY_API && window.CGMI_INVENTORY_API.categories) || '';
    if (!url) {
      console.warn('[Data] No categories API URL configured.');
      return CATEGORIES;
    }
    const response = await fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('HTTP ' + response.status);
    const payload = await response.json();
    if (payload.success && payload.categories) {
      CATEGORIES = payload.categories;
    }
  } catch (e) {
    console.error('[Data] Failed to load categories from API:', e);
  }
  return CATEGORIES;
}

/**
 * Load units from the database API.
 * Returns the UNITS array.
 */
async function loadUnitsFromAPI() {
  try {
    const url = (window.CGMI_INVENTORY_API && window.CGMI_INVENTORY_API.units) || '';
    if (!url) {
      console.warn('[Data] No units API URL configured.');
      return UNITS;
    }
    const response = await fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('HTTP ' + response.status);
    const payload = await response.json();
    if (payload.success && Array.isArray(payload.units)) {
      UNITS = payload.units;
    }
  } catch (e) {
    console.error('[Data] Failed to load units from API:', e);
  }
  return UNITS;
}

/**
 * Load all reference data (categories + units) from the database.
 * Call this once at app startup before populating dropdowns.
 */
async function loadReferenceData() {
  await Promise.all([loadCategoriesFromAPI(), loadUnitsFromAPI()]);
}
