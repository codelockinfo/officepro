<?php
/**
 * Saved Credentials Management Page
 */

$pageTitle = 'My Credentials';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1><i class="fas fa-key"></i> My Credentials</h1>
    <button onclick="openAddCredentialModal()" class="btn btn-primary custom-btn-primary"><i class="fas fa-plus"></i> Add Credential</button>
</div>

<div class="alert alert-warning">
    <strong><i class="fas fa-exclamation-triangle"></i> Security Warning:</strong> Passwords are stored in plain text. Only save credentials you're comfortable sharing within your organization.
</div>

<div class="card">
    <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; gap: 20px;">
        <input type="text" id="search-input" placeholder="Search by website or username..." class="form-control" style="flex: 1;" onkeyup="searchCredentials()">
        <select id="filter-select" class="form-control" style="width: 200px;" onchange="filterCredentials()">
            <option value="all">All Credentials</option>
            <option value="mine">My Credentials</option>
            <option value="shared">Shared with Me</option>
        </select>
    </div>
    
    <div id="credentials-list">
        <div style="text-align: center; padding: 40px;">
            <div class="loader"></div>
            <p>Loading credentials...</p>
        </div>
    </div>
</div>

<!-- Add/Edit Credential Modal -->
<div id="credential-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-title">Add Credential</h3>
            <button type="button" class="modal-close" onclick="closeModal('credential-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="credential-form" onsubmit="saveCredential(event)">
                <input type="hidden" id="credential-id" name="id">
                
                <div class="form-group">
                    <label class="form-label" for="website_name">Website Name *</label>
                    <input type="text" id="website_name" name="website_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="website_url">Website URL</label>
                    <input type="url" id="website_url" name="website_url" class="form-control" placeholder="https://example.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="text" id="password" name="password" class="form-control">
                    <small class="text-muted">Stored in plain text</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('credential-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Credential</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div id="share-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Share Credential</h3>
            <button type="button" class="modal-close" onclick="closeModal('share-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="share-credential-id">
            <p>Select employees to share this credential with:</p>
            <div id="employees-list" style="max-height: 300px; overflow-y: auto;">
                <div class="loader"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('share-modal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateSharing()">Save Sharing</button>
            </div>
        </div>
    </div>
</div>

<script>
    let credentials = [];
    let employees = [];
    
    // Load credentials on page load
    document.addEventListener('DOMContentLoaded', () => {
        loadCredentials();
        loadEmployees();
    });
    
    function loadCredentials() {
        ajaxRequest('/public_html/app/api/employee/credentials.php?action=list', 'GET', null, (response) => {
            if (response.success) {
                credentials = response.data;
                renderCredentials(credentials);
            } else {
                document.getElementById('credentials-list').innerHTML = '<p style="text-align: center; padding: 40px;">Failed to load credentials</p>';
            }
        });
    }
    
    function renderCredentials(data) {
        const list = document.getElementById('credentials-list');
        
        if (data.length === 0) {
            list.innerHTML = '<p style="text-align: center; padding: 40px;">No credentials found</p>';
            return;
        }
        
        let html = '<table class="table"><thead><tr><th>Website</th><th>URL</th><th>Username</th><th>Shared</th><th>Actions</th></tr></thead><tbody>';
        
        data.forEach(cred => {
            html += `
                <tr>
                    <td><strong>${escapeHtml(cred.website_name)}</strong></td>
                    <td>${cred.website_url ? `<a href="${escapeHtml(cred.website_url)}" target="_blank">Open</a>` : '-'}</td>
                    <td>${escapeHtml(cred.username || '-')}</td>
                    <td>${cred.is_shared ? '<span class="badge badge-primary">Shared</span>' : '-'}</td>
                    <td>
                        <button onclick="viewCredential(${cred.id})" class="btn btn-sm btn-primary">View</button>
                        ${cred.can_edit ? `
                            <button onclick="editCredential(${cred.id})" class="btn btn-sm btn-primary">Edit</button>
                            <button onclick="shareCredential(${cred.id})" class="btn btn-sm btn-primary">Share</button>
                            <button onclick="deleteCredential(${cred.id})" class="btn btn-sm btn-danger">Delete</button>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        list.innerHTML = html;
    }
    
    function searchCredentials() {
        const search = document.getElementById('search-input').value.toLowerCase();
        const filtered = credentials.filter(c => 
            c.website_name.toLowerCase().includes(search) ||
            (c.username && c.username.toLowerCase().includes(search))
        );
        renderCredentials(filtered);
    }
    
    function filterCredentials() {
        const filter = document.getElementById('filter-select').value;
        let filtered = credentials;
        
        if (filter === 'mine') {
            filtered = credentials.filter(c => c.can_edit);
        } else if (filter === 'shared') {
            filtered = credentials.filter(c => !c.can_edit);
        }
        
        renderCredentials(filtered);
    }
    
    function openAddCredentialModal() {
        document.getElementById('credential-form').reset();
        document.getElementById('credential-id').value = '';
        document.getElementById('modal-title').textContent = 'Add Credential';
        openModal('credential-modal');
    }
    
    function editCredential(id) {
        const cred = credentials.find(c => c.id === id);
        if (!cred) return;
        
        ajaxRequest(`/public_html/app/api/employee/credentials.php?action=view&id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const data = response.data;
                document.getElementById('credential-id').value = data.id;
                document.getElementById('website_name').value = data.website_name;
                document.getElementById('website_url').value = data.website_url || '';
                document.getElementById('username').value = data.username || '';
                document.getElementById('password').value = data.password || '';
                document.getElementById('notes').value = data.notes || '';
                document.getElementById('modal-title').textContent = 'Edit Credential';
                openModal('credential-modal');
            }
        });
    }
    
    function viewCredential(id) {
        ajaxRequest(`/public_html/app/api/employee/credentials.php?action=view&id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const data = response.data;
                const content = `
                    <div style="padding: 20px;">
                        <p><strong>Website:</strong> ${escapeHtml(data.website_name)}</p>
                        ${data.website_url ? `<p><strong>URL:</strong> <a href="${escapeHtml(data.website_url)}" target="_blank">${escapeHtml(data.website_url)}</a></p>` : ''}
                        ${data.username ? `<p><strong>Username:</strong> ${escapeHtml(data.username)}</p>` : ''}
                        ${data.password ? `<p><strong>Password:</strong> <code>${escapeHtml(data.password)}</code></p>` : ''}
                        ${data.notes ? `<p><strong>Notes:</strong><br>${escapeHtml(data.notes)}</p>` : ''}
                    </div>
                `;
                const footer = `<button class="btn btn-primary" onclick="closeModal(this.closest('.modal-overlay').id)">Close</button>`;
                createModal('Credential Details', content, footer);
            }
        });
    }
    
    function saveCredential(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        const id = data.id;
        delete data.id;
        
        const action = id ? 'update' : 'create';
        const url = id ? `/public_html/app/api/employee/credentials.php?action=${action}&id=${id}` : `/public_html/app/api/employee/credentials.php?action=${action}`;
        
        ajaxRequest(url, 'POST', data, (response) => {
            if (response.success) {
                showMessage('success', 'Credential saved successfully');
                closeModal('credential-modal');
                loadCredentials();
            } else {
                showMessage('error', response.message || 'Failed to save credential');
            }
        });
    }
    
    function deleteCredential(id) {
        confirmDialog(
            'This credential will be permanently deleted.',
            () => {
                ajaxRequest(`/public_html/app/api/employee/credentials.php?action=delete&id=${id}`, 'DELETE', null, (response) => {
                    if (response.success) {
                        showMessage('success', 'Credential deleted');
                        loadCredentials();
                    } else {
                        showMessage('error', response.message || 'Failed to delete');
                    }
                });
            },
            null,
            'Delete Credential',
            '<i class="fas fa-key"></i>'
        );
    }
    
    function loadEmployees() {
        ajaxRequest('/public_html/app/api/company/employees.php?action=list', 'GET', null, (response) => {
            if (response.success) {
                employees = response.data;
            }
        });
    }
    
    function shareCredential(id) {
        document.getElementById('share-credential-id').value = id;
        
        // Load current sharing
        ajaxRequest(`/public_html/app/api/employee/credentials.php?action=view&id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const sharedWith = response.data.shared_with || [];
                renderEmployeesList(sharedWith);
                openModal('share-modal');
            }
        });
    }
    
    function renderEmployeesList(selectedIds) {
        const list = document.getElementById('employees-list');
        let html = '';
        
        employees.forEach(emp => {
            const checked = selectedIds.includes(emp.id) ? 'checked' : '';
            html += `
                <div style="padding: 10px; border-bottom: 1px solid #ddd;">
                    <label style="cursor: pointer;">
                        <input type="checkbox" value="${emp.id}" ${checked} style="margin-right: 10px;">
                        ${escapeHtml(emp.full_name)} (${escapeHtml(emp.email)})
                    </label>
                </div>
            `;
        });
        
        list.innerHTML = html;
    }
    
    function updateSharing() {
        const credId = document.getElementById('share-credential-id').value;
        const checkboxes = document.querySelectorAll('#employees-list input[type="checkbox"]:checked');
        const sharedWith = Array.from(checkboxes).map(cb => parseInt(cb.value));
        
        ajaxRequest(`/public_html/app/api/employee/credentials.php?action=share`, 'POST', {
            id: credId,
            shared_with: sharedWith
        }, (response) => {
            if (response.success) {
                showMessage('success', 'Sharing updated');
                closeModal('share-modal');
                loadCredentials();
            } else {
                showMessage('error', response.message || 'Failed to update sharing');
            }
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>



