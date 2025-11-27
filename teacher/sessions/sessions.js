// Teacher Sessions jQuery Functions

function showCreateModal() {
    $('#createModal').addClass('show');
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    $('#session_date').val(today);
}

function hideCreateModal() {
    $('#createModal').removeClass('show');
}

function confirmDelete(sessionId) {
    if (confirm('Are you sure you want to delete this session? This action cannot be undone.')) {
        $('#deleteSessionId').val(sessionId);
        $('#deleteForm').submit();
    }
}

$(document).ready(function() {
    // Close modal when clicking outside
    $('#createModal').on('click', function(e) {
        if (e.target === this) {
            hideCreateModal();
        }
    });

    // Form validation
    $('#createModal form').on('submit', function(e) {
        const startTime = $('#start_time').val();
        const endTime = $('#end_time').val();
        
        if (startTime >= endTime) {
            e.preventDefault();
            alert('End time must be after start time.');
        }
    });
});