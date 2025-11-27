// Student Attendance Page JavaScript Functions

function openJustificationModal(attendanceId, courseName, date) {
    document.getElementById('modal_attendance_id').value = attendanceId;
    document.getElementById('modal_course_name').textContent = courseName;
    document.getElementById('modal_date').textContent = date;
    document.getElementById('justificationModal').style.display = 'flex';
}

function closeJustificationModal() {
    document.getElementById('justificationModal').style.display = 'none';
    document.getElementById('justification_text').value = '';
    document.getElementById('document').value = '';
}

function viewJustification(justificationId) {
    // Open justification viewer in new tab - updated path for new folder structure
    window.open('../utilities/view_justification.php?id=' + justificationId, '_blank');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('justificationModal');
    if (event.target === modal) {
        closeJustificationModal();
    }
}