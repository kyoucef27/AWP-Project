const hamburger = document.querySelector('.hamburger');
const navMenu = document.querySelector('.nav-menu');
const navLinks = document.querySelectorAll('.nav-link');
const contentSections = document.querySelectorAll('.content-section');

const initialStudents = [
    {
        lastName: 'Ahmed',
        firstName: 'Sara',
        sessions: {
            s1: { p: false, pa: false },
            s2: { p: true, pa: true },
            s3: { p: false, pa: false },
            s4: { p: false, pa: false },
            s5: { p: false, pa: false },
            s6: { p: false, pa: false }
        }
    },
    {
        lastName: 'Yacine',
        firstName: 'Ali',
        sessions: {
            s1: { p: true, pa: true },
            s2: { p: true, pa: true },
            s3: { p: true, pa: true },
            s4: { p: true, pa: true },
            s5: { p: true, pa: false },
            s6: { p: false, pa: false }
        }
    },
    {
        lastName: 'Houcine',
        firstName: 'Rania',
        sessions: {
            s1: { p: false, pa: false },
            s2: { p: true, pa: true },
            s3: { p: false, pa: false },
            s4: { p: true, pa: true },
            s5: { p: false, pa: false },
            s6: { p: false, pa: false }
        }
    }
];

document.addEventListener('DOMContentLoaded', function() {
    initializeMobileMenu();
    initializeAttendanceTable();
    setupFormValidation();
    setupReportButton();
    setupJQueryFeatures();
    showSection('home');
    updateDashboardStats();
});
function initializeMobileMenu() {
    if (hamburger && navMenu) {
        hamburger.addEventListener('click', toggleMobileMenu);
    }

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (navMenu.classList.contains('active')) {
                toggleMobileMenu();
            }
        });
    });
}

function toggleMobileMenu() {
    hamburger.classList.toggle('active');
    navMenu.classList.toggle('active');
}

function showSection(sectionId) {
    contentSections.forEach(section => {
        section.classList.remove('active');
    });

    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('onclick')?.includes(sectionId)) {
            link.classList.add('active');
        }
    });
}

function calculateStats(sessions) {
    let absences = 0;
    let participations = 0;

    for (let i = 1; i <= 6; i++) {
        const session = sessions[`s${i}`];
        if (!session.p) absences++;
        if (session.pa) participations++;
    }

    return { absences, participations };
}

function getStatusClass(absences) {
    if (absences < 3) return 'status-green';
    if (absences >= 3 && absences <= 4) return 'status-yellow';
    return 'status-red';
}

function getMessage(absences, participations) {
    if (absences < 3) {
        return 'Good attendance – Excellent participation';
    } else if (absences >= 3 && absences <= 4) {
        return 'Warning – attendance low – You need to participate more';
    } else {
        return 'Excluded – too many absences – You need to participate more';
    }
}

function addStudentToAttendanceTable(student) {
    const tbody = document.getElementById('attendance-tbody');
    const stats = calculateStats(student.sessions);
    const statusClass = getStatusClass(stats.absences);
    const message = getMessage(stats.absences, stats.participations);

    const row = document.createElement('tr');
    row.className = statusClass;
    row.setAttribute('data-student', `${student.lastName}-${student.firstName}`);

    let html = `
        <td class="name-cell">${student.lastName}</td>
        <td class="name-cell">${student.firstName}</td>
    `;

    for (let i = 1; i <= 6; i++) {
        const session = student.sessions[`s${i}`];
        const studentId = `${student.lastName}-${student.firstName}`.replace(/\s/g, '-');
        html += `
            <td><input type="checkbox" class="attendance-checkbox" data-session="s${i}" data-type="p" ${session.p ? 'checked' : ''} onchange="updateAttendance(this)"></td>
            <td><input type="checkbox" class="attendance-checkbox" data-session="s${i}" data-type="pa" ${session.pa ? 'checked' : ''} onchange="updateAttendance(this)"></td>
        `;
    }

    html += `
        <td class="absences-cell"><strong>${stats.absences}</strong></td>
        <td class="participation-cell"><strong>${stats.participations}</strong></td>
        <td class="message-cell">${message}</td>
    `;

    row.innerHTML = html;
    tbody.appendChild(row);
}

function initializeAttendanceTable() {
    initialStudents.forEach(student => addStudentToAttendanceTable(student));
}

function validateFormField(fieldId, regex, errorId) {
    const field = document.getElementById(fieldId);
    const error = document.getElementById(errorId);
    
    field.classList.remove('error');
    error.classList.remove('show');
    
    if (!field.value.trim() || !regex.test(field.value.trim())) {
        field.classList.add('error');
        error.classList.add('show');
        return false;
    }
    return true;
}

function validateNewStudentForm() {
    let isValid = true;

    const numberRegex = /^[0-9]+$/;
    const letterRegex = /^[a-zA-Z\s]+$/;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!validateFormField('studentId', numberRegex, 'studentIdError')) isValid = false;
    if (!validateFormField('lastName', letterRegex, 'lastNameError')) isValid = false;
    if (!validateFormField('firstName', letterRegex, 'firstNameError')) isValid = false;
    if (!validateFormField('email', emailRegex, 'emailError')) isValid = false;

    return isValid;
}

function showSuccessMessage(message) {
    const successMsg = document.getElementById('successMessage');
    if (successMsg) {
        successMsg.textContent = '✓ ' + message;
        successMsg.classList.add('show');

        setTimeout(() => {
            successMsg.classList.remove('show');
        }, 4000);
    }
}

function setupFormValidation() {
    const studentForm = document.getElementById('studentForm');
    if (studentForm) {
        studentForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!validateNewStudentForm()) {
                return;
            }

            const lastName = document.getElementById('lastName').value.trim();
            const firstName = document.getElementById('firstName').value.trim();

            const sessions = {};
            for (let i = 1; i <= 6; i++) {
                sessions[`s${i}`] = {
                    p: false,
                    pa: false
                };
            }

            const newStudent = {
                lastName,
                firstName,
                sessions
            };

            addStudentToAttendanceTable(newStudent);
            showSuccessMessage('Student successfully added! You can now mark their attendance in the Attendance List.');
            updateDashboardStats();
            this.reset();
            
            setTimeout(() => {
                showSection('attendance');
            }, 1500);
        });
    }
}


function updateDashboardStats() {
    const rows = document.querySelectorAll('#attendance-tbody tr');
    const totalStudents = rows.length;
    
    let presentCount = 0;
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        for (let i = 2; i < 14; i += 2) {
            if (cells[i] && cells[i].textContent.trim() === '✓') {
                presentCount++;
                return;
            }
        }
    });
    
    const attendanceRate = totalStudents > 0 ? Math.round((presentCount / totalStudents) * 100) : 0;
    
    const cards = document.querySelectorAll('.card-number');
    if (cards.length >= 3) {
        cards[0].textContent = totalStudents;
        cards[1].textContent = presentCount;
        cards[2].textContent = attendanceRate + '%';
    }
}

function generateDetailedReport() {
    const tbody = document.getElementById('attendance-tbody');
    const rows = tbody.getElementsByTagName('tr');
    
    const totalStudents = rows.length;
    let studentsPresent = 0;
    let studentsParticipated = 0;

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const checkboxes = row.querySelectorAll('.attendance-checkbox');
        
        let hasPresence = false;
        let hasParticipation = false;

        for (let j = 0; j < checkboxes.length; j += 2) {
            const presenceCheckbox = checkboxes[j];
            if (presenceCheckbox && presenceCheckbox.checked) {
                hasPresence = true;
                break;
            }
        }

        for (let j = 1; j < checkboxes.length; j += 2) {
            const participationCheckbox = checkboxes[j];
            if (participationCheckbox && participationCheckbox.checked) {
                hasParticipation = true;
                break;
            }
        }

        if (hasPresence) studentsPresent++;
        if (hasParticipation) studentsParticipated++;
    }

    document.getElementById('totalStudentsDetailed').textContent = totalStudents;
    document.getElementById('studentsPresentDetailed').textContent = studentsPresent;
    document.getElementById('studentsParticipatedDetailed').textContent = studentsParticipated;

    createBarChart(totalStudents, studentsPresent, studentsParticipated);

    showSection('reports');

    setTimeout(() => {
        document.getElementById('detailedReportSection').classList.add('show');
        document.getElementById('detailedReportSection').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }, 100);
}

function createBarChart(total, present, participated) {
    const chartContainer = document.getElementById('barChart');
    chartContainer.innerHTML = '';

    const maxValue = Math.max(total, present, participated);
    
    const data = [
        { label: 'Total Students', value: total, color: '#3498db' },
        { label: 'Students Present', value: present, color: '#27ae60' },
        { label: 'Students Participated', value: participated, color: '#e74c3c' }
    ];

    data.forEach(item => {
        const barWrapper = document.createElement('div');
        barWrapper.style.flex = '1';
        barWrapper.style.display = 'flex';
        barWrapper.style.flexDirection = 'column';
        barWrapper.style.alignItems = 'center';

        const bar = document.createElement('div');
        bar.className = 'bar';
        const heightPercent = maxValue > 0 ? (item.value / maxValue) * 100 : 0;
        bar.style.height = heightPercent + '%';
        bar.style.background = `linear-gradient(180deg, ${item.color} 0%, ${item.color}dd 100%)`;

        const barValue = document.createElement('div');
        barValue.className = 'bar-value';
        barValue.textContent = item.value;
        bar.appendChild(barValue);

        const barLabel = document.createElement('div');
        barLabel.className = 'bar-label';
        barLabel.textContent = item.label;

        barWrapper.appendChild(bar);
        barWrapper.appendChild(barLabel);
        chartContainer.appendChild(barWrapper);
    });
}

function setupReportButton() {
    setTimeout(() => {
        const showReportBtn = document.getElementById('showReportBtn');
        if (showReportBtn) {
            showReportBtn.removeEventListener('click', generateDetailedReport);
            showReportBtn.addEventListener('click', generateDetailedReport);
            console.log('Report button setup complete');
        } else {
            console.log('Report button not found');
        }
    }, 100);
}

function updateAttendance(checkbox) {
    const row = checkbox.closest('tr');
    const checkboxes = row.querySelectorAll('.attendance-checkbox');
    
    let absences = 0;
    let participations = 0;
    
    for (let i = 0; i < checkboxes.length; i += 2) {
        const presenceCheckbox = checkboxes[i];
        const participationCheckbox = checkboxes[i + 1];
        
        if (!presenceCheckbox.checked) {
            absences++;
        }
        
        if (participationCheckbox.checked) {
            participations++;
        }
    }
    
    const absencesCell = row.querySelector('.absences-cell strong');
    const participationCell = row.querySelector('.participation-cell strong');
    const messageCell = row.querySelector('.message-cell');
    
    absencesCell.textContent = absences;
    participationCell.textContent = participations;
    
    const statusClass = getStatusClass(absences);
    row.className = statusClass;
    
    const message = getMessage(absences, participations);
    messageCell.textContent = message;
    
    updateDashboardStats();
}

function setupJQueryFeatures() {
    $(document).ready(function() {
        $('#showReportBtn').off('click').on('click', function() {
            console.log('Show Report button clicked');
            generateDetailedReport();
        });

        $('#searchName').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $('#attendance-tbody tr').filter(function() {
                const lastName = $(this).find('td').eq(0).text().toLowerCase();
                const firstName = $(this).find('td').eq(1).text().toLowerCase();
                const fullName = lastName + ' ' + firstName;
                const match = fullName.indexOf(searchTerm) > -1;
                
                $(this).toggle(match);
                return !match;
            });

            const visibleRows = $('#attendance-tbody tr:visible').length;
            if (searchTerm === '') {
                $('#sortStatus').text('Ready to search or sort');
            } else {
                $('#sortStatus').text(`Found ${visibleRows} student(s) matching "${$(this).val()}"`);
            }
        });

        $('#sortAbsencesAsc').on('click', function() {
            sortTable('absences', 'asc');
            $('#sortStatus').text('Currently sorted by Absences (Ascending)');
        });

        $('#sortParticipationDesc').on('click', function() {
            sortTable('participation', 'desc');
            $('#sortStatus').text('Currently sorted by Participation (Descending)');
        });

        $('#attendance-tbody').on('mouseenter', 'tr', function() {
            $(this).addClass('row-hover');
        });

        $('#attendance-tbody').on('mouseleave', 'tr', function() {
            $(this).removeClass('row-hover');
        });

        $('#attendance-tbody').on('click', 'tr', function(e) {
            if ($(e.target).is('input[type="checkbox"]')) {
                return;
            }

            const lastName = $(this).find('td').eq(0).text();
            const firstName = $(this).find('td').eq(1).text();
            const absences = $(this).find('.absences-cell strong').text();
            
            showStudentModal(lastName, firstName, absences);
        });

        $('#highlightExcellentBtn').on('click', function() {
            $('#attendance-tbody tr').each(function() {
                const absences = parseInt($(this).find('.absences-cell strong').text());
                
                if (absences < 3) {
                    $(this).addClass('excellent-highlight');
                    $(this).fadeOut(300).fadeIn(300).fadeOut(300).fadeIn(300);
                }
            });
        });

        $('#resetColorsBtn').on('click', function() {
            $('#attendance-tbody tr').removeClass('excellent-highlight');
            $('#attendance-tbody tr').stop(true, true).show();
        });
    });
}

function sortTable(criterion, order) {
    const tbody = $('#attendance-tbody');
    const rows = tbody.find('tr').get();

    rows.sort(function(a, b) {
        let valA, valB;

        if (criterion === 'absences') {
            valA = parseInt($(a).find('.absences-cell strong').text());
            valB = parseInt($(b).find('.absences-cell strong').text());
        } else if (criterion === 'participation') {
            valA = parseInt($(a).find('.participation-cell strong').text());
            valB = parseInt($(b).find('.participation-cell strong').text());
        }

        if (order === 'asc') {
            return valA - valB;
        } else {
            return valB - valA;
        }
    });

    $.each(rows, function(index, row) {
        tbody.append(row);
    });
}

function showStudentModal(lastName, firstName, absences) {
    const overlay = $('<div class="modal-overlay"></div>');
    const modal = $(`
        <div class="student-info-modal">
            <div class="modal-header">Student Information</div>
            <div class="modal-content">
                <p><strong>Full Name:</strong> ${firstName} ${lastName}</p>
                <p><strong>Number of Absences:</strong> ${absences}</p>
            </div>
            <button class="modal-close-btn">Close</button>
        </div>
    `);
    
    $('body').append(overlay);
    $('body').append(modal);
    
    overlay.on('click', function() {
        overlay.fadeOut(300, function() { $(this).remove(); });
        modal.fadeOut(300, function() { $(this).remove(); });
    });
    
    modal.find('.modal-close-btn').on('click', function() {
        overlay.fadeOut(300, function() { $(this).remove(); });
        modal.fadeOut(300, function() { $(this).remove(); });
    });
}

function generateReport(reportType) {
    const reportData = getReportData(reportType);
    
    const reportContent = createReportContent(reportType, reportData);
    
    displayReport(reportContent, reportType);
    
    showMessage(`${reportType.charAt(0).toUpperCase() + reportType.slice(1)} report generated successfully!`, 'success');
}

function getReportData(reportType) {
    const rows = document.querySelectorAll('#attendance-tbody tr');
    const data = {
        totalStudents: rows.length,
        presentStudents: 0,
        absentStudents: 0,
        participatedStudents: 0,
        reportDate: new Date().toLocaleDateString()
    };
    
    rows.forEach(row => {
        const checkboxes = row.querySelectorAll('.attendance-checkbox');
        
        let hasPresence = false;
        let hasParticipation = false;

        for (let j = 0; j < checkboxes.length; j += 2) {
            if (checkboxes[j] && checkboxes[j].checked) {
                hasPresence = true;
            }
        }

        for (let j = 1; j < checkboxes.length; j += 2) {
            if (checkboxes[j] && checkboxes[j].checked) {
                hasParticipation = true;
            }
        }
        
        if (hasPresence) {
            data.presentStudents++;
        } else {
            data.absentStudents++;
        }
        
        if (hasParticipation) {
            data.participatedStudents++;
        }
    });
    
    data.attendanceRate = data.totalStudents > 0 ? 
        Math.round((data.presentStudents / data.totalStudents) * 100) : 0;
    data.participationRate = data.totalStudents > 0 ? 
        Math.round((data.participatedStudents / data.totalStudents) * 100) : 0;
    
    return data;
}

function createReportContent(reportType, data) {
    return `
        <h2>${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Attendance Report</h2>
        <p><strong>Report Date:</strong> ${data.reportDate}</p>
        <hr>
        <h3>Summary Statistics</h3>
        <ul>
            <li><strong>Total Students:</strong> ${data.totalStudents}</li>
            <li><strong>Present Students:</strong> ${data.presentStudents}</li>
            <li><strong>Absent Students:</strong> ${data.absentStudents}</li>
            <li><strong>Attendance Rate:</strong> ${data.attendanceRate}%</li>
            <li><strong>Participation Rate:</strong> ${data.participationRate}%</li>
        </ul>
    `;
}
function displayReport(content, reportType) {
    const reportWindow = window.open('', '_blank', 'width=800,height=600');
    reportWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Report</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 40px; 
                    line-height: 1.6; 
                }
                h2 { 
                    color: #2c3e50; 
                    border-bottom: 2px solid #3498db; 
                    padding-bottom: 10px; 
                }
                h3 { 
                    color: #34495e; 
                    margin-top: 30px; 
                }
                ul { 
                    list-style-type: none; 
                    padding: 0; 
                }
                li { 
                    padding: 8px 0; 
                    border-bottom: 1px solid #ecf0f1; 
                }
                hr { 
                    border: none; 
                    height: 1px; 
                    background-color: #bdc3c7; 
                    margin: 20px 0; 
                }
                @media print {
                    body { margin: 20px; }
                }
            </style>
        </head>
        <body>
            ${content}
            <br><br>
            <button onclick="window.print()" style="
                background-color: #3498db; 
                color: white; 
                padding: 10px 20px; 
                border: none; 
                border-radius: 4px; 
                cursor: pointer;
                margin-right: 10px;
            ">Print Report</button>
            <button onclick="window.close()" style="
                background-color: #95a5a6; 
                color: white; 
                padding: 10px 20px; 
                border: none; 
                border-radius: 4px; 
                cursor: pointer;
            ">Close</button>
        </body>
        </html>
    `);
    reportWindow.document.close();
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        showMessage('Logging out...', 'info');
        
        setTimeout(() => {
            showMessage('You have been logged out successfully!', 'success');
            showSection('home');
        }, 1000);
    }
}
function showMessage(message, type = 'info') {
    const existingMessage = document.querySelector('.message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 4px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        max-width: 300px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        animation: slideIn 0.3s ease;
    `;
    
    switch (type) {
        case 'success':
            messageDiv.style.backgroundColor = '#27ae60';
            break;
        case 'error':
            messageDiv.style.backgroundColor = '#e74c3c';
            break;
        case 'info':
            messageDiv.style.backgroundColor = '#3498db';
            break;
        default:
            messageDiv.style.backgroundColor = '#95a5a6';
    }
    
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 300);
        }
    }, 3000);
}
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

document.addEventListener('DOMContentLoaded', updateDashboardStats);