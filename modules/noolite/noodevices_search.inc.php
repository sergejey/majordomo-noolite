<?php
/*
* @version 0.1 (wizard)
*/
global $session;
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$qry = "1";
// search filters

$go_linked_object=gr('go_linked_object');
$go_linked_property=gr('go_linked_property');
if ($go_linked_object && $go_linked_property) {
    $tmp = SQLSelectOne("SELECT ID, DEVICE_ID FROM noocommands WHERE LINKED_OBJECT = '".DBSafe($go_linked_object)."' AND LINKED_PROPERTY='".DBSafe($go_linked_property)."'");
    if ($tmp['ID']) {
        $this->redirect("?id=".$tmp['ID']."&view_mode=edit_noodevices&id=".$tmp['DEVICE_ID']."&tab=data");
    }
}

global $location_id;
if ($location_id > 0) {
    $qry .= " AND noodevices.LOCATION_ID=" . (int)$location_id;
} elseif ($location_id == -1) {
    $qry .= " AND noodevices.LOCATION_ID=0";
}
$out['LOCATION_ID'] = $location_id;

$type = gr('type');
if ($type!='') {
    $out['TYPE']=$type;
    if ($type=='sensor') {
        $type='';
    }
    $qry.=" AND noodevices.DEVICE_TYPE='".$type."'";
}


// QUERY READY
global $save_qry;
if ($save_qry) {
    $qry = $session->data['noodevices_qry'];
} else {
    $session->data['noodevices_qry'] = $qry;
}
if (!$qry) $qry = "1";

global $sortby;
if ($sortby) {
    if ($sortby == 'ADDRESS') {
        $sortby = 'cast(ADDRESS as unsigned)';
    }
    if ($sortby == $session->data['SORTBY_NOODEVICES']) {
        $sortby = $sortby . ' DESC';
    }
    $session->data['SORTBY_NOODEVICES'] = $sortby;
    $sortby_noodevices = $sortby;
} else {
    $sortby_noodevices = $session->data['SORTBY_NOODEVICES'];
}

if (!$sortby_noodevices) {
    $sortby_noodevices = "noodevices.DEVICE_TYPE, cast(REPLACE(noodevices.ADDRESS,'cell','') as unsigned), noodevices.ADDRESS, noodevices.TITLE";
}


$out['SORTBY'] = $sortby_noodevices;
// SEARCH RESULTS
$res = SQLSelect("SELECT noodevices.*, locations.TITLE as LOCATION FROM noodevices LEFT JOIN locations ON noodevices.LOCATION_ID=locations.ID WHERE $qry ORDER BY " . $sortby_noodevices);
if ($res[0]['ID']) {
    //paging($res, 100, $out); // search result paging
    $total = count($res);
    for ($i = 0; $i < $total; $i++) {
        // some action for every record if required
        $linked = SQLSelect("SELECT * FROM noocommands WHERE DEVICE_ID='" . $res[$i]['ID'] . "' AND LINKED_OBJECT!=''");
        if ($linked[0]['ID']) {
            $total_l=count($linked);
            for($il=0;$il<$total_l;$il++) {
                $object_rec=SQLSelectOne("SELECT * FROM objects WHERE TITLE='".DBSafe($linked[$il]['LINKED_OBJECT'])."'");
                if ($object_rec['DESCRIPTION']) {
                    $linked[$il]['DESCRIPTION']=$object_rec['DESCRIPTION'];
                }
            }
            $res[$i]['LINKED'] = $linked;
        }
    }
    $out['RESULT'] = $res;
}

$out['LOCATIONS'] = SQLSelect("SELECT ID, TITLE FROM locations ORDER BY TITLE");
$out['TYPES'] = SQLSelect("SELECT DISTINCT(DEVICE_TYPE) AS VALUE FROM noodevices ORDER BY DEVICE_TYPE");
foreach ($out['TYPES'] as &$type) {
    if (!$type['VALUE']) $type['VALUE'] = 'sensor';
}