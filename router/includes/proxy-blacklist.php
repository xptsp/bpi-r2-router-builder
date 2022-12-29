<?php
$count = 0;

###################################################################################################
# Supporting functions:
###################################################################################################
function filter($short, $description, $url1 = '', $url2 = '')
{
	global $options, $count, $file;
	$cfg = 'list_' . strval(++$count);
	$options[$cfg] = (!empty($url) && strpos($file, $url1) !== false) ? 'Y' : 'N';
	return 
		'<tr>' .
			'<td>' . checkbox($cfg . '" class="filters"', '', false, '', $url1) . '</td>' .
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
	<h5><i class="icon fas fa-info"></i> About these BlackLists</h5>
	Each website blacklist is sourced from <a href="https://github.com/Azothyran/ShallalistMirror/" target="_blank">Azothyran\'s Shallalist Mirror</a>!
</div>
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Blacklists</h3>
	</div>
	<div class="card-body p-0">
		<table class="table table-striped table-sm">
			<thead>
				<tr>
					<th width="10px"></th>
					<th style="width: 25%">List</th>
					<th style="width: 75%">Description</th>
				</tr>
			</thead>
			<tbody>
				', filter('Advertisements', 'All about advertising: This includes sites offering banners and banner creation as well as sites delivering banners to be shown in webpages and advertising companies.'), '
				', filter('Aggressive', 'Sites with aggressive content such as racism and hate speech.'), '
				', filter('Alcohol', 'Sites of breweries, wineries and destilleries. This category also covers sites that explain how to make beer, wines and spirits.'), '
				', filter('Anonymous VPN', 'Sites providing vpn services to the public. The focus is on vpn sites used to hide the origin of the traffic, f.e. tor nodes.'), '
				', filter('Automobile/Cars', 'All sites related to cars. Included are automobile companies and automotive suppliers.'), '
				', filter('Automobile/Bikes', 'All sites related to motorcycles. Included are vendor sites, resellers, fan and hobby pages as well as and suppliers. Scooters included.'), '
				', filter('Automobil/Boats', 'All sites related motorboats. Included are vendor sites, resellers, fan and hobby pages as well as and suppliers.'), '
				', filter('Automobile/Planes', 'All sites related to planes ranging from small one and two seaters up to the large traffic planes, old and new, private, commercial and military. Vendors and supplier are included (airports are not). Helicopter sites are included as well.'), '
				', filter('Chat', 'Site for real-time chatting and instant messaging.'), '
				', filter('Cost Traps', 'Sites that lure with free of charge services but then give then give you a costly subscription (written somewhere in tiny letters nearly unreadable).'), '
				', filter('Dating', 'Sites to contact people for love and living together. He seeks her, she seeks him and so on.'), '
				', filter('Downloads', 'This covers mostly filesharing, p2p and torrent sites. Other download sites (for software, wallpapers, ..) are included as well.'), '
				', filter('Drugs', 'Sites offering drugs or explain how to make drugs. Covers alcohol and tobacco as well as viagra and similar substances.'), '
				', filter('Dynamic (Dialup) Addresses', 'All domains where people login obtaining a dynamic IP address.'), '
				', filter('Schools', 'Home pages of schools, colleges and universities.'), '
				', filter('Finance/Banking', 'Home page of banking companies are listed here. This is not restricted to online banking.'), '
				', filter('Finance/Insurance', 'Sites of insurance companies, information about insurances and link collections concering this subject.'), '
				', filter('Finance/Moneylending', 'Sites one can apply for loans and mortgages or can obtain information about this business.'), '
				', filter('Finance/Other', 'Finance in general.'), '
				', filter('Finance/Realestate', 'Sites about all types of real estate, buying and selling homes, finding apartments for rent.'), '
				', filter('Finance/Trading', 'Sites about and related to stock exchange.'), '
				', filter('Fortune telling', 'All sites about astrology, horoscopes, numerology, palm reading and so on; sites that offer services to fortell the future.'), '
				', filter('Forum', 'Discussion sites. Covers explicit forum sites and some blogs. Sites where people can discuss and share information in a non interactive/real-time way.'), '
				', filter('Gambling/Casino', 'Sites offering the possibility to win money. Poker, Casino, Bingo and other chance games as well as betting sites.'), '
				', filter('Government', 'Sites belonging to the government of a country, county or city.'), '
				', filter('Hacking', 'Sites with information and discussions about security weaknesses and how to exploit them. Sites offering exploits are listed as well as sites distributing programs that help to find security leaks.'), '
				', filter('Hobby/Cooking', 'Sites concerning food and food preparation.'), '
				', filter('Hobby/Games', 'Sites related to games. This includes descriptions, news and general information about games. No gambling sites.'), '
				', filter('Hobby/Online-Games', 'Sites about online games. The games are for fun only (no gambling).'), '
				', filter('Hobby/Gardening', 'Sites about gardening, growing plants, fighting bugs and everything related to gardening.'), '
				', filter('Hobby/Pets', 'All topics concerning pets: description, breed, food, looks, fairs, favorite pet stories and so on.'), '
				', filter('Homestyle', 'Sites about everything need to create a cozy home (interior design and accessories).'), '
				', filter('Hospitals', 'Sites of hospitals and medical facilities.'), '
				', filter('Imagehosting', 'Sites specialized on hosting images, photo galleries and so on.'), '
				', filter('Internet Service Provider', 'Home pages of Internet Service Providers. Sites of companies offering webspace only are now being added, too'), '
				', filter('Jobsearch', 'Portals for job offers and job seekers as well as the career and work-for-us pages of companies.'), '
				', filter('Library', 'Online libraries and sites where you can read e-books.'), '
				', filter('Military', 'Sites of military facilities or related to the armed forces.'), '
				', filter('Models', 'Model agency, model and supermodel fan pages and other model sites presenting model photos. No porn pictures.'), '
				', filter('Movies and Videos', 'Sites offering cinema programs, information about movies and actors. Sites for downloading video clips/movies (as long as it is legal) are included as well.'), '
				', filter('Music', 'Sites that offer the download of music, information about music groups or music in general.'), '
				', filter('News', 'Sites presenting news. Homepages from newspapers, magazines and journals as well as some blogs.'), '
				', filter('Podcasts', 'Sites offering podcasts or podcast services, includes audio books.'), '
				', filter('Politics', 'Sites of political parties, political organisations and associations; sites with political discussions.'), '
				', filter('Porn', 'Sites about all kinds of sexual content ranging from bare bosoms to hardcore porn and sm.'), '
				', filter('Radio and TV', 'Domains and urls of TV and radio stations.'), '
				', filter('Recreation/Humor', 'Humorous pages, comic strips, funny stories, everything which makes people laugh.'), '
				', filter('Recreation/Martialarts', 'Sites dedicated to martial arts such as: karate, kung fu, taek won do as well as fighting sports sites like ufc.'), '
				', filter('Recreation/Restaurants', 'Sites of restaurants as well as restaurant descriptions and commentaries.'), '
				', filter('Recreation/Sports', 'All about sports: sports teams, sport discussions as well as information about sports people and the various sports themselves.'), '
				', filter('Recreation/Travel', 'Sites with information about foreign countries, travel companies, travel fares, accommodations and everything else that has to do with travel.'), '
				', filter('Recreation/Wellness', 'Sites about treatments for feeling internally and externally healthy and beautiful again.'), '
				', filter('Proxy', 'Sites that actively help to bypass url filters by accepting urls via form and play a proxing and redirecting role.'), '
				', filter('Religion', 'Sites with religious content: all kind of churches, sects, religious interpretations, etc.'), '
				', filter('Remote Control', 'Sites offering the service to remotely access computers, especially (but not limited to going) through firewalls. This does not cover traditional VPN.'), '
				', filter('Ringtones', 'Sites that offer the download of ringtones or present other information about ringtones.'), '
				', filter('Science/Astronomy', 'Sites of institutions as well as of amateurs about all topics of astronomy.'), '
				', filter('Science/Chemistry', 'Sites of institutions as well as of amateurs about all topics of chemistry.'), '
				', filter('Searchengines', 'Collection of search engines and directory sites.'), '
				', filter('Sex/Lingerie.', 'Sites selling and presenting sexy lingerie.'), '
				', filter('Sex/Education.', 'Sites explaining the biological functions of the body concerning sexuality as well as sexual health.'), '
				', filter('Shopping', 'Sites offering online shopping and price comparisons.'), '
				', filter('Social Networking', 'Sites bringing people together (social networking) be it for friendship or for business.'), '
				', filter('Spyware', 'Sites that try to actively install software or lure the user in doing so in order to spy the surfing behaviour (or worse). The home calling sites where the collected information is sent, are listed too.'), '
				', filter('Tracker', 'Sites keeping an eye on where you surf and what you do in a passive manner. Covers web bugs, counters and other tracking mechanisms in web pages that do not interfere with the local computer yet collect information about the surfing person for later analysis.'), '
				', filter('Update Sites', 'List to allow necessary downloads from vendors.'), '
				', filter('URL shortener', 'Sites offering short links for URLs.'), '
				', filter('Violence', 'Sites about killing and harming people. Covers anything about brutality and beastiality.'), '
				', filter('Warez', 'Collection of sites offering programs to break licence keys, licence keys themselves, cracked software and other copyrighted material.'), '
				', filter('Weapons', 'Sites offering all kinds of weapons or accessories for weapons: Firearms, knifes, swords, bows, etc. Armory shops are included as well as sites holding general information about arms (manufacturing, usage).'), '
				', filter('Webmail', 'Sites that offer web-based email services.'), '
				', filter('Internet Phone', 'Sites that enable user to phone via the Internet. Any site where users can voice-chat with each other.'), '
				', filter('Internet radios', 'Sites that offer listening to music and radio live streams.'), '
				', filter('Web TV, Internet TV', 'Sites offering TV streams via Internet.'), '
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
