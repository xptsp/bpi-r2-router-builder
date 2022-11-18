<?php

###################################################################################################
# Supporting functions:
###################################################################################################
function filter($short, $description, $url)
{
	return 
		'<tr>' .
			'<td>' . checkbox(preg_replace("/[^A-Za-z0-9]+/", "_", $short), '', false, '', $url) . '</td>' .
			'<td>' . $short . '</td>' .
			'<td>' . $description . '</td>' .
		'</tr>';
}

#################################################################################################
# Main code for this page:
#################################################################################################
site_menu();
echo '
<div class="alert alert-info">
	<h5><i class="icon fas fa-info"></i> Notice about Adblocking Lists</h5>
	Each adblocking list has been sourced from <a href="https://adblockultimate.net/filters/">Adblock Ultimate</a>\'s website!
</div>
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Ad-Blocking</h3>
	</div>
	<div class="card-body p-0">
		<table class="table table-striped table-sm">
			<thead>
				<tr>
					<th width="10px"></th>
					<th>Filter Name</th>
					<th style="width: 70%">Description</th>
				</tr>
			</thead>
			<tbody>
				', filter('Ultimate Ad Filter', 'This is a filter that allows removing ads from websites with English content. It is based on the EasyList and AdGuard filters and modified by the Adblock Ultimate team according to the complaints from users.', 'https://filters.adavoid.org/ultimate-ad-filter.txt'), '
				', filter('Anti-circumvention', 'Filter list designed to fight circumvention ads.', 'https://easylist-downloads.adblockplus.org/abp-filters-anti-cv.txt'), '
				', filter('RuAdList+EasyList', 'Removes ads from Russian websites.', 'https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt'), '
				', filter('Germany+EasyList', 'Removes ads from German websites.', 'https://easylist-downloads.adblockplus.org/easylistgermany+easylist.txt'), '
				', filter('Fanboy\'s Japanese', 'Removes ads from Japanese websites.', 'https://fanboy.co.nz/fanboy-japanese.txt'), '
				', filter('EasyList Dutch+EasyList', 'Removes ads from Dutch websites.', 'https://easylist-downloads.adblockplus.org/easylistdutch+easylist.txt'), '
				', filter('Fanboy\'s Spanish/Portuguese', 'Removes ads from Spanish/Portuguese websites.', 'https://fanboy.co.nz/fanboy-espanol.txt'), '
				', filter('Fanboy\'s Turkish', 'Removes ads from Turkish websites.', 'https://fanboy.co.nz/fanboy-turkish.txt'), '
				', filter('Bulgarian list', 'Removes ads from Bulgarian websites.', 'http://stanev.org/abp/adblock_bg.txt'), '
				', filter('EasyList China', 'Removes ads from Chinese websites.', 'https://easylist-downloads.adblockplus.org/easylistchina.txt'), '
				', filter('EasyList Czech+Slovak', 'Removes ads from Czechs and Slovak websites.', 'https://raw.github.com/tomasko126/easylistczechandslovak/master/filters.txt'), '
				', filter('EasyList Italy', 'Removes ads from Italian websites.', 'https://easylist-downloads.adblockplus.org/easylistitaly.txt'), '
				', filter('Latvian List', 'Removes ads from Latvian websites.', 'https://notabug.org/latvian-list/adblock-latvian/raw/master/lists/latvian-list.txt'), '
				', filter('Adblock Polska', 'Removes ads from Polish websites.', 'https://raw.githubusercontent.com/adblockpolska/Adblock_PL_List/master/adblock_polska.txt'), '
				', filter('Estonian List', 'Removes ads from Estonian websites.', 'http://adblock.ee/list.php'), '
				', filter('Liste FR', 'Removes ads from French websites.', 'https://filters.adavoid.org/filters/FrenchList.txt'), '
				', filter('Hufilter', 'Removes ads from Hungarian websites.', 'https://filters.adavoid.org/filters/HungarianList.txt'), '
				', filter('Adblock-Persian list', 'Removes ads from Persian websites.', 'http://ideone.com/plain/K452p'), '
				', filter('Fanboy\'s Swedish', 'Removes ads from Swedish websites.', 'https://www.fanboy.co.nz/fanboy-swedish.txt'), '
				', filter('Fanboy\'s Korean', 'Removes ads from Korean websites.', 'https://fanboy.co.nz/fanboy-korean.txt'), '
				', filter('Fanboy\'s Vietnamese', 'Removes ads from Vietnamese websites.', 'https://www.fanboy.co.nz/fanboy-vietnam.txt'), '
				', filter('Fanboy\'s Annoyance List', 'Blocks all irritating in-page popups, cookie notices and third-party widgets, which substantially decrease page loading times use this filter and it will block all of them for you.', 'https://easylist.to/easylist/fanboy-annoyance.txt'), '
			</tbody>
		</table>
	</div>
	<div class="card-header">
		<h3 class="card-title">Privacy</h3>
	</div>
	<div class="card-body p-0">
		<table class="table table-striped table-sm">
			<thead>
				<tr>
					<th width="10px"></th>
					<th>Filter Name</th>
					<th style="width: 70%">Description</th>
				</tr>
			</thead>
			<tbody>
				', filter('Ultimate Privacy Filter', 'Blocks an extensive list of various online trackers, counters and web analytics tools. Based on the EasyList Privacy list and upgraded by our team.', 'https://filters.adavoid.org/ultimate-privacy-filter.txt'), '
				', filter('Fanboy\'s Social Blocking List', 'Removes social media integration', 'https://easylist.to/easylist/fanboy-social.txt'), '
			</tbody>
		</table>
	</div>
	<div class="card-header">
		<h3 class="card-title">Security</h3>
	</div>
	<div class="card-body p-0">
		<table class="table table-striped table-sm">
			<thead>
				<tr>
					<th width="10px"></th>
					<th>Filter Name</th>
					<th style="width: 70%">Description</th>
				</tr>
			</thead>
			<tbody>
				', filter('Ultimate Security Filter', 'This filter blocks malicious domains. Based on Online Malicious Domains Blocklist filter and further updated and modified by our team.', 'https://filters.adavoid.org/ultimate-security-filter.txt'), '
				', filter('Spam404', 'This filter protects you from online scams. This filter is regularly updated with data collected by Spam404.com.', 'https://raw.githubusercontent.com/Spam404/lists/master/adblock-list.txt'), '
				', filter('NoCoin', 'Stops cryptomining in your browser.', 'https://github.com/hoshsadiq/adblock-nocoin-list/blob/master/nocoin.txt'), '
			</tbody>
		</table>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
site_footer('Init_Filters();');
