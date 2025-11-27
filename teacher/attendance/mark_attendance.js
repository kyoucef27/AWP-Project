// Teacher Mark Attendance jQuery Functions

function selectAll() {
    $('.student-checkbox').prop('checked', true).each(function() {
        updateStudentStatus(this);
    });
    updateSummary();
}

function selectNone() {
    $('.student-checkbox').prop('checked', false).each(function() {
        updateStudentStatus(this);
    });
    updateSummary();
}

function updateStudentStatus(checkbox) {
    const $studentItem = $(checkbox).closest('.student-item');
    const $statusElement = $studentItem.find('.attendance-status');
    
    if ($(checkbox).is(':checked')) {
        $studentItem.addClass('present');
        $statusElement.html('<span class="status-present"><i class="fas fa-check"></i> Present</span>');
    } else {
        $studentItem.removeClass('present');
        $statusElement.html('<span class="status-absent"><i class="fas fa-times"></i> Absent</span>');
    }
    
    updateSummary();
}

function updateSummary() {
    const totalCount = $('.student-checkbox').length;
    const presentCount = $('.student-checkbox:checked').length;
    const absentCount = totalCount - presentCount;
    
    $('#presentCount').text(presentCount);
    $('#absentCount').text(absentCount);
}

$(document).ready(function() {
    // Add change listeners to all checkboxes
    $('.student-checkbox').on('change', function() {
        updateStudentStatus(this);
    });
    
    // Initial summary update
    updateSummary();
});