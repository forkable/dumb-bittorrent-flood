<?php
    declare(ticks = 1);



    pcntl_signal(SIGALRM, "signal_handler", true);
    pcntl_alarm(3);

    for(;;) {
        
        
        sleep(1);
    }


    function signal_handler($signal) {
        print "Caught SIGALRM\n";
        die();
        pcntl_alarm(5);
    }

?>
