const app = document.getElementById('roadmap-app');

if (app) {
    const elements = {
        search: document.getElementById('roadmap-search'),
        sort: document.getElementById('roadmap-sort'),
        shipped: document.getElementById('roadmap-shipped-filter'),
        categoryFilters: document.getElementById('roadmap-category-filters'),
        statusFilters: document.getElementById('roadmap-status-filters'),
        phaseFilters: document.getElementById('roadmap-phase-filters'),
        tagFilters: document.getElementById('roadmap-tag-filters'),
        lanes: document.getElementById('roadmap-lanes'),
        metrics: {
            total: document.querySelector('#metric-total .metric-value'),
            shipped: document.querySelector('#metric-shipped .metric-value'),
            progress: document.querySelector('#metric-progress .metric-value'),
            updated: document.querySelector('#metric-next-update .metric-value')
        },
        themeToggle: document.getElementById('roadmap-theme-toggle'),
        editButton: document.getElementById('edit-json'),
        modal: document.getElementById('roadmap-json-modal'),
        modalTextarea: document.getElementById('roadmap-json-editor'),
        modalClose: document.getElementById('roadmap-json-close'),
        modalCancel: document.getElementById('roadmap-json-cancel'),
        modalSave: document.getElementById('roadmap-json-save'),
        toast: document.getElementById('roadmap-toast'),
        jsonLd: document.getElementById('roadmap-jsonld')
    };

    const phaseOrder = ['I', 'II', 'III', 'IV', 'V'];
    const themeStorageKey = 'nx-roadmap-theme';

    const randomId = () => {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return `nx-roadmap-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
    };

    const state = {
        metadata: {},
        items: [],
        itemsById: new Map(),
        searchTokens: [],
        filters: {
            categories: new Set(),
            statuses: new Set(),
            phases: new Set(),
            tags: new Set(),
            shipped: 'all'
        },
        sort: 'relevance',
        rawJson: ''
    };

    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    function setTheme(mode) {
        let resolved = mode;
        if (resolved === 'auto') {
            resolved = prefersDark ? 'dark' : 'light';
        }
        if (resolved !== 'light' && resolved !== 'dark') {
            resolved = prefersDark ? 'dark' : 'light';
        }
        app.dataset.theme = resolved;
        if (elements.themeToggle) {
            elements.themeToggle.setAttribute('aria-pressed', resolved === 'dark' ? 'true' : 'false');
            elements.themeToggle.querySelector('.label').textContent = resolved === 'dark' ? 'Switch to light' : 'Switch to dark';
        }
        localStorage.setItem(themeStorageKey, resolved);
    }

    function toggleTheme() {
        const current = app.dataset.theme || (prefersDark ? 'dark' : 'light');
        setTheme(current === 'dark' ? 'light' : 'dark');
    }

    function sanitiseValue(value) {
        if (typeof value !== 'string') {
            return '';
        }
        return value.trim();
    }

    function normalisePhase(phase) {
        if (typeof phase !== 'string') {
            return 'I';
        }
        const upper = phase.toUpperCase().trim();
        return phaseOrder.includes(upper) ? upper : 'I';
    }

    function parseTokens(query) {
        if (!query || typeof query !== 'string') {
            return [];
        }
        const matches = query.match(/"[^"]+"|\S+/g);
        if (!matches) {
            return [];
        }
        return matches.map((token) => token.replace(/^"|"$/g, '').toLowerCase());
    }

    function showToast(message, variant = 'info') {
        if (!elements.toast) return;
        elements.toast.textContent = message;
        elements.toast.dataset.variant = variant;
        elements.toast.hidden = false;
        clearTimeout(elements.toast._timeout);
        elements.toast._timeout = setTimeout(() => {
            elements.toast.hidden = true;
        }, 4000);
    }

    function buildChips(container, values, filtersSet) {
        if (!container) return;
        container.innerHTML = '';
        const sortedValues = Array.from(values.entries()).sort((a, b) => b[1] - a[1]);
        for (const [value, count] of sortedValues) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'filter-chip';
            button.dataset.value = value;
            const label = document.createElement('span');
            label.className = 'chip-label';
            label.textContent = value;
            const counter = document.createElement('span');
            counter.className = 'chip-count';
            counter.textContent = String(count);
            button.append(label, counter);
            if (filtersSet.has(value)) {
                button.classList.add('active');
                button.setAttribute('aria-pressed', 'true');
            } else {
                button.setAttribute('aria-pressed', 'false');
            }
            button.addEventListener('click', () => {
                if (filtersSet.has(value)) {
                    filtersSet.delete(value);
                    button.classList.remove('active');
                    button.setAttribute('aria-pressed', 'false');
                } else {
                    filtersSet.add(value);
                    button.classList.add('active');
                    button.setAttribute('aria-pressed', 'true');
                }
                render();
            });
            container.appendChild(button);
        }
    }

    function matchesSearch(item) {
        if (!state.searchTokens.length) {
            return true;
        }
        const haystacks = [];
        haystacks.push(item.title ?? '');
        haystacks.push(item.description ?? '');
        haystacks.push(item.owner ?? '');
        haystacks.push(item.eta ?? '');
        haystacks.push(item.status ?? '');
        haystacks.push(item.phase ?? '');
        if (Array.isArray(item.category)) {
            haystacks.push(item.category.join(' '));
        }
        if (Array.isArray(item.surveyTags)) {
            const tags = item.surveyTags.map((tag) => typeof tag === 'string' ? tag : tag?.tag).filter(Boolean);
            haystacks.push(tags.join(' '));
        }
        const haystack = haystacks.join(' ').toLowerCase();
        return state.searchTokens.every((token) => haystack.includes(token));
    }

    function itemMatchesFilters(item) {
        if (state.filters.categories.size) {
            const categories = new Set((item.category ?? []).map((cat) => cat.toString()));
            let categoryMatch = false;
            state.filters.categories.forEach((value) => {
                if (categories.has(value)) {
                    categoryMatch = true;
                }
            });
            if (!categoryMatch) {
                return false;
            }
        }

        if (state.filters.statuses.size) {
            const status = (item.status ?? '').toString();
            if (!state.filters.statuses.has(status)) {
                return false;
            }
        }

        if (state.filters.phases.size) {
            const phase = normalisePhase(item.phase ?? '');
            if (!state.filters.phases.has(phase)) {
                return false;
            }
        }

        if (state.filters.tags.size) {
            const tags = new Set((item.surveyTags ?? []).map((tag) => typeof tag === 'string' ? tag : tag?.tag).filter(Boolean));
            let tagMatch = false;
            state.filters.tags.forEach((value) => {
                if (tags.has(value)) {
                    tagMatch = true;
                }
            });
            if (!tagMatch) {
                return false;
            }
        }

        if (state.filters.shipped === 'shipped' && !item.shipped) {
            return false;
        }
        if (state.filters.shipped === 'active' && item.shipped) {
            return false;
        }

        return matchesSearch(item);
    }

    function computeRelevance(item) {
        const weights = state.metadata?.surveyWeights ?? {};
        let score = 0;
        if (Array.isArray(item.surveyTags)) {
            for (const rawTag of item.surveyTags) {
                let tagName = '';
                let tagWeight = 1;
                if (typeof rawTag === 'string') {
                    tagName = rawTag;
                } else if (rawTag && typeof rawTag === 'object') {
                    tagName = rawTag.tag ?? '';
                    if (typeof rawTag.weight === 'number') {
                        tagWeight = rawTag.weight;
                    } else if (typeof rawTag.weight === 'string') {
                        const parsed = parseFloat(rawTag.weight);
                        if (!Number.isNaN(parsed)) {
                            tagWeight = parsed;
                        }
                    }
                }
                if (!tagName) continue;
                const surveyWeight = typeof weights[tagName] === 'number' ? weights[tagName] : 1;
                score += tagWeight * surveyWeight * 100;
            }
        }
        score += (Number(item.progress) || 0) * 1.5;
        if (item.shipped) {
            score += 250;
        }
        if (state.searchTokens.length) {
            const haystack = [item.title, item.description, item.owner, item.status].map((value) => (value ?? '').toString().toLowerCase()).join(' ');
            let hits = 0;
            for (const token of state.searchTokens) {
                if (haystack.includes(token)) {
                    hits += 1;
                }
            }
            score += hits * 180;
        }
        return score;
    }

    function sortItems(items) {
        const sortBy = state.sort;
        const phaseIndex = (phase) => {
            const normalised = normalisePhase(phase);
            const index = phaseOrder.indexOf(normalised);
            return index === -1 ? Number.MAX_SAFE_INTEGER : index;
        };

        const compareAlpha = (a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' });

        if (sortBy === 'progress-desc') {
            return [...items].sort((a, b) => (b.progress ?? 0) - (a.progress ?? 0) || compareAlpha(a, b));
        }
        if (sortBy === 'progress-asc') {
            return [...items].sort((a, b) => (a.progress ?? 0) - (b.progress ?? 0) || compareAlpha(a, b));
        }
        if (sortBy === 'phase') {
            return [...items].sort((a, b) => phaseIndex(a.phase) - phaseIndex(b.phase) || compareAlpha(a, b));
        }
        if (sortBy === 'alpha') {
            return [...items].sort(compareAlpha);
        }
        return [...items].sort((a, b) => computeRelevance(b) - computeRelevance(a) || compareAlpha(a, b));
    }

    function renderJsonLd(items) {
        if (!elements.jsonLd) return;
        const list = {
            '@context': 'https://schema.org',
            '@type': 'ItemList',
            name: 'Devnexus Online Roadmap',
            description: state.metadata?.description ?? 'Devnexus Online development roadmap.',
            dateModified: state.metadata?.updated ?? new Date().toISOString(),
            numberOfItems: items.length,
            itemListElement: items.map((item, index) => ({
                '@type': 'ListItem',
                position: index + 1,
                item: {
                    '@type': 'CreativeWork',
                    name: item.title,
                    description: item.description,
                    additionalType: Array.isArray(item.category) ? item.category : [],
                    identifier: item.id,
                    isAccessibleForFree: true,
                    provider: state.metadata?.owner ?? 'Devnexus Online Team'
                }
            }))
        };
        elements.jsonLd.textContent = JSON.stringify(list, null, 2);
    }

    function renderMetaTags() {
        const metaPairs = [
            ['description', state.metadata?.description ?? 'Explore the Devnexus Online roadmap.'],
            ['og:title', 'Devnexus Online Roadmap'],
            ['og:description', state.metadata?.description ?? 'Explore the Devnexus Online roadmap.'],
            ['og:type', 'website'],
            ['og:url', window.location.href],
            ['og:image', window.location.origin + '/assets/img/logo.png'],
            ['twitter:card', 'summary_large_image'],
            ['twitter:title', 'Devnexus Online Roadmap'],
            ['twitter:description', state.metadata?.description ?? 'Explore the Devnexus Online roadmap.']
        ];
        for (const [name, content] of metaPairs) {
            if (name.startsWith('og:') || name.startsWith('twitter:')) {
                let element = document.head.querySelector(`meta[property="${name}"]`);
                if (!element) {
                    element = document.createElement('meta');
                    element.setAttribute('property', name);
                    document.head.appendChild(element);
                }
                element.setAttribute('content', content);
            } else {
                let element = document.head.querySelector(`meta[name="${name}"]`);
                if (!element) {
                    element = document.createElement('meta');
                    element.setAttribute('name', name);
                    document.head.appendChild(element);
                }
                element.setAttribute('content', content);
            }
        }
        const owner = state.metadata?.owner ? ` • ${state.metadata.owner}` : '';
        document.title = `Devnexus Online Roadmap${owner}`;
    }

    function renderMetrics() {
        if (!elements.metrics.total) return;
        const total = state.items.length;
        const shipped = state.items.filter((item) => item.shipped).length;
        const avgProgress = total ? Math.round(state.items.reduce((acc, item) => acc + (Number(item.progress) || 0), 0) / total) : 0;
        elements.metrics.total.textContent = total.toString();
        elements.metrics.shipped.textContent = shipped.toString();
        elements.metrics.progress.textContent = `${avgProgress}%`;
        const updated = state.metadata?.updated ? new Date(state.metadata.updated) : null;
        elements.metrics.updated.textContent = updated && !Number.isNaN(updated.getTime())
            ? updated.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
            : '—';
    }

    function renderEmpty() {
        elements.lanes.innerHTML = '';
        const emptyState = document.createElement('p');
        emptyState.className = 'roadmap-empty';
        emptyState.textContent = 'No roadmap items match your filters yet. Adjust your filters to see more initiatives.';
        elements.lanes.appendChild(emptyState);
    }

    function render() {
        if (!Array.isArray(state.items)) return;
        const filtered = state.items.filter(itemMatchesFilters);
        if (!filtered.length) {
            renderEmpty();
            renderJsonLd([]);
            return;
        }
        const sorted = sortItems(filtered);
        const phases = state.metadata?.phases ?? {};
        elements.lanes.innerHTML = '';
        const orderedPhases = phaseOrder.filter((phaseKey) => true);
        for (const phaseKey of orderedPhases) {
            const lane = document.createElement('section');
            lane.className = 'roadmap-lane';
            lane.dataset.phase = phaseKey;
            const header = document.createElement('header');
            const title = document.createElement('h3');
            title.className = 'roadmap-lane-title';
            title.textContent = `Phase ${phaseKey}`;
            const subtitle = document.createElement('span');
            subtitle.className = 'roadmap-lane-subtitle';
            subtitle.textContent = phases[phaseKey] ?? '';
            header.appendChild(title);
            header.appendChild(subtitle);
            lane.appendChild(header);

            const itemsContainer = document.createElement('div');
            itemsContainer.className = 'roadmap-items';
            const laneItems = sorted.filter((item) => normalisePhase(item.phase) === phaseKey);
            if (!laneItems.length) {
                const empty = document.createElement('p');
                empty.className = 'roadmap-empty-lane';
                empty.textContent = 'No initiatives scheduled for this phase yet.';
                itemsContainer.appendChild(empty);
            } else {
                for (const item of laneItems) {
                    const card = renderItem(item);
                    if (card) {
                        itemsContainer.appendChild(card);
                    }
                }
            }
            lane.appendChild(itemsContainer);
            elements.lanes.appendChild(lane);
        }
        renderJsonLd(sorted);
    }

    function renderItem(item) {
        const template = document.getElementById('roadmap-item-template');
        if (!template) return null;
        const fragment = template.content.cloneNode(true);
        const card = fragment.querySelector('.roadmap-item');
        if (!card) return null;
        card.dataset.id = item.id;
        const titleEl = fragment.querySelector('.item-title');
        if (titleEl) {
            titleEl.textContent = item.title ?? '';
            if (item.shipped) {
                const flag = document.createElement('span');
                flag.className = 'shipped-flag';
                flag.textContent = '*';
                flag.title = 'Shipped milestone';
                titleEl.appendChild(flag);
            }
        }
        const statusEl = fragment.querySelector('.item-status');
        if (statusEl) {
            statusEl.textContent = item.status ?? '';
        }
        const descriptionEl = fragment.querySelector('.item-description');
        if (descriptionEl) {
            descriptionEl.textContent = item.description ?? '';
        }
        const ownerEl = fragment.querySelector('.item-owner');
        if (ownerEl) {
            ownerEl.textContent = item.owner ?? 'Unassigned';
        }
        const etaEl = fragment.querySelector('.item-eta');
        if (etaEl) {
            etaEl.textContent = item.eta ?? 'TBD';
        }
        const progressBar = fragment.querySelector('.progress-bar');
        const progressValue = fragment.querySelector('.progress-value');
        if (progressBar && progressValue) {
            const progress = Math.max(0, Math.min(100, Number(item.progress) || 0));
            progressBar.setAttribute('aria-valuenow', progress.toString());
            progressValue.style.width = `${progress}%`;
        }
        const categoriesEl = fragment.querySelector('.item-categories');
        if (categoriesEl) {
            categoriesEl.innerHTML = '';
            const categories = Array.isArray(item.category) ? item.category : [];
            if (!categories.length) {
                const li = document.createElement('li');
                li.textContent = '—';
                categoriesEl.appendChild(li);
            } else {
                for (const category of categories) {
                    const li = document.createElement('li');
                    li.textContent = category;
                    categoriesEl.appendChild(li);
                }
            }
        }
        const tagsEl = fragment.querySelector('.item-tags');
        if (tagsEl) {
            tagsEl.innerHTML = '';
            const tags = Array.isArray(item.surveyTags) ? item.surveyTags : [];
            if (!tags.length) {
                const li = document.createElement('li');
                li.textContent = '—';
                tagsEl.appendChild(li);
            } else {
                for (const rawTag of tags) {
                    const li = document.createElement('li');
                    if (typeof rawTag === 'string') {
                        li.textContent = rawTag;
                    } else if (rawTag && typeof rawTag === 'object') {
                        li.textContent = rawTag.tag ?? '';
                        if (typeof rawTag.weight === 'number') {
                            li.title = `Survey weight ${rawTag.weight}`;
                        }
                    }
                    tagsEl.appendChild(li);
                }
            }
        }
        const depsEl = fragment.querySelector('.item-dependencies');
        if (depsEl) {
            depsEl.innerHTML = '';
            const deps = Array.isArray(item.dependencies) ? item.dependencies : [];
            if (!deps.length) {
                const li = document.createElement('li');
                li.textContent = 'None';
                depsEl.appendChild(li);
            } else {
                for (const dep of deps) {
                    const li = document.createElement('li');
                    const target = state.itemsById.get(dep);
                    li.textContent = target ? target.title : dep;
                    depsEl.appendChild(li);
                }
            }
        }
        return fragment;
    }

    async function fetchRoadmap() {
        try {
            const response = await fetch('/Site/roadmap/roadmap.json', { cache: 'no-store' });
            if (!response.ok) {
                throw new Error(`Failed to load roadmap (${response.status})`);
            }
            const data = await response.json();
            if (!data || typeof data !== 'object') {
                throw new Error('Unexpected roadmap format');
            }
            state.metadata = data.metadata ?? {};
            state.items = Array.isArray(data.items) ? data.items.map((item) => ({
                ...item,
                title: sanitiseValue(item.title ?? ''),
                id: sanitiseValue(item.id ?? '') || randomId(),
                status: sanitiseValue(item.status ?? ''),
                phase: normalisePhase(item.phase ?? ''),
                owner: sanitiseValue(item.owner ?? ''),
                eta: sanitiseValue(item.eta ?? '')
            })) : [];
            state.itemsById = new Map(state.items.map((item) => [item.id, item]));
            state.rawJson = JSON.stringify(data, null, 2);
            buildFilters();
            renderMetrics();
            renderMetaTags();
            render();
        } catch (error) {
            console.error(error);
            renderEmpty();
            showToast('Unable to load roadmap data. Please try again later.', 'error');
        }
    }

    function buildFilters() {
        const categoryCounts = new Map();
        const statusCounts = new Map();
        const phaseCounts = new Map();
        const tagCounts = new Map();
        for (const item of state.items) {
            const categories = Array.isArray(item.category) ? item.category : [];
            for (const category of categories) {
                const label = category.toString();
                categoryCounts.set(label, (categoryCounts.get(label) ?? 0) + 1);
            }
            const statusLabel = (item.status ?? '').toString();
            if (statusLabel) {
                statusCounts.set(statusLabel, (statusCounts.get(statusLabel) ?? 0) + 1);
            }
            const phaseLabel = normalisePhase(item.phase);
            phaseCounts.set(phaseLabel, (phaseCounts.get(phaseLabel) ?? 0) + 1);
            const tags = Array.isArray(item.surveyTags) ? item.surveyTags : [];
            for (const rawTag of tags) {
                const tagLabel = typeof rawTag === 'string' ? rawTag : rawTag?.tag;
                if (tagLabel) {
                    tagCounts.set(tagLabel, (tagCounts.get(tagLabel) ?? 0) + 1);
                }
            }
        }
        buildChips(elements.categoryFilters, categoryCounts, state.filters.categories);
        buildChips(elements.statusFilters, statusCounts, state.filters.statuses);
        buildChips(elements.phaseFilters, phaseCounts, state.filters.phases);
        buildChips(elements.tagFilters, tagCounts, state.filters.tags);
    }

    function attachEvents() {
        if (elements.search) {
            elements.search.addEventListener('input', (event) => {
                const value = event.target.value;
                state.searchTokens = parseTokens(value);
                render();
            });
        }
        if (elements.sort) {
            elements.sort.addEventListener('change', (event) => {
                state.sort = event.target.value;
                render();
            });
        }
        if (elements.shipped) {
            elements.shipped.addEventListener('change', (event) => {
                state.filters.shipped = event.target.value;
                render();
            });
        }
        if (elements.themeToggle) {
            elements.themeToggle.addEventListener('click', toggleTheme);
        }
        if (elements.editButton) {
            elements.editButton.addEventListener('click', () => {
                if (!elements.modal || !elements.modalTextarea) return;
                elements.modalTextarea.value = state.rawJson;
                elements.modal.hidden = false;
                elements.modalTextarea.focus();
            });
        }
        const closeModal = () => {
            if (elements.modal) {
                elements.modal.hidden = true;
            }
        };
        if (elements.modalClose) {
            elements.modalClose.addEventListener('click', closeModal);
        }
        if (elements.modalCancel) {
            elements.modalCancel.addEventListener('click', closeModal);
        }
        if (elements.modal && elements.modal === document.activeElement) {
            elements.modal.addEventListener('click', (event) => {
                if (event.target === elements.modal) {
                    closeModal();
                }
            });
        } else if (elements.modal) {
            elements.modal.addEventListener('click', (event) => {
                if (event.target === elements.modal) {
                    closeModal();
                }
            });
        }
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && elements.modal && !elements.modal.hidden) {
                event.preventDefault();
                elements.modal.hidden = true;
            }
        });
        if (elements.modalSave) {
            elements.modalSave.addEventListener('click', async () => {
                if (!elements.modalTextarea) return;
                let payload;
                try {
                    payload = JSON.parse(elements.modalTextarea.value);
                    if (typeof payload !== 'object' || payload === null) {
                        throw new Error('Payload must be an object.');
                    }
                } catch (error) {
                    showToast('JSON is invalid. Please fix parsing errors before saving.', 'error');
                    return;
                }
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                payload.csrfToken = token;
                try {
                    const response = await fetch('/Site/roadmap/save.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const result = await response.json();
                    if (!response.ok || !result?.ok) {
                        throw new Error(result?.message || 'Save failed');
                    }
                    showToast('Roadmap saved successfully.', 'success');
                    if (elements.modal) {
                        elements.modal.hidden = true;
                    }
                    fetchRoadmap();
                } catch (error) {
                    console.error(error);
                    showToast(error.message ?? 'Unable to save roadmap.', 'error');
                }
            });
        }
    }

    (function init() {
        const storedTheme = localStorage.getItem(themeStorageKey);
        setTheme(storedTheme || 'auto');
        attachEvents();
        fetchRoadmap();
    })();
}
