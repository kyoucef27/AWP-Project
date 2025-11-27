// Student Attendance Page jQuery Functions

function openJustificationModal(attendanceId, courseName, date) {
    $('#modal_attendance_id').val(attendanceId);
    $('#modal_course_name').text(courseName);
    $('#modal_date').text(date);
    $('#justificationModal').css('display', 'flex');
}

function closeJustificationModal() {
    $('#justificationModal').css('display', 'none');
    $('#justification_text').val('');
    $('#document').val('');
}

function viewJustification(justificationId) {
    // Open justification viewer in new tab - updated path for new folder structure
    window.open('../utilities/view_justification.php?id=' + justificationId, '_blank');
}

$(document).ready(function() {
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        const modal = $('#justificationModal')[0];
        if (event.target === modal) {
            closeJustificationModal();
        }
    });
});