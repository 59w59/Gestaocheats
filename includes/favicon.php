<?php
$favicon_path = isset($favicon_base_path) ? $favicon_base_path : '';
?>
<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?php echo $favicon_path; ?>assets/images/favicon/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo $favicon_path; ?>assets/images/favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo $favicon_path; ?>assets/images/favicon/favicon-16x16.png">
<?php 
$favicon_base_path = '';
include 'includes/favicon.php'; 
?>