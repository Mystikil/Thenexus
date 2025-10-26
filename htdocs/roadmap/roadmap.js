const state = {
  search: '',
  statuses: new Set(),
  categories: new Set(),
  phases: new Set(),
  shippedOnly: false,
  sortBy: 'relevance'
};

const tagWeights = {
  Nostalgia: 100,
  RPG: 90,
  PvP: 80,
  'New Mechanics': 70,
  PvE: 60,
  'New Maps': 50,
  Social: 40,
  Visual: 30,
  Economy: 20,
  Grind: 10
};

const phasesOrder = [];
let roadmapData = null;

const elements = {
  search: document.getElementById('roadmap-search'),
  shippedToggle: document.getElementById('shipped-toggle'),
  sortSelect: document.getElementById('sort-select'),
  resetButton: document.getElementById('reset-filters'),
  statusFilters: document.querySelector('#status-filters div'),
  categoryFilters: document.querySelector('#category-filters div'),
  phaseFilters: document.querySelector('#phase-filters div'),
  sections: document.getElementById('roadmap-sections'),
  emptyState: document.getElementById('empty-state'),
  activeFilters: document.getElementById('active-filters'),
  updated: document.getElementById('meta-updated'),
  themeToggle: document.getElementById('theme-toggle'),
  editButton: document.getElementById('edit-json'),
  modal: document.getElementById('json-modal'),
  modalClose: document.getElementById('close-modal'),
  modalCancel: document.getElementById('cancel-json'),
  modalValidate: document.getElementById('validate-json'),
  modalSave: document.getElementById('save-json'),
  modalFeedback: document.getElementById('json-feedback'),
  modalTextarea: document.getElementById('json-editor')
};

const filterContainers = {
  statuses: elements.statusFilters,
  categories: elements.categoryFilters,
  phases: elements.phaseFilters
};

const THEME_STORAGE_KEY = 'nx-roadmap-theme';

initialiseTheme();

function initialiseTheme() {
  const saved = localStorage.getItem(THEME_STORAGE_KEY) || 'dark';
  applyTheme(saved);
  elements.themeToggle?.setAttribute('aria-pressed', saved === 'light' ? 'true' : 'false');
  updateThemeToggleLabel(saved);
}

function applyTheme(mode) {
  const root = document.documentElement;
  root.classList.remove('light', 'dark');
  if (mode === 'light') {
    root.classList.add('light');
  } else {
    root.classList.add('dark');
  }
  localStorage.setItem(THEME_STORAGE_KEY, mode);
}

function updateThemeToggleLabel(mode) {
  if (!elements.themeToggle) return;
  elements.themeToggle.dataset.mode = mode;
  elements.themeToggle.setAttribute('aria-label', mode === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
}

function toggleTheme() {
  const root = document.documentElement;
  const isLight = root.classList.contains('light');
  const next = isLight ? 'dark' : 'light';
  applyTheme(next);
  updateThemeToggleLabel(next);
  elements.themeToggle?.setAttribute('aria-pressed', next === 'light' ? 'true' : 'false');
}

function parseQueryParams(data) {
  const params = new URLSearchParams(window.location.search);
  const result = {
    search: params.get('q') ? decodeURIComponent(params.get('q')) : '',
    shippedOnly: params.get('shipped') === 'true',
    sortBy: params.get('sort') || 'relevance',
    statuses: new Set(),
    categories: new Set(),
    phases: new Set()
  };

  const parseMulti = (key, allowed) => {
    const raw = params.get(key);
    if (!raw) return;
    raw.split(',').forEach((value) => {
      const decoded = decodeURIComponent(value.trim());
      if (allowed.includes(decoded)) {
        result[key === 'status' ? 'statuses' : key === 'category' ? 'categories' : 'phases'].add(decoded);
      }
    });
  };

  parseMulti('status', data.filters.statuses);
  parseMulti('category', data.filters.categories);
  parseMulti('phase', data.filters.phases);

  if (!['relevance', 'progress', 'title'].includes(result.sortBy)) {
    result.sortBy = 'relevance';
  }

  return result;
}

function syncQueryParams() {
  const params = new URLSearchParams();
  if (state.search.trim()) {
    params.set('q', state.search.trim());
  }
  if (state.statuses.size) {
    params.set('status', Array.from(state.statuses).map(encodeURIComponent).join(','));
  }
  if (state.categories.size) {
    params.set('category', Array.from(state.categories).map(encodeURIComponent).join(','));
  }
  if (state.phases.size) {
    params.set('phase', Array.from(state.phases).map(encodeURIComponent).join(','));
  }
  if (state.shippedOnly) {
    params.set('shipped', 'true');
  }
  if (state.sortBy !== 'relevance') {
    params.set('sort', state.sortBy);
  }

  const query = params.toString();
  const url = `${window.location.pathname}${query ? `?${query}` : ''}`;
  window.history.replaceState(null, '', url);
}

function createFilterChip(group, value) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'roadmap-chip roadmap-chip-default card-focus transition';
  button.textContent = value;
  button.dataset.value = value;
  button.dataset.group = group;
  button.setAttribute('role', 'checkbox');
  button.setAttribute('aria-checked', 'false');
  button.addEventListener('click', () => {
    toggleFilterSelection(group, value, button);
  });
  button.addEventListener('keyup', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      toggleFilterSelection(group, value, button);
    }
  });
  return button;
}

function setChipActive(button, active) {
  button.setAttribute('aria-checked', active ? 'true' : 'false');
  button.classList.toggle('bg-indigo-500/80', active);
  button.classList.toggle('text-white', active);
  button.classList.toggle('border-indigo-400', active);
  button.classList.toggle('shadow-lg', active);
}

function renderFilterGroup(group, values) {
  const container = filterContainers[group];
  if (!container) return;
  container.innerHTML = '';
  values.forEach((value) => {
    const chip = createFilterChip(group, value);
    const active = state[group].has(value);
    setChipActive(chip, active);
    container.appendChild(chip);
  });
}

function toggleFilterSelection(group, value, button) {
  const collection = state[group];
  if (collection.has(value)) {
    collection.delete(value);
    setChipActive(button, false);
  } else {
    collection.add(value);
    setChipActive(button, true);
  }
  applyFilters();
}

function getDisplayTitle(item) {
  return item.shipped ? `${item.title} *` : item.title;
}

function matchesSearch(item, query) {
  if (!query) return true;
  const target = `${item.title} ${item.description}`.toLowerCase();
  return target.includes(query.toLowerCase());
}

function calculateRelevance(item) {
  const tags = Array.isArray(item.surveyTags) ? item.surveyTags : [];
  let score = 0;
  tags.forEach((tag) => {
    if (tagWeights[tag]) {
      score += tagWeights[tag];
    }
  });
  if (item.shipped) {
    score += 25;
  }
  score += Math.min(20, item.progress / 5);
  return score;
}

function sortItems(items) {
  const list = [...items];
  switch (state.sortBy) {
    case 'progress':
      return list.sort((a, b) => b.progress - a.progress || b.shipped - a.shipped || a.title.localeCompare(b.title));
    case 'title':
      return list.sort((a, b) => a.title.localeCompare(b.title));
    default:
      return list.sort((a, b) => {
        const diff = calculateRelevance(b) - calculateRelevance(a);
        if (diff !== 0) return diff;
        if (b.progress !== a.progress) return b.progress - a.progress;
        return a.title.localeCompare(b.title);
      });
  }
}

function formatDate(dateString) {
  if (!dateString) return '—';
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) {
    return dateString;
  }
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
}

function createStatusBadge(status) {
  const badge = document.createElement('span');
  badge.className = 'status-badge status-' + status.toLowerCase().replace(/\s+/g, '-');
  badge.textContent = status;
  return badge;
}

function createCategoryChips(categories) {
  const wrapper = document.createElement('div');
  wrapper.className = 'flex flex-wrap gap-2';
  categories.forEach((category) => {
    const chip = document.createElement('span');
    chip.className = 'roadmap-chip roadmap-chip-default text-xs';
    chip.textContent = category;
    wrapper.appendChild(chip);
  });
  return wrapper;
}

function createSurveyBadges(tags) {
  const wrapper = document.createElement('div');
  wrapper.className = 'flex flex-wrap gap-1';
  tags.forEach((tag) => {
    const badge = document.createElement('span');
    badge.className = 'roadmap-badge';
    badge.textContent = tag;
    wrapper.appendChild(badge);
  });
  return wrapper;
}

function createProgressBar(item) {
  const container = document.createElement('div');
  container.className = 'space-y-2';
  const label = document.createElement('div');
  label.className = 'flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
  label.innerHTML = `<span>Progress</span><span>${item.progress}%</span>`;
  const track = document.createElement('div');
  track.className = 'progress-track';
  const fill = document.createElement('div');
  fill.className = 'progress-fill';
  fill.style.width = `${Math.max(0, Math.min(100, item.progress))}%`;
  fill.setAttribute('role', 'presentation');
  track.appendChild(fill);
  const accessible = document.createElement('div');
  accessible.className = 'sr-only';
  accessible.textContent = `Progress ${item.progress} percent`;
  const progressWrapper = document.createElement('div');
  progressWrapper.setAttribute('role', 'progressbar');
  progressWrapper.setAttribute('aria-valuenow', String(item.progress));
  progressWrapper.setAttribute('aria-valuemin', '0');
  progressWrapper.setAttribute('aria-valuemax', '100');
  progressWrapper.appendChild(track);
  progressWrapper.appendChild(accessible);
  container.appendChild(label);
  container.appendChild(progressWrapper);
  return container;
}

function renderDependencies(item) {
  if (!Array.isArray(item.dependencies) || !item.dependencies.length) {
    return null;
  }
  const list = document.createElement('ul');
  list.className = 'ml-4 list-disc space-y-1 text-sm text-slate-600 dark:text-slate-300';
  item.dependencies.forEach((dep) => {
    const dependencyItem = roadmapData.items.find((candidate) => candidate.id === dep);
    const li = document.createElement('li');
    const anchor = document.createElement('a');
    anchor.href = `#${dep}`;
    anchor.className = 'text-indigo-300 hover:text-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 rounded';
    anchor.textContent = dependencyItem ? getDisplayTitle(dependencyItem) : dep;
    anchor.addEventListener('click', () => {
      setTimeout(() => {
        const target = document.getElementById(dep);
        target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 0);
    });
    li.appendChild(anchor);
    list.appendChild(li);
  });
  const container = document.createElement('div');
  container.className = 'space-y-2';
  const title = document.createElement('p');
  title.className = 'text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
  title.textContent = 'Dependencies';
  container.appendChild(title);
  container.appendChild(list);
  return container;
}

function renderCard(item) {
  const article = document.createElement('article');
  article.id = item.id;
  article.className = 'flex h-full flex-col justify-between rounded-3xl border border-slate-200 bg-white p-6 shadow-glow transition-colors hover:border-indigo-400/60 focus-within:border-indigo-400/60 dark:border-slate-800/60 dark:bg-slate-900/70';
  article.setAttribute('tabindex', '-1');

  const header = document.createElement('div');
  header.className = 'space-y-3';
  const titleWrapper = document.createElement('div');
  titleWrapper.className = 'space-y-2';

  const title = document.createElement('h3');
  title.className = 'text-xl font-semibold text-slate-900 dark:text-white';
  title.textContent = getDisplayTitle(item);
  titleWrapper.appendChild(title);

  if (Array.isArray(item.surveyTags) && item.surveyTags.length) {
    const survey = createSurveyBadges(item.surveyTags);
    titleWrapper.appendChild(survey);
  }

  header.appendChild(titleWrapper);

  const statusRow = document.createElement('div');
  statusRow.className = 'flex flex-wrap items-center gap-3';
  const badge = createStatusBadge(item.status);
  statusRow.appendChild(badge);
  const phaseChip = document.createElement('span');
  phaseChip.className = 'roadmap-chip roadmap-chip-default text-xs';
  phaseChip.textContent = `Phase ${item.phase}`;
  statusRow.appendChild(phaseChip);
  header.appendChild(statusRow);

  const body = document.createElement('div');
  body.className = 'flex flex-1 flex-col gap-4 pt-4 text-sm text-slate-700 dark:text-slate-200';
  const description = document.createElement('p');
  description.className = 'text-base text-slate-700 dark:text-slate-200';
  description.textContent = item.description;
  body.appendChild(description);

  if (Array.isArray(item.category) && item.category.length) {
    const categories = createCategoryChips(item.category);
    body.appendChild(categories);
  }

  const progress = createProgressBar(item);
  body.appendChild(progress);

  const dependencies = renderDependencies(item);
  if (dependencies) {
    body.appendChild(dependencies);
  }

  const detailsWrapper = document.createElement('div');
  detailsWrapper.className = 'mt-4 border-t border-slate-200 pt-4 dark:border-slate-800/60';
  const detailsButton = document.createElement('button');
  detailsButton.type = 'button';
  detailsButton.className = 'card-focus inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-300 dark:hover:text-indigo-200';
  detailsButton.setAttribute('aria-expanded', 'false');
  detailsButton.textContent = 'Details';

  const chevron = document.createElement('span');
  chevron.textContent = '▾';
  chevron.setAttribute('aria-hidden', 'true');
  detailsButton.appendChild(chevron);

  const detailsContent = document.createElement('div');
  detailsContent.className = 'mt-3 space-y-3 text-sm text-slate-600 dark:text-slate-300 hidden';

  const dl = document.createElement('dl');
  dl.className = 'space-y-2';
  const entries = [
    { term: 'Roadmap ID', value: item.id },
    { term: 'Phase narrative', value: roadmapData.phases[item.phase] || `Phase ${item.phase}` },
    {
      term: 'Survey focus',
      value: Array.isArray(item.surveyTags) && item.surveyTags.length
        ? item.surveyTags.join(', ')
        : '—'
    },
    {
      term: 'Share link',
      value: `${window.location.origin ? `${window.location.origin}/roadmap/` : '/roadmap/'}?q=${encodeURIComponent(item.title)}`
    }
  ];
  entries.forEach(({ term, value }) => {
    const dt = document.createElement('dt');
    dt.className = 'text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
    dt.textContent = term;
    const dd = document.createElement('dd');
    dd.className = 'text-sm text-slate-700 dark:text-slate-200';
    if (term === 'Share link') {
      const link = document.createElement('a');
      link.href = value;
      link.className = 'text-indigo-600 hover:text-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 rounded dark:text-indigo-300 dark:hover:text-indigo-200';
      link.textContent = value;
      dd.appendChild(link);
    } else {
      dd.textContent = value;
    }
    dl.appendChild(dt);
    dl.appendChild(dd);
  });
  detailsContent.appendChild(dl);

  detailsButton.addEventListener('click', () => {
    const expanded = detailsButton.getAttribute('aria-expanded') === 'true';
    detailsButton.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    detailsContent.classList.toggle('hidden', expanded);
    chevron.textContent = expanded ? '▾' : '▴';
  });

  detailsWrapper.appendChild(detailsButton);
  detailsWrapper.appendChild(detailsContent);

  article.appendChild(header);
  article.appendChild(body);
  article.appendChild(detailsWrapper);

  return article;
}

function updateActiveFiltersSummary() {
  const segments = [];
  if (state.search.trim()) {
    segments.push(`Search: “${state.search.trim()}”`);
  }
  if (state.statuses.size) {
    segments.push(`Status: ${Array.from(state.statuses).join(', ')}`);
  }
  if (state.categories.size) {
    segments.push(`Category: ${Array.from(state.categories).join(', ')}`);
  }
  if (state.phases.size) {
    segments.push(`Phase: ${Array.from(state.phases).join(', ')}`);
  }
  if (state.shippedOnly) {
    segments.push('Showing only shipped items');
  }

  if (!segments.length) {
    elements.activeFilters?.classList.add('hidden');
    elements.activeFilters.textContent = '';
    return;
  }
  elements.activeFilters.classList.remove('hidden');
  elements.activeFilters.textContent = segments.join(' • ');
}

function applyFilters() {
  if (!roadmapData) return;
  const filtered = roadmapData.items.filter((item) => {
    if (state.shippedOnly && !item.shipped) return false;
    if (state.statuses.size && !state.statuses.has(item.status)) return false;
    if (state.categories.size && !item.category.some((category) => state.categories.has(category))) return false;
    if (state.phases.size && !state.phases.has(item.phase)) return false;
    if (!matchesSearch(item, state.search)) return false;
    return true;
  });

  const sorted = sortItems(filtered);
  const grouped = new Map();
  phasesOrder.forEach((phase) => grouped.set(phase, []));
  sorted.forEach((item) => {
    if (!grouped.has(item.phase)) {
      grouped.set(item.phase, []);
    }
    grouped.get(item.phase).push(item);
  });

  elements.sections.innerHTML = '';

  let hasAny = false;
  grouped.forEach((items, phase) => {
    const section = document.createElement('section');
    section.className = 'space-y-4';
    section.setAttribute('aria-labelledby', `phase-${phase}`);
    const header = document.createElement('div');
    header.className = 'flex flex-col gap-1';
    const heading = document.createElement('h2');
    heading.id = `phase-${phase}`;
    heading.className = 'text-2xl font-bold text-slate-900 dark:text-white';
    heading.textContent = `Phase ${phase} (${items.length})`;
    const description = document.createElement('p');
    description.className = 'text-sm text-slate-600 dark:text-slate-300';
    description.textContent = roadmapData.phases[phase] || '';
    header.appendChild(heading);
    header.appendChild(description);
    section.appendChild(header);
    if (items.length) {
      hasAny = true;
      const grid = document.createElement('div');
      grid.className = 'grid gap-6 md:grid-cols-2 xl:grid-cols-3';
      items.forEach((item) => {
        const card = renderCard(item);
        grid.appendChild(card);
      });
      section.appendChild(grid);
    } else {
      const empty = document.createElement('p');
      empty.className = 'rounded-2xl border border-dashed border-slate-300 bg-white/90 p-4 text-sm text-slate-600 dark:border-slate-700/60 dark:bg-slate-900/70 dark:text-slate-400';
      empty.textContent = 'No items in this phase match the active filters yet.';
      section.appendChild(empty);
    }
    elements.sections.appendChild(section);
  });

  if (!hasAny) {
    elements.emptyState.classList.remove('hidden');
  } else {
    elements.emptyState.classList.add('hidden');
  }

  updateActiveFiltersSummary();
  syncQueryParams();
}

function populateFiltersFromState() {
  ['statuses', 'categories', 'phases'].forEach((group) => {
    const container = filterContainers[group];
    if (!container) return;
    Array.from(container.children).forEach((child) => {
      const button = child;
      const active = state[group].has(button.dataset.value);
      setChipActive(button, active);
    });
  });
  elements.search.value = state.search;
  elements.shippedToggle.checked = state.shippedOnly;
  elements.sortSelect.value = state.sortBy;
}

function attachEventListeners() {
  elements.search?.addEventListener('input', (event) => {
    state.search = event.target.value;
    applyFilters();
  });
  elements.shippedToggle?.addEventListener('change', (event) => {
    state.shippedOnly = event.target.checked;
    applyFilters();
  });
  elements.sortSelect?.addEventListener('change', (event) => {
    state.sortBy = event.target.value;
    applyFilters();
  });
  elements.resetButton?.addEventListener('click', () => {
    state.search = '';
    state.statuses.clear();
    state.categories.clear();
    state.phases.clear();
    state.shippedOnly = false;
    state.sortBy = 'relevance';
    populateFiltersFromState();
    applyFilters();
  });
  elements.themeToggle?.addEventListener('click', toggleTheme);
  elements.editButton?.addEventListener('click', openModal);
  elements.modalClose?.addEventListener('click', closeModal);
  elements.modalCancel?.addEventListener('click', closeModal);
  elements.modalValidate?.addEventListener('click', () => {
    const validation = validateJson(elements.modalTextarea.value);
    displayValidationResult(validation);
  });
  elements.modalSave?.addEventListener('click', handleSaveJson);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && elements.modal.classList.contains('active')) {
      closeModal();
    }
  });

  elements.modal.addEventListener('click', (event) => {
    if (event.target === elements.modal) {
      closeModal();
    }
  });
}

function openModal() {
  if (!elements.modal) return;
  elements.modalTextarea.value = JSON.stringify(roadmapData, null, 2);
  elements.modalFeedback.textContent = '';
  elements.modalFeedback.className = 'text-sm font-semibold';
  elements.modal.classList.add('active');
  elements.modal.setAttribute('aria-hidden', 'false');
  setTimeout(() => {
    elements.modalTextarea.focus();
  }, 50);
}

function closeModal() {
  if (!elements.modal) return;
  elements.modal.classList.remove('active');
  elements.modal.setAttribute('aria-hidden', 'true');
}

function validateJson(raw) {
  try {
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') {
      return { valid: false, message: 'JSON root must be an object.' };
    }
    if (!Array.isArray(parsed.items)) {
      return { valid: false, message: 'Missing `items` array in JSON.' };
    }
    const errors = [];
    parsed.items.forEach((item, index) => {
      const prefix = `Item ${index + 1} (${item?.id || 'no id'})`;
      if (!item.id) errors.push(`${prefix}: missing id`);
      if (!item.title) errors.push(`${prefix}: missing title`);
      if (!Array.isArray(item.category) || !item.category.length) errors.push(`${prefix}: category must be a non-empty array`);
      if (!item.status) errors.push(`${prefix}: missing status`);
      if (typeof item.progress !== 'number' || item.progress < 0 || item.progress > 100) {
        errors.push(`${prefix}: progress must be a number between 0 and 100`);
      }
      if (!item.phase) errors.push(`${prefix}: missing phase`);
    });
    if (errors.length) {
      return { valid: false, message: errors.join('\n') };
    }
    return { valid: true, message: 'JSON looks good! Remember to keep backups before saving.' };
  } catch (error) {
    return { valid: false, message: error.message };
  }
}

function displayValidationResult(result) {
  if (!elements.modalFeedback) return;
  elements.modalFeedback.textContent = result.message;
  if (result.valid) {
    elements.modalFeedback.className = 'text-sm font-semibold text-emerald-400';
  } else {
    elements.modalFeedback.className = 'text-sm font-semibold text-rose-400 whitespace-pre-line';
  }
}

async function handleSaveJson() {
  const raw = elements.modalTextarea.value;
  const validation = validateJson(raw);
  displayValidationResult(validation);
  if (!validation.valid) {
    return;
  }
  try {
    const response = await fetch('/roadmap/save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: raw
    });
    if (!response.ok) {
      throw new Error('Server rejected save request');
    }
    const payload = await response.json().catch(() => ({ message: 'Saved.' }));
    elements.modalFeedback.textContent = payload.message || 'Saved successfully.';
    elements.modalFeedback.className = 'text-sm font-semibold text-emerald-400';
  } catch (error) {
    const blob = new Blob([raw], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'roadmap.json';
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    elements.modalFeedback.textContent = 'Server save unavailable. Downloaded updated roadmap.json instead.';
    elements.modalFeedback.className = 'text-sm font-semibold text-amber-300';
  }
}

function insertMetaTags(data) {
  document.title = data.meta?.title || 'Nexus Online — Vision 2025 Roadmap';
  const description = 'Follow the Nexus Online Vision 2025 roadmap, inspired by the community survey and grouped by development phases.';
  const ogImage = 'https://thenexus.online/static/roadmap-og.png';
  const siteName = 'Nexus Online';
  const ensureMeta = ({ name, property, content }) => {
    const selector = name ? `meta[name="${name}"]` : `meta[property="${property}"]`;
    let tag = document.head.querySelector(selector);
    if (!tag) {
      tag = document.createElement('meta');
      if (name) {
        tag.name = name;
      }
      if (property) {
        tag.setAttribute('property', property);
      }
      document.head.appendChild(tag);
    }
    tag.setAttribute('content', content);
  };
  ensureMeta({ name: 'description', content: description });
  ensureMeta({ property: 'og:title', content: data.meta?.title || document.title });
  ensureMeta({ property: 'og:description', content: description });
  ensureMeta({ property: 'og:image', content: ogImage });
  ensureMeta({ property: 'og:site_name', content: siteName });
  ensureMeta({ property: 'og:url', content: 'https://thenexus.online/roadmap/' });
  ensureMeta({ name: 'twitter:card', content: 'summary_large_image' });
  ensureMeta({ name: 'twitter:title', content: data.meta?.title || document.title });
  ensureMeta({ name: 'twitter:description', content: description });
  ensureMeta({ name: 'twitter:image', content: ogImage });
}

function buildJsonLd(data) {
  const hasPart = [];
  phasesOrder.forEach((phase) => {
    const phaseItems = data.items.filter((item) => item.phase === phase);
    phaseItems.forEach((item, index) => {
      hasPart.push({
        '@type': 'CreativeWork',
        'name': getDisplayTitle(item),
        'description': item.description,
        'position': `${phase}-${index + 1}`,
        'url': `https://thenexus.online/roadmap/#${item.id}`
      });
    });
  });
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'CreativeWorkSeries',
    'name': data.meta?.title || 'Nexus Online — Vision 2025 Roadmap',
    'description': 'A phased roadmap highlighting the Nexus Online Vision 2025 initiative.',
    'url': 'https://thenexus.online/roadmap/',
    'hasPart': hasPart
  };
  const script = document.createElement('script');
  script.type = 'application/ld+json';
  script.textContent = JSON.stringify(jsonLd, null, 2);
  document.head.appendChild(script);
}

async function initialise() {
  try {
    const response = await fetch('/roadmap/roadmap.json', { cache: 'no-store' });
    if (!response.ok) {
      throw new Error('Unable to load roadmap data');
    }
    roadmapData = await response.json();
    phasesOrder.splice(0, phasesOrder.length, ...(roadmapData.filters?.phases || ['I', 'II', 'III', 'IV', 'V']));
    if (elements.updated && roadmapData.meta?.updated) {
      elements.updated.textContent = formatDate(roadmapData.meta.updated);
    }
    insertMetaTags(roadmapData);
    buildJsonLd(roadmapData);

    const queryState = parseQueryParams(roadmapData);
    state.search = queryState.search;
    state.shippedOnly = queryState.shippedOnly;
    state.sortBy = queryState.sortBy;
    state.statuses = queryState.statuses;
    state.categories = queryState.categories;
    state.phases = queryState.phases;

    renderFilterGroup('statuses', roadmapData.filters?.statuses || []);
    renderFilterGroup('categories', roadmapData.filters?.categories || []);
    renderFilterGroup('phases', roadmapData.filters?.phases || []);

    populateFiltersFromState();
    attachEventListeners();
    applyFilters();
  } catch (error) {
    console.error(error);
    elements.sections.innerHTML = '<p class="rounded-3xl border border-rose-400 bg-rose-100/80 p-6 text-center text-sm font-semibold text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">Failed to load roadmap data. Please try again later.</p>';
  }
}

initialise();
