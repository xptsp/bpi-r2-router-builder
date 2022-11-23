<?php
$count = 0;

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	if ($_POST['action'] == 'submit')
	{
		$data = $_POST['misc'];
		if (!is_array($data))
			die("ERROR: Invalid data passed!");
		$file = file_get_contents("/etc/privoxy/blocklist.conf");
		if (!preg_match("/URLS=\(([^)]*)\)/", $file, $regex))
			die("ERROR: Blocklist file is invalid!");
		$output = implode("\"\n\t\"", $data);
		$file = str_replace($regex[1], "\n\t\"" . $output . "\"\n", $file);
		$handle = fopen("/tmp/router-settings", "w");
		fwrite($handle, $file);
		fclose($handle);
		@shell_exec("router-helper move privoxy-blocklist restart");
		die("OK");
	}
	die("Invalid action");
}

###################################################################################################
# Supporting functions:
###################################################################################################
function filter($short, $description, $url)
{
	global $options, $count;
	$cfg = 'list_' . strval(++$count);
	$options[$cfg] = file_exists('/etc/privoxy/' . str_replace('.txt', '.adblock.action', basename($url))) ? 'Y' : 'N';
	return 
		'<tr>' .
			'<td>' . checkbox($cfg . '" class="filters"', '', false, '', $url) . '</td>' .
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
	<h5><i class="icon fas fa-info"></i> About these Adblocking Lists</h5>
	Each adblocking list has been referenced by the <a href="https://easylist.to/index.html" target="_blank">EasyList</a>\'s website!
</div>
<div class="card card-primary">
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
				', filter('EasyPrivacy List', 'Removes all forms of tracking from the internet, including web bugs, tracking scripts and information collectors, thereby protecting your personal data.', 'https://easylist.to/easylist/easylist.txt'), '
				', filter('EasyList Cookie List', 'Blocks cookies banners, GDPR overlay windows and other privacy-related notices.', 'https://secure.fanboy.co.nz/fanboy-cookiemonster.txt'), '
				', filter('Fanboy\'s Social Blocking List', 'Removes Social Media content on web pages such as the Facebook like button and other widgets.', 'https://easylist.to/easylist/fanboy-social.txt'), '
				', filter('Adblock Warning Removal List', 'Removes obtrusive messages and warnings targeted to users who use an adblocker.', 'https://easylist-downloads.adblockplus.org/antiadblockfilters.txt'), '
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
				', filter('Spam404', 'Protects you from online scams. This filter is regularly updated with data collected by Spam404.com.', 'https://raw.githubusercontent.com/Spam404/lists/master/adblock-list.txt'), '
				', filter('NoCoin', 'Stops cryptomining in your browser.', 'https://raw.githubusercontent.com/hoshsadiq/adblock-nocoin-list/master/nocoin.txt'), '
			</tbody>
		</table>
	</div>
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
				', filter('EasyList', 'Primary filter list that removes most adverts from international webpages, including unwanted frames, images and objects. It is the most popular list used by many ad blockers and forms the basis of over a dozen combination and supplementary filter lists.', 'https://easylist.to/easylist/easylist.txt'), '
				', filter('Anti-circumvention', 'Filter list designed to fight circumvention ads.', 'https://easylist-downloads.adblockplus.org/abp-filters-anti-cv.txt'), '
				', filter('Fanboy\'s Annoyance List', 'Blocks Social Media content, in-page pop-ups and other annoyances; thereby substantially decreasing web page loading times and uncluttering them. EasyList Cookie List and Fanboy\'s Social Blocking List are already included, there is no need to subscribe to them if you already have Fanboy\'s Annoyance List.', 'https://secure.fanboy.co.nz/fanboy-annoyance.txt'), '
				', filter('EasyList Germany', 'EasyList Germany is a filter list written by the EasyList authors MonztA, Famlam and Khrin that specifically removes adverts on German language websites.', 'https://easylist.to/easylistgermany/easylistgermany.txt'), '
				', filter('EasyList Italy', 'EasyList Italy is a filter list written by the EasyList author Khrin that specifically removes adverts on Italian language websites.', 'https://easylist-downloads.adblockplus.org/easylistitaly.txt'), '
				', filter('EasyList Dutch', 'EasyList Dutch is an affiliated filter list written by the EasyList author Famlam that specifically removes adverts on Dutch language websites.', 'https://easylist-downloads.adblockplus.org/easylistdutch.txt'), '
				', filter('RU AdList', 'RU AdList is an affiliated filter list written by Lain_13 and dimisa that specifically removes adverts on русский, українська language websites.', 'https://easylist-downloads.adblockplus.org/advblock.txt'), '
				', filter('Bulgarian list', 'Removes ads from Bulgarian websites.', 'http://stanev.org/abp/adblock_bg.txt'), '
				', filter('ABPindo', 'ABPindo is an affiliated filter list written by hermawan that specifically removes adverts on Indonesian language websites.', 'https://raw.githubusercontent.com/heradhis/indonesianadblockrules/master/subscriptions/abpindo.txt'), '
				', filter('Liste AR', 'Liste AR is an affiliated filter list written by smed79 and Crits that specifically removes adverts on Arabic language websites.', 'https://easylist-downloads.adblockplus.org/Liste_AR.txt'), '
				', filter('EasyList Czech and Slovak', 'EasyList Czech and Slovak is an affiliated filter list written by tomasko126 that specifically removes adverts on Czech and Slovak language websites.', 'https://raw.githubusercontent.com/tomasko126/easylistczechandslovak/master/filters.txt'), '
				', filter('Latvian List', 'Latvian List is an affiliated filter list written by anonymous74100 that specifically removes adverts on Latvian language websites.', 'https://raw.githubusercontent.com/Latvian-List/adblock-latvian/master/lists/latvian-list.txt'), '
				', filter('EasyList Hebrew', 'EasyList Hebrew is an affiliated filter list written by BsT that specifically removes adverts on Hebrew language websites.', 'https://raw.githubusercontent.com/easylist/EasyListHebrew/master/EasyListHebrew.txt'), '
				', filter('Dandelion Sprout\'s Nordic Filters', 'Dandelion Sprout\'s Nordic Filters is an affiliated filter list written by Imre Kristoffer Eilertsen that specifically removes adverts on norsk, norsk, norsk, dansk, íslenska, føroyskt, kalaallisut, suomi language websites.', 'https://raw.githubusercontent.com/DandelionSprout/adfilt/master/NorwegianExperimentalList%20alternate%20versions/NordicFiltersABP-Inclusion.txt'), '
				', filter('EasyList Lithuania', 'EasyList Lithuania is an affiliated filter list written by Imre Kristoffer Eilertsen that specifically removes adverts on Lithuanian language websites.', 'https://raw.githubusercontent.com/EasyList-Lithuania/easylist_lithuania/master/easylistlithuania.txt'), '
				', filter('EasyList Spanish', 'EasyList Spanish is an affiliated filter list written by Felippe Santos that specifically removes adverts on español language websites.', 'https://easylist-downloads.adblockplus.org/easylistspanish.txt'), '
				', filter('EasyList Portuguese', 'EasyList Portuguese is an affiliated filter list written by Felippe Santos that specifically removes adverts on português language websites.', 'https://easylist-downloads.adblockplus.org/easylistportuguese.txt'), '
				', filter('ABPVN List', 'ABPVN List is an affiliated filter list written by Hoàng Rio that specifically removes adverts on Tiếng Việt language websites.', 'https://raw.githubusercontent.com/ABPindo/indonesianadblockrules/master/subscriptions/abpindo.txt'), '
				', filter('EasyList Polish', 'EasyList Polish is an affiliated filter list written by bartoszsobkowiak and mateuszfranckiewicz that specifically removes adverts on polski language websites.', 'https://easylist-downloads.adblockplus.org/easylistpolish.txt'), '
				', filter('IndianList', 'IndianList is an affiliated filter list written by mediumkreation that specifically removes adverts on বাংলা (ভারত), ગુજરાતી (ભારત), भारतीय, ਪੰਜਾਬੀ (ਭਾਰਤ), অসমীয়া, मराठी, മലയാളം, తెలుగు, ಕನ್ನಡ, ଓଡ଼ିଆ, नेपाली, සිංහල language websites.', 'https://easylist-downloads.adblockplus.org/indianlist.txt'), '
				', filter('KoreanList', 'KoreanList is an affiliated filter list written by Mark Choi that specifically removes adverts on 한국어 language websites.', 'https://easylist-downloads.adblockplus.org/koreanlist.txt'), '
				', filter('ROList', 'ROList is an affiliated filter list written by MenetZ and Zoso that specifically removes adverts on românesc language websites.', 'https://www.zoso.ro/pages/rolist.txt'), '
			</tbody>
		</table>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal("Please wait while privoxy service changes are pending...", true);
site_footer('Init_Filters();');
