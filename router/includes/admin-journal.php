<?php

# Divide the program output into pages of specified number of lines:
$lines = "";
$per_page = 50;
foreach (explode("\n", trim(@shell_exec('journalctl'))) as $num => $line)
{
	$pages = floor(($num + $per_page) / $per_page);
	$lines .= '<div class="everything page_' . $pages . ($pages > 1 ? ' hidden' : '') . '" id="dmesg-' . $num . '">' . htmlspecialchars($line) . "\n" . '</div>';
}

# Assemble pagination code:
$pagination = '';
if ($pages > 1)
{
	if ($pages > 10)
		$pagination .= '
				<li class="page-item page_first" id="prev"><span class="page-link">&laquo;&laquo;</span></li>';
	$pagination .= '
				<li class="page-item page_prev" id="prev"><span class="page-link">&laquo;</span></li>';
	for ($page = 1; $page <= $pages; $page++)
		$pagination .= ' 
				<li class="page-item pagelink pagelink_' . $page . ($page == 1 ? ' active' : '') . ($page > 10 ? ' hidden' : '') . '"><span class="page-link">' . $page . '</span></li>';
	$pagination .= '
				<li class="page-item page_next" id="next"><span class="page-link">&raquo;</span></li>';
	if ($pages > 10)
		$pagination .= '
				<li class="page-item page_last" id="next"><span class="page-link">&raquo;&raquo;</span></li>';
}

# Output everything:
site_menu();
echo '
<div class="col-12 col-sm-12">
	<div class="card card-tabs card-primary">
		<div class="card-header">
			<h3 class="card-title">Journal Logs</h3>
        </div>
        <div class="card p-0 pt-1">
			<div class="card-header">
				<div class="card-tools">
					<div class="input-group input-group-sm">
						<input type="text" id="search" class="form-control float-right" placeholder="Search">
						<div class="input-group-append">
							<button type="submit" class="btn btn-default">
								<i class="fas fa-search"></i>
							</button>
						</div>
					</div>
				</div>';
if ($pages > 1)
	echo '
				<ul class="pagination pagination-sm m-0" id="pages">', $pagination, '
				</ul>';
echo '
			</div>
			<div class="card-body">
				<pre id="lines">' . "\n" . $lines . '</pre>
			</div>
		</div>
	</div>';

# Wrap it up:
site_footer('Init_Logs(' . $pages . ');');
