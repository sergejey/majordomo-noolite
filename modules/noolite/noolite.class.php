<?php
/**
 * Noolite
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 13:02:50 [Feb 29, 2016])
 */
//
//
class noolite extends module
{
    /**
     * noolite
     *
     * Module class constructor
     *
     * @access private
     */
    function noolite()
    {
        $this->name = "noolite";
        $this->title = "Noolite";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 0)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->data_source)) {
            $p["data_source"] = $this->data_source;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $data_source;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($data_source)) {
            $this->data_source = $data_source;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['DATA_SOURCE'] = $this->data_source;
        $out['TAB'] = $this->tab;
        $this->data = $out;

        if (!$this->ajax) {
            $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
            $this->result = $p->result;
        }


    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        $this->getConfig();
        $out['API_URL'] = $this->config['API_URL'];
        if (!$out['API_URL']) {
            $out['API_URL'] = 'http://';
        }
        $out['API_GATE'] = $this->config['API_GATE'];
        $out['API_TYPE'] = $this->config['API_TYPE'];
        $out['API_IGNORE'] = $this->config['API_IGNORE'];
        $out['API_BINDING'] = $this->config['API_BINDING'];
        $out['API_PORT'] = $this->config['API_PORT'];
        if ($this->view_mode == 'update_settings') {
            global $api_url;
            $this->config['API_URL'] = $api_url;
            global $api_type;
            $this->config['API_TYPE'] = $api_type;
            global $api_ignore;
            $this->config['API_IGNORE'] = (int)$api_ignore;
            global $api_binding;
            $this->config['API_BINDING'] = (int)$api_binding;

            global $api_gate;
            $this->config['API_GATE'] = $api_gate;


            global $api_port;
            $this->config['API_PORT'] = trim($api_port);

            $this->saveConfig();
            setGlobal('cycle_nooliteControl', 'restart');
            $this->redirect("?");
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }

        if($this->view_mode=='refresh_noodevices') {
            $this->pollNooDevices();
            $this->redirect("?");
        }

        if ($this->data_source == 'noodevices' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_noodevices') {
                $this->search_noodevices($out);
            }
            if ($this->view_mode == 'edit_noodevices') {
                $this->edit_noodevices($out, $this->id);
            }
            if ($this->view_mode == 'delete_noodevices') {
                $this->delete_noodevices($this->id);
                $this->redirect("?data_source=noodevices");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'noocommands') {
            if ($this->view_mode == '' || $this->view_mode == 'search_noocommands') {
                $this->search_noocommands($out);
            }
            if ($this->view_mode == 'edit_noocommands') {
                $this->edit_noocommands($out, $this->id);
            }
        }
    }

    function pollNooDevices($id=0) {

        if ($this->config['API_TYPE'] != 'windows_one' && $this->config['API_TYPE'] != 'serial') {
            // not supported
            return;
        }

        // CHECK POLLING DEVICES
        if ($id) {
            $polling=SQLSelect("SELECT * FROM noodevices WHERE ID=".(int)$id);
        } else {
            $polling=SQLSelect("SELECT * FROM noodevices WHERE POLL_PERIOD>0");
        }

        foreach($polling as &$pdevice) {
            if (!preg_match('/\_f$/', $pdevice['DEVICE_TYPE'])) {
                // not supported
                continue;
            }

            if ($id) {
                $tm=0;
            } else {
                $tm=strtotime($pdevice['UPDATED']);
            }

            if ((time()-$tm)>=$pdevice['POLL_PERIOD']) {
                $pdevice['UPDATED']=date('Y-m-d H:i:s');
                SQLUpdate('noodevices',$pdevice);
                $controller_mode = '2';
                $address = preg_replace('/\D/', '', $pdevice['ADDRESS']);
                $cmd_code = 128;
                $fmt = 0;
                $api_command = $controller_mode . ' 0 0 ' . $address . ' ' . $cmd_code . ' '.$fmt.' 0 0 0 0 00000000 0';
                //DebMes("Polling ".$pdevice['TITLE']." with API: $api_command",'noolite');
                $this->sendAPICommand($api_command);
            }
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {

        if ($out['module']) {
            $this->ajax = 1;
        }

        if ($this->ajax) {

            $op=gr('op');

            if ($op=='check_binding') {
                $start_binding=gr('start_binding','int');
                $id=gr('id','int');
                header("Content-type:application/json");
                $result=array();
                $tmp=SQLSelectOne("SELECT ID FROM noocommands WHERE DEVICE_ID=".$id." AND COMMAND_ID=15 AND UPDATED>='".date('Y-m-d H:i:s',$start_binding)."'");
                if ($tmp['ID']) {
                    $result['RESULT']='ok';
                } else {
                    $result['RESULT']='waiting';
                }
                echo json_encode($result);
                exit;
            }

            //DebMes("noolite request: " . $_SERVER['REQUEST_URI'], 'noolite');

            $feedback = 0;
            $value = 0;
            $this->getConfig();
            //DebMes('AJAX request: '.serialize($_GET),'noolite');

            if (preg_match('/^RECEIVED:(.+)/', $_GET['data'], $m)) {
                $tmp = explode(':', $m[1]);
                $_GET['did'] = $tmp[0];
                $_GET['cmd'] = $tmp[1];
                $command_id = hexdec($_GET['cmd']);
                if ($tmp[3] != '') {
                    $tmp[3] = str_replace(';', '', $tmp[3]);
                }

                $ok = 1;
                //if ($this->config['API_IGNORE']) {
                $device_rec = SQLSelectOne("SELECT ID FROM noodevices WHERE ADDRESS LIKE '" . DBSafe($_GET['did']) . "'");
                if (!$device_rec['ID']) {
                    DebMes("Data from unknown device: " . serialize($_GET), 'noolite');
                    $ok = 0;
                }
                //}

                if ($this->config['API_BINDING'] && $command_id == 15) {
                    $ok = 1;
                }

                if (!$ok) {
                    return 0;
                }

                if ($command_id == 21) {
                    $_GET['t'] = str_replace('t=', '', $tmp[2]);
                    $_GET['rh'] = str_replace('h=', '', $tmp[3]);
                }


            }

            if ($_GET['did']) {
                $addr = $_GET['did'];
                $command_id = hexdec($_GET['cmd']);
                $title = 'Device ' . $addr . ' (cmd: ' . $command_id . ')';
                $d0 = $_GET['d0'];
                $d1 = $_GET['d1'];
                $d2 = $_GET['d2'];
                $d3 = $_GET['d3'];
                $t = trim($_GET['t']);
                $rh = trim($_GET['rh']);
            } elseif ($this->config['API_TYPE'] == '' ||
                $this->config['API_TYPE'] == 'windows' ||
                $this->config['API_TYPE'] == 'windows_one' ||
                $this->config['API_TYPE'] == 'pr1132' ||
                $this->config['API_TYPE'] == 'serial'
            ) {
                if ($this->config['API_TYPE'] == 'serial') {
                    $addr = $_GET['cell'];
                } else {
                    $addr = 'cell' . $_GET['cell'];
                }

                $title = $_GET['name'];
                if (!$title) {
                    $title = $addr;
                }

                $command_id = (int)$_GET['cmd'];
                $d0 = $_GET['d0'];
                $d1 = $_GET['d1'];
                $d2 = $_GET['d2'];
                $d3 = $_GET['d3'];
            } elseif ($this->config['API_TYPE'] == 'linux' && IsSet($_GET['channel'])) {
                $addr = 'ch' . $_GET['channel'];
                $title = 'Channel ' . $_GET['channel'];
                $command_id = (int)$_GET['command'];
                $d0 = $_GET['d0'];
                $d1 = $_GET['d1'];
                $d2 = $_GET['d2'];
                $d3 = $_GET['d3'];
            }

            if ($addr=='') {
                echo "No address set";
                DebMes("No address set",'noolite');
                return;
            }

            if ($command_id == 128) { // wtf?
                return;
            }

            $req_qry = "(ADDRESS LIKE '" . DBSafe($addr) . "' OR ADDRESS='" . (int)preg_replace('/\D/', '', $addr) . "')";
            if ($this->config['API_TYPE'] != 'serial') {
                $req_qry .= " AND (DEVICE_TYPE='' OR DEVICE_TYPE='sensor' OR DEVICE_TYPE LIKE '%_f')";
            }
            $rec = SQLSelectOne("SELECT * FROM noodevices WHERE $req_qry");
            if (!$rec['ID']) {
                $rec['ADDRESS'] = $addr;
                $rec['TITLE'] = $title;
                $rec['UPDATED'] = date('Y-m-d H:i:s');
                $rec['ID'] = SQLInsert('noodevices', $rec);
            } else {
                $rec['UPDATED'] = date('Y-m-d H:i:s');
                SQLUpdate('noodevices', $rec);
            }

            $command_id2 = 0;

            if ($command_id == 0) { // off
                $command_id = 2;
                $value = '0';
            } elseif ($command_id == 2) {  // on
                $value = '1';
            } elseif ($command_id == 4) { // switch
                $value = '1';
            } elseif ($command_id == 25) { // motion
                $value = '1';
            } elseif ($command_id == 21) { // temperature/humidity
                $command_id = 121; // temperature
                if (IsSet($t)) {
                    $value = $t;
                } else {
                    $b1 = (int)str_replace('-', '', $d0);
                    $b2 = (int)str_replace('-', '', $d1);
                    $y_temp = 256 * ($b2 & 15) + $b1;
                    if (($b2 & 8) != 0) {
                        $y_temp = 4096 - $y_temp;
                        $value = -1 * ($y_temp) / 10;
                    } else {
                        $value = $y_temp / 10;
                    }
                }
                $command_id2 = 122; // humidity
                if (IsSet($rh) && $rh > 0) {
                    $value2 = $rh;
                } else {
                    $value2 = (int)str_replace('-', '', $d2);
                }
            } elseif ($command_id == 130) { // state command
                $feedback=1;
                //return; // temporary disabled
                //state received
                //d1 -- version
                //d2 -- state
                //d3 -- brightness
                if (($rec['DEVICE_TYPE'] == 'power_f') || ($rec['DEVICE_TYPE'] == 'power_dimmer_f')) {
                    //d2
                    $command_id = 102;  // power on/off
                    $bins = decbin((int)$d2);
                    $state_bin = substr($bins, 0, 4);
                    $state_val = bindec($state_bin);
                    if ($state_val == 0) {
                        $value = 0;
                    } else {
                        $value = 1;
                    }

                    //DebMes("Status bins: $bins (state: $state_bin), value: $value",'noolite');
                    if ($rec['DEVICE_TYPE'] == 'power_dimmer_f') {
                        $command_id2 = 103; // dimmer
                        $value2 = floor($d3 * 100 / 256);
                    }
                } else {
                    DebMes("Incorrect status command for device type ".$rec['DEVICE_TYPE'],'noolite');
                    return;
                }
            }


            //1st command
            $command = SQLSelectOne("SELECT * FROM noocommands WHERE DEVICE_ID='" . (int)$rec['ID'] . "' AND COMMAND_ID='" . (int)$command_id . "'");
            if (!$command['ID']) {
                $command['DEVICE_ID'] = $rec['ID'];
                $command['COMMAND_ID'] = $command_id;
                $command['ID'] = SQLInsert('noocommands', $command);
            }
            $command['VALUE'] = $value;
            $command['UPDATED'] = date('Y-m-d H:i:s');
            SQLUpdate('noocommands', $command);
            if ($command['LINKED_OBJECT'] && $command['LINKED_PROPERTY']) {
                if ($feedback) {
                    setGlobal($command['LINKED_OBJECT'] . '.' . $command['LINKED_PROPERTY'], $command['VALUE'], array($this->name => '0'), $this->name); //)
                } else {
                    setGlobal($command['LINKED_OBJECT'] . '.' . $command['LINKED_PROPERTY'], $command['VALUE'], 0, $this->name); //, array($this->name=>'0')
                }
            }
            if ($command['LINKED_OBJECT'] && $command['LINKED_METHOD']) {
                $params = array();
                $params['TITLE'] = $command['TITLE'];
                $params['VALUE'] = $command['VALUE'];
                $params['value'] = $command['VALUE'];
                callMethod($command['LINKED_OBJECT'] . '.' . $command['LINKED_METHOD'], $params);
            }

            if ($command['SCRIPT_ID']) {
                $params['VALUE'] = $command['VALUE'];
                $params['value'] = $command['VALUE'];
                runScript($command['SCRIPT_ID'], $params);
            }


            // 2nd command
            if ($command_id2) {

                $command_id = $command_id2;
                $value = $value2;

                $command = SQLSelectOne("SELECT * FROM noocommands WHERE DEVICE_ID='" . (int)$rec['ID'] . "' AND COMMAND_ID='" . (int)$command_id . "'");
                if (!$command['ID']) {
                    $command['DEVICE_ID'] = $rec['ID'];
                    $command['COMMAND_ID'] = $command_id;
                    $command['ID'] = SQLInsert('noocommands', $command);
                }
                $command['VALUE'] = $value;
                $command['UPDATED'] = date('Y-m-d H:i:s');
                SQLUpdate('noocommands', $command);
                if ($command['LINKED_OBJECT'] && $command['LINKED_PROPERTY']) {
                    if ($feedback) {
                        setGlobal($command['LINKED_OBJECT'] . '.' . $command['LINKED_PROPERTY'], $command['VALUE'], array($this->name => '0'),$this->name); //)
                    } else {
                        setGlobal($command['LINKED_OBJECT'] . '.' . $command['LINKED_PROPERTY'], $command['VALUE'], 0, $this->name); //, array($this->name=>'0')
                    }
                }
                if ($command['LINKED_OBJECT'] && $command['LINKED_METHOD']) {
                    $params = array();
                    $params['TITLE'] = $command['TITLE'];
                    $params['VALUE'] = $command['VALUE'];
                    $params['value'] = $command['VALUE'];
                    callMethod($command['LINKED_OBJECT'] . '.' . $command['LINKED_METHOD'], $params);
                }

            }

            //load preset
            if ($command_id == '7') {
                $commands = SQLSelect("SELECT noocommands.*, nooscenarios.VALUE AS SCENE_VALUE FROM nooscenarios LEFT JOIN noocommands ON nooscenarios.COMMAND_ID=noocommands.ID WHERE nooscenarios.MASTER_DEVICE_ID='" . $rec['ID'] . "' AND nooscenarios.COMMAND_ID>0");
                $total = count($commands);
                for ($i = 0; $i < $total; $i++) {
                    if (!$commands[$i]['ID']) {
                        continue;
                    }
                    if ($commands[$i]['LINKED_OBJECT'] && $commands[$i]['LINKED_PROPERTY']) {
                        setGlobal($commands[$i]['LINKED_OBJECT'] . '.' . $commands[$i]['LINKED_PROPERTY'], $commands[$i]['SCENE_VALUE'], array($this->name => '0'), $this->name);
                        $commands[$i]['VALUE'] = $commands[$i]['SCENE_VALUE'];
                    }
                    $commands[$i]['VALUE'] = $commands[$i]['SCENE_VALUE'];
                    $commands[$i]['UPDATED'] = date('Y-m-d H:i:s');
                    unset($commands[$i]['SCENE_VALUE']);
                    SQLUpdate('noocommands', $commands[$i]);
                }

                if ($rec['SCENARIO_ADDRESS']) {
                    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
                        $api_command = '-load_preset_ch' . $rec['SCENARIO_ADDRESS'];
                    } elseif ($this->config['API_TYPE'] == 'linux') {
                        $api_command = '--load ' . $rec['SCENARIO_ADDRESS'];
                    }
                    $this->sendAPICommand($api_command);
                }


            }

            //save preset
            if ($command_id == '8') {
                $scenarios = SQLSelect("SELECT * FROM nooscenarios WHERE MASTER_DEVICE_ID='" . $rec['ID'] . "'");
                $total = count($scenarios);
                for ($i = 0; $i < $total; $i++) {
                    $linked_command = SQLSelectOne("SELECT * FROM noocommands WHERE DEVICE_ID='" . $scenarios[$i]['DEVICE_ID'] . "' AND (COMMAND_ID='102' OR COMMAND_ID='103')");
                    if ($linked_command['ID']) {
                        $scenarios[$i]['VALUE'] = $linked_command['VALUE'];
                        $scenarios[$i]['COMMAND_ID'] = $linked_command['ID'];
                        SQLUpdate('nooscenarios', $scenarios[$i]);
                    }
                }
            }
            echo "OK";

        }
    }

    /**
     * noodevices search
     *
     * @access public
     */
    function search_noodevices(&$out)
    {
        require(DIR_MODULES . $this->name . '/noodevices_search.inc.php');
    }

    /**
     * noodevices edit/add
     *
     * @access public
     */
    function edit_noodevices(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/noodevices_edit.inc.php');
    }

    /**
     * noodevices delete record
     *
     * @access public
     */
    function delete_noodevices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM noodevices WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM noocommands WHERE DEVICE_ID='" . $rec['ID'] . "'");
        SQLExec("DELETE FROM noodevices WHERE ID='" . $rec['ID'] . "'");
    }

    /**
     * noocommands search
     *
     * @access public
     */
    function search_noocommands(&$out)
    {
        require(DIR_MODULES . $this->name . '/noocommands_search.inc.php');
    }

    /**
     * noocommands edit/add
     *
     * @access public
     */
    function edit_noocommands(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/noocommands_edit.inc.php');
    }


    function sendAPICommand($api_command)
    {
        $cmdline = '';
        startMeasure('noolite_sendapi');

        if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
            if (file_exists("c:/Program Files/nooLite/nooLite.exe")) {
                $cmdline = '"c:/Program Files/nooLite/nooLite.exe" -api ' . $api_command;
            } elseif (file_exists("c:/Program Files (x86)/nooLite/nooLite.exe")) {
                $cmdline = '"c:/Program Files (x86)/nooLite/nooLite.exe" -api ' . $api_command;
            } else {
                DebMes("Noolite App not found", 'noolite');
            }
        } elseif ($this->config['API_TYPE'] == 'windows_one') {
            if (file_exists("c:/Program Files/nooLite ONE/nooLite_ONE.exe")) {
                $cmdline = '"c:/Program Files/nooLite ONE/nooLite_ONE.exe" api ' . $api_command;
            } elseif (file_exists("c:/Program Files (x86)/nooLite ONE/nooLite_ONE.exe")) {
                $cmdline = '"c:/Program Files (x86)/nooLite ONE/nooLite_ONE.exe" api ' . $api_command;
            } else {
                DebMes("Noolite App not found", 'noolite');
            }

        } elseif ($this->config['API_TYPE'] == 'http' && $this->config['API_URL']) {
            $url = $this->config['API_URL'] . ($api_command);
            DebMes("Sending noo api request: " . $url, 'noolite');
            getURL($url, 0);
        } elseif ($this->config['API_TYPE'] == 'pr1132' && $this->config['API_GATE']) {
            $url = 'http://' . $this->config['API_GATE'] . '/api.htm?' . ($api_command);
            DebMes("Sending noo api request: " . $url, 'noolite');
            getURL($url, 0);
        } elseif ($this->config['API_TYPE'] == 'serial') {
            addToOperationsQueue('noolite_queue','command',$api_command);
            /*
            $current_queue = getGlobal('noolitePushMessage');
            $queue = explode("\n", $current_queue);
            $queue[] = $api_command;
            if (count($queue) >= 25) {
                $queue = array_slice($queue, -25);
            }
            setGlobal('noolitePushMessage', implode("\n", $queue));
            */

        } elseif ($this->config['API_TYPE'] == 'linux') {
            $cmdline = 'sudo noolitepc ' . $api_command;
        }

        if ($cmdline) {
            $latest_command_sent = (int)getGlobal('ThisComputer.LatestNooliteCommand');
            $diff = $latest_command_sent - time();
            if ($diff < 0) {
                DebMes("Noolite instant cmd: " . $cmdline, 'noolite');
                setGlobal('ThisComputer.LatestNooliteCommand', time(), 0, $this->name);
                exec($cmdline);
            } else {
                $diff = $diff + 1;
                DebMes("Noolite delayed (" . ($diff) . ") cmd: " . $cmdline, 'noolite');
                setGlobal('ThisComputer.LatestNooliteCommand', time() + $diff, 0, $this->name);
                setTimeOut('noocommand' . md5($cmdline), 'exec(\'' . $cmdline . '\');', $diff);
            }
        }
        endMeasure('noolite_sendapi');
    }

    function propertySetHandle($object, $property, $value)
    {
        $this->getConfig();

        if ($property == '' && is_numeric($object)) {
            $commands = SQLSelect("SELECT noocommands.*, noodevices.DEVICE_TYPE, noodevices.ADDRESS, noodevices.SCENARIO_ADDRESS FROM noocommands LEFT JOIN noodevices ON noocommands.DEVICE_ID=noodevices.ID WHERE noocommands.ID=" . (int)$object);
        } else {
            $commands = SQLSelect("SELECT noocommands.*, noodevices.DEVICE_TYPE, noodevices.ADDRESS, noodevices.SCENARIO_ADDRESS FROM noocommands LEFT JOIN noodevices ON noocommands.DEVICE_ID=noodevices.ID WHERE LINKED_OBJECT LIKE '" . DBSafe($object) . "' AND LINKED_PROPERTY LIKE '" . DBSafe($property) . "'");
        }

        //DebMes("nooCommand: $object.$property",'noolite');
        $total = count($commands);
        if ($total) {
            for ($i = 0; $i < $total; $i++) {
                //to-do
                $api_command = '';
                $cmdline = '';

                if ($this->config['API_TYPE'] != 'http') {
                    $commands[$i]['ADDRESS'] = preg_replace('/\D/', '', $commands[$i]['ADDRESS']);
                    $commands[$i]['SCENARIO_ADDRESS'] = preg_replace('/\D/', '', $commands[$i]['SCENARIO_ADDRESS']);
                }

                if (preg_match('/f$/', $commands[$i]['DEVICE_TYPE'])) {
                    $controller_mode = '2';
                } else {
                    $controller_mode = '0';
                }

                // LOAD PRESET
                if ($commands[$i]['COMMAND_ID'] == '7' && $value && $commands[$i]['SCENARIO_ADDRESS']) { //load preset

                    $commands_linked = SQLSelect("SELECT noocommands.*, nooscenarios.VALUE AS SCENE_VALUE FROM nooscenarios LEFT JOIN noocommands ON nooscenarios.COMMAND_ID=noocommands.ID WHERE nooscenarios.MASTER_DEVICE_ID='" . $commands[$i]['DEVICE_ID'] . "' AND nooscenarios.COMMAND_ID>0");
                    $total2 = count($commands_linked);
                    for ($i2 = 0; $i2 < $total2; $i2++) {
                        if (!$commands_linked[$i2]['ID']) {
                            continue;
                        }
                        if ($commands_linked[$i2]['LINKED_OBJECT'] && $commands_linked[$i2]['LINKED_PROPERTY']) {
                            setGlobal($commands_linked[$i2]['LINKED_OBJECT'] . '.' . $commands_linked[$i2]['LINKED_PROPERTY'], $commands_linked[$i2]['SCENE_VALUE'], array($this->name => '0'), $this->name);
                            $commands_linked[$i2]['VALUE'] = $commands_linked[$i2]['SCENE_VALUE'];
                        }
                        $commands_linked[$i2]['VALUE'] = $commands_linked[$i2]['SCENE_VALUE'];
                        $commands_linked[$i2]['UPDATED'] = date('Y-m-d H:i:s');
                        unset($commands_linked[$i2]['SCENE_VALUE']);
                        SQLUpdate('noocommands', $commands_linked[$i2]);
                    }

                    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
                        $api_command = '-load_preset_ch' . $commands[$i]['SCENARIO_ADDRESS'];
                    } elseif ($this->config['API_TYPE'] == 'windows_one' || $this->config['API_TYPE'] == 'serial') {
                        $cmd_code = 7;
                        $api_command = $controller_mode . ' 0 0 ' . $commands[$i]['SCENARIO_ADDRESS'] . ' ' . $cmd_code . ' 0 0 0 0 0 00000000 0';
                    } elseif ($this->config['API_TYPE'] == 'linux') {
                        $api_command = '--load ' . $commands[$i]['SCENARIO_ADDRESS'];
                    } elseif ($this->config['API_TYPE'] == 'http') {
                        $api_command = 'CHANNEL:' . $commands[$i]['SCENARIO_ADDRESS'] . ':7'; // ???
                    } elseif ($this->config['API_TYPE'] == 'pr1132') {
                        $api_command = 'ch=' . $commands[$i]['SCENARIO_ADDRESS'] . '&cmd=7';
                    }
                }

                // SWITCH ON/OFF
                if ($commands[$i]['COMMAND_ID'] == '102') { //switch on/off
                    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
                        if ($value) {
                            $api_command = '-on_ch' . $commands[$i]['ADDRESS'];
                        } else {
                            $api_command = '-off_ch' . $commands[$i]['ADDRESS'];
                        }
                    } elseif ($this->config['API_TYPE'] == 'linux') {
                        if ($value) {
                            $api_command = '--on ' . $commands[$i]['ADDRESS'];
                        } else {
                            $api_command = '--off ' . $commands[$i]['ADDRESS'];
                        }
                    } elseif ($this->config['API_TYPE'] == 'windows_one' || $this->config['API_TYPE'] == 'serial') {
                        if ($value) {
                            $cmd_code = 2;
                        } else {
                            $cmd_code = 0;
                        }
                        $api_command = $controller_mode . ' 0 0 ' . $commands[$i]['ADDRESS'] . ' ' . $cmd_code . ' 0 0 0 0 0 00000000 0';
                    } elseif ($this->config['API_TYPE'] == 'http') {
                        if ($value) {
                            $api_command = 'CHANNEL:' . $commands[$i]['ADDRESS'] . ':2';
                        } else {
                            $api_command = 'CHANNEL:' . $commands[$i]['ADDRESS'] . ':0';
                        }
                    } elseif ($this->config['API_TYPE'] == 'pr1132') {
                        if ($value) {
                            $api_command = 'ch=' . $commands[$i]['ADDRESS'] . '&cmd=2';
                        } else {
                            $api_command = 'ch=' . $commands[$i]['ADDRESS'] . '&cmd=0';
                        }
                    }
                }

                // BRIGHTNESS LEVEL
                if ($commands[$i]['COMMAND_ID'] == '103') { //dimmer brightness
                    $v = floor($value * 256 / 100);
                    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
                        $api_command = '-set_ch' . $commands[$i]['ADDRESS'] . ' -' . $v;
                    } elseif ($this->config['API_TYPE'] == 'windows_one' || $this->config['API_TYPE'] == 'serial') {
                        // SET_BR.bat - установка яркости (10 - номер канала, 6 - команда установки яркости/цвета, 1 - формат для установки яркости, 100 - уровень яркости);
                        // "C:\Program Files (x86)\nooLite ONE\nooLite_ONE.exe" api 0 0 0 10 6 1 100 0 0 0 00000000 0
                        $cmd_code = 6;
                        $d0 = (int)$value;
                        $d1 = 0;
                        $d2 = 0;
                        $d3 = 0;
                        $api_command = $controller_mode . ' 0 0 ' . $commands[$i]['ADDRESS'] . ' ' . $cmd_code . ' 1 ' . $d0 . ' ' . $d1 . ' ' . $d2 . ' ' . $d3 . ' 00000000 0';
                    } elseif ($this->config['API_TYPE'] == 'linux') {
                        $api_command = '--set ' . $commands[$i]['ADDRESS'] . ' ' . $v;
                    }
                }

                // RGB COLOR
                if ($commands[$i]['COMMAND_ID'] == '104') { //rgb
                    $tmp = explode('-', $value);
                    if (isset($tmp[1])) {
                        $r = (int)$tmp[0];
                        $g = (int)$tmp[1];
                        $b = (int)$tmp[2];
                    } else {
                        $tmp = str_replace('#', '', $value);
                        $r = round(hexdec(substr($tmp, 0, 2)) * 100 / 256);
                        $g = round(hexdec(substr($tmp, 2, 2)) * 100 / 256);
                        $b = round(hexdec(substr($tmp, 4, 2)) * 100 / 256);
                        DebMes($tmp . '=' . "$r-$g-$b", 'noolite');
                    }
                    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
                        $api_command = '-set_color_ch' . $commands[$i]['ADDRESS'] . ' -' . $r . ' -' . $g . ' -' . $b;
                    } elseif ($this->config['API_TYPE'] == 'windows_one' || $this->config['API_TYPE'] == 'serial') {
                        // SET_COLOR.bat - установка цвета RGB (10 - номер канала, 6 - команда установки яркости/цвета, 3 - формат для установки цвета, 100 - уровень красного, 255 - уровень зелёного, 200 - уровень синего);
                        $cmd_code = 6;
                        $d0 = $r;
                        $d1 = $g;
                        $d2 = $b;
                        $d3 = 0;
                        $api_command = $controller_mode . ' 0 0 ' . $commands[$i]['ADDRESS'] . ' ' . $cmd_code . ' 3 ' . $d0 . ' ' . $d1 . ' ' . $d2 . ' ' . $d3 . ' 000000 0 0 0';
                    } elseif ($this->config['API_TYPE'] == 'linux') {
                        $api_command = '--color ' . $commands[$i]['ADDRESS'] . ' ' . $r . ' ' . $g . ' ' . $b;
                    }
                }

                // ROLL COLOR COMMAND
                if ($commands[$i]['COMMAND_ID'] == '105') { //roll color
                    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
                        if ($value) {
                            $api_command = '-roll_color_ch' . $commands[$i]['ADDRESS'];
                        } else {
                            $api_command = '-stop_reg_ch' . $commands[$i]['ADDRESS'];
                        }
                    }
                }

                // SWITCH SPEED COMMAND
                if ($commands[$i]['COMMAND_ID'] == '106') { //switch speed
                    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
                        if ($value) {
                            $api_command = '-speed_mode_sw_ch' . $commands[$i]['ADDRESS'];
                        }
                    }
                }

                // SWITCH MODE COMMAND
                if ($commands[$i]['COMMAND_ID'] == '107') { //switch mode
                    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
                        if ($value) {
                            $api_command = '-sw_mode_ch' . $commands[$i]['ADDRESS'];
                        }
                    }
                }

                if ($api_command) {
                    $this->sendAPICommand($api_command);
                }


                unset($commands[$i]['DEVICE_TYPE']);
                unset($commands[$i]['ADDRESS']);
                unset($commands[$i]['SCENARIO_ADDRESS']);
                $commands[$i]['UPDATED'] = date('Y-m-d H:i:s');
                $commands[$i]['VALUE'] = $value;
                SQLUpdate('noocommands', $commands[$i]);
                SQLExec("UPDATE noodevices SET UPDATED='" . $commands[$i]['UPDATED'] . "' WHERE ID='" . $commands[$i]['DEVICE_ID'] . "'");


            }
        }
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS noodevices');
        SQLExec('DROP TABLE IF EXISTS noocommands');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data = '')
    {
        /*
        noodevices -
        noocommands -
        */
        $data = <<<EOD
 noodevices: ID int(10) unsigned NOT NULL auto_increment
 noodevices: TITLE varchar(100) NOT NULL DEFAULT ''
 noodevices: DEVICE_TYPE varchar(255) NOT NULL DEFAULT ''
 noodevices: ADDRESS varchar(255) NOT NULL DEFAULT ''
 noodevices: SCENARIO_ADDRESS varchar(255) NOT NULL DEFAULT ''
 noodevices: UPDATED datetime
 noodevices: POLL_PERIOD int(10) unsigned NOT NULL DEFAULT 0 
 noodevices: LOCATION_ID int(10) unsigned NOT NULL DEFAULT 0  
 noodevices: DESCRIPTION varchar(100) NOT NULL DEFAULT ''

 noocommands: ID int(10) unsigned NOT NULL auto_increment
 noocommands: TITLE varchar(100) NOT NULL DEFAULT ''
 noocommands: VALUE varchar(255) NOT NULL DEFAULT ''
 noocommands: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 noocommands: COMMAND_ID int(10) NOT NULL DEFAULT '0'
 noocommands: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 noocommands: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 noocommands: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 noocommands: SCRIPT_ID int(10) NOT NULL DEFAULT '0'
 noocommands: UPDATED datetime

 nooscenarios: ID int(10) unsigned NOT NULL auto_increment
 nooscenarios: MASTER_DEVICE_ID int(10) NOT NULL DEFAULT '0'
 nooscenarios: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 nooscenarios: COMMAND_ID int(10) NOT NULL DEFAULT '0'
 nooscenarios: VALUE varchar(255) NOT NULL DEFAULT ''


EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRmViIDI5LCAyMDE2IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
