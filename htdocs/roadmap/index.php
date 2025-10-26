<?php include $_SERVER['DOCUMENT_ROOT'] . '/header.php'; ?>
<script>
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        colors: {
          nexus: {
            surface: '#0b1220',
            accent: '#6366f1'
          }
        },
        boxShadow: {
          glow: '0 20px 45px -12px rgba(15,23,42,0.65)'
        }
      }
    }
  };
</script>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/roadmap/roadmap.css" />
<main id="roadmap-root" class="min-h-screen bg-slate-100 text-slate-900 transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100">
  <div class="mx-auto flex max-w-7xl flex-col gap-10 px-4 pb-16 pt-12 sm:px-6 lg:px-8">
    <header class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">Vision 2025</p>
          <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-5xl">Nexus Online — Vision 2025 Roadmap</h1>
          <p class="mt-2 max-w-3xl text-lg text-slate-600 dark:text-slate-300">Classic soul, modern flow — built around the 2025 community survey.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button id="theme-toggle" type="button" class="card-focus inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white/80 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-white focus-visible:outline-none dark:border-slate-700/60 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:bg-slate-800/70" aria-label="Toggle dark or light theme">
            <span class="inline-flex h-2.5 w-2.5 rounded-full bg-amber-400" aria-hidden="true"></span>
            <span class="hidden sm:inline">Toggle dark / light</span>
            <span class="sm:hidden">Theme</span>
          </button>
          <button id="edit-json" type="button" class="card-focus inline-flex items-center gap-2 rounded-full border border-indigo-600 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus-visible:outline-none dark:border-indigo-500/50 dark:bg-indigo-500/20 dark:text-indigo-200 dark:hover:bg-indigo-500/30">
            <span aria-hidden="true">✎</span>
            <span>Edit JSON</span>
          </button>
        </div>
      </div>
      <p class="text-sm text-slate-500 dark:text-slate-400">Last updated <span id="meta-updated">—</span>. Filters persist via URL for easy sharing.</p>
    </header>

    <section aria-labelledby="filters-heading" class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-glow dark:border-slate-800/60 dark:bg-slate-900/70">
      <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="w-full lg:max-w-2xl">
            <label for="roadmap-search" class="sr-only">Search roadmap</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 dark:text-slate-500" aria-hidden="true">🔍</span>
              <input id="roadmap-search" name="roadmap-search" type="search" autocomplete="off" placeholder="Search features, lore beats, or keywords" class="card-focus w-full rounded-2xl border border-slate-300 bg-white py-3 pl-11 pr-4 text-base text-slate-900 placeholder:text-slate-400 focus-visible:outline-none dark:border-slate-700/60 dark:bg-slate-950/80 dark:text-slate-100 dark:placeholder:text-slate-500" />
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
              <input id="shipped-toggle" type="checkbox" class="h-4 w-4 rounded border-slate-300 bg-white text-indigo-600 focus:ring-indigo-400 dark:border-slate-600 dark:bg-slate-900 dark:text-indigo-500" />
              <span>Show only shipped (*)</span>
            </label>
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
              <label for="sort-select">Sort by</label>
              <select id="sort-select" class="card-focus rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus-visible:outline-none dark:border-slate-700/60 dark:bg-slate-950/80 dark:text-slate-100">
                <option value="relevance">Relevance</option>
                <option value="progress">Progress</option>
                <option value="title">Title A–Z</option>
              </select>
            </div>
            <button id="reset-filters" type="button" class="card-focus inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 focus-visible:outline-none dark:border-slate-700/60 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:bg-slate-800/80">
              <span aria-hidden="true">↺</span>
              <span>Reset filters</span>
            </button>
          </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" role="group" aria-labelledby="filters-heading">
          <fieldset class="space-y-2" id="status-filters">
            <legend id="filters-heading" class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Status</legend>
            <div class="flex flex-wrap gap-2" aria-live="polite"></div>
          </fieldset>
          <fieldset class="space-y-2" id="category-filters">
            <legend class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Category</legend>
            <div class="flex flex-wrap gap-2" aria-live="polite"></div>
          </fieldset>
          <fieldset class="space-y-2" id="phase-filters">
            <legend class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Phase</legend>
            <div class="flex flex-wrap gap-2" aria-live="polite"></div>
          </fieldset>
        </div>
      </div>
    </section>

    <section id="active-filters" aria-live="polite" class="hidden rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-700 dark:border-slate-800/60 dark:bg-slate-900/70 dark:text-slate-200"></section>

    <section id="roadmap-sections" class="space-y-10" aria-live="polite"></section>
    <div id="empty-state" class="hidden rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-300">
      <p class="text-lg font-semibold text-slate-700 dark:text-slate-200">No roadmap items match those filters yet.</p>
      <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Try clearing filters or searching for a broader term.</p>
    </div>
  </div>
</main>

<div id="json-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-panel">
    <div class="modal-header">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Edit roadmap.json</h2>
      <button id="close-modal" type="button" class="card-focus inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 hover:bg-slate-100 focus-visible:outline-none dark:border-slate-600/60 dark:bg-slate-900/80 dark:text-slate-300 dark:hover:bg-slate-800/80" aria-label="Close edit dialog">✕</button>
    </div>
    <div class="modal-body">
      <p class="text-sm text-slate-600 dark:text-slate-300">Validate changes before saving. Direct save requires server-side write permission. Always keep a backup of the previous file.</p>
      <textarea id="json-editor" class="json-editor" spellcheck="false"></textarea>
      <p id="json-feedback" class="text-sm font-semibold"></p>
    </div>
    <div class="modal-footer">
      <button id="validate-json" type="button" class="card-focus inline-flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400 focus-visible:outline-none dark:bg-emerald-500/80 dark:text-emerald-50 dark:hover:bg-emerald-500">
        <span aria-hidden="true">✔</span>
        <span>Validate</span>
      </button>
      <button id="save-json" type="button" class="card-focus inline-flex items-center gap-2 rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus-visible:outline-none dark:bg-indigo-500 dark:hover:bg-indigo-400">
        <span aria-hidden="true">💾</span>
        <span>Save</span>
      </button>
      <button id="cancel-json" type="button" class="card-focus inline-flex items-center gap-2 rounded-full border border-slate-300 bg-transparent px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-none dark:border-slate-600/60 dark:text-slate-200 dark:hover:bg-slate-800/60">
        <span aria-hidden="true">✖</span>
        <span>Cancel</span>
      </button>
    </div>
  </div>
</div>

<script type="module" src="/roadmap/roadmap.js" defer></script>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>
