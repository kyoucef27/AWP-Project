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

    let html = `
        <td class="name-cell">${student.lastName}</td>
        <td class="name-cell">${student.firstName}</td>
    `;

    for (let i = 1; i <= 6; i++) {
        const session = student.sessions[`s${i}`];
        html += `
            <td>${session.p ? '✓' : '✗'}</td>
            <td>${session.pa ? '✓' : '✗'}</td>
        `;
    }

    html += `
        <td><strong>${stats.absences}</strong></td>
        <td><strong>${stats.participations}</strong></td>
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
                    p: document.getElementById(`s${i}_p`).checked,
                    pa: document.getElementById(`s${i}_pa`).checked
                };
            }

            const newStudent = {
                lastName,
                firstName,
                sessions
            };

            addStudentToAttendanceTable(newStudent);
            showSuccessMessage('Student successfully added!');
            updateDashboardStats();
            this.reset();
            
            setTimeout(() => {
                showSection('attendance');
            }, 1000);
        });
    }
}

function addStudent(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const studentData = {
        studentId: formData.get('studentId'),
        lastName: formData.get('lastName'),
        firstName: formData.get('firstName'),
        course: formData.get('course'),
        present: formData.get('present'),
        participated: formData.get('participated'),
        sessionDate: formData.get('sessionDate')
    };

    if (!validateStudentData(studentData)) {
        return;
    }

    addStudentToTable(studentData);
    
    event.target.reset();
    
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('sessionDate').value = today;
    
    showMessage('Student added successfully!', 'success');
    
    showSection('attendance');
}
function validateStudentData(data) {
    const existingRows = document.querySelectorAll('#attendance-tbody tr');
    for (let row of existingRows) {
        const existingId = row.cells[0].textContent.trim();
        if (existingId === data.studentId) {
            showMessage('Student ID already exists!', 'error');
            return false;
        }
    }

    const requiredFields = ['studentId', 'lastName', 'firstName', 'course', 'present', 'participated', 'sessionDate'];
    for (let field of requiredFields) {
        if (!data[field] || data[field].trim() === '') {
            showMessage(`Please fill in the ${field.replace(/([A-Z])/g, ' $1').toLowerCase()} field.`, 'error');
            return false;
        }
    }

    return true;
}

function addStudentToTable(studentData) {
    const tbody = document.getElementById('attendance-tbody');
    const newRow = document.createElement('tr');
    
    const presentClass = studentData.present === 'Yes' ? 'present' : 'absent';
    const participatedClass = studentData.participated === 'Yes' ? 'participated' : 'not-participated';
    
    newRow.innerHTML = `
        <td>${studentData.studentId}</td>
        <td>${studentData.lastName}</td>
        <td>${studentData.firstName}</td>
        <td>${studentData.course}</td>
        <td><span class="status ${presentClass}">${studentData.present}</span></td>
        <td><span class="status ${participatedClass}">${studentData.participated}</span></td>
        <td>${studentData.sessionDate}</td>
    `;
    
    tbody.appendChild(newRow);
    updateDashboardStats();
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
        const cells = rows[i].getElementsByTagName('td');
        
        let hasPresence = false;
        let hasParticipation = false;

        for (let j = 2; j < 14; j += 2) {
            if (cells[j] && cells[j].textContent.trim() === '✓') {
                hasPresence = true;
                break;
            }
        }

        for (let j = 3; j < 14; j += 2) {
            if (cells[j] && cells[j].textContent.trim() === '✓') {
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

    document.getElementById('detailedReportSection').classList.add('show');

    document.getElementById('detailedReportSection').scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
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
    const showReportBtn = document.getElementById('showReportBtn');
    if (showReportBtn) {
        showReportBtn.addEventListener('click', generateDetailedReport);
    }
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
        courses: new Set(),
        reportDate: new Date().toLocaleDateString()
    };
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const course = cells[3].textContent;
        const isPresent = row.querySelector('.status.present');
        const participated = row.querySelector('.status.participated');
        
        data.courses.add(course);
        
        if (isPresent) {
            data.presentStudents++;
        } else {
            data.absentStudents++;
        }
        
        if (participated) {
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
            <li><strong>Courses Covered:</strong> ${Array.from(data.courses).join(', ')}</li>
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