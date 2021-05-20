<?php
$_GET['action'] = '404';
header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found", true, 404);
$site_title = '404 Error Page';
site_header();
site_menu();
echo '
			<div class="error-page">
				<h2 class="headline text-warning"> 404</h2>

				<div class="error-content">
					<h3><i class="fas fa-exclamation-triangle text-warning"></i> Oops! Page not found.</h3>
					<p>Sorry!  The page you were looking for cannot be found!.</p>
				</div>
				<!-- /.error-content -->
			</div>
			<!-- /.error-page -->';
site_footer();