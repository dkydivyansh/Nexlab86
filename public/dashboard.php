<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: /public/index.php');
    exit;
}

// Check if email is verified
$auth->checkVerification();

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../templates/header.php';

// Get user's data entries
$db = Database::getInstance();
$sql = "SELECT * FROM data_entries WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $db->query($sql, [$_SESSION['user_id']]);
$entries = $stmt->fetchAll();

// Get messages from session
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';

// Clear session messages
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<head><meta name="description" content="Secure your data with token authentication, real-time dashboard & comprehensive logging. Perfect for developers seeking robust API management."></head>
<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        <button class="btn btn-primary" id="new-entry-btn">New Data Entry</button>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Modal for data entry -->
    <div id="entry-modal" class="modal hidden">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="form-title">New Data Entry</h2>
            <form id="entry-form" method="POST" action="/public/actions/save_entry.php">
                <input type="hidden" name="id" id="entry-id">
                <div class="form-group">
                    <label for="type">Data Type</label>
                    <select name="type" id="type" class="form-control" required>
                        <option value="string">String</option>
                        <option value="number">Number</option>
                        <option value="float">Float</option>
                        <option value="boolean">Boolean</option>
                        <option value="json">JSON</option>
                        <option value="array">Array</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="value">Value</label>
                    <div id="value-container">
                        <!-- Dynamic input field will be inserted here -->
                    </div>
                    <small class="help-text" id="value-help"></small>
                </div>
                <div class="form-group">
                    <label for="note">Note (Optional)</label>
                    <textarea name="note" id="note" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="require_auth" id="require_auth">
                        Enable Access Key Authentication
                    </label>
                </div>
                <div class="form-group access-key-group hidden">
                    <label for="access_key">Access Key</label>
                    <div class="input-group">
                        <input type="text" name="access_key" id="access_key" class="form-control">
                        <button type="button" class="btn btn-secondary" id="generate-key">Generate Key</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="switch-label">
                        <span class="label-text">Temporarily Disable Data</span>
                        <div class="switch">
                            <input type="checkbox" name="is_disabled" id="is_disabled" 
                                   <?php echo isset($entry['is_disabled']) && $entry['is_disabled'] ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </div>
                        <small class="help-text">When disabled, the data cannot be accessed via the public API</small>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" id="cancel-entry">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Entries Table -->
    <div class="entries-table">
        <div class="table-filters">
            <div class="search-group">
                <input type="text" id="search-entries" class="form-control" placeholder="Search by ID, value, or notes...">
                <button class="btn btn-primary btn-search" id="search-btn">
                    <span class="search-text">Search</span>
                    <span class="search-loading hidden">
                        <span class="loader"></span>
                    </span>
                </button>
            </div>
            <select id="type-filter" class="form-control type-select">
                <option value="">All Types</option>
                <option value="string">String</option>
                <option value="number">Number</option>
                <option value="float">Float</option>
                <option value="boolean">Boolean</option>
                <option value="json">JSON</option>
                <option value="array">Array</option>
            </select>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Note</th>
                    <th>Auth Required</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr class="<?php echo $entry['is_disabled'] ? 'entry-disabled' : ''; ?>" data-id="<?php echo $entry['id']; ?>">
                    <td class="px-4 py-2"><?php echo htmlspecialchars($entry['id']); ?>&nbsp;</td>
                    <td class="px-4 py-2">
                        <?php echo htmlspecialchars($entry['type']); ?>
                    </td>
                    <td class="value-cell">
                        <?php 
                            $displayValue = $entry['value'];
                            $type = $entry['type'];
                            
                            if ($type === 'json' || $type === 'array') {
                                // Show truncated version for JSON/Array
                                $decoded = json_decode($displayValue, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    if (is_array($decoded)) {
                                        $count = count($decoded);
                                        $displayValue = "[{$count} items]";
                                    } else {
                                        $count = count((array)$decoded);
                                        $displayValue = "{$count} properties";
                                    }
                                }
                            } else {
                                // Truncate long values
                                if (strlen($displayValue) > 50) {
                                    $displayValue = substr($displayValue, 0, 47) . '...';
                                }
                            }
                        ?>
                        <span><?php echo htmlspecialchars($displayValue); ?></span>
                        <button class="btn btn-small btn-secondary view-value" 
                                data-value="<?php echo htmlspecialchars($entry['value']); ?>"
                                data-type="<?php echo htmlspecialchars($entry['type']); ?>">
                            View Full
                        </button>
                    </td>
                    <td>
                        <?php 
                            $noteText = $entry['note'] ? htmlspecialchars(substr($entry['note'], 0, 30)) : '-';
                            if ($entry['is_disabled']) {
                                echo '<div class="note-with-status">';
                                echo '<span class="note-text">' . $noteText . '</span>';
                                echo '<span class="status-indicator">Disabled</span>';
                                echo '</div>';
                            } else {
                                echo $noteText;
                            }
                        ?>
                    </td>
                    <td><?php echo $entry['require_auth'] ? 'Yes' : 'No'; ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($entry['created_at'])); ?></td>
                    <td>
                        <button class="btn btn-small btn-secondary edit-entry" data-id="<?php echo $entry['id']; ?>">Edit</button>
                        <button class="btn btn-small btn-danger delete-entry" data-id="<?php echo $entry['id']; ?>">Delete</button>
                        <button class="btn btn-small btn-info view-api" data-id="<?php echo $entry['id']; ?>">API Info</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for viewing full value -->
<div id="value-modal" class="modal hidden">
    <div class="modal-content large-modal">
        <div class="modal-header">
            <h2>Full Value</h2>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="copy-value">Copy Value</button>
                <span class="close">&times;</span>
            </div>
        </div>
        <div class="modal-body">
            <div class="value-type-label"></div>
            <pre id="full-value" class="json-display"></pre>
        </div>
    </div>
</div>

<!-- Modal for API Information -->
<div id="api-modal" class="modal hidden">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>API Information</h2>
        <div id="api-info"></div>
    </div>
</div>

<script>
    // Update the form action when editing
    document.addEventListener('DOMContentLoaded', function() {
        const entryForm = document.getElementById('entry-form');
        const newEntryBtn = document.getElementById('new-entry-btn');
        const typeSelect = document.getElementById('type');
        const valueContainer = document.getElementById('value-container');
        const valueHelp = document.getElementById('value-help');

        function updateValueField(type, currentValue = '') {
            let html = '';
            let helpText = '';
            
            switch(type) {
                case 'string':
                    html = `<textarea name="value" id="value" class="form-control" required>${currentValue}</textarea>`;
                    helpText = 'Enter any text value';
                    break;
                    
                case 'number':
                    html = `<input type="number" name="value" id="value" class="form-control" value="${currentValue}" required>`;
                    helpText = 'Enter a whole number (integer)';
                    break;
                    
                case 'float':
                    html = `<input type="number" name="value" id="value" step="0.01" class="form-control" value="${currentValue}" required>`;
                    helpText = 'Enter a decimal number';
                    break;
                    
                case 'boolean':
                    html = `
                        <select name="value" id="value" class="form-control" required>
                            <option value="true" ${currentValue === 'true' ? 'selected' : ''}>True</option>
                            <option value="false" ${currentValue === 'false' ? 'selected' : ''}>False</option>
                        </select>`;
                    helpText = 'Select true or false';
                    break;
                    
                case 'json':
                    if (currentValue) {
                        try {
                            // Clean up the JSON value
                            currentValue = currentValue.replace(/\\r\\n/g, '')
                                                     .replace(/\r\n/g, '\n')
                                                     .replace(/\\n/g, '\n')
                                                     .replace(/\\/g, '')
                                                     .replace(/\s+$/g, '');
                            const parsed = JSON.parse(currentValue);
                            currentValue = JSON.stringify(parsed, null, 2);
                        } catch (e) {
                            console.error('JSON parsing error:', e);
                        }
                    }
                    html = `<textarea name="value" id="value" class="form-control code-editor" spellcheck="false" required>${currentValue}</textarea>`;
                    helpText = 'Enter a valid JSON object or array. Example: {"key": "value"} or [1, 2, 3]';
                    break;
                    
                case 'array':
                    if (currentValue) {
                        try {
                            // Clean up the array value
                            currentValue = currentValue.replace(/\\r\\n/g, '')
                                                     .replace(/\r\n/g, '\n')
                                                     .replace(/\\n/g, '\n')
                                                     .replace(/\\/g, '')
                                                     .replace(/\s+$/g, '');
                            if (currentValue.startsWith('[')) {
                                const parsed = JSON.parse(currentValue);
                                currentValue = JSON.stringify(parsed, null, 2);
                            } else {
                                const values = currentValue.split(',').map(v => v.trim());
                                currentValue = JSON.stringify(values, null, 2);
                            }
                        } catch (e) {
                            console.error('Array parsing error:', e);
                        }
                    }
                    html = `<textarea name="value" id="value" class="form-control code-editor" spellcheck="false" required>${currentValue}</textarea>`;
                    helpText = 'Enter values separated by commas or a JSON array. Example: value1, value2, value3 or ["value1", "value2"]';
                    break;
            }
            
            valueContainer.innerHTML = html;
            valueHelp.textContent = helpText;

            // Add JSON formatting for code editors
            if (type === 'json' || type === 'array') {
                const editor = document.getElementById('value');
                editor.addEventListener('blur', function() {
                    try {
                        if (this.value.trim()) {
                            let parsed;
                            if (type === 'array' && !this.value.startsWith('[')) {
                                // Handle comma-separated values
                                parsed = this.value.split(',').map(item => item.trim());
                            } else {
                                parsed = JSON.parse(this.value);
                            }
                            this.value = JSON.stringify(parsed, null, 2);
                        }
                    } catch (e) {
                        // Invalid JSON, leave as is
                    }
                });
            }
        }

        // Listen for type changes
        typeSelect.addEventListener('change', function() {
            updateValueField(this.value);
        });

        // Initialize with default type
        updateValueField(typeSelect.value);

        newEntryBtn.addEventListener('click', () => {
            entryForm.reset();
            entryForm.action = '/public/actions/save_entry.php';
            document.getElementById('form-title').textContent = 'New Data Entry';
            document.getElementById('entry-id').value = '';
            updateValueField(typeSelect.value);
            entryModal.classList.remove('hidden');
        });

        document.querySelectorAll('.edit-entry').forEach(button => {
            button.addEventListener('click', () => {
                entryForm.action = '/public/actions/update_entry.php';
            });
        });

        // Add this after the updateValueField function
        function formatJsonInput(input) {
            try {
                // Remove escaped slashes
                input = input.replace(/\\/g, '');
                const parsed = JSON.parse(input);
                return JSON.stringify(parsed, null, 2);
            } catch (e) {
                return input;
            }
        }

        // Update the form submission
        entryForm.addEventListener('submit', function(e) {
            const type = typeSelect.value;
            const valueInput = document.getElementById('value');
            
            if (type === 'json' || type === 'array') {
                try {
                    let value = valueInput.value;
                    // Clean up the value before submission
                    value = value.replace(/\\r\\n/g, '')
                                .replace(/\r\n/g, '\n')
                                .replace(/\\n/g, '\n')
                                .replace(/\\/g, '')
                                .replace(/\s+$/g, '');

                    if (type === 'array' && !value.startsWith('[')) {
                        // Handle comma-separated values
                        const values = value.split(',').map(v => v.trim());
                        value = JSON.stringify(values);
                    } else {
                        // Parse and re-stringify to ensure proper format
                        const parsed = JSON.parse(value);
                        value = JSON.stringify(parsed);
                    }
                    valueInput.value = value;
                } catch (e) {
                    console.error('Error formatting input:', e);
                }
            }
        });
    });

    // Add this after your existing DOMContentLoaded event listener
    document.addEventListener('DOMContentLoaded', function() {
        const valueModal = document.getElementById('value-modal');
        const fullValue = document.getElementById('full-value');
        const copyValueBtn = document.getElementById('copy-value');
        const valueTypeLabel = document.querySelector('.value-type-label');

        // Function to format and display value based on type
        function displayFormattedValue(value, type) {
            let displayValue = value;
            let typeLabel = `Type: ${type.charAt(0).toUpperCase() + type.slice(1)}`;

            try {
                if (type === 'json' || type === 'array') {
                    // Clean up the value
                    value = value.replace(/\\r\\n/g, '')
                               .replace(/\r\n/g, '\n')
                               .replace(/\\n/g, '\n')
                               .replace(/\\/g, '')
                               .replace(/\s+$/g, '');
                    
                    const parsed = JSON.parse(value);
                    displayValue = JSON.stringify(parsed, null, 2);
                    
                    // Add item count for arrays
                    if (type === 'array' && Array.isArray(parsed)) {
                        typeLabel += ` (${parsed.length} items)`;
                    }
                    // Add property count for objects
                    else if (type === 'json' && typeof parsed === 'object') {
                        const count = Object.keys(parsed).length;
                        typeLabel += ` (${count} properties)`;
                    }
                }
            } catch (e) {
                console.error('Error formatting value:', e);
            }

            valueTypeLabel.textContent = typeLabel;
            fullValue.textContent = displayValue;
            fullValue.className = 'json-display' + (type === 'json' || type === 'array' ? ' code-format' : '');
        }

        // Handle view full value buttons
        document.querySelectorAll('.view-value').forEach(button => {
            button.addEventListener('click', function() {
                const value = this.dataset.value;
                const type = this.dataset.type;
                displayFormattedValue(value, type);
                valueModal.classList.remove('hidden');
            });
        });

        // Handle copy button
        copyValueBtn.addEventListener('click', function() {
            const textToCopy = fullValue.textContent;
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Copied!';
                this.classList.add('btn-success');
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.classList.remove('btn-success');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                this.textContent = 'Failed to copy';
                this.classList.add('btn-danger');
                
                setTimeout(() => {
                    this.textContent = 'Copy Value';
                    this.classList.remove('btn-danger');
                }, 2000);
            });
        });
    });
</script>

<script src="/assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?> 