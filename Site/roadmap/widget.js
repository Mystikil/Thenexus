(function () {
  const root = document.getElementById('nx-roadmap-widget');
  if (!root) return;

  root.innerHTML = '<div class="nx-roadmap-widget">Loading roadmap highlights…</div>';

  const statusPriority = new Map([
    ['Shipped', 1.6],
    ['In Review', 1.4],
    ['In Progress', 1.3],
    ['In Development', 1.15],
    ['Prototype', 1],
    ['Planned', 0.85],
    ['Backlog', 0.7]
  ]);

  fetch('/Site/roadmap/roadmap.json', { cache: 'no-cache' })
    .then(response => {
      if (!response.ok) throw new Error('Unable to load roadmap.');
      return response.json();
    })
    .then(data => {
      const weights = data?.surveyWeights || {};
      const items = Array.isArray(data?.items) ? data.items.slice() : [];
      const ranked = items
        .filter(Boolean)
        .sort((a, b) => computeRelevance(b, weights) - computeRelevance(a, weights))
        .slice(0, 6);

      root.innerHTML = renderWidget(ranked, data?.updated);
    })
    .catch(error => {
      console.error(error);
      root.innerHTML = '<div class="nx-roadmap-widget">Roadmap data is unavailable right now.</div>';
    });

  function computeRelevance(item, weights) {
    if (!item) return 0;
    const base = 1;
    const surveyWeight = Array.isArray(item.surveyTags) && item.surveyTags.length
      ? item.surveyTags.reduce((sum, tag) => sum + (weights?.[tag] || 1), 0) / item.surveyTags.length
      : 1;
    const statusWeight = statusPriority.get(item.status) || 1;
    const progressWeight = (typeof item.progress === 'number' ? item.progress : 0) / 100;
    const shippedBonus = item.shipped ? 0.5 : 0;
    return base + surveyWeight + statusWeight + progressWeight + shippedBonus;
  }

  function renderWidget(items, updated) {
    if (!items.length) {
      return '<div class="nx-roadmap-widget">No roadmap items to display yet.</div>';
    }
    const updatedText = updated ? new Date(updated).toLocaleDateString() : '';
    return `
      <section class="nx-roadmap-widget" aria-label="Roadmap highlights">
        <header>
          <div>
            <h2>Roadmap highlights</h2>
            ${updatedText ? `<p>Updated ${updatedText}</p>` : ''}
          </div>
          <a href="/Site/roadmap/" aria-label="View the full roadmap">View full roadmap</a>
        </header>
        <div class="nx-roadmap-widget-list">
          ${items.map(renderItem).join('')}
        </div>
      </section>
    `;
  }

  function renderItem(item) {
    const shippedMark = item.shipped ? ' *' : '';
    const categories = Array.isArray(item.category) ? item.category.join(', ') : '';
    const description = item.description || '';
    return `
      <article class="nx-roadmap-widget-item">
        <div>
          <h3>${item.title}${shippedMark}</h3>
          <div class="nx-widget-meta">
            <span>Phase ${item.phase}</span>
            <span>${item.status}</span>
            ${categories ? `<span>${categories}</span>` : ''}
          </div>
          <p>${description}</p>
        </div>
        <div class="nx-widget-progress">${item.progress ?? 0}%</div>
      </article>
    `;
  }
})();
