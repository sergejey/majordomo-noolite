<?php
chdir(dirname(__FILE__) . '/../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");

$checkedTime=0;
set_time_limit(0);

// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);

include_once("./load_settings.php");


include_once(DIR_MODULES . 'noolite/noolite.class.php');
echo date("H:i:s") . " running " . basename(__FILE__) . "\n";

$noo = new noolite();
$noo->getConfig();

if ($noo->config['API_TYPE'] == 'serial') {

    $device_path=$noo->config['API_PORT'];
    if (!$device_path) {
        $device_path='/dev/ttyUSB0';
    }
    $device_speed=9600;
    $init_str='stty -F '.$device_path.' ispeed '.$device_speed.' ospeed '.$device_speed.' -ignpar cs8 -cstopb';
    echo date('Y-m-d H:i:s')." Init string: ".$init_str."\n";
    exec($init_str);

    echo date('Y-m-d H:i:s')." Opening port ".$device_path."\n";
    $fp =fopen($device_path, "w+");

    $buffer='';
    if ($fp) {
        echo "OK\n";

        stream_set_blocking($fp, 0);
        $read_time = 0;

        $readInProgress=0;
        $readStarted=0;
        while (1) {

            if ((time()-$checkedTime)>=5) {
                $checkedTime=time();
                setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
            }

            $r = fread($fp,1);
            $ch=binaryToString($r);
            if ($ch=='ad') {
                $in = $ch;
                $readInProgress=1;
                $readStarted=time();
            } elseif ($ch=='ae') {
                $line=$in.$ch;
                echo date('H:i:s')." In: [".$line."];\n";
                $data=HexStringToArray($line);
                /*
                echo "Data:\n";
                print_r($data);
                */
                $readInProgress=0;

                $url=BASE_URL.'/ajax/noolite.html?serial=1';
                $url.='&mode='.$data[1];
                $url.='&answ='.$data[2];
                $url.='&toggl='.$data[3];
                $url.='&cell='.$data[4];
                $url.='&cmd='.$data[5];
                $url.='&fmt='.$data[6];
                $url.='&d0='.$data[7];
                $url.='&d1='.$data[8];
                $url.='&d2='.$data[9];
                $url.='&d3='.$data[10];
                $url.='&id='.binaryToString(makePayload(array($data[11],$data[12],$data[13],$data[14])));
                $url.='&crc='.$data[15];
                echo date('Y-m-d H:i:s')." URL: $url\n";
                $res = get_headers($url);
            } else {
                $in.=$ch;
                $readInProgress=2;
            }

            if ($readInProgress>0 && (time()-$readStarted)>3) {
                //read not finished within 3 seconds for some reason
                $readInProgress=0;
            }

            if ($readInProgress>0) {
                usleep(10000);
                continue;
            }

            $current_queue=getGlobal('noolitePushMessage');
            if ($current_queue!='') {
                setGlobal('noolitePushMessage', '');
                $queue=explode("\n", $current_queue);
                $total=count($queue);
                $sent_ok=true;
                for($i=0;$i<$total;$i++) {
                    if (!$queue[$i]) {
                        continue;
                    }
                    if ($i>0) {
                        usleep(200000);
                    }
                    echo "Sending: ".$queue[$i]."\n";
                    $tmp = explode(' ',$queue[$i]);
                    $total = count($tmp);
                    $in = '';
                    $crc = 0;
                    for ($i = 0; $i < $total; $i++) {
                        if (strlen($tmp[$i])<=2) {
                            $in .= str_pad(dechex((int)$tmp[$i]),2,'0',STR_PAD_LEFT);
                        } else {
                            $in .= $tmp[$i];
                        }
                    }
                    $in = substr($in,0,14*2);
                    $in = dechex(171) . $in;
                    $total_bytes=(int)(strlen($in)/2);
                    for($i=0;$i<$total_bytes;$i++) {
                        $crc+=hexdec(substr($in,$i*2,2));
                    }
                    $high_byte=floor($crc/256);
                    $low_byte=$crc-$high_byte*256;

                    $in .= dechex($crc);
                    $in .= dechex(172);
                    echo date('Y-m-d H:i:s')." Sending (hex): ".$in." (".(int)(strlen($in)/2).")\n";
                    $payload=makePayload(HexStringToArray($in));
                    $send_status=fwrite($fp, $payload,17);
                    if (!$send_status) {
                        echo date('Y-m-d H:i:s')." Sending ERROR\n";
                    } else {
                        echo date('Y-m-d H:i:s')." Sent OK\n";
                    }
                }
            }


            if (file_exists('./reboot') || IsSet($_GET['onetime']))
            {
                fclose($fp);
                $db->Disconnect();
                exit;
            }

            //echo "Wating for data...\n";
            usleep(10000);

        }
    } else {
        echo date('Y-m-d H:i:s')." Cannot open port at $device_path \n";
    }

}

$db->Disconnect(); // closing database connection
