<!-- Page Header Component -->
<?php
/**
 * Display a page header with title and breadcrumb
 * 
 * @param string $title - Page title
 * @param string $breadcrumb - Breadcrumb text (e.g., "Home / Admin / Dashboard")
 * @param string $icon - Optional emoji icon
 */
function renderPageHeader($title, $breadcrumb, $icon = '') {
    ?>
    <div class="page-header">
        <h1 class="page-title"><?php echo $icon ? $icon . ' ' : ''; ?><?php echo htmlspecialchars($title); ?></h1>
        <div class="breadcrumb">
            <?php echo htmlspecialchars($breadcrumb); ?>
        </div>
    </div>
    <?php
}
?>
