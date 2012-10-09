#!/usr/bin/php
<?php
include('TorrentParsev2/BDecode.php');
include('TorrentParsev2/BEncode.php');

$ip="";
$port="80";

print "Starting\n";

$curl = curl_init(); 

curl_setopt($curl, CURLOPT_VERBOSE, 0); 
curl_setopt($curl, CURLOPT_HEADER, 0); 
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 

$peerid=hextostr("5F7042416679748C2276");

$hash = BDecode(file_get_contents("some.torrent"));
$infohash = sha1(BEncode($hash["info"]), true);
$size=0;
foreach($hash['info']['files'] as $taid=>$sumpart){
    $size=$size+$sumpart['length'];
}

print $size;
      $url=$hash['announce']."&info_hash=".rawurlencode($infohash)."&peer_id=".urlencode("-TR2130-qgau5woezi9x")."&ip=".$ip."&port=".$port."&uploaded=".$size."&downloaded=0&left=0&event=completed";
      print "\n\n".$url."\n\n";
           // curl_setopt($curl, CURLOPT_URL, $url); 
            $page = curl_exec($curl); 
            if(!curl_errno($curl)){ 
                $info = curl_getinfo($curl); 
                print 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']."\n"; 
                
                print "\nGot:\n".$page."\n\n";
            } else {
                print 'Curl error: ' . curl_error($curl)."\n"; 
            }

curl_close($curl); 
print "End\n";

function hextostr($x) { 
  $s=''; 
  foreach(explode("\n",trim(chunk_split($x,2))) as $h) $s.=chr(hexdec($h)); 
  return($s); 
} 

function strtohex($x) { 
  $s=''; 
  foreach(str_split($x) as $c) $s.=sprintf("%02X",ord($c)); 
  return($s); 
} 
?>
