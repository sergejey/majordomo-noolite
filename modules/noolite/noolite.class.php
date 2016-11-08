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
class noolite extends module {
/**
* noolite
*
* Module class constructor
*
* @access private
*/
function noolite() {
  $this->name="noolite";
  $this->title="Noolite";
  $this->module_category="<#LANG_SECTION_DEVICES#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->data_source)) {
  $p["data_source"]=$this->data_source;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
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
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $data_source;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($data_source)) {
   $this->data_source=$data_source;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['DATA_SOURCE']=$this->data_source;
  $out['TAB']=$this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 $this->getConfig();
 $out['API_URL']=$this->config['API_URL'];
 if (!$out['API_URL']) {
  $out['API_URL']='http://';
 }
 $out['API_TYPE']=$this->config['API_TYPE'];
 $out['API_IGNORE']=$this->config['API_IGNORE'];
 $out['API_BINDING']=$this->config['API_BINDING'];
 if ($this->view_mode=='update_settings') {
   global $api_url;
   $this->config['API_URL']=$api_url;
   global $api_type;
   $this->config['API_TYPE']=$api_type;
   global $api_ignore;
   $this->config['API_IGNORE']=(int)$api_ignore;
   global $api_binding;
   $this->config['API_BINDING']=(int)$api_binding;
   $this->saveConfig();
   $this->redirect("?");
 }
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='noodevices' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_noodevices') {
   $this->search_noodevices($out);
  }
  if ($this->view_mode=='edit_noodevices') {
   $this->edit_noodevices($out, $this->id);
  }
  if ($this->view_mode=='delete_noodevices') {
   $this->delete_noodevices($this->id);
   $this->redirect("?data_source=noodevices");
  }
 }
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='noocommands') {
  if ($this->view_mode=='' || $this->view_mode=='search_noocommands') {
   $this->search_noocommands($out);
  }
  if ($this->view_mode=='edit_noocommands') {
   $this->edit_noocommands($out, $this->id);
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
function usual(&$out) {


 if ($out['module']) {
  $this->ajax=1;
 }

 if ($this->ajax) {
  //DebMes("noolite request: ".$_SERVER['REQUEST_URI']);

  $value=0;
  $this->getConfig();
  DebMes('AJAX request: '.serialize($_GET));

  if (preg_match('/^RECEIVED:(.+)/', $_GET['data'], $m)) {
   $tmp=explode(':', $m[1]);
   $_GET['did']=$tmp[0];
   $_GET['cmd']=$tmp[1];
   $command_id=hexdec($_GET['cmd']);
   if ($tmp[3]!='') {
    $tmp[3]=str_replace(';', '', $tmp[3]);
   }

   $ok=1;
   //if ($this->config['API_IGNORE']) {
    $device_rec=SQLSelectOne("SELECT ID FROM noodevices WHERE ADDRESS LIKE '".DBSafe($_GET['did'])."'");
    if (!$device_rec['ID']) {
     DebMes("Data from unknown device: ".serialize($_GET));
     $ok=0;
    }
   //}

   if ($this->config['API_BINDING'] && $command_id==15) {
    $ok=1;
   }

   if (!$ok) {
    return 0;
   }

   if ($command_id==21) {
    $_GET['t']=str_replace('t=', '', $tmp[2]);
    $_GET['rh']=str_replace('h=', '', $tmp[3]);
   }



  }

  if ($_GET['did']) {
   $addr=$_GET['did'];
   $command_id=hexdec($_GET['cmd']);
   $title='Device '.$addr. ' (cmd: '.$command_id.')';
   $d0=$_GET['d0'];
   $d1=$_GET['d1'];
   $d2=$_GET['d2'];
   $d3=$_GET['d3'];
   $t=trim($_GET['t']);
   $rh=trim($_GET['rh']);
  } elseif ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
   $addr='cell'.$_GET['cell'];
   $title=$_GET['name'];
   $command_id=(int)$_GET['cmd'];
   $d0=$_GET['d0'];
   $d1=$_GET['d1'];
   $d2=$_GET['d2'];
   $d3=$_GET['d3'];
  } elseif ($this->config['API_TYPE']=='linux' && IsSet($_GET['channel'])) {
   $addr='ch'.$_GET['channel'];
   $title='Channel '.$_GET['channel'];
   $command_id=(int)$_GET['command'];
   $d0=$_GET['d0'];
   $d1=$_GET['d1'];
   $d2=$_GET['d2'];
   $d3=$_GET['d3'];
  }

  if (!$addr) {
   echo "No address set";
   return;
  }

  $rec=SQLSelectOne("SELECT * FROM noodevices WHERE (ADDRESS LIKE '".DBSafe($addr)."' OR ADDRESS='".(int)preg_replace('/\D/', '', $addr)."') AND (DEVICE_TYPE='' OR DEVICE_TYPE='sensor')");
  if (!$rec['ID']) {
   $rec['ADDRESS']=$addr;
   $rec['TITLE']=$title;
   $rec['UPDATED']=date('Y-m-d H:i:s');
   $rec['ID']=SQLInsert('noodevices', $rec);
  } else {
   $rec['UPDATED']=date('Y-m-d H:i:s');
   SQLUpdate('noodevices', $rec);
  }

  $command_id2=0;

  if ($command_id==0) {
   $command_id=2;
   $value='0';
  } elseif ($command_id==2) {
   $value='1';
  } elseif ($command_id==4) {
   $value='1';
  } elseif ($command_id==21) {
   $command_id=121;
   if (IsSet($t)) {
    $value = $t;
   } else {
    $b1 =(int)str_replace('-','',$d0);
    $b2 =(int)str_replace('-','',$d1);
    $y_temp=256*($b2 & 15)+$b1;
    if  (($b2 & 8) != 0 ) {
     $y_temp=4096-$y_temp;
     $value = -1*($y_temp)/10;
    } else {
     $value = $y_temp/10;
    }
   }

   $command_id2=122;
   if (IsSet($rh) && $rh>0) {
    $value2=$rh;
   } else {
    $value2=(int)str_replace('-','',$d2);
   }

  }

  $command=SQLSelectOne("SELECT * FROM noocommands WHERE DEVICE_ID='".(int)$rec['ID']."' AND COMMAND_ID='".(int)$command_id."'");
  if (!$command['ID']) {
   $command['DEVICE_ID']=$rec['ID'];
   $command['COMMAND_ID']=$command_id;
   $command['ID']=SQLInsert('noocommands', $command);
  }
  $command['VALUE']=$value;
  $command['UPDATED']=date('Y-m-d H:i:s');
  SQLUpdate('noocommands', $command);
  if ($command['LINKED_OBJECT'] && $command['LINKED_PROPERTY']) {
    setGlobal($command['LINKED_OBJECT'].'.'.$command['LINKED_PROPERTY'], $command['VALUE'], array($this->name=>'0'));
  }
  if ($command['LINKED_OBJECT'] && $command['LINKED_METHOD']) {
   $params=array();
   $params['TITLE']=$command['TITLE'];
   $params['VALUE']=$command['VALUE'];
   $params['value']=$command['VALUE'];
   callMethod($command['LINKED_OBJECT'].'.'.$command['LINKED_METHOD'], $params);
  }

  if ($command['SCRIPT_ID']) {
   $params['VALUE']=$command['VALUE'];
   $params['value']=$command['VALUE'];
   runScript($command['SCRIPT_ID'], $params);
  }

  if ($command_id2) {

   $command_id=$command_id2;
   $value=$value2;

   $command=SQLSelectOne("SELECT * FROM noocommands WHERE DEVICE_ID='".(int)$rec['ID']."' AND COMMAND_ID='".(int)$command_id."'");
   if (!$command['ID']) {
    $command['DEVICE_ID']=$rec['ID'];
    $command['COMMAND_ID']=$command_id;
    $command['ID']=SQLInsert('noocommands', $command);
   }
   $command['VALUE']=$value;
   $command['UPDATED']=date('Y-m-d H:i:s');
   SQLUpdate('noocommands', $command);
   if ($command['LINKED_OBJECT'] && $command['LINKED_PROPERTY']) {
     setGlobal($command['LINKED_OBJECT'].'.'.$command['LINKED_PROPERTY'], $command['VALUE']); //, array($this->name=>'0')
   }
   if ($command['LINKED_OBJECT'] && $command['LINKED_METHOD']) {
    $params=array();
    $params['TITLE']=$command['TITLE'];
    $params['VALUE']=$command['VALUE'];
    $params['value']=$command['VALUE'];
    callMethod($command['LINKED_OBJECT'].'.'.$command['LINKED_METHOD'], $params);
   }

  }

  if ($command_id=='7') {
   //load preset
   $commands=SQLSelect("SELECT noocommands.*, nooscenarios.VALUE as SCENE_VALUE FROM nooscenarios LEFT JOIN noocommands ON nooscenarios.COMMAND_ID=noocommands.ID WHERE nooscenarios.MASTER_DEVICE_ID='".$rec['ID']."' AND nooscenarios.COMMAND_ID>0");
   $total=count($commands);
   for($i=0;$i<$total;$i++) {
    if (!$commands[$i]['ID']) {
     continue;
    }
    if ($commands[$i]['LINKED_OBJECT'] && $commands[$i]['LINKED_PROPERTY']) {
     setGlobal($commands[$i]['LINKED_OBJECT'].'.'.$commands[$i]['LINKED_PROPERTY'], $commands[$i]['SCENE_VALUE'], array($this->name=>'0'));
     $commands[$i]['VALUE']=$commands[$i]['SCENE_VALUE'];
    }
    $commands[$i]['VALUE']=$commands[$i]['SCENE_VALUE'];
    $commands[$i]['UPDATED']=date('Y-m-d H:i:s');
    unset($commands[$i]['SCENE_VALUE']);
    SQLUpdate('noocommands', $commands[$i]);
   }

   if ($rec['SCENARIO_ADDRESS']) {
      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
        $api_command='-load_preset_ch'.$rec['SCENARIO_ADDRESS'];
      } elseif ($this->config['API_TYPE']=='linux') {
        $api_command='--load '.$rec['SCENARIO_ADDRESS'];
      }
      $this->sendAPICommand($api_command);
   }


  }

  if ($command_id=='8') {
   //save preset
   $scenarios=SQLSelect("SELECT * FROM nooscenarios WHERE MASTER_DEVICE_ID='".$rec['ID']."'");
   $total=count($scenarios);
   for($i=0;$i<$total;$i++) {
    $linked_command=SQLSelectOne("SELECT * FROM noocommands WHERE DEVICE_ID='".$scenarios[$i]['DEVICE_ID']."' AND (COMMAND_ID='102' OR COMMAND_ID='103')");
    if ($linked_command['ID']) {
     $scenarios[$i]['VALUE']=$linked_command['VALUE'];
     $scenarios[$i]['COMMAND_ID']=$linked_command['ID'];
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
 function search_noodevices(&$out) {
  require(DIR_MODULES.$this->name.'/noodevices_search.inc.php');
 }
/**
* noodevices edit/add
*
* @access public
*/
 function edit_noodevices(&$out, $id) {
  require(DIR_MODULES.$this->name.'/noodevices_edit.inc.php');
 }
/**
* noodevices delete record
*
* @access public
*/
 function delete_noodevices($id) {
  $rec=SQLSelectOne("SELECT * FROM noodevices WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM noocommands WHERE DEVICE_ID='".$rec['ID']."'");
  SQLExec("DELETE FROM noodevices WHERE ID='".$rec['ID']."'");
 }
/**
* noocommands search
*
* @access public
*/
 function search_noocommands(&$out) {
  require(DIR_MODULES.$this->name.'/noocommands_search.inc.php');
 }
/**
* noocommands edit/add
*
* @access public
*/
 function edit_noocommands(&$out, $id) {
  require(DIR_MODULES.$this->name.'/noocommands_edit.inc.php');
 }


 function sendAPICommand($api_command) {
  $cmdline='';
  startMeasure('noolite_sendapi');
      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       if (file_exists("c:/Program Files/nooLite/nooLite.exe")) {
        $cmdline='"c:/Program Files/nooLite/nooLite.exe" -api '.$api_command;
       } elseif ($this->config['API_TYPE']=='http' && $this->config['API_URL']) {
        $url=$this->config['API_URL'].urlencode($api_command);
        DebMes("Sending noo api request: ".$url);
        $data=getURL($url, 0);
       } elseif (file_exists("c:/Program Files (x86)/nooLite/nooLite.exe")) {
        $cmdline='"c:/Program Files (x86)/nooLite/nooLite.exe" -api '.$api_command;
       } else {
        DebMes("Noolite App not found");
       }
      } elseif ($this->config['API_TYPE']=='linux') {
       $cmdline='sudo noolitepc '.$api_command;
      }

     if ($cmdline) {
      $latest_command_sent=(int)getGlobal('ThisComputer.LatestNooliteCommand');
      $diff=$latest_command_sent-time();
      if ($diff<0) {
       DebMes("Noolite instant cmd: ".$cmdline);
       setGlobal('ThisComputer.LatestNooliteCommand', time());
       exec($cmdline);
      } else {
       $diff=$diff+1;
       DebMes("Noolite delayed (".($diff).") cmd: ".$cmdline); 
       setGlobal('ThisComputer.LatestNooliteCommand', time()+$diff);
       setTimeOut('noocommand'.md5($cmdline), 'exec("'.$cmdline.'");', $diff);
      }
     }
   endMeasure('noolite_sendapi');
 }

 function propertySetHandle($object, $property, $value) {
  $this->getConfig();
   $commands=SQLSelect("SELECT noocommands.*, noodevices.ADDRESS, noodevices.SCENARIO_ADDRESS FROM noocommands LEFT JOIN noodevices ON noocommands.DEVICE_ID=noodevices.ID WHERE LINKED_OBJECT LIKE '".DBSafe($object)."' AND LINKED_PROPERTY LIKE '".DBSafe($property)."'");
   //DebMes("nooCommand: $object.$property");
   $total=count($commands);
   if ($total) {
    for($i=0;$i<$total;$i++) {
     //to-do
     $api_command='';
     $cmdline='';

     if ($commands[$i]['COMMAND_ID']=='7' && $value && $commands[$i]['SCENARIO_ADDRESS']) { //load preset

      $commands_linked=SQLSelect("SELECT noocommands.*, nooscenarios.VALUE as SCENE_VALUE FROM nooscenarios LEFT JOIN noocommands ON nooscenarios.COMMAND_ID=noocommands.ID WHERE nooscenarios.MASTER_DEVICE_ID='".$commands[$i]['DEVICE_ID']."' AND nooscenarios.COMMAND_ID>0");
      $total2=count($commands_linked);
      for($i2=0;$i2<$total2;$i2++) {
       if (!$commands_linked[$i2]['ID']) {
             continue;
       }
       if ($commands_linked[$i2]['LINKED_OBJECT'] && $commands_linked[$i2]['LINKED_PROPERTY']) {
             setGlobal($commands_linked[$i2]['LINKED_OBJECT'].'.'.$commands_linked[$i2]['LINKED_PROPERTY'], $commands_linked[$i2]['SCENE_VALUE'], array($this->name=>'0'));
             $commands_linked[$i2]['VALUE']=$commands_linked[$i2]['SCENE_VALUE'];
       }
       $commands_linked[$i2]['VALUE']=$commands_linked[$i2]['SCENE_VALUE'];
       $commands_linked[$i2]['UPDATED']=date('Y-m-d H:i:s');
       unset($commands_linked[$i2]['SCENE_VALUE']);
       SQLUpdate('noocommands', $commands_linked[$i2]);
      }

      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
        $api_command='-load_preset_ch'.$commands[$i]['SCENARIO_ADDRESS'];
      } elseif ($this->config['API_TYPE']=='linux') {
        $api_command='--load '.$commands[$i]['SCENARIO_ADDRESS'];
      } elseif ($this->config['API_TYPE']=='http') {
       $api_command='CHANNEL:'.$commands[$i]['SCENARIO_ADDRESS'].':7'; // ???
      }
     }

     if ($commands[$i]['COMMAND_ID']=='102') { //switch on/off
      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       if ($value) {
        $api_command='-on_ch'.$commands[$i]['ADDRESS'];
       } else {
        $api_command='-off_ch'.$commands[$i]['ADDRESS'];
       }
      } elseif ($this->config['API_TYPE']=='linux') {
       if ($value) {
        $api_command='--on '.$commands[$i]['ADDRESS'];
       } else {
        $api_command='--off '.$commands[$i]['ADDRESS'];
       }
      } elseif ($this->config['API_TYPE']=='http') {
       if ($value) {
        $api_command='CHANNEL:'.$commands[$i]['ADDRESS'].':2';
       } else {
        $api_command='CHANNEL:'.$commands[$i]['ADDRESS'].':0';
       }
      }
     }

     if ($commands[$i]['COMMAND_ID']=='103') { //dimmer brightness
      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       $api_command='-set_ch'.$commands[$i]['ADDRESS'].' -'.$value;
      } elseif ($this->config['API_TYPE']=='linux') {
      $api_command='--set '.$commands[$i]['ADDRESS'].' '.$value;
      }
     }

     if ($commands[$i]['COMMAND_ID']=='104') { //rgb
      $tmp=explode('-', $value);
      if (isset($tmp[1])) {
       $r=(int)$tmp[0];
       $g=(int)$tmp[1];
       $b=(int)$tmp[2];
      } else {
       $tmp=str_replace('#','',$value);
       $r=round(hexdec(substr($tmp,0,2))*100/256);
       $g=round(hexdec(substr($tmp,2,2))*100/256);
       $b=round(hexdec(substr($tmp,4,2))*100/256);
       DebMes($tmp.'='."$r-$g-$b");
      }
      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       $api_command='-set_color_ch'.$commands[$i]['ADDRESS'].' -'.$r.' -'.$g.' -'.$b;
      } elseif ($this->config['API_TYPE']=='linux') {
       $api_command='--color '.$commands[$i]['ADDRESS'].' '.$r.' '.$g.' '.$b;
      }
     }

     if ($commands[$i]['COMMAND_ID']=='105') { //roll color
      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       if ($value) {
        $api_command='-roll_color_ch'.$commands[$i]['ADDRESS'];
       } else {
        $api_command='-stop_reg_ch'.$commands[$i]['ADDRESS'];
       }
      }
     }

     if ($commands[$i]['COMMAND_ID']=='106') { //switch speed
      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       if ($value) {
        $api_command='-speed_mode_sw_ch'.$commands[$i]['ADDRESS'];
       }
      }
     }

     if ($commands[$i]['COMMAND_ID']=='107') { //switch mode
      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       if ($value) {
        $api_command='-sw_mode_ch'.$commands[$i]['ADDRESS'];
       }
      }
     }

     if ($api_command) {
      $this->sendAPICommand($api_command);
     }


      unset($commands[$i]['ADDRESS']);
      unset($commands[$i]['SCENARIO_ADDRESS']);
      $commands[$i]['UPDATED']=date('Y-m-d H:i:s');
      $commands[$i]['VALUE']=$value;
      SQLUpdate('noocommands', $commands[$i]);
      SQLExec("UPDATE noodevices SET UPDATED='".$commands[$i]['UPDATED']."' WHERE ID='".$commands[$i]['DEVICE_ID']."'");


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
 function install($data='') {
  parent::install();
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
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
 function dbInstall() {
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
