(function () {
  const container = document.getElementById('nx-roadmap-widget');
  if (!container) return;

  const weights = {
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

  function score(item) {
    let total = 0;
    (item.surveyTags || []).forEach((tag) => {
      if (weights[tag]) total += weights[tag];
    });
    if (item.shipped) total += 25;
    total += Math.min(20, item.progress / 5);
    return total;
  }

  function titleWithStar(item) {
    return item.shipped ? `${item.title} *` : item.title;
  }

  function render(items) {
    container.innerHTML = '';
    container.classList.add('nx-widget-root');

    const header = document.createElement('div');
    header.className = 'nx-header';
    const title = document.createElement('h3');
    title.textContent = 'Vision 2025 Snapshot';
    const link = document.createElement('a');
    link.href = '/roadmap/';
    link.textContent = 'View full roadmap';
    link.setAttribute('aria-label', 'View the complete Nexus Online roadmap');
    header.appendChild(title);
    header.appendChild(link);

    const grid = document.createElement('div');
    grid.className = 'nx-grid';

    items.forEach((item) => {
      const anchor = document.createElement('a');
      anchor.href = `/roadmap/?q=${encodeURIComponent(item.title)}`;
      anchor.className = 'nx-card';
      anchor.setAttribute('tabindex', '0');
      anchor.setAttribute('aria-label', `${titleWithStar(item)} — ${item.status} — ${item.progress}% progress`);

      const name = document.createElement('h4');
      name.textContent = titleWithStar(item);
      name.style.margin = '0';
      name.style.fontSize = '1rem';
      name.style.fontWeight = '700';

      const status = document.createElement('span');
      status.className = 'nx-status';
      status.textContent = item.status;

      const progressTrack = document.createElement('div');
      progressTrack.className = 'nx-progress-track';
      progressTrack.setAttribute('role', 'progressbar');
      progressTrack.setAttribute('aria-valuenow', String(item.progress));
      progressTrack.setAttribute('aria-valuemin', '0');
      progressTrack.setAttribute('aria-valuemax', '100');

      const progressFill = document.createElement('div');
      progressFill.className = 'nx-progress-fill';
      progressFill.style.width = `${Math.max(0, Math.min(item.progress, 100))}%`;
      progressTrack.appendChild(progressFill);

      const progressLabel = document.createElement('span');
      progressLabel.className = 'nx-progress-label';
      progressLabel.textContent = `${item.progress}%`;

      anchor.appendChild(name);
      anchor.appendChild(status);
      anchor.appendChild(progressTrack);
      anchor.appendChild(progressLabel);
      grid.appendChild(anchor);
    });

    container.appendChild(header);
    container.appendChild(grid);
  }

  fetch('/roadmap/roadmap.json', { cache: 'no-store' })
    .then((response) => {
      if (!response.ok) throw new Error('Network error');
      return response.json();
    })
    .then((data) => {
      const list = Array.isArray(data.items) ? data.items.slice() : [];
      list.sort((a, b) => {
        const diff = score(b) - score(a);
        if (diff !== 0) return diff;
        if (b.progress !== a.progress) return b.progress - a.progress;
        return a.title.localeCompare(b.title);
      });
      render(list.slice(0, 6));
    })
    .catch(() => {
      container.innerHTML = '<p style="padding:1rem;border-radius:0.75rem;background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;font-weight:600;">Unable to load the roadmap widget right now.</p>';
    });
})();
