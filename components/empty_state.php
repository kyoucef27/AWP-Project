<!-- Empty State Component -->
<?php
/**
 * Display an empty state message
 * 
 * @param string $icon - Emoji icon to display
 * @param string $title - Main title
 * @param string $message - Descriptive message
 * @param string $actionLink - Optional action link URL
 * @param string $actionText - Optional action link text
 */
function renderEmptyState($icon, $title, $message, $actionLink = '', $actionText = '') {
    ?>
    <div class="empty-state">
        <div class="empty-icon"><?php echo $icon; ?></div>
        <div class="empty-title"><?php echo htmlspecialchars($title); ?></div>
        <div class="empty-message"><?php echo htmlspecialchars($message); ?></div>
        <?php if ($actionLink && $actionText): ?>
            <a href="<?php echo htmlspecialchars($actionLink); ?>" class="btn btn-primary">
                <?php echo htmlspecialchars($actionText); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
}
?>
