<!-- Modal Component -->
<?php
/**
 * Display a modal dialog
 * 
 * @param string $id - Modal ID
 * @param string $title - Modal title
 * @param string $content - Modal content (HTML)
 * @param array $buttons - Array of buttons with 'text', 'class', 'onclick'
 */
function renderModal($id, $title, $content, $buttons = []) {
    ?>
    <div id="<?php echo htmlspecialchars($id); ?>" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('<?php echo htmlspecialchars($id); ?>').style.display='none'">&times;</span>
            <h3><?php echo htmlspecialchars($title); ?></h3>
            <div class="modal-body">
                <?php echo $content; ?>
            </div>
            <?php if (!empty($buttons)): ?>
                <div class="modal-footer">
                    <?php foreach ($buttons as $button): ?>
                        <button 
                            class="btn <?php echo htmlspecialchars($button['class'] ?? 'btn-secondary'); ?>"
                            <?php if (isset($button['onclick'])): ?>onclick="<?php echo htmlspecialchars($button['onclick']); ?>"<?php endif; ?>
                        >
                            <?php echo htmlspecialchars($button['text']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
