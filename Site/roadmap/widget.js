(function () {
    const root = document.getElementById('nx-roadmap-widget');
    if (!root) {
        return;
    }

    root.setAttribute('role', 'region');
    root.setAttribute('aria-label', 'Roadmap highlights');

    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = prefersDark ? 'dark' : 'light';

    const createEl = (tag, className) => {
        const element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        return element;
    };

    const computeRelevance = (item, weights) => {
        let score = (Number(item.progress) || 0) * 1.2;
        if (item.shipped) {
            score += 160;
        }
        if (Array.isArray(item.surveyTags)) {
            for (const rawTag of item.surveyTags) {
                let tag = '';
                let weight = 1;
                if (typeof rawTag === 'string') {
                    tag = rawTag;
                } else if (rawTag && typeof rawTag === 'object') {
                    tag = rawTag.tag ?? '';
                    if (typeof rawTag.weight === 'number') {
                        weight = rawTag.weight;
                    } else if (typeof rawTag.weight === 'string') {
                        const parsed = parseFloat(rawTag.weight);
                        if (!Number.isNaN(parsed)) {
                            weight = parsed;
                        }
                    }
                }
                if (!tag) continue;
                const surveyWeight = typeof weights[tag] === 'number' ? weights[tag] : 1;
                score += weight * surveyWeight * 120;
            }
        }
        return score;
    };

    const render = (items, weights) => {
        root.innerHTML = '';
        const header = createEl('div', 'widget-header');
        const title = createEl('h3');
        title.textContent = 'On the roadmap';
        const cta = createEl('a');
        cta.href = '/Site/roadmap/';
        cta.innerHTML = 'View all <span aria-hidden="true">→</span>';
        cta.setAttribute('aria-label', 'View the full roadmap');
        header.appendChild(title);
        header.appendChild(cta);
        root.appendChild(header);

        if (!items.length) {
            const empty = createEl('p', 'widget-empty');
            empty.textContent = 'Roadmap data will appear here soon.';
            root.appendChild(empty);
            return;
        }

        const list = createEl('ul');
        const topItems = [...items]
            .sort((a, b) => computeRelevance(b, weights) - computeRelevance(a, weights) || a.title.localeCompare(b.title))
            .slice(0, 6);
        for (const item of topItems) {
            const li = createEl('li');
            const titleRow = createEl('div', 'item-title');
            const name = createEl('span');
            name.textContent = item.title;
            if (item.shipped) {
                const star = createEl('strong');
                star.textContent = '*';
                star.setAttribute('aria-label', 'Shipped milestone');
                titleRow.appendChild(star);
            }
            titleRow.appendChild(name);
            const status = createEl('span', 'item-status');
            status.textContent = item.status ?? '';
            li.appendChild(titleRow);
            li.appendChild(status);

            const progress = createEl('div', 'item-progress');
            const progressValue = createEl('span');
            const pct = Math.max(0, Math.min(100, Number(item.progress) || 0));
            progressValue.style.width = `${pct}%`;
            progress.appendChild(progressValue);
            li.appendChild(progress);

            const tags = Array.isArray(item.surveyTags)
                ? item.surveyTags.map((entry) => typeof entry === 'string' ? entry : entry?.tag).filter(Boolean)
                : [];
            if (tags.length) {
                const tagRow = createEl('div', 'item-tags');
                for (const tag of tags.slice(0, 3)) {
                    const badge = createEl('span');
                    badge.textContent = tag;
                    tagRow.appendChild(badge);
                }
                li.appendChild(tagRow);
            }
            list.appendChild(li);
        }
        root.appendChild(list);
    };

    fetch('/Site/roadmap/roadmap.json', { cache: 'no-store' })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Unable to fetch roadmap widget data.');
            }
            return response.json();
        })
        .then((data) => {
            const items = Array.isArray(data?.items) ? data.items : [];
            const weights = data?.metadata?.surveyWeights ?? {};
            render(items, weights);
        })
        .catch((error) => {
            console.error(error);
            root.innerHTML = '<p class="widget-empty">Roadmap widget unavailable.</p>';
        });
})();
