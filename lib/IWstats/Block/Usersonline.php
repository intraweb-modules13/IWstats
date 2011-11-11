<?php

class IWstats_Block_Usersonline extends Zikula_Controller_AbstractBlock {

    public function init() {
        SecurityUtil::registerPermissionSchema("IWstats:usersonlineblock:", "::");
    }

    public function info() {
        return array('text_type' => 'UsersOnLine',
            'module' => 'IWstats',
            'text_type_long' => $this->__('Display the users on line'),
            'allow_multiple' => true,
            'form_content' => false,
            'form_refresh' => false,
            'show_preview' => true);
    }

    /**
     * Show the list of forms for choosed categories
     * @autor:	Albert Pérez Monfort
     * return:	The list of forms
     */
    public function display($blockinfo) {
        // Security check
        if (!SecurityUtil::checkPermission("IWstats:usersonlineblock:", "::", ACCESS_READ)) {
            return;
        }

        // Check if the module is available
        if (!ModUtil::available('IWstats')) {
            return;
        }

        $uid = (UserUtil::isLoggedIn()) ? UserUtil::getVar('uid') : '-1';

        $sv = ModUtil::func('IWmain', 'user', 'genSecurityValue');
        $exists = ModUtil::apiFunc('IWmain', 'user', 'userVarExists', array('name' => 'usersonlineblock',
                    'module' => 'IWstats',
                    'uid' => $uid,
                    'sv' => $sv));

        //$exists = false;

        if ($exists) {
            $sv = ModUtil::func('IWmain', 'user', 'genSecurityValue');
            $s = ModUtil::func('IWmain', 'user', 'userGetVar', array('uid' => $uid,
                        'name' => 'usersonlineblock',
                        'module' => 'IWstats',
                        'sv' => $sv,
                        'nult' => true));

            // Create output object
            $view = Zikula_View::getInstance('IWstats', false);
            $blockinfo['content'] = $s;
            return BlockUtil::themesideblock($blockinfo);
        }

        // get block parameters
        $content = unserialize($blockinfo['content']);
        $sessiontime = $content['sessiontime'];
        $refreshtime = $content['refreshtime'];
        $unsee = $content['unsee'];

        $time = time();
        $time0 = $time - $sessiontime * 60;

        $fromDate = date('d-m-Y H:i:s', $time0);
        $toDate = date('d-m-Y H:i:s', $time);

        $records = ModUtil::apiFunc('IWstats', 'user', 'getAllRecords', array('rpp' => -1,
                    'init' => -1,
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'all' => 1,
                    'timeIncluded' => 1,
                ));

        $users = array();
        $ipArray = array();
        $usersArray = array();
        // get the number of validated users and the number of different Ips detected
        foreach ($records as $record) {
            if (!in_array($record['ip'], $ipArray)) {
                $ipArray[] = $record['ip'];
            }
            if (!in_array($record['uid'], $usersArray) && $record['uid'] > 0) {
                $usersArray[] = $record['uid'];
            }
        }

        $online = count($ipArray) - count($usersArray);

        $seeunames = ($unsee == 1 || $uid > 0) ? 1 : 0;

        if ($seeunames && count($usersArray) > 0) {
            $sv = ModUtil::func('IWmain', 'user', 'genSecurityValue');
            $users = ModUtil::func('IWmain', 'user', 'getAllUsersInfo', array('info' => 'ncc',
                        'sv' => $sv,
                        'fromArray' => $usersArray));
        }

        // create output object
        $view = Zikula_View::getInstance('IWstats', false);
        $view->assign('seeunames', $seeunames);
        $view->assign('users', $users);
        $view->assign('online', $online);
        $view->assign('validated', count($usersArray));

        $s = $view->fetch('IWstats_block_usersonline.htm');
        // copy the block information into user vars
        $sv = ModUtil::func('IWmain', 'user', 'genSecurityValue');
        ModUtil::func('IWmain', 'user', 'userSetVar', array('uid' => $uid,
            'name' => 'usersonlineblock',
            'module' => 'IWstats',
            'sv' => $sv,
            'value' => $s,
            'lifetime' => $refreshtime));
        // Populate block info and pass to theme
        $blockinfo['content'] = $s;
        return BlockUtil::themesideblock($blockinfo);
    }

    function update($blockinfo) {
        // Security check
        if (!SecurityUtil::checkPermission("IWstats:usersonlineblock:", $blockinfo['url'] . "::", ACCESS_ADMIN)) {
            return;
        }

        // default values in case they are not correct
        $refreshtime = (!is_numeric($blockinfo['refreshtime']) || $blockinfo['refreshtime'] > 100) ? $blockinfo['refreshtime'] : 100;
        $sessiontime = (!is_numeric($blockinfo['sessiontime']) || $blockinfo['sessiontime'] < 10 || $blockinfo['sessiontime'] < 120) ? $blockinfo['sessiontime'] : 100;
        $unsee = ($blockinfo['unsee'] != 1) ? 0 : 1;
        $blockinfo['content'] = serialize(array('refreshtime' => $refreshtime,
            'unsee' => $unsee,
            'sessiontime' => $sessiontime,
                ));

        return $blockinfo;
    }

    function modify($blockinfo) {
        // Security check
        if (!SecurityUtil::checkPermission("IWstats:usersonlineblock:", "::", ACCESS_ADMIN)) {
            return;
        }

        $content = unserialize($blockinfo['content']);

        $refreshtime = (!isset($content['refreshtime'])) ? 100 : $content['refreshtime'];
        $sessiontime = (!isset($content['sessiontime'])) ? 100 : $content['sessiontime'];
        $unsee = (!isset($content['unsee'])) ? 0 : $content['unsee'];

        // create output object
        $view = Zikula_View::getInstance('IWstats', false);
        $view->assign('refreshtime', $refreshtime);
        $view->assign('sessiontime', $sessiontime);
        $view->assign('unsee', $unsee);
        return $view->fetch('IWstats_block_editusersonline.htm');
    }

}