// Admin Dashboard jQuery
$(document).ready(function() {
    // Smooth scroll for internal links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $($(this).attr('href'));
        if (target.length) {
            target[0].scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });

    // Animate stat numbers on load
    animateStatNumbers();

    // Add hover effect sounds (optional)
    addCardInteractions();
});

function animateStatNumbers() {
    $('.stat-number').each(function() {
        const $stat = $(this);
        const target = parseInt($stat.text());
        if (isNaN(target)) return;
        
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                $stat.text(target);
                clearInterval(timer);
            } else {
                $stat.text(Math.floor(current));
            }
        }, 20);
    });
}

function addCardInteractions() {
    $('.dashboard-card, .health-card').each(function() {
        $(this).on('mouseenter', function() {
            $(this).css('transition', 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)');
        }).on('mouseleave', function() {
            $(this).css('transition', 'all 0.3s ease');
        });
    });
}

// Refresh system health status
function refreshSystemHealth() {
    $('.health-card').css('opacity', '0.6');
    setTimeout(() => {
        $('.health-card').css('opacity', '1');
    }, 500);
}

// Optional: Auto-refresh stats every 5 minutes
setInterval(() => {
    console.log('Dashboard stats would refresh here');
    // Uncomment to enable auto-refresh
    // location.reload();
}, 300000);
