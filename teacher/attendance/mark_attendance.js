// Teacher Mark Attendance JavaScript Functions

function selectAll() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
        updateStudentStatus(checkbox);
    });
    updateSummary();
}

function selectNone() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
        updateStudentStatus(checkbox);
    });
    updateSummary();
}

function updateStudentStatus(checkbox) {
    const studentItem = checkbox.closest('.student-item');
    const statusElement = studentItem.querySelector('.attendance-status');
    
    if (checkbox.checked) {
        studentItem.classList.add('present');
        statusElement.innerHTML = '<span class="status-present"><i class="fas fa-check"></i> Present</span>';
    } else {
        studentItem.classList.remove('present');
        statusElement.innerHTML = '<span class="status-absent"><i class="fas fa-times"></i> Absent</span>';
    }
    
    updateSummary();
}

function updateSummary() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const presentCount = document.querySelectorAll('.student-checkbox:checked').length;
    const totalCount = checkboxes.length;
    const absentCount = totalCount - presentCount;
    
    document.getElementById('presentCount').textContent = presentCount;
    document.getElementById('absentCount').textContent = absentCount;
}

// Add change listeners to all checkboxes
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateStudentStatus(this);
        });
    });
    
    // Initial summary update
    updateSummary();
});