<?php
chdir(dirname(__FILE__) . '/../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");

$checkedTime = 0;
$polledTime = 0;
set_time_limit(0);

// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);

include_once("./load_settings.php");


include_once(DIR_MODULES . 'noolite/noolite.class.php');
echo date("H:i:s") . " running " . basename(__FILE__) . "\n";

$errors=0;
$noo = new noolite();
$noo->getConfig();

if ($noo->config['API_TYPE'] == 'serial') {

    $device_path = $noo->config['API_PORT'];
    if (!$device_path) {
        $device_path = '/dev/ttyUSB0';
    }
    $device_speed = 9600;
    $init_str = 'stty -F ' . $device_path . ' cs8 ' . $device_speed . ' -icrnl -ixon -ixoff -opost -isig -icanon -echo';
    DebMes("Init string: ".$init_str,'noolite');
    echo date('Y-m-d H:i:s') . " Init string: " . $init_str . "\n";
    exec($init_str);

    echo date('Y-m-d H:i:s') . " Opening port " . $device_path . "\n";
    DebMes("Opening port " . $device_path,'noolite');
    $fp = fopen($device_path, "w+");

    $buffer = '';
    if ($fp) {
        echo "OK\n";

        stream_set_blocking($fp, 0);
        $read_time = 0;

        $readInProgress = 0;
        $readStarted = 0;
        $sequence = '';
        $sequenceStart = false;
        while (1) {

            // CHECKPORT
            $r = fread($fp, 1);
            $ch = binaryToString($r);
            //echo ord($r)."\n";
            if ($ch != '') {
                //echo $ch."\n";
                $sequence .= $ch;
                $latest_in = time();
            } else {
                if ((time() - $latest_in) > 1 && $sequence != '') {
                    //echo "SEQ: $sequence\n";
                    $sequence = '';
                }
            }
            if ($ch == 'ad') {
                $in = $ch;
                $readInProgress = 1;
                $readStarted = time();
                $sequenceStart = true;
            } elseif ($ch == 'ae') {
                $errors=0;
                $line = $in . $ch;
                echo date('H:i:s') . " In: [" . $line . "];\n";
                $data = HexStringToArray($line);
                $readInProgress = 0;
                $url = BASE_URL . '/ajax/noolite.html?serial=1';
                $url .= '&mode=' . $data[1];
                $url .= '&answ=' . $data[2];
                $url .= '&toggl=' . $data[3];
                $url .= '&cell=' . $data[4];
                $url .= '&cmd=' . $data[5];
                $url .= '&fmt=' . $data[6];
                $url .= '&d0=' . $data[7];
                $url .= '&d1=' . $data[8];
                $url .= '&d2=' . $data[9];
                $url .= '&d3=' . $data[10];
                $url .= '&id=' . binaryToString(makePayload(array($data[11], $data[12], $data[13], $data[14])));
                $url .= '&crc=' . $data[15];
                echo date('Y-m-d H:i:s') . " URL: $url\n";
                DebMes("IN: $line", 'noolite');
                $res = get_headers($url);
                $sequenceStart = false;
            } else {
                if ($sequenceStart) {
                    $in .= $ch;
                    $readInProgress = 2;
                }
            }

            if ($readInProgress > 0 && (time() - $readStarted) > 3) {
                //read not finished within 3 seconds for some reason
                $readInProgress = 0;
            }

            if ($readInProgress > 0) {
                usleep(1000);
                continue;
            }


            if ((time() - $checkedTime) >= 5) {
                $checkedTime = time();
                setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
            }

            if ((time() - $polledTime) >= 1) {
                $polledTime = time();
                $noo->pollNooDevices();
            }


            // CHECK QUEUE
            $devices_data = checkOperationsQueue('noolite_queue');
            $current_queue = '';
            foreach ($devices_data as $property_data) {
                $current_queue .= $property_data['DATAVALUE'] . "\n";
            }
            //$current_queue=getGlobal('noolitePushMessage');
            if ($current_queue != '') {
                //setGlobal('noolitePushMessage', '');
                $queue = explode("\n", $current_queue);
                $total = count($queue);
                $sent_ok = true;
                for ($i = 0; $i < $total; $i++) {
                    if (!$queue[$i]) {
                        continue;
                    }
                    echo date('Y-m-d H:i:s') . " Sending: " . $queue[$i] . "\n";
                    //DebMes("OUT: " . $queue[$i], 'noolite');
                    $tmp = explode(' ', $queue[$i]);
                    $total = count($tmp);
                    $in = '';
                    $crc = 0;
                    for ($i = 0; $i < $total; $i++) {
                        if (strlen($tmp[$i]) <= 3) {
                            $in .= str_pad(dechex((int)$tmp[$i]), 2, '0', STR_PAD_LEFT);
                        } else {
                            $in .= $tmp[$i];
                        }
                    }
                    $in = substr($in, 0, 14 * 2);
                    $in = dechex(171) . $in;
                    $total_bytes = (int)(strlen($in) / 2);
                    for ($i = 0; $i < $total_bytes; $i++) {
                        $crc += hexdec(substr($in, $i * 2, 2));
                    }

                    $high_byte = floor($crc / 256);
                    $low_byte = $crc - $high_byte * 256;
                    //DebMes ("CRC: " . $crc. " High: $high_byte, Low: $low_byte",'noolite');

                    $in .= dechex($low_byte);
                    $in .= dechex(172);
                    $in2send = str_replace(' ','',$in);
                    echo date('Y-m-d H:i:s') . " Sending (hex): " . $in . " (" . (int)(strlen($in2send) / 2) . ")\n";
                    //DebMes  ("Sending (hex): " . $in . " (" . (int)(strlen($in2send) / 2) . ")",'noolite');
                    $payload = makePayload(HexStringToArray($in2send));
                    $send_status = fwrite($fp, $payload, 17);
                    if (!$send_status) {
                        DebMes("Sending ERROR",'noolite');
                        echo date('Y-m-d H:i:s') . " Sending ERROR\n";
                        $errors++;
                    } else {
                        $errors=0;
                        echo date('Y-m-d H:i:s') . " Sent OK\n";
                    }
                    if ($i > 0) {
                        usleep(20000);
                    }
                }
            }


            if (file_exists('./reboot') || IsSet($_GET['onetime']) || $errors>5) {
                fclose($fp);
                $db->Disconnect();
                exit;
            }

            //echo "Wating for data...\n";
            usleep(10000);

        }
    } else {
        DebMes("Cannot open port at $device_path",'noolite');
        echo date('Y-m-d H:i:s') . " Cannot open port at $device_path \n";
    }

}

if ($noo->config['API_TYPE'] == 'windows_one') {
    while (1) {
        if ((time() - $checkedTime) >= 5) {
            $checkedTime = time();
            setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
        }

        $noo->pollNooDevices();

        if (file_exists('./reboot') || IsSet($_GET['onetime'])) {
            fclose($fp);
            $db->Disconnect();
            exit;
        }
        sleep(1);
    }
}

$db->Disconnect(); // closing database connection

