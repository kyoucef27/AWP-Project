// Admin Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scroll for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Animate stat numbers on load
    animateStatNumbers();

    // Add hover effect sounds (optional)
    addCardInteractions();
});

function animateStatNumbers() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach(stat => {
        const target = parseInt(stat.textContent);
        if (isNaN(target)) return;
        
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                stat.textContent = target;
                clearInterval(timer);
            } else {
                stat.textContent = Math.floor(current);
            }
        }, 20);
    });
}

function addCardInteractions() {
    const cards = document.querySelectorAll('.dashboard-card, .health-card');
    
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transition = 'all 0.3s ease';
        });
    });
}

// Refresh system health status
function refreshSystemHealth() {
    const healthCards = document.querySelectorAll('.health-card');
    healthCards.forEach(card => {
        card.style.opacity = '0.6';
        setTimeout(() => {
            card.style.opacity = '1';
        }, 500);
    });
}

// Optional: Auto-refresh stats every 5 minutes
setInterval(() => {
    console.log('Dashboard stats would refresh here');
    // Uncomment to enable auto-refresh
    // location.reload();
}, 300000);
