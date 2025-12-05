/**
 * Modal System with Smooth Animations
 */

// Open modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.error('Modal not found:', modalId);
        return;
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Focus first input
    setTimeout(() => {
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) firstInput.focus();
    }, 300);
}

// Close modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.classList.add('closing');
    
    setTimeout(() => {
        modal.classList.remove('active', 'closing');
        document.body.style.overflow = '';
        
        // Clear form if exists
        const form = modal.querySelector('form');
        if (form) form.reset();
        
        // Clear errors
        const errors = modal.querySelectorAll('.form-error');
        errors.forEach(el => el.remove());
        
        const errorInputs = modal.querySelectorAll('.error');
        errorInputs.forEach(el => el.classList.remove('error'));
    }, 200);
}

// Create dynamic modal
function createModal(title, content, footer, size = '') {
    const modalId = 'dynamic-modal-' + Date.now();
    
    const modalHTML = `
        <div id="${modalId}" class="modal-overlay">
            <div class="modal-content ${size}">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button type="button" class="modal-close" onclick="closeModal('${modalId}')">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                ${footer ? `<div class="modal-footer">${footer}</div>` : ''}
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add event listeners
    setTimeout(() => {
        const modal = document.getElementById(modalId);
        
        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modalId);
            }
        });
        
        // Close on ESC key
        const escHandler = (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal(modalId);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
        
        openModal(modalId);
    }, 100);
    
    return modalId;
}

// Confirm dialog with custom styling
function confirmDialog(message, onConfirm, onCancel, title = 'Confirm Action', icon = '⚠️') {
    const content = `
        <div style="text-align: center; padding: 30px 20px;">
            <div style="font-size: 64px; margin-bottom: 20px;">${icon}</div>
            <h3 style="color: #333; margin-bottom: 15px;">${title}</h3>
            <p style="color: #666; font-size: 16px;">${message}</p>
        </div>
    `;
    
    const footer = `
        <button type="button" class="btn btn-secondary" onclick="confirmDialogCancel()">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="confirmDialogConfirm()">Confirm</button>
    `;
    
    const modalId = createModal('', content, footer, 'modal-sm');
    
    window.confirmDialogConfirm = () => {
        closeModal(modalId);
        if (onConfirm) onConfirm();
        delete window.confirmDialogConfirm;
        delete window.confirmDialogCancel;
    };
    
    window.confirmDialogCancel = () => {
        closeModal(modalId);
        if (onCancel) onCancel();
        delete window.confirmDialogConfirm;
        delete window.confirmDialogCancel;
    };
}

// Alert dialog
function alertDialog(message, type = 'info') {
    const icons = {
        success: '✓',
        error: '✗',
        warning: '⚠',
        info: 'ℹ'
    };
    
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#4da6ff'
    };
    
    const content = `
        <div style="text-align: center; padding: 20px;">
            <div style="font-size: 48px; color: ${colors[type]}; margin-bottom: 15px;">${icons[type]}</div>
            <p style="font-size: 16px;">${message}</p>
        </div>
    `;
    
    const footer = `<button type="button" class="btn btn-primary" onclick="closeModal(this.closest('.modal-overlay').id)">OK</button>`;
    
    createModal('', content, footer, 'modal-sm');
}

// Initialize modal close handlers
document.addEventListener('DOMContentLoaded', function() {
    // Close modals on overlay click
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) {
            closeModal(e.target.id);
        }
    });
    
    // Close modals on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
    
    // Initialize close buttons
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) closeModal(modal.id);
        });
    });
});



