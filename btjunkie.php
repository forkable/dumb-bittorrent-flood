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

//for($o=1;$o<10;$o++){
    for($p=1;$p<200;$p++){
    $pagerl="http://btjunkie.org/browse/TV/page".$p."/?o=32&t=0&s=1";
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
    //                                                            1      2
    preg_match_all('/<a href="http:\/\/dl.btjunkie.org\/torrent\/(.*)\/(.*)\/download.torrent" rel="nofollow">/msU',
    $page, $torrents);

    if(count($torrents[2])==0){ print "Nothing to do...\n"; continue; }
        
////////////////////////////////////////////////////////////////////////////////

$open_threads=array(); // Open fork array (don't touch it)
$t=0; // Current fork' iteration
$num=count($torrents[2]);
$i=0;
        foreach($torrents[2] as $tch=>$tid){
            /*//http://dl.btjunkie.org/torrent/AVI-1-36-GB-Official-Psycho-Parody-Zero-Tolerance-2010//download.torrent
            
                curl_setopt($curl, CURLOPT_URL, "http://dl.btjunkie.org/torrent/torrent/29081dd623caaa6e2cc1edada35d47d00c1b24d7fa7a/download.torrent"); 
                $file = curl_exec($curl); 
                if(!curl_errno($curl)){ 
                    $info = curl_getinfo($curl); 
                    print 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url']."\n"; 
                   // print $file;
		            $hash = BDecode($file);
            print_r($hash);
            }
                    //print_r($torrents[2]);
        
        die();
            */
            
////////////////////////////////////////////////////////////////////////////////
            $open_threads[$t] = pcntl_fork(); // Launching fork
            if(!$open_threads[$t]) {
                ////////////// Fork code
                print "Child process\n"; // child process
                pcntl_signal(SIGALRM, "signal_handler", true);
                pcntl_alarm($alarm_timeout);
/////////////////////////////////////////////////////////////////////////////////
                curl_setopt($curl, CURLOPT_URL, "http://dl.btjunkie.org/torrent/torrent/".$tid."/download.torrent"); 
                
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
                    if(isset($hash['info']['files'])){
                        foreach($hash['info']['files'] as $taid=>$sumpart){
                            $size=$size+$sumpart['length'];
                        }
                        print "KNOWN size: ".$size."\n\n";
                    }else{
                    print "Unknown size...\n\n";
                        $size=40000;
                    }
                    $annurls=array();
                    if(isset($hash['announce-list'])){
                        foreach($hash['announce-list'] as $jid=>$trak){
                            if($jid>3) break;
                            $annurls[]=$trak[0];
                        }
                    }else{
                        $annurls[]=$hash['announce'];   
                    }
                    
                    foreach($annurls as $annurl){
                           // print $size;
                     // $url=$hash['announce']."?info_hash=".rawurlencode($infohash)."&peer_id=".urlencode("-TR2130-qgau5woezi9x")."&ip=".$ip."&port=".$port."&uploaded=".$size."&downloaded=0&left=0&event=completed";
                   //   $url=$annurl."?info_hash=".rawurlencode($infohash)."&peer_id=".$peerid."&ip=".$ip."&port=".$port."&uploaded=0&downloaded=0&left=".($size/2)."&event=started";
                      $url=$annurl."?info_hash=".rawurlencode($infohash)."&peer_id=".$peerid."&ip=".$ip."&port=".$port."&uploaded=0&downloaded=".$size."&left=0&event=completed";
                     // print "\n\n".$url."\n\n";
                            curl_setopt($curl, CURLOPT_URL, $url); 
                            $page = curl_exec($curl); 
                            if(!curl_errno($curl)){
                                $info = curl_getinfo($curl); 
                                print 'Took ' . $info['total_time'] . ' seconds to add peer to '.$torrents[1][$tch]."\n";
                                print "Got reponse: ".$page."\n\n";
                            } else {
                                print 'Curl error: ' . curl_error($curl)."\n"; 
                            }          
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
        print "Waiting 10 sec (renew)...\n";
        sleep(10);
        $curl=go_curl($curl_timeout);
        
    }
    
     /*   
        curl_close($curl);
        print "Waiting 15 sec (renew)...\n";
        sleep(15);
        $curl=go_curl($curl_timeout);    
    */
//}

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
