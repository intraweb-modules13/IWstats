<?php

class IWstats_Installer extends Zikula_AbstractInstaller {

    public function Install() {
        // Checks if module IWmain is installed. If not returns error
        $modid = ModUtil::getIdFromName('IWmain');
        $modinfo = ModUtil::getInfo($modid);

        if ($modinfo['state'] != 3) {
            return LogUtil::registerError($this->__('Module IWmain is needed. You have to install the IWmain module before installing it.'));
        }

        // Check if the version needed is correct
        $versionNeeded = '2.0';
        if (!ModUtil::func('IWmain', 'admin', 'checkVersion', array('version' => $versionNeeded))) {
            return false;
        }

        // create module tables
        $tables = array('IWstats');
        foreach ($tables as $table) {
            if (!DBUtil::createTable($table)) {
                return false;
            }
        }

        // create several indexes for IWstats table
        $table = DBUtil::getTables();
        $c = $table['IWstats_column'];
        if (!DBUtil::createIndex($c['moduleid'], 'IWstats', 'moduleid'))
            return false;
        if (!DBUtil::createIndex($c['uid'], 'IWstats', 'uid'))
            return false;
        if (!DBUtil::createIndex($c['ip'], 'IWstats', 'ip'))
            return false;
        if (!DBUtil::createIndex($c['isadmin'], 'IWstats', 'isadmin'))
            return false;

        EventUtil::registerPersistentModuleHandler('IWstats', 'core.postinit', array('IWstats_Listeners', 'coreinit'));

        // Initialisation successful
        return true;
    }

    /**
     * upgrade
     *
     * @todo recode using DBUtil
     */
    public function Upgrade($oldversion) {

        // Update successful
        return true;
    }

    /**
     * delete the comments module
     *
     */
    public function Uninstall() {
        // drop tables
        $tables = array('IWstats');
        foreach ($tables as $table) {
            if (!DBUtil::dropTable($table)) {
                return false;
            }
        }

        EventUtil::unregisterPersistentModuleHandler('IWstats', 'core.postinit', array('IWstats_Listeners', 'coreinit'));

        // Deletion successful
        return true;
    }

}