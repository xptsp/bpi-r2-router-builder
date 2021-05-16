<?php
# Divide the program output into pages of specified number of lines:
$lines = "";
$per_page = 100;
foreach (explode("\n", trim(@shell_exec('dmesg'))) as $num => $line)
{
	$pages = floor(($num + $per_page) / $per_page);
	$lines .= '<div class="everything page_' . $pages . ($pages > 1 ? ' hidden' : '') . '" id="dmesg-' . $num . '">' . htmlspecialchars($line) . "\n" . '</div>';
}

# Assemble pagination code:
$pagination = '';
if ($pages > 1)
{
	$pagination .= '
				<li class="page-item pageprev" id="prev"><span class="page-link">«</span></li>';
	for ($page = 1; $page <= $pages; $page++)
		$pagination .= ' 
				<li class="page-item pagelink pagelink_' . $page . ($page == 1 ? ' active' : '') . '"><span class="page-link">' . $page . '</span></li>';
	$pagination .= '
				<li class="page-item pagenext" id="next"><span class="page-link">&#187;</span></li>';
}

# Output everything:
site_menu();
echo '
<div class="col-12 col-sm-12">
	<div class="card">
		<div class="card-header clearfix">
			<div class="card-tools">
				<div class="input-group input-group-sm">
					<input type="text" id="search" class="form-control float-right" placeholder="Search">
					<div class="input-group-append">
						<button type="submit" class="btn btn-default">
							<i class="fas fa-search"></i>
						</button>
					</div>
				</div>
			</div>
			<ul class="pagination pagination-sm m-0" id="pages">', $pagination, '
			</ul>
		</div>
		<div class="card-body">
			<pre id="lines">' . "\n" . $lines . '</pre>
		</div>
	</div>';

# Wrap it up:
site_footer('
	MaxPages=' . $pages . ';
	$("#search").on("propertychange input", Logs_Filter);
	$("#pages").on("click", ".pagelink", Logs_Page);
	$("#pages").on("click", ".pageprev", Logs_Prev);
	$("#pages").on("click", ".pagenext", Logs_Next);
');
