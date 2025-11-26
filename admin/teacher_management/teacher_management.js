// Initialize search functionality
function initializeSearch() {
    const searchBox = document.getElementById('searchBox');
    if (searchBox) {
        searchBox.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.querySelector('.table tbody');
            if (!table) return;
            
            const rows = table.getElementsByTagName('tr');
            
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
    }
}

// Edit teacher function
function editTeacher(teacherId) {
    // Fetch teacher data via AJAX
    fetch('../api/get_teacher_data.php?id=' + teacherId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate the edit form with existing data
                document.getElementById('edit_teacher_id').value = data.teacher.id;
                document.getElementById('edit_teacher_number').value = data.teacher.teacher_id || '';
                document.getElementById('edit_username').value = data.teacher.username;
                document.getElementById('edit_full_name').value = data.teacher.full_name;
                document.getElementById('edit_email').value = data.teacher.email;
                document.getElementById('edit_department').value = data.teacher.department || '';
                document.getElementById('edit_position').value = data.teacher.position || '';
                document.getElementById('edit_specialization').value = data.teacher.specialization || '';
                
                // Clear password field
                document.getElementById('edit_password').value = '';
                
                // Show the modal
                document.getElementById('editTeacherModal').style.display = 'block';
            } else {
                alert('Error loading teacher data: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading teacher data');
        });
}

function closeEditModal() {
    document.getElementById('editTeacherModal').style.display = 'none';
}

// File upload feedback
function initializeFileUpload() {
    const csvFile = document.getElementById('csv_file');
    if (csvFile) {
        csvFile.addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileUpload = document.querySelector('.file-upload');
                if (fileUpload) {
                    fileUpload.innerHTML = 
                        '<div class="file-upload-icon">📁</div>' +
                        '<div>Selected: ' + this.files[0].name + '</div>' +
                        '<small style="color: #666;">File will be uploaded...</small>';
                }
            }
        });
    }
}

// Close modal when clicking outside of it
function initializeModals() {
    window.onclick = function(event) {
        const editModal = document.getElementById('editTeacherModal');
        const importModal = document.getElementById('importModal');
        
        if (event.target == editModal) {
            editModal.style.display = 'none';
        }
        if (event.target == importModal) {
            importModal.style.display = 'none';
        }
    }
}

function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
}

// Initialize all functions when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeSearch();
    initializeFileUpload();
    initializeModals();
});
