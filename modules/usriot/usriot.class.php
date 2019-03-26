<?php
/**
 * USRIoT
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 18:03:43 [Mar 19, 2019])
 */
//
//
class usriot extends module
{
    /**
     * usriot
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "usriot";
        $this->title = "USRIoT";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
        include_once DIR_MODULES.$this->name.'/usriot.inc.php';
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
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
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
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
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'usriot_devices' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_usriot_devices') {
                $this->search_usriot_devices($out);
            }
            if ($this->view_mode == 'edit_usriot_devices') {
                $this->edit_usriot_devices($out, $this->id);
            }
            if ($this->view_mode == 'delete_usriot_devices') {
                $this->delete_usriot_devices($this->id);
                $this->redirect("?data_source=usriot_devices");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'usriot_ports') {
            if ($this->view_mode == '' || $this->view_mode == 'search_usriot_ports') {
                $this->search_usriot_ports($out);
            }
            if ($this->view_mode == 'edit_usriot_ports') {
                $this->edit_usriot_ports($out, $this->id);
            }
        }
    }

    function refreshDevice($device_id, $debug = 0) {
        $device_rec = SQLSelectOne("SELECT * FROM usriot_devices WHERE ID=" . (int)$device_id);
        if (!$device_rec['ID']) {
            return 0;
        }
        $device_rec['UPDATED']=date('Y-m-d H:i:s');
        SQLUpdate('usriot_devices',$device_rec);

        $iot=new USRIoTDevice($device_rec['IP'],$device_rec['PORT'],$device_rec['PASSWORD']);
        $iot->debug = $debug;
        $iot->getPorts();
        $iot->closeConnection();
        if (count($iot->outputs)>=8) {
            for($i=0;$i<count($iot->outputs);$i++) {
                $port_num = $i+1;
                $port_rec=SQLSelectOne("SELECT * FROM usriot_ports WHERE DEVICE_ID=".$device_rec['ID']." AND `NUM`=".$port_num." AND `INOUT`='out'");
                $port_rec['VALUE']=$iot->outputs[$i];
                $port_rec['UPDATED']=date('Y-m-d H:i:s');
                if ($port_rec['ID']) {
                    SQLUpdate('usriot_ports',$port_rec);
                    if ($port_rec['LINKED_OBJECT'] && $port_rec['LINKED_PROPERTY']) {
                        sg($port_rec['LINKED_OBJECT'].'.'.$port_rec['LINKED_PROPERTY'],$port_rec['VALUE'],array($this->name=>'0'));
                    }
                } else {
                    $port_rec['INOUT']='out';
                    $port_rec['DEVICE_ID']=$device_rec['ID'];
                    $port_rec['NUM']=$port_num;
                    SQLInsert('usriot_ports',$port_rec);
                }
            }
        }
    }

    function sendDeviceCommand($device_id, $port_num, $command)
    {
        $device_rec = SQLSelectOne("SELECT * FROM usriot_devices WHERE ID=" . (int)$device_id);
        if (!$device_rec['ID']) {
            return 0;
        }
        $device_rec['UPDATED']=date('Y-m-d H:i:s');
        SQLUpdate('usriot_devices',$device_rec);

        $iot=new USRIoTDevice($device_rec['IP'],$device_rec['PORT'],$device_rec['PASSWORD']);
        if ($command=='turnon') {
            $iot->turnOn($port_num);
        }
        if ($command=='turnoff') {
            $iot->turnOff($port_num);
        }
        $iot->closeConnection();

        $port_rec=SQLSelectOne("SELECT * FROM usriot_ports WHERE DEVICE_ID=".$device_rec['ID']." AND `NUM`=".$port_num);
        if ($port_rec['ID']) {
            $port_rec['UPDATED']=date('Y-m-d H:i:s');
            if ($command=='turnon') {
                $port_rec['VALUE']=1;
            }
            if ($command=='turnoff') {
                $port_rec['VALUE']=0;
            }
            SQLUpdate('usriot_ports',$port_rec);
            if ($port_rec['LINKED_OBJECT'] && $port_rec['LINKED_PROPERTY']) {
                sg($port_rec['LINKED_OBJECT'].'.'.$port_rec['LINKED_PROPERTY'],$port_rec['VALUE'],array($this->name=>'0'));
            }
        }

    }

    function usual(&$out)
    {
        if ($this->ajax) {
            $op = gr('op');
            $device_id = gr('device_id', 'int');
            $command = gr('command');
            $port = gr('port', 'int');
            if ($command) {
                $this->sendDeviceCommand($device_id, $port, $command);
            }
            if ($op=='processCycle') {
                $devices=SQLSelect("SELECT * FROM usriot_devices");
                foreach($devices as $device) {
                    $tm=strtotime($device['UPDATED']);
                    $poll_period=(int)$device['POLL_PERIOD'];
                    if (!$poll_period) $poll_period=60;
                    if ((time()-$tm)>=$poll_period) {
                        $this->refreshDevice($device['ID']);
                    }
                }
            }

            echo 'OK';
        }
        exit;
    }

    /**
     * usriot_devices search
     *
     * @access public
     */
    function search_usriot_devices(&$out)
    {
        require(DIR_MODULES . $this->name . '/usriot_devices_search.inc.php');
    }

    /**
     * usriot_devices edit/add
     *
     * @access public
     */
    function edit_usriot_devices(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/usriot_devices_edit.inc.php');
    }

    /**
     * usriot_devices delete record
     *
     * @access public
     */
    function delete_usriot_devices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM usriot_devices WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM usriot_devices WHERE ID='" . $rec['ID'] . "'");
        SQLExec("DELETE FROM usriot_ports WHERE DEVICE_ID='" . $rec['ID'] . "'");
    }

    /**
     * usriot_ports search
     *
     * @access public
     */
    function search_usriot_ports(&$out)
    {
        require(DIR_MODULES . $this->name . '/usriot_ports_search.inc.php');
    }

    /**
     * usriot_ports edit/add
     *
     * @access public
     */
    function edit_usriot_ports(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/usriot_ports_edit.inc.php');
    }

    function propertySetHandle($object, $property, $value)
    {
        $properties = SQLSelect("SELECT * FROM usriot_ports WHERE LINKED_OBJECT LIKE '" . DBSafe($object) . "' AND LINKED_PROPERTY LIKE '" . DBSafe($property) . "'");
        $total = count($properties);
        if ($total) {
            for ($i = 0; $i < $total; $i++) {
                if ($properties[$i]['INOUT'] != 'out') continue;
                if ($value) {
                    $this->sendDeviceCommand($properties[$i]['DEVICE_ID'], $properties[$i]['NUM'], 'turnon');
                } else {
                    $this->sendDeviceCommand($properties[$i]['DEVICE_ID'], $properties[$i]['NUM'], 'turnoff');
                }
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
        SQLExec('DROP TABLE IF EXISTS usriot_devices');
        SQLExec('DROP TABLE IF EXISTS usriot_ports');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        usriot_devices - 
        usriot_ports - 
        */
        $data = <<<EOD
 usriot_devices: ID int(10) unsigned NOT NULL auto_increment
 usriot_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 usriot_devices: IP varchar(255) NOT NULL DEFAULT ''
 usriot_devices: PORT varchar(255) NOT NULL DEFAULT ''
 usriot_devices: DEVICE_TYPE varchar(255) NOT NULL DEFAULT '' 
 usriot_devices: PASSWORD varchar(255) NOT NULL DEFAULT ''
 usriot_devices: POLL_PERIOD int(10) NOT NULL DEFAULT '0'
 usriot_devices: UPDATED datetime

 usriot_ports: ID int(10) unsigned NOT NULL auto_increment
 usriot_ports: NUM int(10) NOT NULL DEFAULT '0' 
 usriot_ports: INOUT char(10) NOT NULL DEFAULT 'out'
 usriot_ports: VALUE varchar(255) NOT NULL DEFAULT ''
 usriot_ports: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 usriot_ports: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 usriot_ports: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 usriot_ports: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 usriot_ports: UPDATED datetime
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgTWFyIDE5LCAyMDE5IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
