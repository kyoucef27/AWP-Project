// Initialize search functionality
function initializeSearch() {
    const $searchBox = $('#searchBox');
    if ($searchBox.length) {
        $searchBox.on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            const $table = $('.table tbody');
            if (!$table.length) return;
            
            $table.find('tr').each(function() {
                let text = $(this).text().toLowerCase();
                
                if (text.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
    }
}

// Edit teacher function
function editTeacher(teacherId) {
    // Fetch teacher data via AJAX
    $.ajax({
        url: '../api/get_teacher_data.php?id=' + teacherId,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Populate the edit form with existing data
                $('#edit_teacher_id').val(data.teacher.id);
                $('#edit_teacher_number').val(data.teacher.teacher_id || '');
                $('#edit_username').val(data.teacher.username);
                $('#edit_full_name').val(data.teacher.full_name);
                $('#edit_email').val(data.teacher.email);
                $('#edit_department').val(data.teacher.department || '');
                $('#edit_position').val(data.teacher.position || '');
                $('#edit_specialization').val(data.teacher.specialization || '');
                
                // Clear password field
                $('#edit_password').val('');
                
                // Show the modal
                $('#editTeacherModal').show();
            } else {
                alert('Error loading teacher data: ' + data.error);
            }
        },
        error: function() {
            alert('Error loading teacher data');
        }
    });
}

function closeEditModal() {
    $('#editTeacherModal').hide();
}

// File upload feedback
function initializeFileUpload() {
    const $csvFile = $('#csv_file');
    if ($csvFile.length) {
        $csvFile.on('change', function() {
            if (this.files.length > 0) {
                const $fileUpload = $('.file-upload');
                if ($fileUpload.length) {
                    $fileUpload.html(
                        '<div class="file-upload-icon">📁</div>' +
                        '<div>Selected: ' + this.files[0].name + '</div>' +
                        '<small style="color: #666;">File will be uploaded...</small>'
                    );
                }
            }
        });
    }
}

// Close modal when clicking outside of it
function initializeModals() {
    $(window).on('click', function(event) {
        const $editModal = $('#editTeacherModal');
        const $importModal = $('#importModal');
        
        if ($editModal.length && event.target === $editModal[0]) {
            $editModal.hide();
        }
        if ($importModal.length && event.target === $importModal[0]) {
            $importModal.hide();
        }
    });
}

function closeImportModal() {
    $('#importModal').hide();
}

// Initialize all functions when DOM is loaded
$(document).ready(function() {
    initializeSearch();
    initializeFileUpload();
    initializeModals();
});
