// Main JavaScript file for common functionality
if ("fonts" in document) {
    document.documentElement.classList.add('fonts-loading');
    
    Promise.all([
        document.fonts.load('1em CosmicOcto')
    ]).then(() => {
        document.documentElement.classList.remove('fonts-loading');
        document.documentElement.classList.add('fonts-loaded');
    });
}
document.addEventListener('DOMContentLoaded', function() {
    // Common message handling - exclude password reset messages
    const messages = document.querySelectorAll('.message:not(.password-reset-message)');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 300);
        }, 3000);
    });

    // Common modal handling
    const modals = document.querySelectorAll('.modal');
    const closeButtons = document.querySelectorAll('.modal .close');

    // Close modal when clicking outside
    modals.forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });

    // Close modal when clicking close button
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            button.closest('.modal').classList.add('hidden');
        });
    });

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            modals.forEach(modal => modal.classList.add('hidden'));
        }
    });

    // Handle tab switching for login/register
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', () => {
            // Update active tab
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            // Show/hide forms
            const formId = button.dataset.tab + '-form';
            document.querySelectorAll('.auth-form').forEach(form => {
                form.classList.add('hidden');
            });
            document.getElementById(formId).classList.remove('hidden');
        });
    });

    // Profile form validation
    const profileForm = document.querySelector('.profile-form');
    if (profileForm) {
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const currentPasswordInput = document.getElementById('current_password');

        profileForm.addEventListener('submit', function(e) {
            const errors = [];

            // Validate email
            const emailInput = document.getElementById('email');
            if (!emailInput.value) {
                errors.push('Email is required');
            } else if (!isValidEmail(emailInput.value)) {
                errors.push('Invalid email format');
            }

            // Validate password fields if any are filled
            if (newPasswordInput.value || confirmPasswordInput.value || currentPasswordInput.value) {
                if (!currentPasswordInput.value) {
                    errors.push('Current password is required to change password');
                }
                if (!newPasswordInput.value) {
                    errors.push('New password is required');
                }
                if (!confirmPasswordInput.value) {
                    errors.push('Password confirmation is required');
                }
                if (newPasswordInput.value !== confirmPasswordInput.value) {
                    errors.push('New passwords do not match');
                }
                if (newPasswordInput.value.length < 8) {
                    errors.push('Password must be at least 8 characters long');
                }
            }

            if (errors.length > 0) {
                e.preventDefault();
                const errorDiv = document.createElement('div');
                errorDiv.className = 'message error';
                errorDiv.innerHTML = errors.map(error => `<div>${error}</div>`).join('');
                
                // Remove any existing error messages
                const existingError = profileForm.querySelector('.message.error');
                if (existingError) {
                    existingError.remove();
                }
                
                // Insert error message at the top of the form
                profileForm.insertBefore(errorDiv, profileForm.firstChild);
                
                // Scroll to error message
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        // Real-time password match validation
        confirmPasswordInput.addEventListener('input', function() {
            if (newPasswordInput.value !== this.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        newPasswordInput.addEventListener('input', function() {
            if (confirmPasswordInput.value && this.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        });
    }
});

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
} 