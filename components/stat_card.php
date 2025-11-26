<!-- Stat Card Component -->
<?php
/**
 * Display a statistics card
 * 
 * @param string $number - The statistic number/value
 * @param string $label - The label for the statistic
 * @param string $icon - Optional emoji icon
 * @param string $colorClass - Optional color class (e.g., 'students', 'teachers', 'modules')
 */
function renderStatCard($number, $label, $icon = '', $colorClass = '') {
    ?>
    <div class="stat-card <?php echo htmlspecialchars($colorClass); ?>">
        <?php if ($icon): ?>
            <div class="stat-icon"><?php echo $icon; ?></div>
        <?php endif; ?>
        <div class="stat-number"><?php echo htmlspecialchars($number); ?></div>
        <div class="stat-label"><?php echo htmlspecialchars($label); ?></div>
    </div>
    <?php
}

/**
 * Display a grid of stat cards
 * 
 * @param array $stats - Array of stat cards, each with 'number', 'label', 'icon', 'colorClass'
 */
function renderStatsGrid($stats) {
    ?>
    <div class="stats-grid">
        <?php foreach ($stats as $stat): ?>
            <?php renderStatCard(
                $stat['number'] ?? '0',
                $stat['label'] ?? '',
                $stat['icon'] ?? '',
                $stat['colorClass'] ?? ''
            ); ?>
        <?php endforeach; ?>
    </div>
    <?php
}
?>
