// Student Profile Page jQuery Functions

$(document).ready(function() {
    // Form validation for password change
    const $confirmPasswordField = $('form input[name="confirm_password"]');
    
    if ($confirmPasswordField.length) {
        $confirmPasswordField.on('input', function() {
            const newPassword = $('input[name="new_password"]').val();
            const confirmPassword = $(this).val();
            
            if (newPassword && confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // Auto-hide alerts after 5 seconds
    $('.alert').each(function() {
        const $alert = $(this);
        setTimeout(() => {
            $alert.css('opacity', '0');
            setTimeout(() => $alert.remove(), 300);
        }, 5000);
    });
});