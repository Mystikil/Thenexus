<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
require_once $root . '/config.php';
require_once $root . '/includes/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentGroupId = $_SESSION['account']['group_id'] ?? 1;

if ((int)$currentGroupId < (int)ADMIN_GROUP_ID) {
    http_response_code(403);
    die('Forbidden');
}

$pageTitle = 'Roadmap Admin';
$metaDescription = 'Edit the roadmap, manage phases, and publish updates to the public roadmap page.';
$metaOg = [];
$additionalStyles = ['/Site/site.css'];

$header = $root . '/includes/header.php';
$footer = $root . '/includes/footer.php';

$roadmapPath = $root . '/Site/roadmap/roadmap.json';
$roadmapRaw = file_exists($roadmapPath) ? file_get_contents($roadmapPath) : '';
$roadmapData = json_decode($roadmapRaw, true);
if (!is_array($roadmapData)) {
    $roadmapData = [
        'updated' => gmdate('c'),
        'phases' => [],
        'surveyWeights' => [],
        'items' => []
    ];
}

$csrfToken = csrf_token();

if (file_exists($header)) include $header;
?>
<section class="admin-roadmap">
    <header class="admin-roadmap__header">
        <div>
            <h1>Roadmap editor</h1>
            <p>Review initiatives, adjust metadata, and publish updates directly to the public roadmap.</p>
        </div>
        <a class="admin-roadmap__back" href="/Site/roadmap/">&#8592; Back to site</a>
    </header>
    <div class="admin-roadmap__controls">
        <div class="admin-roadmap__control-group">
            <button id="admin-add" type="button">Add item</button>
            <button id="admin-save" type="button">Save all</button>
        </div>
        <div class="admin-roadmap__search">
            <label for="admin-search" class="sr-only">Search items</label>
            <input id="admin-search" type="search" placeholder="Search by title, status, or tag" autocomplete="off">
        </div>
    </div>
    <div class="admin-roadmap__table-wrapper">
        <table id="admin-table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Title</th>
                    <th scope="col">Status</th>
                    <th scope="col">Phase</th>
                    <th scope="col">Progress</th>
                    <th scope="col">Categories</th>
                    <th scope="col">Survey tags</th>
                    <th scope="col">Dependencies</th>
                    <th scope="col" class="actions">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <form id="admin-csrf-form" class="sr-only" aria-hidden="true">
        <?= csrf_input(); ?>
    </form>
</section>
<div class="admin-modal" id="admin-modal" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title" hidden>
    <div class="admin-modal__content">
        <header>
            <h2 id="admin-modal-title">Roadmap item</h2>
            <button type="button" id="admin-modal-close" aria-label="Close editor">&times;</button>
        </header>
        <form id="admin-form">
            <div class="admin-form__grid">
                <label>
                    <span>ID</span>
                    <input type="text" id="admin-id" required>
                </label>
                <label>
                    <span>Title</span>
                    <input type="text" id="admin-title" required>
                </label>
                <label>
                    <span>Status</span>
                    <input type="text" id="admin-status" list="admin-statuses" required>
                </label>
                <label>
                    <span>Progress (%)</span>
                    <input type="number" id="admin-progress" min="0" max="100" step="1" required>
                </label>
                <label>
                    <span>Phase</span>
                    <select id="admin-phase"></select>
                </label>
                <label class="admin-checkbox">
                    <input type="checkbox" id="admin-shipped">
                    <span>Shipped</span>
                </label>
                <label class="admin-span-col">
                    <span>Categories (comma separated)</span>
                    <input type="text" id="admin-category">
                </label>
                <label class="admin-span-col">
                    <span>Survey tags (comma separated)</span>
                    <input type="text" id="admin-survey">
                </label>
                <label class="admin-span-col">
                    <span>Dependencies (IDs, comma separated)</span>
                    <input type="text" id="admin-dependencies">
                </label>
                <label class="admin-span-col">
                    <span>Description</span>
                    <textarea id="admin-description" rows="5" required></textarea>
                </label>
            </div>
            <footer class="admin-modal__footer">
                <button type="button" id="admin-modal-cancel">Cancel</button>
                <button type="submit" id="admin-modal-save">Save item</button>
            </footer>
        </form>
    </div>
</div>
<datalist id="admin-statuses">
    <option value="Planned"></option>
    <option value="Prototype"></option>
    <option value="In Development"></option>
    <option value="In Progress"></option>
    <option value="In Review"></option>
    <option value="Shipped"></option>
</datalist>
<script type="application/json" id="admin-roadmap-data"><?= json_encode($roadmapData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<style>
    .admin-roadmap__header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }
    .admin-roadmap__back {
        align-self: center;
        text-decoration: none;
        color: var(--nx-accent, #2563eb);
    }
    .admin-roadmap__controls {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 1rem;
        margin: 1.5rem 0;
    }
    .admin-roadmap__control-group {
        display: flex;
        gap: 0.75rem;
    }
    .admin-roadmap__control-group button {
        border: 1px solid var(--nx-border, #d1d5db);
        background: var(--nx-card, #ffffff);
        padding: 0.6rem 1rem;
        border-radius: 0.75rem;
        cursor: pointer;
    }
    .admin-roadmap__search input {
        padding: 0.6rem 0.8rem;
        border-radius: 0.75rem;
        border: 1px solid var(--nx-border, #d1d5db);
        width: min(280px, 100%);
    }
    .admin-roadmap__table-wrapper {
        overflow-x: auto;
        border-radius: 1rem;
        border: 1px solid color-mix(in srgb, var(--nx-border, #d1d5db) 60%, transparent);
        background: var(--nx-card, #ffffff);
    }
    #admin-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 960px;
    }
    #admin-table th,
    #admin-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--nx-border, #d1d5db);
        text-align: left;
        vertical-align: top;
    }
    #admin-table thead {
        background: color-mix(in srgb, var(--nx-muted, #f3f4f6) 70%, transparent);
    }
    #admin-table td.actions {
        white-space: nowrap;
    }
    #admin-table button {
        border: 1px solid var(--nx-border, #d1d5db);
        background: var(--nx-card, #ffffff);
        border-radius: 0.5rem;
        padding: 0.35rem 0.6rem;
        cursor: pointer;
        margin-right: 0.25rem;
    }
    .admin-modal {
        position: fixed;
        inset: 0;
        display: grid;
        place-items: center;
        background: rgba(15, 23, 42, 0.55);
        z-index: 1000;
        padding: 2rem;
    }
    .admin-modal[hidden] { display: none; }
    .admin-modal__content {
        background: var(--nx-card, #ffffff);
        color: var(--nx-text, #0f172a);
        border-radius: 1rem;
        padding: 1.5rem;
        width: min(780px, 92vw);
    }
    .admin-form__grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .admin-form__grid label {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        font-weight: 600;
    }
    .admin-form__grid input,
    .admin-form__grid textarea,
    .admin-form__grid select {
        font: inherit;
        padding: 0.6rem 0.75rem;
        border-radius: 0.75rem;
        border: 1px solid var(--nx-border, #d1d5db);
    }
    .admin-form__grid textarea {
        resize: vertical;
    }
    .admin-checkbox {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .admin-span-col {
        grid-column: 1 / -1;
    }
    .admin-modal__footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1.5rem;
    }
    .admin-modal__footer button {
        border: 1px solid var(--nx-border, #d1d5db);
        background: var(--nx-card, #ffffff);
        padding: 0.65rem 1.25rem;
        border-radius: 0.75rem;
        cursor: pointer;
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    @media (max-width: 720px) {
        .admin-roadmap__header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
<script>
(function() {
    const dataElement = document.getElementById('admin-roadmap-data');
    if (!dataElement) {
        return;
    }
    const csrfInput = document.querySelector('#admin-csrf-form input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    const data = JSON.parse(dataElement.textContent || '{}');
    const state = {
        phases: Array.isArray(data.phases) ? data.phases : [],
        surveyWeights: data.surveyWeights || {},
        items: Array.isArray(data.items) ? data.items.slice() : [],
        filter: ''
    };

    const tableBody = document.querySelector('#admin-table tbody');
    const searchInput = document.getElementById('admin-search');
    const addButton = document.getElementById('admin-add');
    const saveButton = document.getElementById('admin-save');
    const modal = document.getElementById('admin-modal');
    const modalForm = document.getElementById('admin-form');
    const modalClose = document.getElementById('admin-modal-close');
    const modalCancel = document.getElementById('admin-modal-cancel');
    const modalSave = document.getElementById('admin-modal-save');
    const phaseSelect = document.getElementById('admin-phase');
    const idField = document.getElementById('admin-id');
    const titleField = document.getElementById('admin-title');
    const statusField = document.getElementById('admin-status');
    const progressField = document.getElementById('admin-progress');
    const shippedField = document.getElementById('admin-shipped');
    const categoryField = document.getElementById('admin-category');
    const surveyField = document.getElementById('admin-survey');
    const dependenciesField = document.getElementById('admin-dependencies');
    const descriptionField = document.getElementById('admin-description');
    let editIndex = null;

    function populatePhaseOptions() {
        phaseSelect.innerHTML = '';
        if (!state.phases.length) {
            const unique = [...new Set(state.items.map(item => item.phase).filter(Boolean))];
            unique.forEach(phase => {
                const option = document.createElement('option');
                option.value = phase;
                option.textContent = phase;
                phaseSelect.appendChild(option);
            });
        } else {
            state.phases.forEach(phase => {
                const option = document.createElement('option');
                option.value = phase.id;
                option.textContent = `${phase.id} – ${phase.name}`;
                phaseSelect.appendChild(option);
            });
        }
    }

    function renderTable() {
        tableBody.innerHTML = '';
        const term = state.filter.trim().toLowerCase();
        state.items.forEach((item, index) => {
            const haystack = `${item.id} ${item.title} ${item.status} ${(item.category || []).join(' ')} ${(item.surveyTags || []).join(' ')}`.toLowerCase();
            if (term && !haystack.includes(term)) {
                return;
            }
            const row = document.createElement('tr');
            row.dataset.index = index;
            row.innerHTML = `
                <td>${index + 1}</td>
                <td>${item.title || ''}${item.shipped ? ' *' : ''}</td>
                <td>${item.status || ''}</td>
                <td>${item.phase || ''}</td>
                <td>${item.progress ?? 0}%</td>
                <td>${Array.isArray(item.category) ? item.category.join(', ') : ''}</td>
                <td>${Array.isArray(item.surveyTags) ? item.surveyTags.join(', ') : ''}</td>
                <td>${Array.isArray(item.dependencies) ? item.dependencies.join(', ') : ''}</td>
                <td class="actions">
                    <button type="button" data-action="edit">Edit</button>
                    <button type="button" data-action="delete">Delete</button>
                    <button type="button" data-action="up">&#8593;</button>
                    <button type="button" data-action="down">&#8595;</button>
                </td>
            `;
            tableBody.appendChild(row);
        });
    }

    function openModal(index = null) {
        editIndex = index;
        populatePhaseOptions();
        if (index !== null) {
            const item = state.items[index];
            idField.value = item.id || '';
            titleField.value = item.title || '';
            statusField.value = item.status || '';
            progressField.value = item.progress ?? 0;
            phaseSelect.value = item.phase || (phaseSelect.options[0]?.value ?? '');
            shippedField.checked = !!item.shipped;
            categoryField.value = Array.isArray(item.category) ? item.category.join(', ') : '';
            surveyField.value = Array.isArray(item.surveyTags) ? item.surveyTags.join(', ') : '';
            dependenciesField.value = Array.isArray(item.dependencies) ? item.dependencies.join(', ') : '';
            descriptionField.value = item.description || '';
        } else {
            idField.value = '';
            titleField.value = '';
            statusField.value = '';
            progressField.value = 0;
            phaseSelect.value = phaseSelect.options[0]?.value ?? '';
            shippedField.checked = false;
            categoryField.value = '';
            surveyField.value = '';
            dependenciesField.value = '';
            descriptionField.value = '';
        }
        modal.removeAttribute('hidden');
        document.body.style.setProperty('overflow', 'hidden');
    }

    function closeModal() {
        modal.setAttribute('hidden', '');
        document.body.style.removeProperty('overflow');
        editIndex = null;
    }

    function saveItem(event) {
        event.preventDefault();
        const payload = {
            id: idField.value.trim(),
            title: titleField.value.trim(),
            status: statusField.value.trim(),
            progress: Number(progressField.value) || 0,
            phase: phaseSelect.value || '',
            shipped: shippedField.checked,
            category: categoryField.value.split(',').map(s => s.trim()).filter(Boolean),
            surveyTags: surveyField.value.split(',').map(s => s.trim()).filter(Boolean),
            dependencies: dependenciesField.value.split(',').map(s => s.trim()).filter(Boolean),
            description: descriptionField.value.trim()
        };
        if (!payload.id || !payload.title || !payload.status || !payload.description) {
            alert('ID, title, status, and description are required.');
            return;
        }
        payload.progress = Math.min(100, Math.max(0, payload.progress));
        if (editIndex !== null) {
            state.items.splice(editIndex, 1, payload);
        } else {
            state.items.push(payload);
        }
        renderTable();
        closeModal();
    }

    function deleteItem(index) {
        if (!confirm('Delete this roadmap item?')) return;
        state.items.splice(index, 1);
        renderTable();
    }

    function moveItem(index, direction) {
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= state.items.length) return;
        const [item] = state.items.splice(index, 1);
        state.items.splice(newIndex, 0, item);
        renderTable();
    }

    tableBody.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const row = button.closest('tr');
        if (!row) return;
        const index = Number(row.dataset.index);
        const action = button.dataset.action;
        if (action === 'edit') {
            openModal(index);
        } else if (action === 'delete') {
            deleteItem(index);
        } else if (action === 'up') {
            moveItem(index, -1);
        } else if (action === 'down') {
            moveItem(index, 1);
        }
    });

    searchInput?.addEventListener('input', () => {
        state.filter = searchInput.value;
        renderTable();
    });

    addButton?.addEventListener('click', () => openModal());
    saveButton?.addEventListener('click', async () => {
        const payload = {
            ...data,
            items: state.items,
            updated: new Date().toISOString(),
            csrfToken
        };
        try {
            const response = await fetch('/Site/roadmap/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result?.message || 'Save failed');
            }
            data.items = state.items.slice();
            data.updated = payload.updated;
            alert('Roadmap saved.');
        } catch (error) {
            console.error(error);
            alert(`Unable to save: ${error.message}`);
        }
    });

    modalForm?.addEventListener('submit', saveItem);
    modalClose?.addEventListener('click', closeModal);
    modalCancel?.addEventListener('click', closeModal);
    modal.addEventListener('click', event => {
        if (event.target === modal) {
            closeModal();
        }
    });

    populatePhaseOptions();
    renderTable();
})();
</script>
<?php if (file_exists($footer)) include $footer; ?>
