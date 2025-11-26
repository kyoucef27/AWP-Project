// Search functionality
document.getElementById('searchBox').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('studentsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        let row = rows[i];
        let text = row.textContent.toLowerCase();
        
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// Edit student function
function editStudent(studentId) {
    // Fetch student data via AJAX
    fetch('../api/get_student_data.php?id=' + studentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate the edit form with existing data
                document.getElementById('edit_student_id').value = data.student.id;
                document.getElementById('edit_student_number').value = data.student.student_number || '';
                document.getElementById('edit_username').value = data.student.username;
                document.getElementById('edit_full_name').value = data.student.full_name;
                document.getElementById('edit_email').value = data.student.email;
                document.getElementById('edit_specialization').value = data.student.specialization || '';
                document.getElementById('edit_year_of_study').value = data.student.year_of_study || '';
                
                // Clear password field
                document.getElementById('edit_password').value = '';
                
                // Show the modal
                document.getElementById('editStudentModal').style.display = 'block';
            } else {
                alert('Error loading student data: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading student data');
        });
}

function closeEditModal() {
    document.getElementById('editStudentModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    var modal = document.getElementById('editStudentModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// File upload feedback
document.getElementById('excel_file').addEventListener('change', function() {
    if (this.files.length > 0) {
        document.querySelector('.file-upload').innerHTML = 
            '<div class="file-upload-icon">📁</div>' +
            '<div>Selected: ' + this.files[0].name + '</div>' +
            '<small style="color: #666;">File will be uploaded...</small>';
    }
});

// Export feedback
function showExportFeedback(format) {
    // Create a temporary notification
    const notification = document.createElement('div');
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.background = '#2ecc71';
    notification.style.color = 'white';
    notification.style.padding = '1rem';
    notification.style.borderRadius = '8px';
    notification.style.zIndex = '9999';
    notification.style.boxShadow = '0 4px 20px rgba(0,0,0,0.2)';
    notification.innerHTML = `📥 Preparing ${format} export...`;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}
