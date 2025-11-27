// Search functionality
$(document).ready(function() {
    $('#searchBox').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        const $table = $('#studentsTable');
        const $rows = $table.find('tbody tr');
        
        $rows.each(function() {
            let text = $(this).text().toLowerCase();
            
            if (text.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // File upload feedback
    $('#excel_file').on('change', function() {
        if (this.files.length > 0) {
            $('.file-upload').html(
                '<div class="file-upload-icon">📁</div>' +
                '<div>Selected: ' + this.files[0].name + '</div>' +
                '<small style="color: #666;">File will be uploaded...</small>'
            );
        }
    });

    // Close modal when clicking outside of it
    $(window).on('click', function(event) {
        var $modal = $('#editStudentModal');
        if ($modal.length && event.target === $modal[0]) {
            $modal.hide();
        }
    });
});

// Edit student function
function editStudent(studentId) {
    // Fetch student data via AJAX
    $.ajax({
        url: '../api/get_student_data.php?id=' + studentId,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Populate the edit form with existing data
                $('#edit_student_id').val(data.student.id);
                $('#edit_student_number').val(data.student.student_number || '');
                $('#edit_username').val(data.student.username);
                $('#edit_full_name').val(data.student.full_name);
                $('#edit_email').val(data.student.email);
                $('#edit_specialization').val(data.student.specialization || '');
                $('#edit_year_of_study').val(data.student.year_of_study || '');
                
                // Clear password field
                $('#edit_password').val('');
                
                // Show the modal
                $('#editStudentModal').show();
            } else {
                alert('Error loading student data: ' + data.error);
            }
        },
        error: function() {
            alert('Error loading student data');
        }
    });
}

function closeEditModal() {
    $('#editStudentModal').hide();
}

// Export feedback
function showExportFeedback(format) {
    // Create a temporary notification
    const $notification = $('<div>')
        .css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: '#2ecc71',
            color: 'white',
            padding: '1rem',
            borderRadius: '8px',
            zIndex: '9999',
            boxShadow: '0 4px 20px rgba(0,0,0,0.2)'
        })
        .html(`📥 Preparing ${format} export...`);
    
    $('body').append($notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        $notification.remove();
    }, 3000);
}
