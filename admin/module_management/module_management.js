// Module Management jQuery

// Search functionality
$(document).ready(function() {
    const $searchBox = $('#searchBox');
    if ($searchBox.length) {
        $searchBox.on('keyup', function() {
            var searchTerm = $(this).val().toLowerCase();
            $('tbody tr').each(function() {
                var text = $(this).text().toLowerCase();
                if (text.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
    }
});

// View module function
function viewModule(moduleId) {
    $.ajax({
        url: '../api/get_module_data.php?id=' + moduleId + '&view=full',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                $('#moduleDetails').html(data.html);
                $('#viewModuleModal').show();
            } else {
                alert('Error loading module data: ' + data.error);
            }
        },
        error: function() {
            alert('Error loading module data');
        }
    });
}

// Edit module function
function editModule(moduleId) {
    $.ajax({
        url: '../api/get_module_data.php?id=' + moduleId,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                const module = data.module;
                $('#edit_module_id').val(module.id);
                $('#edit_module_code').val(module.module_code);
                $('#edit_module_name').val(module.module_name);
                $('#edit_description').val(module.description || '');
                $('#edit_credits').val(module.credits);
                $('#edit_department').val(module.department);
                $('#edit_year_level').val(module.year_level);
                $('#edit_semester').val(module.semester);
                $('#edit_is_active').prop('checked', module.is_active == 1);
                
                $('#editModuleModal').show();
            } else {
                alert('Error loading module data: ' + data.error);
            }
        },
        error: function() {
            alert('Error loading module data');
        }
    });
}

// Close modal function
function closeModal(modalId) {
    $('#' + modalId).hide();
}

// Import modal functions
function openImportModal() {
    $('#importModal').show();
}

function closeImportModal() {
    $('#importModal').hide();
}

// Export modules function
function exportModules(format) {
    window.location.href = '../export_modules.php?format=' + format;
}

// Close modals when clicking outside
$(document).ready(function() {
    $(window).on('click', function(event) {
        var modals = ['viewModuleModal', 'editModuleModal', 'importModal'];
        modals.forEach(function(modalId) {
            var $modal = $('#' + modalId);
            if ($modal.length && event.target === $modal[0]) {
                $modal.hide();
            }
        });
    });
});
