<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
require_once $root . '/Site/config.php';
require_once $root . '/Site/functions.php';
require_once $root . '/Site/includes/security.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Replace with your real session user/group logic:
$currentGroupId = $_SESSION['account']['group_id'] ?? 1;

if ((int)$currentGroupId < (int)ADMIN_GROUP_ID) {
    http_response_code(403);
    die('Forbidden');
}

$header = $root . '/includes/header.php';
$footer = $root . '/includes/footer.php';
if (file_exists($header)) include $header;

$csrfToken = csrf_token();
?>
<div class="container my-4" id="roadmap-admin-app">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h2 mb-1">Roadmap Editor</h1>
            <p class="text-muted mb-0">Manage roadmap items, metadata, and dependencies. Changes are saved to <code>Site/roadmap/roadmap.json</code>.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/Site/roadmap/" class="btn btn-outline-secondary">Back to site</a>
            <button type="button" class="btn btn-primary" id="roadmap-admin-add">Add Item</button>
            <button type="button" class="btn btn-success" id="roadmap-admin-save">Save All</button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="roadmap-admin-search" class="form-label">Search roadmap</label>
                        <input type="search" class="form-control" id="roadmap-admin-search" placeholder="Filter by title, status, or id">
                    </div>
                    <div class="table-responsive" style="max-height: 520px; overflow:auto;">
                        <table class="table table-hover align-middle" id="roadmap-admin-table">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Phase</th>
                                    <th scope="col" class="text-end">Progress</th>
                                    <th scope="col" class="text-center">Shipped</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Roadmap metadata</h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="metadata-description" class="form-label">Description</label>
                        <textarea class="form-control" id="metadata-description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="metadata-owner" class="form-label">Owner</label>
                        <input type="text" class="form-control" id="metadata-owner" placeholder="Live Operations Guild">
                    </div>
                    <div class="mb-3">
                        <label for="metadata-updated" class="form-label">Last updated (ISO)</label>
                        <input type="text" class="form-control" id="metadata-updated" placeholder="2024-05-01T00:00:00Z">
                    </div>
                    <div class="mb-3">
                        <label for="metadata-survey" class="form-label">Survey tag weights (JSON)</label>
                        <textarea class="form-control" id="metadata-survey" rows="4" placeholder='{"pvp":1.2}'></textarea>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Item details</h2>
                    <span class="text-muted small" id="roadmap-admin-selected">No item selected</span>
                </div>
                <div class="card-body">
                    <form id="roadmap-admin-form" class="d-grid gap-3">
                        <input type="hidden" id="roadmap-admin-csrf" value="<?php echo sanitize($csrfToken); ?>">
                        <div>
                            <label for="item-id" class="form-label">ID</label>
                            <input type="text" class="form-control" id="item-id" readonly>
                        </div>
                        <div>
                            <label for="item-title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="item-title" required>
                        </div>
                        <div>
                            <label for="item-status" class="form-label">Status</label>
                            <input type="text" class="form-control" id="item-status" list="status-suggestions">
                            <datalist id="status-suggestions"></datalist>
                        </div>
                        <div>
                            <label for="item-progress" class="form-label">Progress (0-100)</label>
                            <input type="number" class="form-control" id="item-progress" min="0" max="100">
                        </div>
                        <div>
                            <label for="item-phase" class="form-label">Phase</label>
                            <select class="form-select" id="item-phase">
                                <option value="I">Phase I</option>
                                <option value="II">Phase II</option>
                                <option value="III">Phase III</option>
                                <option value="IV">Phase IV</option>
                                <option value="V">Phase V</option>
                            </select>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="item-shipped">
                            <label class="form-check-label" for="item-shipped">Mark as shipped</label>
                        </div>
                        <div>
                            <label for="item-eta" class="form-label">ETA</label>
                            <input type="text" class="form-control" id="item-eta" placeholder="2024-Q4">
                        </div>
                        <div>
                            <label for="item-owner" class="form-label">Owner</label>
                            <input type="text" class="form-control" id="item-owner" placeholder="Systems Team">
                        </div>
                        <div>
                            <label for="item-description" class="form-label">Description</label>
                            <textarea class="form-control" id="item-description" rows="3"></textarea>
                        </div>
                        <div>
                            <label for="item-categories" class="form-label">Categories (comma separated)</label>
                            <input type="text" class="form-control" id="item-categories" placeholder="Gameplay, Social">
                        </div>
                        <div>
                            <label for="item-tags" class="form-label">Survey tags (tag:weight per line)</label>
                            <textarea class="form-control" id="item-tags" rows="3" placeholder="pvp:1.0"></textarea>
                        </div>
                        <div>
                            <label for="item-dependencies" class="form-label">Dependencies</label>
                            <select id="item-dependencies" class="form-select" multiple size="6"></select>
                            <div class="form-text">Hold Ctrl/⌘ to select multiple dependencies.</div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-danger" id="roadmap-admin-delete">Delete item</button>
                            <button type="submit" class="btn btn-outline-primary">Apply changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="alert mt-4" id="roadmap-admin-alert" role="status" hidden></div>
</div>

<script type="module">
const app = document.getElementById('roadmap-admin-app');
if (app) {
    const state = {
        metadata: {},
        items: [],
        filtered: [],
        selectedId: null
    };

    const randomId = () => {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return `nx-roadmap-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
    };

    const elements = {
        tableBody: document.querySelector('#roadmap-admin-table tbody'),
        search: document.getElementById('roadmap-admin-search'),
        add: document.getElementById('roadmap-admin-add'),
        save: document.getElementById('roadmap-admin-save'),
        form: document.getElementById('roadmap-admin-form'),
        selectedLabel: document.getElementById('roadmap-admin-selected'),
        delete: document.getElementById('roadmap-admin-delete'),
        alert: document.getElementById('roadmap-admin-alert'),
        datalist: document.getElementById('status-suggestions'),
        dependencies: document.getElementById('item-dependencies')
    };

    const metadataFields = {
        description: document.getElementById('metadata-description'),
        owner: document.getElementById('metadata-owner'),
        updated: document.getElementById('metadata-updated'),
        surveyWeights: document.getElementById('metadata-survey')
    };

    const formFields = {
        id: document.getElementById('item-id'),
        title: document.getElementById('item-title'),
        status: document.getElementById('item-status'),
        progress: document.getElementById('item-progress'),
        phase: document.getElementById('item-phase'),
        shipped: document.getElementById('item-shipped'),
        eta: document.getElementById('item-eta'),
        owner: document.getElementById('item-owner'),
        description: document.getElementById('item-description'),
        categories: document.getElementById('item-categories'),
        tags: document.getElementById('item-tags'),
        dependencies: document.getElementById('item-dependencies')
    };

    const csrfField = document.getElementById('roadmap-admin-csrf');

    function showAlert(message, type = 'info') {
        if (!elements.alert) return;
        elements.alert.className = `alert alert-${type}`;
        elements.alert.textContent = message;
        elements.alert.hidden = false;
        clearTimeout(elements.alert._timeout);
        elements.alert._timeout = setTimeout(() => {
            elements.alert.hidden = true;
        }, 4000);
    }

    function parseTags(value) {
        if (!value.trim()) return [];
        return value.split(/\n+/).map((line) => line.trim()).filter(Boolean).map((line) => {
            const [tag, weight] = line.split(':').map((segment) => segment.trim());
            const parsedWeight = weight !== undefined ? Number(weight) : 1;
            return {
                tag,
                weight: Number.isFinite(parsedWeight) ? parsedWeight : 1
            };
        });
    }

    function stringifyTags(tags) {
        if (!Array.isArray(tags) || !tags.length) return '';
        return tags.map((entry) => {
            if (typeof entry === 'string') {
                return `${entry}:1`;
            }
            return `${entry.tag}:${entry.weight}`;
        }).join('\n');
    }

    function parseCategories(value) {
        if (!value.trim()) return [];
        return value.split(',').map((item) => item.trim()).filter(Boolean);
    }

    function populateDatalist() {
        if (!elements.datalist) return;
        const statuses = new Set(state.items.map((item) => item.status).filter(Boolean));
        elements.datalist.innerHTML = '';
        for (const status of statuses) {
            const option = document.createElement('option');
            option.value = status;
            elements.datalist.appendChild(option);
        }
    }

    function populateDependencies() {
        if (!elements.dependencies) return;
        elements.dependencies.innerHTML = '';
        for (const item of state.items) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = `${item.title} (${item.id})`;
            elements.dependencies.appendChild(option);
        }
    }

    function selectItem(id) {
        state.selectedId = id;
        const item = state.items.find((entry) => entry.id === id);
        if (!item) {
            elements.selectedLabel.textContent = 'No item selected';
            elements.form.reset();
            formFields.id.value = '';
            return;
        }
        elements.selectedLabel.textContent = item.title;
        formFields.id.value = item.id;
        formFields.title.value = item.title ?? '';
        formFields.status.value = item.status ?? '';
        formFields.progress.value = item.progress ?? 0;
        formFields.phase.value = item.phase ?? 'I';
        formFields.shipped.checked = Boolean(item.shipped);
        formFields.eta.value = item.eta ?? '';
        formFields.owner.value = item.owner ?? '';
        formFields.description.value = item.description ?? '';
        formFields.categories.value = (item.category ?? []).join(', ');
        formFields.tags.value = stringifyTags(item.surveyTags ?? []);
        const dependencies = new Set((item.dependencies ?? []).map((dep) => dep.toString()));
        for (const option of formFields.dependencies.options) {
            option.selected = dependencies.has(option.value);
        }
    }

    function renderTable() {
        if (!elements.tableBody) return;
        const query = elements.search.value.trim().toLowerCase();
        state.filtered = state.items.filter((item) => {
            if (!query) return true;
            return (
                item.title.toLowerCase().includes(query) ||
                item.status.toLowerCase().includes(query) ||
                item.id.toLowerCase().includes(query)
            );
        });
        elements.tableBody.innerHTML = '';
        for (const item of state.filtered) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="text-nowrap">${item.id}</td>
                <td>${item.title}</td>
                <td>${item.status}</td>
                <td>${item.phase}</td>
                <td class="text-end">${item.progress}%</td>
                <td class="text-center">${item.shipped ? '★' : ''}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary">Edit</button>
                </td>
            `;
            row.querySelector('button').addEventListener('click', () => selectItem(item.id));
            elements.tableBody.appendChild(row);
        }
    }

    function loadMetadata(metadata) {
        metadataFields.description.value = metadata.description ?? '';
        metadataFields.owner.value = metadata.owner ?? '';
        metadataFields.updated.value = metadata.updated ?? '';
        metadataFields.surveyWeights.value = metadata.surveyWeights ? JSON.stringify(metadata.surveyWeights, null, 2) : '';
    }

    function updateMetadataFromForm() {
        const weightsRaw = metadataFields.surveyWeights.value.trim();
        let surveyWeights = {};
        if (weightsRaw) {
            try {
                const parsed = JSON.parse(weightsRaw);
                if (parsed && typeof parsed === 'object') {
                    surveyWeights = parsed;
                }
            } catch (error) {
                showAlert('Survey weights JSON is invalid.', 'warning');
            }
        }
        state.metadata = {
            ...state.metadata,
            description: metadataFields.description.value.trim(),
            owner: metadataFields.owner.value.trim(),
            updated: metadataFields.updated.value.trim(),
            surveyWeights
        };
    }

    async function fetchRoadmap() {
        try {
            const response = await fetch('/Site/roadmap/roadmap.json', { cache: 'no-store' });
            if (!response.ok) {
                throw new Error(`Failed to load roadmap (${response.status})`);
            }
            const data = await response.json();
            state.items = Array.isArray(data.items) ? data.items.map((item) => ({
                ...item,
                id: item.id ?? randomId(),
                title: item.title ?? '',
                status: item.status ?? '',
                progress: Number(item.progress) || 0,
                phase: item.phase ?? 'I',
                shipped: Boolean(item.shipped),
                category: Array.isArray(item.category) ? item.category : [],
                surveyTags: Array.isArray(item.surveyTags) ? item.surveyTags : [],
                dependencies: Array.isArray(item.dependencies) ? item.dependencies : []
            })) : [];
            state.metadata = data.metadata ?? {};
            populateDatalist();
            populateDependencies();
            loadMetadata(state.metadata);
            renderTable();
            showAlert('Roadmap loaded.', 'success');
        } catch (error) {
            console.error(error);
            showAlert('Unable to load roadmap data.', 'danger');
        }
    }

    elements.search.addEventListener('input', () => renderTable());

    elements.add.addEventListener('click', () => {
        const newItem = {
            id: randomId(),
            title: 'New roadmap item',
            status: 'Draft',
            progress: 0,
            phase: 'I',
            shipped: false,
            description: '',
            category: [],
            surveyTags: [],
            dependencies: [],
            eta: '',
            owner: ''
        };
        state.items.unshift(newItem);
        populateDatalist();
        populateDependencies();
        renderTable();
        selectItem(newItem.id);
        showAlert('Draft item created. Remember to fill the details and apply changes.', 'info');
    });

    elements.form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!state.selectedId) {
            showAlert('Select an item before applying changes.', 'warning');
            return;
        }
        const index = state.items.findIndex((item) => item.id === state.selectedId);
        if (index === -1) return;
        const updated = {
            ...state.items[index],
            title: formFields.title.value.trim(),
            status: formFields.status.value.trim(),
            progress: Math.max(0, Math.min(100, Number(formFields.progress.value) || 0)),
            phase: formFields.phase.value,
            shipped: formFields.shipped.checked,
            eta: formFields.eta.value.trim(),
            owner: formFields.owner.value.trim(),
            description: formFields.description.value.trim(),
            category: parseCategories(formFields.categories.value),
            surveyTags: parseTags(formFields.tags.value),
            dependencies: Array.from(formFields.dependencies.selectedOptions).map((option) => option.value)
        };
        state.items[index] = updated;
        populateDatalist();
        populateDependencies();
        renderTable();
        showAlert('Item updated locally. Remember to save.', 'success');
    });

    elements.delete.addEventListener('click', () => {
        if (!state.selectedId) {
            showAlert('Select an item before deleting.', 'warning');
            return;
        }
        state.items = state.items.filter((item) => item.id !== state.selectedId);
        state.selectedId = null;
        elements.form.reset();
        formFields.id.value = '';
        populateDependencies();
        renderTable();
        showAlert('Item removed. Save to apply changes.', 'info');
    });

    elements.save.addEventListener('click', async () => {
        updateMetadataFromForm();
        const payload = {
            metadata: state.metadata,
            items: state.items,
            csrfToken: csrfField ? csrfField.value : ''
        };
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
            showAlert('Roadmap saved successfully.', 'success');
            fetchRoadmap();
        } catch (error) {
            console.error(error);
            showAlert(error.message ?? 'Unable to save roadmap.', 'danger');
        }
    });

    fetchRoadmap();
}
</script>
<?php if (file_exists($footer)) include $footer; ?>
