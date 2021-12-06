<?php
function word($file)
{
	global $num;
	$lines = file('/usr/share/dict/' . $file . '.list');
	$max = count($lines);
	$word = explode("'", trim(ucfirst( $lines[ rand(0, $max) ] )))[0]; 
	return $word;
}
echo word('adjectives') . word('animals') . strval(rand(0,100));