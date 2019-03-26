<?php
chdir(dirname(__FILE__) . '/../');
include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");
set_time_limit(0);
// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");
$ctl = new control_modules();

echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;

$hasDevices=SQLSelectOne("SELECT ID FROM usriot_devices");
if (!$hasDevices['ID']) {
    echo "No USRIoT devices in the system.";
    exit;
}

$latest_check=0;
$checkEvery=5; // poll every 5 seconds
while (1)
{
    setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
    if ((time()-$latest_check)>$checkEvery) {
        $latest_check=time();
        $url=BASE_URL.'/ajax/usriot.html?op=processCycle';
        getURL($url,0);
    }
    if (file_exists('./reboot') || IsSet($_GET['onetime']))
    {
        $db->Disconnect();
        exit;
    }
    sleep(1);
}
DebMes("Unexpected close of cycle: " . basename(__FILE__));
