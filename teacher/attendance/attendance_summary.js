// Teacher Attendance Summary JavaScript Functions

// Auto-submit form when report type changes
document.getElementById('report_type').addEventListener('change', function() {
    // Clear specialty filter when switching to sessions report
    if (this.value === 'sessions') {
        const specialtySelect = document.getElementById('specialty');
        if (specialtySelect) {
            specialtySelect.value = '';
        }
    }
    this.form.submit();
});

// Auto-submit form when module changes
document.getElementById('module_id').addEventListener('change', function() {
    if (this.value) {
        this.form.submit();
    }
});