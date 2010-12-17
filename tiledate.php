<?php
/*
tiledate.php
===========================================================================
A quick hack to create an 256x256 overlay tile showing the capture date of a Bing Maps tile as provided in the HTTP metadata.
Sample request:
(was: tiledate.php?t=http://ecn.t7.tiles.virtualearth.net/tiles/h12020033230.jpeg?g=587&mkt=en-us&n=z)
tiledate.php?z=18&x=134926&y=86121

See also: http://lists.openstreetmap.org/pipermail/talk-nl/2010-December/012083.html

===========================================================================
Copyright (c) 2010 Martijn van Exel and Peter Hazenberg

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

$d = isset($_GET['d']);
$zoom = (int) $_GET['z'];
$tx = (int) $_GET['x'];
$ty = (int) $_GET['y'];
$file = "./tiles/".$zoom."/".$tx."/".$ty.".png";

if(!($d)) header("Content-type: image/png");

// if not in cache, or older than 1 week
if (!file_exists($file) or (file_exists($file) and filemtime($file) < strtotime("-1 week")))
{
	$quadKey = '';
	foreach(range($zoom, 1, -1) as $i)
	{
		$digit = 0;
		$mask = 1 << ($i-1);
		if(($tx & $mask) != 0) $digit += 1;
		if(($ty & $mask) != 0) $digit += 2;
		$quadKey .= $digit;
	}

	$url = "http://ecn.t".rand(0,7).".tiles.virtualearth.net/tiles/a".$quadKey.".jpeg?g=587&mkt=en-us&n=z";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	$r = curl_exec($ch); 

	$h = curl_getinfo($ch,CURLINFO_HEADER_SIZE);
	$jpg = substr($r, $h);
	$r = substr($r, 0, $h);

	$r = explode("\n", $r);

	$headers = array();

	foreach ($r as $kv)
	{
		$x = explode(":",$kv);
		if(count($x)>1) $headers[$x[0]] = $x[1];
	}

	$dates = explode("-",trim($headers['X-VE-TILEMETA-CaptureDatesRange']));

	if ($dates[0]=="")
	{
		$dates = "N/A";
	}
	else
	{
		$dates = date("j M Y - ",strtotime($dates[0])).date("j M Y",strtotime($dates[1]));
	}

	if($d)
	{
		echo "<p style=\"float: left; padding: 0px; margin: 0px;\">\n\t<img src=\"".$url."\">\n\t<br>\n\t<br>\n";
		echo "\t<img src=\"tiledate.php?x=".$tx."&y=".$ty."&z=".$zoom."\">\n</p>\n<pre>\n".$url."\n".$file." => ".realpath($file)."\n".$dates."\n\n";
		print_r($headers);
		echo "</pre>";
	}
	else
	{
		putenv('GDFONTPATH='.realpath('.'));
		$im = imagecreatefromstring($jpg);
		$background_color = imagecolorallocate($im, 0, 0, 0);
		$bg = imagecolorallocatealpha($im, 255, 0, 0, 127);
		$halo = imagecolorallocate($im, 255, 255, 255);
		$halo2 = imagecolorallocatealpha($im, 255, 255, 255, 63);
		$text = imagecolorallocate($im, 0, 0, 127);
		imagesavealpha($im,true);

		$b = imagettfbbox(10, 0, "DejaVuSans", $dates);
		$x = 128-($b[2]-$b[0])/2;
		$y = 128-($b[1]-$b[7])/2;

		imagettftext($im, 10, 0, $x-1, $y-1, $halo2, "DejaVuSans", $dates);
		imagettftext($im, 10, 0, $x-1, $y,   $halo,  "DejaVuSans", $dates);
		imagettftext($im, 10, 0, $x-1, $y+1, $halo2, "DejaVuSans", $dates);
		imagettftext($im, 10, 0, $x,   $y-1, $halo,  "DejaVuSans", $dates);
		imagettftext($im, 10, 0, $x,   $y,   $halo,  "DejaVuSans", $dates);
		imagettftext($im, 10, 0, $x,   $y+1, $halo,  "DejaVuSans", $dates);
		imagettftext($im, 10, 0, $x+1, $y-1, $halo2, "DejaVuSans", $dates);
		imagettftext($im, 10, 0, $x+1, $y,   $halo,  "DejaVuSans", $dates);
		imagettftext($im, 10, 0, $x+1, $y+1, $halo2, "DejaVuSans", $dates);

		imagettftext($im, 10, 0, $x,   $y,   $text,  "DejaVuSans", $dates);

		imageline($im,   0,   0,   3,   3, $text);
		imageline($im, 255,   0, 252,   3, $text);
		imageline($im,   0, 255,   3, 252, $text);
		imageline($im, 255, 255, 252, 252, $text);

		imageline($im,   1,   0,   0,   1, $text);
		imageline($im, 254,   0, 255,   1, $text);
		imageline($im,   0, 254,   1, 255, $text);
		imageline($im, 254, 255, 255, 254, $text);

		if (!is_dir("./tiles/".$zoom)) mkdir("./tiles/".$zoom);
		if (!is_dir("./tiles/".$zoom."/".$tx)) mkdir("./tiles/".$zoom."/".$tx);
		imagepng($im, $file);
		readfile($file);
		imagedestroy($im);
	}
}
else
{
	if ($d)
	{
		echo "<p style=\"float: left; padding: 0px; margin: 0px;\">\n\t<img src=\"tiledate.php?x=".$tx."&y=".$ty."&z=".$zoom."\">\n</p>\n";
		echo "<pre>\nCached: ".date("r",filemtime($file))."\n".$file." => ".realpath($file)."\n".$dates."\n</pre>";
	}
	else
	{
		readfile($file);
	}
}
?>
