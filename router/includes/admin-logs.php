<?php
# Decide what program to run:
$commands = array(
	0 => array('text' => 'Kernel Messages', 'cmd' => 'dmesg'),
);
$tab = isset($_GET['tab']) ? $_GET['tab'] : 0;
$tab = isset($commands[$tab]) ? $tab : 0;

# Divide the program output into pages of specified number of lines:
$lines = "";
$per_page = 100;
foreach (explode("\n", trim(@shell_exec($commands[$tab]['cmd']))) as $num => $line)
{
	$pages = floor(($num + $per_page) / $per_page);
	$lines .= '<div class="everything page_' . $pages . ($pages > 1 ? ' hidden' : '') . '" id="dmesg-' . $num . '">' . htmlspecialchars($line) . "\n" . '</div>';
}

# Assemble pagination code:
$pagination = '';
if ($pages > 1)
{
	$pagination .= '
				<li class="page-item pageprev" id="prev"><span class="page-link">&laquo;</span></li>';
	for ($page = 1; $page <= $pages; $page++)
		$pagination .= ' 
				<li class="page-item pagelink pagelink_' . $page . ($page == 1 ? ' active' : '') . '"><span class="page-link">' . $page . '</span></li>';
	$pagination .= '
				<li class="page-item pagenext" id="next"><span class="page-link">&raquo;</span></li>';
}

# Output everything:
site_menu();
echo '
<div class="col-12 col-sm-12">
	<div class="card card-tabs card-primary">
		<div class="card-header p-0 pt-1">
			<ul class="nav nav-tabs" id="custom-tabs-one-tab" role="tablist">
				<li class="nav-item">';
foreach ($commands as $id => $ele)
	echo '
					<a class="nav-link', $tab == $id ? ' active' : '', '" href="?tab=', $id, '">', $ele['text'], '</a>';
echo '
				</li>
			</ul>
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
