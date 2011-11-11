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
        $tables = array('IWstats', 'IWstats_summary');
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

        // Set up config variables
        //ModUtil::setVar('IWstats', 'excludeusers', '');
        // create the system init hook
        if (!ModUtil::registerHook('zikula', 'systeminit', 'API', 'IWstats', 'user', 'collect')) {
            return LogUtil::registerError($this->__('unable to create system init hook'));
        }
        ModUtil::apiFunc('Modules', 'admin', 'enablehooks', array('callermodname' => 'zikula', 'hookmodname' => 'IWstats'));
        LogUtil::registerStatus($this->__('Stats have been enabled, you can change this in the hook settings (Administration -> Modules -> System hooks) by deactivating the Stats systeminit hook for Zikula itself'));


        // Initialisation successful
        return true;
    }

    /**
     * upgrade
     *
     * @todo recode using DBUtil
     */
    public function Upgrade($oldversion) {
        switch ($oldversion) {
            case '0.1':
                if (!DBUtil::createTable('IWstats_summary'))
                    return false;
        }
        // Update successful
        return true;
    }

    /**
     * delete the comments module
     *
     */
    public function uninstall() {
        // drop tables
        $tables = array('IWstats');
        foreach ($tables as $table) {
            if (!DBUtil::dropTable($table)) {
                return false;
            }
        }

        // delete the system init hook
        if (!ModUtil::unregisterHook('zikula', 'systeminit', 'API', 'IWstats', 'user', 'collect')) {
            return LogUtil::registerError($this->__('unable to delete system init hook'));
        }

        // Deletion successful
        return true;
    }

}