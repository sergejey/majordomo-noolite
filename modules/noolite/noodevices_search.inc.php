<?php
/*
* @version 0.1 (wizard)
*/
 global $session;
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $qry="1";
  // search filters

  global $location_id;
  if ($location_id>0) {
   $qry.=" AND noodevices.LOCATION_ID=".(int)$location_id;
  } elseif ($location_id==-1) {
   $qry.=" AND noodevices.LOCATION_ID=0";
  }
  $out['LOCATION_ID']=$location_id;

  // QUERY READY
  global $save_qry;
  if ($save_qry) {
   $qry=$session->data['noodevices_qry'];
  } else {
   $session->data['noodevices_qry']=$qry;
  }
  if (!$qry) $qry="1";

  global $sortby;
  if ($sortby) {
   if ($sortby == $session->data['SORTBY_NOODEVICES']) {
    $sortby = $sortby.' DESC';
   }
   $session->data['SORTBY_NOODEVICES']=$sortby;
   $sortby_noodevices=$sortby;
  } else {
   $sortby_noodevices=$session->data['SORTBY_NOODEVICES'];
  }

  if (!$sortby_noodevices) {
   $sortby_noodevices="noodevices.DEVICE_TYPE, noodevices.ADDRESS, noodevices.TITLE";
  }
  $out['SORTBY']=$sortby_noodevices;
  // SEARCH RESULTS
  $res=SQLSelect("SELECT noodevices.*, locations.TITLE as LOCATION FROM noodevices LEFT JOIN locations ON noodevices.LOCATION_ID=locations.ID WHERE $qry ORDER BY ".$sortby_noodevices);
  if ($res[0]['ID']) {
   //paging($res, 100, $out); // search result paging
   $total=count($res);
   for($i=0;$i<$total;$i++) {
    // some action for every record if required
    $linked=SQLSelect("SELECT * FROM noocommands WHERE DEVICE_ID='".$res[$i]['ID']."' AND LINKED_OBJECT!=''");
    if ($linked[0]['ID']) {
     $res[$i]['LINKED']=$linked;
    }
   }
   $out['RESULT']=$res;
  }

$out['LOCATIONS']=SQLSelect("SELECT ID, TITLE FROM locations ORDER BY TITLE");