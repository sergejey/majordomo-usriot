<?php
/*
* @version 0.1 (wizard)
*/
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$table_name = 'usriot_devices';
$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");
if ($this->mode == 'refresh') {
    $this->refreshDevice($rec['ID'],$_GET['debug']);
    $this->redirect("?id=".$rec['ID']."&view_mode=".$this->view_mode."&tab=data");
}
if ($this->mode == 'update') {
    $ok = 1;
    // step: default
    if ($this->tab == '') {
        //updating '<%LANG_TITLE%>' (varchar, required)
        $rec['TITLE'] = gr('title');
        if ($rec['TITLE'] == '') {
            $out['ERR_TITLE'] = 1;
            $ok = 0;
        }

        if (!$rec['ID']) {
            $rec['DEVICE_TYPE']=gr('device_type');
            if (!$rec['DEVICE_TYPE']) {
                $ok=0;
                $out['ERR_DEVICE_TYPE']=1;
            }
        }

        $rec['POLL_PERIOD']=gr('poll_period','int');
        if (!$rec['POLL_PERIOD']) {
            $rec['POLL_PERIOD']=60;
        }
        
        //updating 'IP' (varchar)
        $rec['IP'] = gr('ip');
        if ($rec['IP'] == '') {
            $out['ERR_IP'] = 1;
            $ok = 0;
        }
        //updating 'PORT' (varchar)
        $rec['PORT'] = gr('port');
        //updating 'PASSWORD' (varchar)
        $rec['PASSWORD'] = gr('password');

        //UPDATING RECORD
        if ($ok) {
            if ($rec['ID']) {
                SQLUpdate($table_name, $rec); // update
            } else {
                $new_rec = 1;
                $rec['ID'] = SQLInsert($table_name, $rec); // adding new record


                if ($rec['DEVICE_TYPE']=='8i8o') {
                    for($i=0;$i<8;$i++) {
                        $port=array('DEVICE_ID'=>$rec['ID'],'NUM'=>$i+1,'INOUT'=>'out');
                        SQLInsert('usriot_ports',$port);
                    }
                    /*
                    for($i=8;$i<16;$i++) {
                        $port=array('DEVICE_ID'=>$rec['ID'],'NUM'=>$i+1,'INOUT'='in');
                        SQLInsert('usriot_ports',$port);
                    }
                    */
                } elseif ($rec['DEVICE_TYPE']=='16o') {
                    for($i=0;$i<16;$i++) {
                        $port=array('DEVICE_ID'=>$rec['ID'],'NUM'=>$i+1,'INOUT'=>'out');
                        SQLInsert('usriot_ports',$port);
                    }
                }

                setGlobal('cycle_usriotControl', 'restart');

            }
            $out['OK'] = 1;
            $this->redirect("?id=".$rec['ID']."&view_mode=".$this->view_mode."&tab=data&mode=refresh");
        } else {
            $out['ERR'] = 1;
        }

    }
    // step: data
    if ($this->tab == 'data') {
    }

}
// step: default
if ($this->tab == '') {
}
// step: data
if ($this->tab == 'data') {
}
if ($this->tab == 'data') {
    //dataset2
    $new_id = 0;
    /*
    global $delete_id;
    if ($delete_id) {
        SQLExec("DELETE FROM usriot_ports WHERE ID='" . (int)$delete_id . "'");
    }
    */
    $properties = SQLSelect("SELECT * FROM usriot_ports WHERE DEVICE_ID='" . $rec['ID'] . "' ORDER BY `INOUT`, `NUM`");
    $total = count($properties);
    for ($i = 0; $i < $total; $i++) {
        if ($properties[$i]['ID'] == $new_id) continue;
        if ($this->mode == 'update') {
            /*
            global ${'title' . $properties[$i]['ID']};
            $properties[$i]['TITLE'] = trim(${'title' . $properties[$i]['ID']});
            global ${'value' . $properties[$i]['ID']};
            $properties[$i]['VALUE'] = trim(${'value' . $properties[$i]['ID']});
            */
            global ${'linked_object' . $properties[$i]['ID']};
            $properties[$i]['LINKED_OBJECT'] = trim(${'linked_object' . $properties[$i]['ID']});
            global ${'linked_property' . $properties[$i]['ID']};
            $properties[$i]['LINKED_PROPERTY'] = trim(${'linked_property' . $properties[$i]['ID']});
            /*
            global ${'linked_method' . $properties[$i]['ID']};
            $properties[$i]['LINKED_METHOD'] = trim(${'linked_method' . $properties[$i]['ID']});
            */
            SQLUpdate('usriot_ports', $properties[$i]);
            $old_linked_object = $properties[$i]['LINKED_OBJECT'];
            $old_linked_property = $properties[$i]['LINKED_PROPERTY'];
            if ($old_linked_object && $old_linked_object != $properties[$i]['LINKED_OBJECT'] && $old_linked_property && $old_linked_property != $properties[$i]['LINKED_PROPERTY']) {
                removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
            }
            if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
                addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
            }
        }
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
