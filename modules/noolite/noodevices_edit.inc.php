<?php
/*
* @version 0.1 (wizard)
*/
$this->getConfig();

if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$table_name = 'noodevices';
$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");

if (preg_match('/\_f$/',$rec['DEVICE_TYPE'])) {
    $out['CAN_POLL']=1;
}

$ch = preg_replace('/\D/', '', $rec['ADDRESS']);

if ($this->mode == 'autobind' && (int)$rec['SCENARIO_ADDRESS']) {
    global $bind_id;
    $original = SQLSelectOne("SELECT * FROM noodevices WHERE ID='" . (int)$bind_id . "'");
    $original_ch = preg_replace('/\D/', '', $original['ADDRESS']);
    if ($original_ch) {
        $msg = "Auto-binding to " . (int)$rec['SCENARIO_ADDRESS'] . " originally binded to $original_ch";
        //1. Send bind from Original Channel
        if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
            $api_command = '-bind_' . $original_ch;
        } elseif ($this->config['API_TYPE'] == 'linux') {
            $api_command = '--bind ' . $original_ch;
        }
        $this->sendAPICommand($api_command);
        sleep(2);
        //2. Send bind from New channel
        if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
            $api_command = '-bind_' . (int)$rec['SCENARIO_ADDRESS'];
        } elseif ($this->config['API_TYPE'] == 'linux') {
            $api_command = '--bind ' . (int)$rec['SCENARIO_ADDRESS'];
        }
        $this->sendAPICommand($api_command);
        sleep(2);
        //3. Send bind from New channel
        if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
            $api_command = '-bind_' . (int)$rec['SCENARIO_ADDRESS'];
        } elseif ($this->config['API_TYPE'] == 'linux') {
            $api_command = '--bind ' . (int)$rec['SCENARIO_ADDRESS'];
        }
        $this->sendAPICommand($api_command);

        $out['MESSAGE'] = $msg;
    }
}

if ($this->mode == 'start_binding' && $ch != '') {
    $out['MESSAGE'] = 'Включен режим привязки для канала #' . $ch;
    if ($this->config['API_TYPE'] == 'linux') {
        $api_command = 'bind ' . $ch;
        safe_exec('php ' . DIR_MODULES . 'noolite/socket.php ' . $api_command);
    } elseif ($this->config['API_TYPE'] == 'windows_one' || $this->config['API_TYPE'] == 'serial') {
        //включение режима привязки датчика (1 - режим работы адаптера nooLite-RX, 3 -включить привязку, 10 - номер канала);
        //1 3 0 10 0 0 0 0 0 0 00000000 0
        $cmd_code = 0;
        $d0 = 0;
        $d1 = 0;
        $d2 = 0;
        $d3 = 0;
        $api_command = '1 3 0 ' . $ch . ' ' . $cmd_code . ' 0 ' . $d0 . ' ' . $d1 . ' ' . $d2 . ' ' . $d3 . ' 000000 0 0 0';
        $this->sendAPICommand($api_command);
    }
    $out['START_BINDING']=time();
}

if ($this->mode == 'stop_binding' && $ch != '') {
    $out['MESSAGE'] = 'Остановлен режим привязки для кнаала #' . $ch;
    if ($this->config['API_TYPE'] == 'linux') {
        $api_command = 'stop ' . $ch;
        safe_exec('php ' . DIR_MODULES . 'noolite/socket.php ' . $api_command);
    } elseif ($this->config['API_TYPE'] == 'windows_one' || $this->config['API_TYPE'] == 'serial') {
        $cmd_code = 0;
        $d0 = 0;
        $d1 = 0;
        $d2 = 0;
        $d3 = 0;
        $api_command = '1 4 0 ' . $ch . ' ' . $cmd_code . ' 0 ' . $d0 . ' ' . $d1 . ' ' . $d2 . ' ' . $d3 . ' 000000 0 0 0';
        $this->sendAPICommand($api_command);
    }
}

if ($this->mode == 'clear_binding' && $ch != '') {
    $out['MESSAGE'] = 'Режим очистки привязки на канале #' . $ch;
    if ($this->config['API_TYPE'] == 'linux') {
        $api_command = 'stop ' . $ch;
        safe_exec('php ' . DIR_MODULES . 'noolite/socket.php ' . $api_command);
    } elseif ($this->config['API_TYPE'] == 'windows_one' || $this->config['API_TYPE'] == 'serial') {
        $cmd_code = 0;
        $d0 = 0;
        $d1 = 0;
        $d2 = 0;
        $d3 = 0;
        $api_command = '1 5 0 ' . $ch . ' ' . $cmd_code . ' 0 ' . $d0 . ' ' . $d1 . ' ' . $d2 . ' ' . $d3 . ' 000000 0 0 0';
        $this->sendAPICommand($api_command);
    }
}


if ($this->mode == 'bind' && $ch != '') {
    $out['MESSAGE'] = 'Отправлена команда привязки для канала #' . $ch;

    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
        $api_command = '-bind_' . $ch;
    } elseif ($this->config['API_TYPE'] == 'windows_one' || $this->config['API_TYPE'] == 'serial') {
        //0 0 0 10 15 0 0 0 0 0 00000000 0
        $cmd_code = 15;
        $d0 = 0;
        $d1 = 0;
        $d2 = 0;
        $d3 = 0;
        if (preg_match('/f$/', $rec['DEVICE_TYPE'])) {
            $controller_mode = '2';
        } else {
            $controller_mode = '0';
        }
        $api_command = $controller_mode . ' 0 0 ' . $ch . ' ' . $cmd_code . ' 0 ' . $d0 . ' ' . $d1 . ' ' . $d2 . ' ' . $d3 . ' 00000000 0';
    } elseif ($this->config['API_TYPE'] == 'linux') {
        $api_command = '--bind ' . $ch;
    } elseif ($this->config['API_TYPE'] == 'http') {
        $api_command = 'CHANNEL:' . $ch . ':15';
    } elseif ($this->config['API_TYPE'] == 'pr1132') {
        $api_command = 'ch=' . $ch . '&cmd=15';
    }
    $this->sendAPICommand($api_command);
}

if ($this->mode == 'unbind' && $ch != '') {
    $out['MESSAGE'] = 'Отправлена команда снятия привязки для канала #' . $ch;

    if ($this->config['API_TYPE'] == '' || $this->config['API_TYPE'] == 'windows') {
        $api_command = '-unbind_' . $ch;
    } elseif ($this->config['API_TYPE'] == 'linux') {
        $api_command = '--unbind ' . $ch;
    } elseif ($this->config['API_TYPE'] == 'http') {
        $api_command = 'CHANNEL:' . $ch . ':9';
    } elseif ($this->config['API_TYPE'] == 'pr1132') {
        $api_command = 'ch=' . $ch . '&cmd=9';
    }
    $this->sendAPICommand($api_command);


}

if ($this->mode == 'poll') {
    $this->pollNooDevices($rec['ID']);
    $this->redirect("?view_mode=".$this->view_mode."&id=".$rec['ID']."&tab=".$this->tab);
}

if ($this->mode == 'update') {
    $ok = 1;
    // step: default
    if ($this->tab == '') {
        //updating '<%LANG_TITLE%>' (varchar, required)
        global $title;
        $rec['TITLE'] = $title;
        if ($rec['TITLE'] == '') {
            $out['ERR_TITLE'] = 1;
            $ok = 0;
        }
        //updating 'DEVICE_TYPE' (varchar)
        global $device_type;
        $rec['DEVICE_TYPE'] = $device_type;

        global $address;
        $rec['ADDRESS'] = $address;

        global $description;
        $rec['DESCRIPTION'] = $description;

        global $location_id;
        $rec['LOCATION_ID'] = (int)$location_id;

        $rec['POLL_PERIOD'] = gr('poll_period','int');


    }
    // step: data
    if ($this->tab == 'data') {
    }
    // step: scenarios
    if ($this->tab == 'scenarios') {
        global $scenario_address;
        $rec['SCENARIO_ADDRESS'] = (int)$scenario_address;
    }


    //UPDATING RECORD
    if ($ok) {
        if ($rec['ID']) {
            unset($rec['UPDATED']);
            SQLUpdate($table_name, $rec); // update
        } else {
            $new_rec = 1;
            $rec['ID'] = SQLInsert($table_name, $rec); // adding new record
        }

        if ($rec['LOCATION_ID']) {
            $location_title = getRoomObjectByLocation($rec['LOCATION_ID'], 1);
        }

        $commands = array();

        // POWER
        if ($rec['DEVICE_TYPE'] == 'power' ||
            $rec['DEVICE_TYPE'] == 'power_f' ||
            $rec['DEVICE_TYPE'] == 'power_dimmer_f' ||
            $rec['DEVICE_TYPE'] == 'power_dimmer' ||
            $rec['DEVICE_TYPE'] == 'power_rgb'
        ) {
            $command = array();
            $command['DEVICE_ID'] = $rec['ID'];
            $command['COMMAND_ID'] = 102; //turn on/off
            $command['VALUE'] = 0;
            $commands[] = $command;
        }

        // DIMMER
        if ($rec['DEVICE_TYPE'] == 'power_dimmer' || $rec['DEVICE_TYPE'] == 'power_dimmer_f') {
            $command = array();
            $command['DEVICE_ID'] = $rec['ID'];
            $command['COMMAND_ID'] = 103; //dim
            $command['VALUE'] = 0;
            $commands[] = $command;
        }

        //RGB
        if ($rec['DEVICE_TYPE'] == 'power_rgb') {
            $command = array();
            $command['DEVICE_ID'] = $rec['ID'];
            $command['COMMAND_ID'] = 104; //rgb
            $command['VALUE'] = 0;
            $commands[] = $command;

            $command = array();
            $command['DEVICE_ID'] = $rec['ID'];
            $command['COMMAND_ID'] = 105; //roll color
            $command['VALUE'] = 0;
            $commands[] = $command;

            $command = array();
            $command['DEVICE_ID'] = $rec['ID'];
            $command['COMMAND_ID'] = 106; //speed switch
            $command['VALUE'] = 0;
            $commands[] = $command;

            $command = array();
            $command['DEVICE_ID'] = $rec['ID'];
            $command['COMMAND_ID'] = 107; //mode switch
            $command['VALUE'] = 0;
            $commands[] = $command;
        }

        foreach ($commands as $command) {
            $tmp = SQLSelectOne("SELECT ID FROM noocommands WHERE DEVICE_ID=" . $rec['ID'] . " AND COMMAND_ID=" . $command['COMMAND_ID']);
            if (!$tmp['ID']) {
                SQLInsert('noocommands', $command);
            }
        }


        $out['OK'] = 1;
    } else {
        $out['ERR'] = 1;
    }
}
// step: default
if ($this->tab == '') {
}

if ($this->tab == 'scenarios') {
    $devices = SQLSelect("SELECT * FROM noodevices WHERE (DEVICE_TYPE='power' OR DEVICE_TYPE='power_dimmer') ORDER BY ADDRESS, TITLE");

    $total = count($devices);
    for ($i = 0; $i < $total; $i++) {

        if ($this->mode == 'update') {
            global ${"linked" . $devices[$i]['ID']};


            $old_rec = SQLSelectOne("SELECT * FROM nooscenarios WHERE MASTER_DEVICE_ID='" . $rec['ID'] . "' AND DEVICE_ID='" . $devices[$i]['ID'] . "'");
            SQLExec("DELETE FROM nooscenarios WHERE MASTER_DEVICE_ID='" . $rec['ID'] . "' AND DEVICE_ID='" . $devices[$i]['ID'] . "'");

            if (${"linked" . $devices[$i]['ID']}) {
                unset($old_rec['ID']);
                $old_rec['MASTER_DEVICE_ID'] = $rec['ID'];
                $old_rec['DEVICE_ID'] = $devices[$i]['ID'];
                SQLInsert('nooscenarios', $old_rec);
            }

        }

        $linked = SQLSelectOne("SELECT ID, VALUE FROM nooscenarios WHERE MASTER_DEVICE_ID='" . $rec['ID'] . "' AND DEVICE_ID='" . $devices[$i]['ID'] . "'");
        if ($linked['ID']) {
            $devices[$i]['LINKED'] = 1;
            $devices[$i]['LINKED_VALUE'] = $linked['VALUE'];
        }
    }
    if ($this->mode == 'update') {
    }
    $out['DEVICES'] = $devices;
}


// step: data
if ($this->tab == 'data') {
    //dataset2
    $new_id = 0;
    global $delete_id;
    if ($delete_id) {
        SQLExec("DELETE FROM noocommands WHERE ID='" . (int)$delete_id . "'");
    }
    $properties = SQLSelect("SELECT * FROM noocommands WHERE DEVICE_ID='" . $rec['ID'] . "' ORDER BY ID");
    $scripts = SQLSelect("SELECT ID, TITLE FROM scripts ORDER BY TITLE");
    $total = count($properties);
    $to_set = array();
    for ($i = 0; $i < $total; $i++) {
        if ($properties[$i]['ID'] == $new_id) continue;
        if ($this->mode == 'update') {

            global ${'linked_object' . $properties[$i]['ID']};
            $properties[$i]['LINKED_OBJECT'] = trim(${'linked_object' . $properties[$i]['ID']});
            global ${'linked_property' . $properties[$i]['ID']};
            $properties[$i]['LINKED_PROPERTY'] = trim(${'linked_property' . $properties[$i]['ID']});
            global ${'linked_method' . $properties[$i]['ID']};
            $properties[$i]['LINKED_METHOD'] = trim(${'linked_method' . $properties[$i]['ID']});

            global ${'script_id' . $properties[$i]['ID']};
            $properties[$i]['SCRIPT_ID'] = (int)(${'script_id' . $properties[$i]['ID']});

            global ${'set' . $properties[$i]['ID']};
            if (${'set' . $properties[$i]['ID']} !== "") {
                $to_set[$properties[$i]['ID']] = ${'set' . $properties[$i]['ID']};
            }


            unset($properties[$i]['UPDATED']);

            SQLUpdate('noocommands', $properties[$i]);
            $old_linked_object = $properties[$i]['LINKED_OBJECT'];
            $old_linked_property = $properties[$i]['LINKED_PROPERTY'];
            if ($old_linked_object && $old_linked_object != $properties[$i]['LINKED_OBJECT'] && $old_linked_property && $old_linked_property != $properties[$i]['LINKED_PROPERTY']) {
                removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
            }
        }

        if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
            addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
        }


        if (file_exists(DIR_MODULES . 'devices/devices.class.php')) {
            if ($properties[$i]['COMMAND_ID'] == '121') {
                $properties[$i]['SDEVICE_TYPE'] = 'sensor_temp';
            } elseif ($properties[$i]['COMMAND_ID'] == '122') {
                $properties[$i]['SDEVICE_TYPE'] = 'sensor_humidity';
            } elseif ($properties[$i]['COMMAND_ID'] == '25') {
                $properties[$i]['SDEVICE_TYPE'] = 'motion';
            } elseif ($properties[$i]['COMMAND_ID'] == '2' || $properties[$i]['COMMAND_ID'] == '4' || $properties[$i]['COMMAND_ID'] == '102') {
                $properties[$i]['SDEVICE_TYPE'] = 'relay';
            } elseif ($properties[$i]['COMMAND_ID'] == '7' || $properties[$i]['COMMAND_ID'] == '8' || $properties[$i]['COMMAND_ID'] == '17' || $properties[$i]['COMMAND_ID'] == '18') {
                $properties[$i]['SDEVICE_TYPE'] = 'button';
            } elseif ($properties[$i]['COMMAND_ID'] != '103') {
                $properties[$i]['SDEVICE_TYPE'] = 'dimmer';
            } elseif ($properties[$i]['COMMAND_ID'] != '104') {
                $properties[$i]['SDEVICE_TYPE'] = 'rgb';
            } elseif ($properties[$i]['COMMAND_ID'] != '15') {
                $properties[$i]['SDEVICE_TYPE'] = 'any';
            }
        }

        if ($rec['DEVICE_TYPE'] == '') {
            $properties[$i]['SCRIPTS'] =& $scripts;
        }
    }
    $need_redirect = 0;
    foreach ($to_set as $k => $v) {
        $need_redirect = 1;
        $this->propertySetHandle($k, '', $v);
    }
    if ($need_redirect) {
        $this->redirect("?view_mode=" . $this->view_mode . "&id=" . $this->id . "&tab=" . $this->tab);
    }
    $out['PROPERTIES'] = $properties;
}
if (is_array($rec)) {
    foreach ($rec as $k => $v) {
        if (!is_array($v)) {
            $rec[$k] = htmlspecialchars($v);
        }
    }
}
outHash($rec, $out);

if ($rec['ID']) {
    $tmp = SQLSelectOne("SELECT ID FROM noocommands WHERE (COMMAND_ID=7 OR COMMAND_ID=8) AND DEVICE_ID='" . $rec['ID'] . "'");
    if ($tmp['ID']) {
        $out['SHOW_SCENE'] = 1;
    }
} else {
    $tmp = SQLSelectOne("SELECT MAX(cast(ADDRESS AS UNSIGNED)) AS MX FROM noodevices");
    $out['ADDRESS'] = $tmp['MX'] + 1;
}

$out['API_TYPE'] = $this->config['API_TYPE'];

$out['LOCATIONS'] = SQLSelect("SELECT ID, TITLE FROM locations ORDER BY TITLE");