// Student Profile Page JavaScript Functions

// Form validation for password change
document.addEventListener('DOMContentLoaded', function() {
    const confirmPasswordField = document.querySelector('form input[name="confirm_password"]');
    
    if (confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function() {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = this.value;
            
            if (newPassword && confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});