// Teacher Sessions JavaScript Functions

function showCreateModal() {
    document.getElementById('createModal').classList.add('show');
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('session_date').value = today;
}

function hideCreateModal() {
    document.getElementById('createModal').classList.remove('show');
}

function confirmDelete(sessionId) {
    if (confirm('Are you sure you want to delete this session? This action cannot be undone.')) {
        document.getElementById('deleteSessionId').value = sessionId;
        document.getElementById('deleteForm').submit();
    }
}

// Close modal when clicking outside
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideCreateModal();
    }
});

// Form validation
document.querySelector('#createModal form').addEventListener('submit', function(e) {
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    
    if (startTime >= endTime) {
        e.preventDefault();
        alert('End time must be after start time.');
    }
});