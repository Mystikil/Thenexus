const elements = {
  updated: document.getElementById('roadmap-updated'),
  search: document.getElementById('roadmap-search'),
  sort: document.getElementById('roadmap-sort'),
  statusFilters: document.getElementById('status-filters'),
  categoryFilters: document.getElementById('category-filters'),
  phaseFilters: document.getElementById('phase-filters'),
  summary: document.getElementById('summary-cards'),
  phases: document.getElementById('roadmap-phases'),
  results: document.getElementById('roadmap-results'),
  empty: document.getElementById('roadmap-empty'),
  clearFilters: document.getElementById('clear-filters'),
  themeToggle: document.getElementById('theme-toggle'),
  downloadJson: document.getElementById('download-json'),
  editJson: document.getElementById('edit-json'),
  modal: document.getElementById('roadmap-modal'),
  modalTextarea: document.getElementById('roadmap-modal-textarea'),
  modalSave: document.getElementById('modal-save'),
  modalCancel: document.getElementById('modal-cancel'),
  modalClose: document.getElementById('modal-close'),
  ldJson: document.getElementById('roadmap-ld-json')
};

const state = {
  data: null,
  items: [],
  filtered: [],
  weights: {},
  filters: {
    status: new Set(),
    category: new Set(),
    phase: new Set()
  },
  phaseOrder: {}
};

const statusPriority = new Map([
  ['Shipped', 1.6],
  ['In Review', 1.4],
  ['In Progress', 1.3],
  ['In Development', 1.15],
  ['Prototype', 1],
  ['Planned', 0.85],
  ['Backlog', 0.7]
]);

function initTheme() {
  const stored = localStorage.getItem('nx-theme');
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = stored || (prefersDark ? 'dark' : 'light');
  applyTheme(theme);

  if (elements.themeToggle) {
    elements.themeToggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    elements.themeToggle.addEventListener('click', () => {
      const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      localStorage.setItem('nx-theme', next);
      elements.themeToggle.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
    });
  }
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
}

async function loadRoadmap() {
  try {
    const response = await fetch('/Site/roadmap/roadmap.json', { cache: 'no-cache' });
    if (!response.ok) {
      throw new Error(`Failed to load roadmap (${response.status})`);
    }
    const data = await response.json();
    refreshData(data);
  } catch (error) {
    console.error(error);
    if (elements.results) {
      elements.results.innerHTML = '<p class="roadmap-empty">Unable to load roadmap data. Please try again later.</p>';
    }
  }
}

function refreshData(data) {
  state.data = data;
  state.items = Array.isArray(data?.items) ? data.items.slice() : [];
  state.weights = data?.surveyWeights || {};
  state.phaseOrder = {};

  if (Array.isArray(data?.phases)) {
    data.phases.forEach((phase, index) => {
      if (phase?.id) {
        state.phaseOrder[phase.id] = index;
      }
    });
  }

  if (elements.updated) {
    const updatedText = data?.updated ? new Date(data.updated).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : 'Last updated: —';
    elements.updated.textContent = `Last updated: ${updatedText}`;
  }

  buildFilterGroups();
  applyFilters();
}

function buildFilterGroups() {
  const statuses = new Set();
  const categories = new Set();

  state.items.forEach(item => {
    if (item.status) statuses.add(item.status);
    if (Array.isArray(item.category)) {
      item.category.forEach(cat => categories.add(cat));
    }
  });

  renderFilterChips(elements.statusFilters, [...statuses].sort(), state.filters.status);
  renderFilterChips(elements.categoryFilters, [...categories].sort(), state.filters.category);

  const phaseButtons = Array.isArray(state.data?.phases)
    ? state.data.phases.map(phase => phase.id)
    : [...new Set(state.items.map(item => item.phase))];
  renderFilterChips(elements.phaseFilters, phaseButtons, state.filters.phase);
}

function renderFilterChips(container, values, store) {
  if (!container) return;
  container.innerHTML = '';
  values.forEach(value => {
    if (!value) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = value;
    button.dataset.value = value;
    button.setAttribute('aria-pressed', store.has(value) ? 'true' : 'false');
    button.addEventListener('click', () => {
      if (store.has(value)) {
        store.delete(value);
        button.setAttribute('aria-pressed', 'false');
      } else {
        store.add(value);
        button.setAttribute('aria-pressed', 'true');
      }
      applyFilters();
    });
    container.appendChild(button);
  });
}

function applyFilters() {
  const searchTerm = (elements.search?.value || '').trim().toLowerCase();
  const sortValue = elements.sort?.value || 'relevance';

  const filtered = state.items.filter(item => {
    if (!item) return false;
    const matchesSearch = !searchTerm || `${item.title} ${item.description} ${item.id}`.toLowerCase().includes(searchTerm);
    const matchesStatus = state.filters.status.size === 0 || state.filters.status.has(item.status);
    const matchesCategory = state.filters.category.size === 0 || (Array.isArray(item.category) && item.category.some(cat => state.filters.category.has(cat)));
    const matchesPhase = state.filters.phase.size === 0 || state.filters.phase.has(item.phase);
    return matchesSearch && matchesStatus && matchesCategory && matchesPhase;
  });

  let sorted = filtered.slice();
  switch (sortValue) {
    case 'progress':
      sorted.sort((a, b) => (b.progress ?? 0) - (a.progress ?? 0));
      break;
    case 'phase':
      sorted.sort((a, b) => (state.phaseOrder[a.phase] ?? 999) - (state.phaseOrder[b.phase] ?? 999));
      break;
    case 'title':
      sorted.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
      break;
    case 'relevance':
    default:
      sorted.sort((a, b) => computeRelevance(b) - computeRelevance(a));
      break;
  }

  state.filtered = sorted;
  renderSummary(sorted);
  renderPhases(sorted);
  renderItems(sorted);
  renderJsonLd(sorted);
}

function computeRelevance(item) {
  if (!item) return 0;
  const weightBase = 1;
  const surveyWeight = Array.isArray(item.surveyTags) && item.surveyTags.length
    ? item.surveyTags.reduce((sum, tag) => sum + (state.weights?.[tag] || 1), 0) / item.surveyTags.length
    : 1;
  const statusWeight = statusPriority.get(item.status) || 1;
  const progressWeight = (typeof item.progress === 'number' ? item.progress : 0) / 100;
  const dependencyWeight = Array.isArray(item.dependencies) ? item.dependencies.length * 0.05 : 0;
  const shippedBonus = item.shipped ? 0.5 : 0;

  return weightBase + surveyWeight + statusWeight + progressWeight + dependencyWeight + shippedBonus;
}

function renderSummary(items) {
  if (!elements.summary) return;
  const total = items.length;
  const shipped = items.filter(item => item.shipped).length;
  const active = items.filter(item => ['In Progress', 'In Review', 'In Development', 'Prototype'].includes(item.status)).length;
  const avgProgress = total ? Math.round(items.reduce((sum, item) => sum + (item.progress || 0), 0) / total) : 0;

  const cards = [
    { title: 'Total initiatives', value: total },
    { title: 'Actively building', value: active },
    { title: 'Shipped milestones', value: shipped },
    { title: 'Average progress', value: `${avgProgress}%` }
  ];

  elements.summary.innerHTML = cards.map(card => `
    <article class="summary-card">
      <h3>${card.title}</h3>
      <strong>${card.value}</strong>
    </article>
  `).join('');
}

function renderPhases(items) {
  if (!elements.phases) return;
  const phaseMap = new Map();

  items.forEach(item => {
    if (!phaseMap.has(item.phase)) {
      phaseMap.set(item.phase, []);
    }
    phaseMap.get(item.phase).push(item);
  });

  const cards = (state.data?.phases || []).map(phase => {
    const phaseItems = phaseMap.get(phase.id) || [];
    const avg = phaseItems.length
      ? Math.round(phaseItems.reduce((sum, item) => sum + (item.progress || 0), 0) / phaseItems.length)
      : 0;
    const shippedCount = phaseItems.filter(item => item.shipped).length;
    return `
      <article class="phase-card">
        <header>
          <div>
            <h2>Phase ${phase.id}: ${phase.name}</h2>
            <p>${phase.description || ''}</p>
          </div>
          <div class="phase-meta">
            <span>${phaseItems.length} initiative${phaseItems.length === 1 ? '' : 's'}</span>
            <span>${shippedCount} shipped</span>
            <span>Avg. progress ${avg}%</span>
          </div>
        </header>
        <div class="phase-progress" aria-hidden="true">
          <span style="width:${avg}%"></span>
        </div>
      </article>
    `;
  });

  elements.phases.innerHTML = cards.join('');
}

function renderItems(items) {
  if (!elements.results) return;
  if (!items.length) {
    elements.results.innerHTML = '';
    if (elements.empty) elements.empty.hidden = false;
    return;
  }

  const idToTitle = new Map(state.items.map(item => [item.id, item.title]));

  elements.results.innerHTML = items.map(item => {
    const shippedMark = item.shipped ? ' *' : '';
    const categories = Array.isArray(item.category) ? item.category.map(cat => `<span class="tag" aria-label="Category">${cat}</span>`).join('') : '';
    const surveyTags = Array.isArray(item.surveyTags) ? item.surveyTags.map(tag => `<span class="tag" aria-label="Survey tag">#${tag}</span>`).join('') : '';
    const dependencies = Array.isArray(item.dependencies) && item.dependencies.length
      ? `<p class="dependency-list">Dependencies: ${item.dependencies.map(dep => idToTitle.get(dep) || dep).join(', ')}</p>`
      : '';

    return `
      <article class="roadmap-card" role="listitem" data-phase="${item.phase}">
        <div class="badge" aria-label="Phase ${item.phase}">Phase ${item.phase}</div>
        <h3>${item.title}${shippedMark}</h3>
        <div class="card-meta">
          <span>${item.status || 'Unknown status'}</span>
          <span>${item.progress ?? 0}% complete</span>
        </div>
        <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${item.progress ?? 0}">
          <span style="width:${item.progress ?? 0}%"></span>
        </div>
        <p>${item.description || ''}</p>
        <div class="tags" aria-label="Categories and tags">
          ${categories}
          ${surveyTags}
        </div>
        ${dependencies}
      </article>
    `;
  }).join('');

  if (elements.empty) elements.empty.hidden = true;
}

function renderJsonLd(items) {
  if (!elements.ldJson) return;
  const descriptionMeta = document.querySelector('meta[name="description"]');
  const ld = {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    name: document.title,
    description: descriptionMeta ? descriptionMeta.getAttribute('content') : 'Roadmap overview.',
    itemListElement: items.map((item, index) => ({
      '@type': 'CreativeWork',
      position: index + 1,
      name: item.title + (item.shipped ? ' *' : ''),
      description: item.description,
      identifier: item.id,
      isPartOf: `Phase ${item.phase}`,
      keywords: [...(item.category || []), ...(item.surveyTags || [])].join(', '),
      interactionStatistic: {
        '@type': 'InteractionCounter',
        interactionType: 'https://schema.org/ReadAction',
        userInteractionCount: Math.round(computeRelevance(item) * 100)
      }
    }))
  };

  elements.ldJson.textContent = JSON.stringify(ld, null, 2);
}

function clearFilters() {
  state.filters.status.clear();
  state.filters.category.clear();
  state.filters.phase.clear();
  if (elements.search) elements.search.value = '';
  if (elements.sort) elements.sort.value = 'relevance';
  buildFilterGroups();
  applyFilters();
}

function setupFilterListeners() {
  elements.search?.addEventListener('input', debounce(applyFilters, 150));
  elements.sort?.addEventListener('change', applyFilters);
  elements.clearFilters?.addEventListener('click', clearFilters);
}

function setupExport() {
  elements.downloadJson?.addEventListener('click', () => {
    if (!state.data) return;
    const blob = new Blob([JSON.stringify(state.data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'roadmap.json';
    link.click();
    URL.revokeObjectURL(url);
  });
}

function setupModal() {
  if (!elements.editJson) return;

  const close = () => {
    elements.modal?.setAttribute('hidden', '');
    document.body.style.removeProperty('overflow');
  };

  elements.editJson.addEventListener('click', () => {
    if (!state.data || !elements.modalTextarea) return;
    elements.modalTextarea.value = JSON.stringify(state.data, null, 2);
    elements.modal?.removeAttribute('hidden');
    document.body.style.setProperty('overflow', 'hidden');
  });

  elements.modalCancel?.addEventListener('click', close);
  elements.modalClose?.addEventListener('click', close);

  elements.modal?.addEventListener('click', event => {
    if (event.target === elements.modal) {
      close();
    }
  });

  elements.modalSave?.addEventListener('click', async () => {
    if (!elements.modalTextarea) return;
    try {
      const parsed = JSON.parse(elements.modalTextarea.value);
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const payload = { ...parsed, csrfToken: token };
      const response = await fetch('/Site/roadmap/save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || !result.ok) {
        throw new Error(result?.message || 'Unable to save roadmap');
      }
      close();
      refreshData(parsed);
      alert('Roadmap saved successfully.');
    } catch (error) {
      console.error(error);
      alert(`Save failed: ${error.message}`);
    }
  });
}

function debounce(fn, delay = 200) {
  let timer;
  return function debounced(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

(function init() {
  initTheme();
  setupFilterListeners();
  setupExport();
  setupModal();
  loadRoadmap();
})();
