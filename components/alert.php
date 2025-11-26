<!-- Alert Component -->
<?php
/**
 * Display an alert message
 * 
 * @param string $message - Alert message
 * @param string $type - Alert type: 'success', 'error', 'warning', 'info'
 * @param string $icon - Optional emoji icon
 */
function renderAlert($message, $type = 'info', $icon = '') {
    if (empty($message)) return;
    
    $icons = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];
    
    $displayIcon = $icon ?: ($icons[$type] ?? '');
    ?>
    <div class="alert alert-<?php echo htmlspecialchars($type); ?>">
        <?php echo $displayIcon ? $displayIcon . ' ' : ''; ?>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php
}
?>
