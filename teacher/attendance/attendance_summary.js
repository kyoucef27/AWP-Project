// Teacher Attendance Summary jQuery Functions

$(document).ready(function() {
    // Auto-submit form when report type changes
    $('#report_type').on('change', function() {
        // Clear specialty filter when switching to sessions report
        if ($(this).val() === 'sessions') {
            const $specialtySelect = $('#specialty');
            if ($specialtySelect.length) {
                $specialtySelect.val('');
            }
        }
        $(this).closest('form').submit();
    });

    // Auto-submit form when module changes
    $('#module_id').on('change', function() {
        if ($(this).val()) {
            $(this).closest('form').submit();
        }
    });
});