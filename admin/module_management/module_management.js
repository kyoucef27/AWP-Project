// Module Management JavaScript

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchBox = document.getElementById('searchBox');
    if (searchBox) {
        searchBox.addEventListener('keyup', function() {
            var searchTerm = this.value.toLowerCase();
            var rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});

// View module function
function viewModule(moduleId) {
    fetch('../api/get_module_data.php?id=' + moduleId + '&view=full')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('moduleDetails').innerHTML = data.html;
                document.getElementById('viewModuleModal').style.display = 'block';
            } else {
                alert('Error loading module data: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading module data');
        });
}

// Edit module function
function editModule(moduleId) {
    fetch('../api/get_module_data.php?id=' + moduleId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const module = data.module;
                document.getElementById('edit_module_id').value = module.id;
                document.getElementById('edit_module_code').value = module.module_code;
                document.getElementById('edit_module_name').value = module.module_name;
                document.getElementById('edit_description').value = module.description || '';
                document.getElementById('edit_credits').value = module.credits;
                document.getElementById('edit_department').value = module.department;
                document.getElementById('edit_year_level').value = module.year_level;
                document.getElementById('edit_semester').value = module.semester;
                document.getElementById('edit_is_active').checked = module.is_active == 1;
                
                document.getElementById('editModuleModal').style.display = 'block';
            } else {
                alert('Error loading module data: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading module data');
        });
}

// Close modal function
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Import modal functions
function openImportModal() {
    document.getElementById('importModal').style.display = 'block';
}

function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
}

// Export modules function
function exportModules(format) {
    window.location.href = '../export_modules.php?format=' + format;
}

// Close modals when clicking outside
window.onclick = function(event) {
    var modals = ['viewModuleModal', 'editModuleModal', 'importModal'];
    modals.forEach(function(modalId) {
        var modal = document.getElementById(modalId);
        if (modal && event.target == modal) {
            modal.style.display = 'none';
        }
    });
}
