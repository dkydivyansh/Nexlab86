document.addEventListener('DOMContentLoaded', function() {
    const entryModal = document.getElementById('entry-modal');
    const entryForm = document.getElementById('entry-form');
    const newEntryBtn = document.getElementById('new-entry-btn');
    const cancelEntryBtn = document.getElementById('cancel-entry');
    const requireAuthCheckbox = document.getElementById('require_auth');
    const accessKeyGroup = document.querySelector('.access-key-group');
    const generateKeyBtn = document.getElementById('generate-key');
    const accessKeyInput = document.getElementById('access_key');
    const typeSelect = document.getElementById('type');
    const valueContainer = document.getElementById('value-container');
    const searchInput = document.getElementById('search-entries');
    const typeFilter = document.getElementById('type-filter');
    const tableBody = document.querySelector('table tbody');
    let searchTimeout;

    // Function to update value input based on type
    function updateValueField(type) {
        let html = '';
        switch(type) {
            case 'boolean':
                html = `
                    <select name="value" id="value" class="form-control" required>
                        <option value="true">True</option>
                        <option value="false">False</option>
                    </select>`;
                break;
            case 'number':
                html = `<input type="number" name="value" id="value" class="form-control" required>`;
                break;
            case 'float':
                html = `<input type="number" name="value" id="value" step="0.01" class="form-control" required>`;
                break;
            case 'json':
            case 'array':
                html = `<textarea name="value" id="value" class="form-control code-editor" required></textarea>`;
                break;
            default: // string
                html = `<textarea name="value" id="value" class="form-control" required></textarea>`;
        }
        valueContainer.innerHTML = html;
    }

    // Listen for type changes
    typeSelect.addEventListener('change', function() {
        updateValueField(this.value);
    });

    // Show new entry modal
    newEntryBtn.addEventListener('click', () => {
        entryForm.reset();
        entryForm.action = '/public/actions/save_entry.php';
        document.getElementById('form-title').textContent = 'New Data Entry';
        document.getElementById('entry-id').value = '';
        updateValueField(typeSelect.value); // Update value field for default type
        entryModal.classList.remove('hidden');
    });

    // Handle access key authentication toggle
    requireAuthCheckbox.addEventListener('change', function() {
        accessKeyGroup.classList.toggle('hidden', !this.checked);
        if (this.checked && !accessKeyInput.value) {
            generateKeyBtn.click(); // Auto generate key if enabled and empty
        }
    });

    // Generate random access key
    generateKeyBtn.addEventListener('click', () => {
        accessKeyInput.value = generateRandomKey(32);
    });

    // Form submission
    entryForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Auto generate key if auth is enabled but key is empty
        if (requireAuthCheckbox.checked && !accessKeyInput.value) {
            accessKeyInput.value = generateRandomKey(32);
        }

        try {
            const formData = new FormData(this);
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                window.location.reload();
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while saving the entry');
        }
    });

    // Handle delete button clicks
    document.querySelectorAll('.delete-entry').forEach(button => {
        button.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to delete this entry?')) {
                return;
            }
            
            const entryId = this.dataset.id;
            try {
                const formData = new FormData();
                formData.append('id', entryId);
                
                const response = await fetch('actions/delete_entry.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    // Remove the table row
                    this.closest('tr').remove();
                } else {
                    alert(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while deleting the entry');
            }
        });
    });

    // Handle API Info button clicks
    document.querySelectorAll('.view-api').forEach(button => {
        button.addEventListener('click', async function() {
            const entryId = this.dataset.id;
            const apiModal = document.getElementById('api-modal');
            const apiInfo = document.getElementById('api-info');
            
            try {
                const response = await fetch(`/public/actions/get_entry_info.php?id=${entryId}&_t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success) {
                    apiInfo.innerHTML = updateApiInfo(data);
                    apiModal.classList.remove('hidden');
                } else {
                    alert(data.message || 'Failed to load API information');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while loading API information');
            }
        });
    });

    // Handle edit button clicks
    document.querySelectorAll('.edit-entry').forEach(button => {
        button.addEventListener('click', async function() {
            const entryId = this.dataset.id;
            try {
                const response = await fetch(`/public/actions/get_entry_info.php?id=${entryId}&_t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('form-title').textContent = 'Edit Data Entry';
                    document.getElementById('entry-id').value = entryId;
                    entryForm.action = '/public/actions/update_entry.php';
                    
                    document.getElementById('type').value = data.type;
                    updateValueField(data.type); // Update value field based on type
                    
                    // Set value after field is updated
                    setTimeout(() => {
                        document.getElementById('value').value = data.value;
                        document.getElementById('note').value = data.note || '';
                        requireAuthCheckbox.checked = data.require_auth;
                        if (data.require_auth) {
                            accessKeyInput.value = data.access_key;
                        }
                        requireAuthCheckbox.dispatchEvent(new Event('change'));
                    }, 0);
                    
                    // Update disabled state checkbox
                    const disableToggle = document.getElementById('is_disabled');
                    if (disableToggle) {
                        disableToggle.checked = Boolean(data.is_disabled);
                    }
                    
                    entryModal.classList.remove('hidden');
                } else {
                    alert(data.message || 'Failed to load entry information');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while loading entry information');
            }
        });
    });

    // Update API Info display
    function updateApiInfo(data) {
        const baseUrl = data.api_endpoint;
        const getEndpoint = `${baseUrl}?id=${data.id}`;
        const getEndpointWithAuth = data.require_auth ? 
            `${getEndpoint}&access_key=${data.access_key}` : getEndpoint;
        const putEndpointWithAuth = data.require_auth ? 
            `${baseUrl}?id=${data.id}&access_key=${data.access_key}` : null;

        return `
            <div class="api-info-section">
                <h3>GET Endpoint</h3>
                <code>${getEndpointWithAuth}</code>
                <div class="copy-wrapper">
                    <button class="copy-button" data-copy="${getEndpointWithAuth}">
                        Copy URL
                    </button>
                </div>
            </div>
            
            ${data.require_auth ? `
                <div class="api-info-section">
                    <h3>PUT Endpoint</h3>
                    <code>${putEndpointWithAuth}</code>
                    <div class="copy-wrapper">
                        <button class="copy-button" data-copy="${putEndpointWithAuth}">
                            Copy URL
                        </button>
                    </div>
                </div>
                
                <div class="api-info-section">
                    <h3>Access Key</h3>
                    <code>${data.access_key}</code>
                    <div class="copy-wrapper">
                        <button class="copy-button" data-copy="${data.access_key}">
                            Copy Access Key
                        </button>
                    </div>
                </div>

                <div class="api-info-section">
                    <h3>Example Usage</h3>
                    <pre><code>
// GET Example
fetch('${getEndpointWithAuth}')
    .then(response => response.json())
    .then(data => console.log(data));

// PUT Example
fetch('${putEndpointWithAuth}', {
    method: 'PUT',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        value: 'new value',
        type: '${data.type}',
        access_key: '${data.access_key}'
    })
})
    .then(response => response.json())
    .then(data => console.log(data));
                    </code></pre>
                </div>
            ` : `
                <div class="api-info-section">
                    <h3>Example Usage</h3>
                    <pre><code>
// GET Example
fetch('${getEndpointWithAuth}')
    .then(response => response.json())
    .then(data => console.log(data));

// Note: PUT requests are only available when Access Key Authentication is enabled.
                    </code></pre>
                </div>
            `}
        `;
    }

    // Handle copy buttons
    document.addEventListener('click', async function(e) {
        if (e.target.classList.contains('copy-button')) {
            const textToCopy = e.target.dataset.copy;
            try {
                await navigator.clipboard.writeText(textToCopy);
                
                const success = document.createElement('span');
                success.className = 'copy-success';
                success.textContent = 'Copied!';
                e.target.parentNode.appendChild(success);
                
                setTimeout(() => success.remove(), 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        }
    });

    // Close modals
    document.querySelectorAll('.modal .close, #cancel-entry').forEach(button => {
        button.addEventListener('click', () => {
            button.closest('.modal').classList.add('hidden');
        });
    });

    // Close modal on outside click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });

    // Utility functions
    function generateRandomKey(length) {
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return Array.from(crypto.getRandomValues(new Uint8Array(length)))
            .map(x => chars[x % chars.length])
            .join('');
    }

    // Update the View Full Value functionality
    document.querySelectorAll('.view-full-value').forEach(button => {
        button.addEventListener('click', function() {
            const valuePreview = this.parentElement.querySelector('.value-preview');
            const fullValue = document.getElementById('full-value');
            const valueModal = document.getElementById('value-modal');
            
            fullValue.textContent = valuePreview.textContent;
            valueModal.classList.remove('hidden');
        });
    });

    async function searchEntries() {
        const searchTerm = searchInput.value;
        const selectedType = typeFilter.value;
        const searchBtn = document.getElementById('search-btn');
        const searchText = searchBtn.querySelector('.search-text');
        const searchLoading = searchBtn.querySelector('.search-loading');

        try {
            // Show loading state
            searchText.classList.add('hidden');
            searchLoading.classList.remove('hidden');
            searchBtn.disabled = true;

            const response = await fetch(`/public/actions/search_entries.php?search=${encodeURIComponent(searchTerm)}&type=${encodeURIComponent(selectedType)}`);
            const data = await response.json();

            if (data.success) {
                updateTable(data.entries);
            } else {
                console.error('Search failed:', data.message);
            }
        } catch (error) {
            console.error('Error:', error);
        } finally {
            // Hide loading state
            searchText.classList.remove('hidden');
            searchLoading.classList.add('hidden');
            searchBtn.disabled = false;
        }
    }

    function updateTable(entries) {
        tableBody.innerHTML = entries.map(entry => `
            <tr class="${entry.is_disabled ? 'entry-disabled' : ''}" data-id="${entry.id}">
                <td class="px-4 py-2">${escapeHtml(entry.id)}&nbsp;</td>
                <td class="px-4 py-2">
                    ${escapeHtml(entry.type)}
                    ${entry.is_disabled ? '<span class="disabled-badge">Disabled</span>' : ''}
                </td>
                <td class="value-cell">
                    <div class="value-preview">
                        ${escapeHtml(entry.value ? entry.value.substring(0, 50) : '')}
                        ${entry.value && entry.value.length > 50 ? '...' : ''}
                    </div>
                    <button class="btn btn-small btn-secondary view-full-value">View Full</button>
                </td>
                <td class="px-4 py-2">
                    ${entry.is_disabled ? 
                        `<div class="note-with-status">
                            <span class="note-text">${entry.note ? escapeHtml(entry.note.substring(0, 30)) : '-'}</span>
                            <span class="status-indicator">Disabled</span>
                        </div>` : 
                        (entry.note ? escapeHtml(entry.note.substring(0, 30)) : '-')
                    }
                </td>
                <td class="px-4 py-2">${entry.require_auth ? 'Yes' : 'No'}</td>
                <td class="px-4 py-2">${escapeHtml(entry.created_at)}</td>
                <td class="px-4 py-2">
                    <button class="btn btn-small btn-secondary edit-entry" data-id="${escapeHtml(entry.id)}">Edit</button>
                    <button class="btn btn-small btn-danger delete-entry" data-id="${escapeHtml(entry.id)}">Delete</button>
                    <button class="btn btn-small btn-info view-api" data-id="${escapeHtml(entry.id)}">API Info</button>
                </td>
            </tr>
        `).join('');

        // Reattach event listeners
        attachEventListeners();
    }

    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) {
            return '';
        }
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function attachEventListeners() {
        // Reattach view full value button listeners
        document.querySelectorAll('.view-full-value').forEach(button => {
            button.addEventListener('click', function() {
                const valuePreview = this.parentElement.querySelector('.value-preview');
                const fullValue = document.getElementById('full-value');
                const valueModal = document.getElementById('value-modal');
                
                fullValue.textContent = valuePreview.textContent;
                valueModal.classList.remove('hidden');
            });
        });

        // Reattach delete button listeners
        document.querySelectorAll('.delete-entry').forEach(button => {
            button.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to delete this entry?')) {
                    return;
                }
                
                const entryId = this.dataset.id;
                try {
                    const formData = new FormData();
                    formData.append('id', entryId);
                    
                    const response = await fetch('/public/actions/delete_entry.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        // Remove the table row
                        this.closest('tr').remove();
                    } else {
                        alert(result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the entry');
                }
            });
        });

        // Reattach API info button listeners
        document.querySelectorAll('.view-api').forEach(button => {
            button.addEventListener('click', async function() {
                const entryId = this.dataset.id;
                const apiModal = document.getElementById('api-modal');
                const apiInfo = document.getElementById('api-info');
                
                try {
                    const response = await fetch(`/public/actions/get_entry_info.php?id=${entryId}&_t=${Date.now()}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        apiInfo.innerHTML = updateApiInfo(data);
                        apiModal.classList.remove('hidden');
                    } else {
                        alert(data.message || 'Failed to load API information');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while loading API information');
                }
            });
        });

        // Reattach edit button listeners
        document.querySelectorAll('.edit-entry').forEach(button => {
            button.addEventListener('click', async function() {
                const entryId = this.dataset.id;
                try {
                    const response = await fetch(`/public/actions/get_entry_info.php?id=${entryId}&_t=${Date.now()}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        document.getElementById('form-title').textContent = 'Edit Data Entry';
                        document.getElementById('entry-id').value = entryId;
                        entryForm.action = '/public/actions/update_entry.php';
                        
                        document.getElementById('type').value = data.type;
                        updateValueField(data.type); // Update value field based on type
                        
                        // Set value after field is updated
                        setTimeout(() => {
                            document.getElementById('value').value = data.value;
                            document.getElementById('note').value = data.note || '';
                            requireAuthCheckbox.checked = data.require_auth;
                            if (data.require_auth) {
                                accessKeyInput.value = data.access_key;
                            }
                            requireAuthCheckbox.dispatchEvent(new Event('change'));
                        }, 0);
                        
                        // Update disabled state checkbox
                        const disableToggle = document.getElementById('is_disabled');
                        if (disableToggle) {
                            disableToggle.checked = Boolean(data.is_disabled);
                        }
                        
                        entryModal.classList.remove('hidden');
                    } else {
                        alert(data.message || 'Failed to load entry information');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while loading entry information');
                }
            });
        });

        // Reattach copy button listeners
        document.querySelectorAll('.copy-button').forEach(button => {
            button.addEventListener('click', async function() {
                const textToCopy = this.dataset.copy;
                try {
                    await navigator.clipboard.writeText(textToCopy);
                    
                    const success = document.createElement('span');
                    success.className = 'copy-success';
                    success.textContent = 'Copied!';
                    this.parentNode.appendChild(success);
                    
                    setTimeout(() => success.remove(), 2000);
                } catch (err) {
                    console.error('Failed to copy:', err);
                }
            });
        });
    }

    // Add debounced search
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(searchEntries, 500);
    });

    // Immediate search for type filter
    typeFilter.addEventListener('change', searchEntries);

    // Initial search
    searchEntries();

    // Add search button click handler
    document.getElementById('search-btn').addEventListener('click', searchEntries);

    // Keep the existing input event for real-time search
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(searchEntries, 500);
    });

    // Add enter key support for search
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchEntries();
        }
    });

    // Handle disable toggle change
    const disableToggle = document.getElementById('is_disabled');
    if (disableToggle) {
        disableToggle.addEventListener('change', function() {
            const row = document.querySelector(`tr[data-id="${this.dataset.entryId}"]`);
            if (row) {
                if (this.checked) {
                    row.classList.add('entry-disabled');
                    const typeCell = row.querySelector('td:nth-child(2)');
                    if (!typeCell.querySelector('.disabled-badge')) {
                        typeCell.innerHTML += '<span class="disabled-badge">Disabled</span>';
                    }
                } else {
                    row.classList.remove('entry-disabled');
                    const badge = row.querySelector('.disabled-badge');
                    if (badge) {
                        badge.remove();
                    }
                }
            }
        });
    }
    
    // Update table row after save/update
    function updateTableRow(data) {
        const row = document.querySelector(`tr[data-id="${data.id}"]`);
        if (row) {
            // Update disabled state
            row.className = data.is_disabled ? 'entry-disabled' : '';
            
            // Update cells
            const cells = row.querySelectorAll('td');
            
            // Type cell (without disabled badge)
            cells[1].textContent = data.type;
            
            // Value cell
            cells[2].innerHTML = `
                <span>${formatValue(data.value, data.type)}</span>
                <button class="btn btn-small btn-secondary view-value" 
                        data-value="${escapeHtml(data.value)}"
                        data-type="${data.type}">View Full</button>
            `;
            
            // Note cell with disabled status
            const noteText = data.note || '-';
            cells[3].innerHTML = data.is_disabled ? 
                `<div class="note-with-status">
                    <span class="note-text">${noteText}</span>
                    <span class="status-indicator">Disabled</span>
                </div>` : 
                noteText;
        }
    }

    // Add helper function to format values
    function formatValue(value, type) {
        if (type === 'json' || type === 'array') {
            try {
                const parsed = JSON.parse(value);
                if (Array.isArray(parsed)) {
                    return `[${parsed.length} items]`;
                } else {
                    return `${Object.keys(parsed).length} properties`;
                }
            } catch (e) {
                return value;
            }
        }
        return value.length > 50 ? value.substring(0, 47) + '...' : value;
    }

    // Edit entry function - defined in global scope
    window.editEntry = function(id) {
        fetch(`/public/actions/get_entry_info.php?id=${id}&_t=${Date.now()}`)
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    const entry = response.data;
                    document.getElementById('entry-id').value = entry.id;
                    document.getElementById('type').value = entry.type;
                    updateValueField(entry.type, entry.value);
                    document.getElementById('note').value = entry.note || '';
                    document.getElementById('require_auth').checked = Boolean(entry.require_auth);
                    document.getElementById('access_key').value = entry.access_key || '';
                    
                    // Set disabled state
                    const disableToggle = document.getElementById('is_disabled');
                    if (disableToggle) {
                        disableToggle.checked = Boolean(parseInt(entry.is_disabled));
                        disableToggle.dataset.entryId = entry.id;
                    }
                    
                    toggleAccessKeyField();
                    
                    document.getElementById('form-title').textContent = 'Edit Data Entry';
                    entryForm.action = '/public/actions/update_entry.php';
                    entryModal.classList.remove('hidden');
                }
            })
            .catch(error => console.error('Error:', error));
    };

    function createTableRow(entry) {
        const row = document.createElement('tr');
        row.setAttribute('data-id', entry.id);
        if (entry.is_disabled) {
            row.classList.add('entry-disabled');
        }

        row.innerHTML = `
            <td class="px-4 py-2">${entry.id}</td>
            <td class="px-4 py-2">${entry.type}</td>
            <td class="px-4 py-2">${formatValue(entry.value, entry.type)}</td>
            <td class="px-4 py-2">
                ${entry.is_disabled ? 
                    `<div class="note-with-status">
                        <span class="note-text">${entry.note || '-'}</span>
                        <span class="status-indicator">Disabled</span>
                    </div>` : 
                    (entry.note || '-')
                }
            </td>
            <td class="px-4 py-2">${entry.require_auth ? 'Yes' : 'No'}</td>
            <td class="px-4 py-2">${entry.created_at}</td>
            <td class="px-4 py-2">
                <button onclick="editEntry(${entry.id})" class="btn btn-small btn-secondary">Edit</button>
                <button onclick="deleteEntry(${entry.id})" class="btn btn-small btn-danger">Delete</button>
                <button onclick="viewApiInfo(${entry.id})" class="btn btn-small btn-info">API Info</button>
            </td>
        `;
        return row;
    }
}); 