// Chart.js configurations
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
        }
    }
};

// User Growth Chart
function initUserGrowthChart(monthlyData) {
    const userGrowthCtx = $('#userGrowthChart')[0].getContext('2d');
    
    new Chart(userGrowthCtx, {
        type: 'line',
        data: {
            labels: monthlyData.map(d => d.month),
            datasets: [{
                label: 'Students',
                data: monthlyData.map(d => d.students),
                borderColor: '#2ecc71',
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                fill: true
            }, {
                label: 'Professors',
                data: monthlyData.map(d => d.professors),
                borderColor: '#e74c3c',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                fill: true
            }]
        },
        options: chartOptions
    });
}

// User Distribution Chart
function initUserDistributionChart(stats) {
    const userDistCtx = $('#userDistributionChart')[0].getContext('2d');
    new Chart(userDistCtx, {
        type: 'doughnut',
        data: {
            labels: ['Students', 'Professors', 'Admins'],
            datasets: [{
                data: [stats.students, stats.professors, stats.admins],
                backgroundColor: ['#3498db', '#2ecc71', '#e74c3c']
            }]
        },
        options: chartOptions
    });
}

// Activity Chart
function initActivityChart(activityData) {
    const activityCtx = $('#activityChart')[0].getContext('2d');
    
    new Chart(activityCtx, {
        type: 'bar',
        data: {
            labels: activityData.map(d => d.month),
            datasets: [{
                label: 'Logins',
                data: activityData.map(d => d.logins),
                backgroundColor: '#3498db'
            }, {
                label: 'Sessions',
                data: activityData.map(d => d.sessions),
                backgroundColor: '#9b59b6'
            }]
        },
        options: chartOptions
    });
}

// Login Trends Chart
function initLoginTrendsChart() {
    const loginTrendsCtx = $('#loginTrendsChart')[0].getContext('2d');
    new Chart(loginTrendsCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Daily Logins',
                data: [45, 62, 38, 55, 48, 25, 18],
                borderColor: '#f39c12',
                backgroundColor: 'rgba(243, 156, 18, 0.1)',
                fill: true
            }]
        },
        options: chartOptions
    });
}

// Initialize all charts when DOM is loaded
$(document).ready(function() {
    // Charts will be initialized by inline script that passes PHP data
});
