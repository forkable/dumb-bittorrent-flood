#!/usr/bin/php
<?php
//header('Content-Type: text/html; charset=utf-8' );
set_time_limit(0);


include('TorrentParsev2/BDecode.php');
include('TorrentParsev2/BEncode.php');
$ip=$argv[1]; 
$port=$argv[2]; //"80";
$curl_timeout=10;
$errror_wait=60;
$threads=20; // Max allowed forks
$alarm_timeout=8; // Stream timeout in sec

////////////////////////////////////////////////////////////////////////////////
print "Starting\n";
$curl=go_curl($curl_timeout);

$peerid=urlencode("-TR2130-qg".rand(0,9)."j".rand(0,9)."woe".rand(0,9)."i".rand(0,9)."x");

for($o=1;$o<10;$o++){
    for($p=1;$p<20;$p++){
    $pagerl="http://www.mininova.org/cat-list/".$o."/added/".$p;
    print "Loading page $pagerl\n";
    curl_setopt($curl, CURLOPT_URL, $pagerl); 
    $page = curl_exec($curl); 
    if(!curl_errno($curl)){ 
        $info = curl_getinfo($curl); 
        print 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']."\n"; 
        
        if(preg_match("/Internal Server Error/i", $page)){
            curl_close($curl);
            print "Waiting $errror_wait sec...\n";
            sleep($errror_wait);
            $curl=go_curl($curl_timeout);
        }
        
    } else {
        print 'Curl error: ' . curl_error($curl)."\n"; 
            curl_close($curl);
            print "Waiting $errror_wait sec...\n";
            sleep($errror_wait);
            $curl=go_curl($curl_timeout);
    }
    //                    1                   2     3        4                           5                                           6                                                   7                                         8
    preg_match_all('/<td>(.*)<a href="\/tor\/(.*)">(.*)<\/a>(.*)<\/td><td align="right">(.*)<\/td><td align="right"><span class="g">(.*)<\/span><\/td><td align="right"><span class="b">(.*)<\/span>(.*)class="d"><td>(.*)<\/td>/msU',
    $page, $torrents);

    if(count($torrents[2])==0){ print "Nothing to do...\n"; continue; }
////////////////////////////////////////////////////////////////////////////////

$open_threads=array(); // Open fork array (don't touch it)
$t=0; // Current fork' iteration
$num=count($torrents[2]);
$i=0;
        foreach($torrents[2] as $tch=>$tid){
////////////////////////////////////////////////////////////////////////////////
            $open_threads[$t] = pcntl_fork(); // Launching fork
            if(!$open_threads[$t]) {
                ////////////// Fork code
                print "Child process\n"; // child process
                pcntl_signal(SIGALRM, "signal_handler", true);
                pcntl_alarm($alarm_timeout);
/////////////////////////////////////////////////////////////////////////////////

            /*
                if($torrents[7][$tch]>0){
                }else{
                    print "Torrent has no leechers\n\n";
                    continue;
                }
                */
                curl_setopt($curl, CURLOPT_URL, "http://www.mininova.org/get/".$tid); 
                
                $file = curl_exec($curl); 
                if(!curl_errno($curl)){ 
                    $info = curl_getinfo($curl); 
                    print 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']."\n"; 
                   // print $file;
		            $hash = BDecode($file);
		           // print $hash["info"]."\n\n\n";
		           // print_r($hash);
		            $infohash = sha1(BEncode($hash["info"]), true);

                    $size=0;
                    foreach($hash['info']['files'] as $taid=>$sumpart){
                        $size=$size+$sumpart['length'];
                    }
                   // print $size;
             // $url=$hash['announce']."?info_hash=".rawurlencode($infohash)."&peer_id=".urlencode("-TR2130-qgau5woezi9x")."&ip=".$ip."&port=".$port."&uploaded=".$size."&downloaded=0&left=0&event=completed";
              $url=$hash['announce']."?info_hash=".rawurlencode($infohash)."&peer_id=".$peerid."&ip=".$ip."&port=".$port."&uploaded=0&downloaded=0&left=".($size/2)."&event=started";
             // print "\n\n".$url."\n\n";
                    curl_setopt($curl, CURLOPT_URL, $url); 
                    $page = curl_exec($curl); 
                    if(!curl_errno($curl)){
                        $info = curl_getinfo($curl); 
                        print 'Took ' . $info['total_time'] . ' seconds to add peer to '.$torrents[3][$tch]."\n";
                        print "Got reponse: ".$page."\n\n";
                    } else {
                        print 'Curl error: ' . curl_error($curl)."\n"; 
                    }          
                    
                } else { 
                    print 'Curl error: ' . curl_error($curl)."\n"; 
                }     
///////////////////////////////////////////////////////////////////////////////
                //posix_kill(getmypid(),9); // Killing to avoid destroying MySQL connection
                exit();
                
            ////////////////////////
            }
            $t++;
            $i++;
            if($t==$threads or $i==$num){ // Checking if 'max allowed forks' are started
                $t=0;
                print "Waiting for forks to exit...\n";
                for($w = 0; $w < count($open_threads); $w++){
                    //print "Waitpid: ".$open_threads[$w]."\n";
                    pcntl_wait($open_threads[$w]);
                }
                print "Fork set exited.\n";
            }
////////////////////////////////////////////////////////////////////////////////
        }
        print "Done!\n";
        
        curl_close($curl);
        print "Waiting 5 sec (renew)...\n";
        sleep(10);
        $curl=go_curl($curl_timeout);
        
    }
    
        
        curl_close($curl);
        print "Waiting 15 sec (renew)...\n";
        sleep(15);
        $curl=go_curl($curl_timeout);    
    
}

////////////////////////////////////////////////////////////////////////////////
function signal_handler($signal) {
        die();
}

function go_curl($curl_timeout){
    $curl = curl_init();
curl_setopt($curl,CURLOPT_TIMEOUT, $curl_timeout); 
curl_setopt($curl, CURLOPT_VERBOSE, 0); 
curl_setopt($curl, CURLOPT_HEADER, 0); 
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
    return $curl;
}

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
