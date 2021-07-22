<?php
$num = 0;
function word($file)
{
	global $num;
	$lines = file('/usr/share/dict/' . $file . '.list');
	$max = count($lines);
	$word = explode("'", trim(ucfirst( $lines[ rand(0, $max) ] )))[0]; 
	for ($i = 0; $i < strlen($word); $i++)
		$num += ord($word[$i]) - 64;
	return $word;
}
echo word('adjectives') . word('animals') . substr(strval($num), 0, 2);
