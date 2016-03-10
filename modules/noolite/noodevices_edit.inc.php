<?php
/*
* @version 0.1 (wizard)
*/
   $this->getConfig();

  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $table_name='noodevices';
  $rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");

  $ch=preg_replace('/\D/', '', $rec['ADDRESS']);


  if ($this->mode=='start_binding' && $ch!='') {
   $out['MESSAGE']='Starting binding mode on channel #'.$ch;
   if ($this->config['API_TYPE']=='linux') {
    $api_command='--bind '.$ch;
    $cmdline='nooliterxcfg '.$api_command;
    safe_exec($cmdline);
   }
  }

  if ($this->mode=='stop_binding' && $ch!='') {
   $out['MESSAGE']='Stopping binding mode on channel #'.$ch;
   if ($this->config['API_TYPE']=='linux') {
    $api_command='--stop '.$ch;
    $cmdline='nooliterxcfg '.$api_command;
    safe_exec($cmdline);
   }
  }



  if ($this->mode=='bind' && $ch!='') {
   $out['MESSAGE']='Bind command sent for channel #'.$ch;

      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       $api_command='-bind_'.$ch;
      } elseif ($this->config['API_TYPE']=='linux') {
       $api_command='--bind '.$ch;
      }
      $this->sendAPICommand($api_command);
  }

  if ($this->mode=='unbind' && $ch!='') {
   $out['MESSAGE']='Un-bind command sent for channel #'.$ch;

      if ($this->config['API_TYPE']=='' || $this->config['API_TYPE']=='windows') {
       $api_command='-unbind_'.$ch;
      } elseif ($this->config['API_TYPE']=='linux') {
       $api_command='--unbind '.$ch;
      }
      $this->sendAPICommand($api_command);


  }


  if ($this->mode=='update') {
   $ok=1;
  // step: default
  if ($this->tab=='') {
  //updating '<%LANG_TITLE%>' (varchar, required)
   global $title;
   $rec['TITLE']=$title;
   if ($rec['TITLE']=='') {
    $out['ERR_TITLE']=1;
    $ok=0;
   }
  //updating 'DEVICE_TYPE' (varchar)
   global $device_type;
   $rec['DEVICE_TYPE']=$device_type;

   global $address;
   $rec['ADDRESS']=$address;

  }
  // step: data
  if ($this->tab=='data') {
  }
  //UPDATING RECORD
   if ($ok) {
    if ($rec['ID']) {
     unset($rec['UPDATED']);
     SQLUpdate($table_name, $rec); // update
    } else {
     $new_rec=1;
     $rec['ID']=SQLInsert($table_name, $rec); // adding new record

     if ($rec['DEVICE_TYPE']=='power' || $rec['DEVICE_TYPE']=='power_dimmer' || $rec['DEVICE_TYPE']=='power_rgb') {
      $command=array();
      $command['DEVICE_ID']=$rec['ID'];
      $command['COMMAND_ID']=102; //turn on/off
      $command['VALUE']=0;
      SQLInsert('noocommands', $command);
     }
     if ($rec['DEVICE_TYPE']=='power_dimmer') {
      $command=array();
      $command['DEVICE_ID']=$rec['ID'];
      $command['COMMAND_ID']=103; //dim
      $command['VALUE']=0;
      SQLInsert('noocommands', $command);
     }
     if ($rec['DEVICE_TYPE']=='power_rgb') {
      $command=array();
      $command['DEVICE_ID']=$rec['ID'];
      $command['COMMAND_ID']=104; //rgb
      $command['VALUE']=0;
      SQLInsert('noocommands', $command);

      $command=array();
      $command['DEVICE_ID']=$rec['ID'];
      $command['COMMAND_ID']=105; //roll color
      $command['VALUE']=0;
      SQLInsert('noocommands', $command);

      $command=array();
      $command['DEVICE_ID']=$rec['ID'];
      $command['COMMAND_ID']=106; //speed switch
      $command['VALUE']=0;
      SQLInsert('noocommands', $command);

      $command=array();
      $command['DEVICE_ID']=$rec['ID'];
      $command['COMMAND_ID']=107; //mode switch
      $command['VALUE']=0;
      SQLInsert('noocommands', $command);




     }


    }
    $out['OK']=1;
   } else {
    $out['ERR']=1;
   }
  }
  // step: default
  if ($this->tab=='') {
  }
  // step: data
  if ($this->tab=='data') {
  }
  if ($this->tab=='data') {
   //dataset2
   $new_id=0;
   global $delete_id;
   if ($delete_id) {
    SQLExec("DELETE FROM noocommands WHERE ID='".(int)$delete_id."'");
   }
   $properties=SQLSelect("SELECT * FROM noocommands WHERE DEVICE_ID='".$rec['ID']."' ORDER BY ID");
   $scripts=SQLSelect("SELECT ID, TITLE FROM scripts ORDER BY TITLE");
   $total=count($properties);
   for($i=0;$i<$total;$i++) {
    if ($properties[$i]['ID']==$new_id) continue;
    if ($this->mode=='update') {
      global ${'value'.$properties[$i]['ID']};
      $properties[$i]['VALUE']=trim(${'value'.$properties[$i]['ID']});
      global ${'linked_object'.$properties[$i]['ID']};
      $properties[$i]['LINKED_OBJECT']=trim(${'linked_object'.$properties[$i]['ID']});
      global ${'linked_property'.$properties[$i]['ID']};
      $properties[$i]['LINKED_PROPERTY']=trim(${'linked_property'.$properties[$i]['ID']});
      global ${'linked_method'.$properties[$i]['ID']};
      $properties[$i]['LINKED_METHOD']=trim(${'linked_method'.$properties[$i]['ID']});

      global ${'script_id'.$properties[$i]['ID']};
      $properties[$i]['SCRIPT_ID']=(int)(${'script_id'.$properties[$i]['ID']});


      unset($properties[$i]['UPDATED']);

      SQLUpdate('noocommands', $properties[$i]);
      $old_linked_object=$properties[$i]['LINKED_OBJECT'];
      $old_linked_property=$properties[$i]['LINKED_PROPERTY'];
      if ($old_linked_object && $old_linked_object!=$properties[$i]['LINKED_OBJECT'] && $old_linked_property && $old_linked_property!=$properties[$i]['LINKED_PROPERTY']) {
       removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
      }
      if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
       addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
      }
     }
     if ($rec['DEVICE_TYPE']=='') {
      $properties[$i]['SCRIPTS']=&$scripts;
     }
   }
   $out['PROPERTIES']=$properties;   
  }
  if (is_array($rec)) {
   foreach($rec as $k=>$v) {
    if (!is_array($v)) {
     $rec[$k]=htmlspecialchars($v);
    }
   }
  }
  outHash($rec, $out);

  $out['API_TYPE']=$this->config['API_TYPE'];
