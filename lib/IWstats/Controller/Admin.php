<?php

class IWstats_Controller_Admin extends Zikula_AbstractController {

    protected function postInitialize() {
        // Set caching to false by default.
        $this->view->setCaching(false);
    }

    public function main() {
        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            throw new Zikula_Exception_Forbidden();
        }

        return System::redirect(ModUtil::url('IWstats', 'admin', 'view'));
    }

    public function view($args) {
        $statsSaved = unserialize(SessionUtil::getVar('statsSaved'));
        $startnum = FormUtil::getPassedValue('startnum', isset($args['startnum']) ? $args['startnum'] : 1, 'GETPOST');
        $moduleId = FormUtil::getPassedValue('moduleId', isset($args['moduleId']) ? $args['moduleId'] : $statsSaved['moduleId'], 'GETPOST');
        $uname = FormUtil::getPassedValue('uname', isset($args['uname']) ? $args['uname'] : $statsSaved['uname'], 'GETPOST');
        $ip = FormUtil::getPassedValue('ip', isset($args['ip']) ? $args['ip'] : $statsSaved['ip'], 'GETPOST');
        $registered = FormUtil::getPassedValue('registered', isset($args['registered']) ? $args['registered'] : $statsSaved['registered'], 'GETPOST');
        $reset = FormUtil::getPassedValue('reset', isset($args['reset']) ? $args['reset'] : 0, 'GET');
        $fromDate = FormUtil::getPassedValue('fromDate', isset($args['fromDate']) ? $args['fromDate'] : null, 'GETPOST');
        $toDate = FormUtil::getPassedValue('toDate', isset($args['toDate']) ? $args['toDate'] : null, 'GETPOST');


        SessionUtil::setVar('statsSaved', serialize(array('moduleId' => $moduleId,
                            'uname' => $uname,
                            'ip' => $ip,
                            'registered' => $registered,
                        )));

        if ($reset == 1) {
            $ip = null;
            $uname = null;
            $registered = 0;
            $moduleId = 0;
            SessionUtil::delVar('statsSaved');
        }

        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            throw new Zikula_Exception_Forbidden();
        }

        $uid = 0;
        $rpp = 50;
        $lastDays = 10;

        if ($uname != null && $uname != '') {
            // get user id from uname
            $uid = UserUtil::getIdFromName($uname);
            if (!$uid) {
                LogUtil::registerError(__f('User \'%s\' not found', array($uname)));
                $uname = '';
            }
        }

        $time = time();

        if ($fromDate != null) {
            $fromDate = mktime(0, 0, 0, substr($fromDate, 3, 2), substr($fromDate, 0, 2), substr($fromDate, 6, 4));
            $fromDate = date('Y-m-d 00:00:00', $fromDate);
            $fromDate = DateUtil::makeTimestamp($fromDate);
            $fromDate = date('d-m-Y', $fromDate);
        } else {
            $fromDate = date('d-m-Y', $time - $lastDays * 24 * 60 * 60);
        }

        if ($toDate != null) {
            $toDate = mktime(0, 0, 0, substr($toDate, 3, 2), substr($toDate, 0, 2), substr($toDate, 6, 4));
            $toDate = date('Y-m-d 00:00:00', $toDate);
            $toDate = DateUtil::makeTimestamp($toDate);
            $toDate = date('d-m-Y', $toDate);
        } else {
            $toDate = date('d-m-Y', $time);
        }

        // get last records
        $records = ModUtil::apiFunc('IWstats', 'user', 'getAllRecords', array('rpp' => $rpp,
                    'init' => $startnum,
                    'moduleId' => $moduleId,
                    'uid' => $uid,
                    'ip' => $ip,
                    'registered' => $registered,
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                ));

        // get last records
        $nRecords = ModUtil::apiFunc('IWstats', 'user', 'getAllRecords', array('onlyNumber' => 1,
                    'moduleId' => $moduleId,
                    'uid' => $uid,
                    'ip' => $ip,
                    'registered' => $registered,
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                ));

        $usersList = '';
        foreach ($records as $record) {
            if ($record['params'] != '') {
                $valueArray = array();
                $paramsArray = explode('&', $record['params']);
                foreach ($paramsArray as $param) {
                    $value = explode('=', $param);
                    $valueArray[$value[0]] = $value[1];
                }
                if ($record['moduleid'] > 0) {
                    $records[$record['statsid']]['func'] = (isset($valueArray['func'])) ? $valueArray['func'] : 'main';
                    $records[$record['statsid']]['type'] = (isset($valueArray['type'])) ? $valueArray['type'] : 'user';
                } else {
                    $records[$record['statsid']]['func'] = '';
                    $records[$record['statsid']]['type'] = '';
                }

                $params = '';
                foreach ($valueArray as $key => $v) {
                    if ($key != 'module' && $key != 'func' && $key != 'type') {
                        $params .= $key . '=' . $v . '&';
                    }
                }
            } else {
                $params = '';
                if ($record['moduleid'] > 0) {
                    $records[$record['statsid']]['func'] = 'main';
                    $records[$record['statsid']]['type'] = 'user';
                } else {
                    $records[$record['statsid']]['func'] = '';
                    $records[$record['statsid']]['type'] = '';
                }
            }

            $params = str_replace('%3F', '?', $params);
            $params = str_replace('%3D', '=', $params);
            $params = str_replace('%2F', '/', $params);
            $params = str_replace('%26', '&', $params);
            $params = str_replace('%7E', '~', $params);

            $records[$record['statsid']]['params'] = substr($params, 0, -1);

            $usersList .= $record['uid'] . '$$';
        }

        $sv = ModUtil::func('IWmain', 'user', 'genSecurityValue');
        $users = ModUtil::func('IWmain', 'user', 'getAllUsersInfo', array('info' => array('ncc', 'l'),
                    'sv' => $sv,
                    'list' => $usersList));

        $users[0] = $this->__('Unregistered');

        // get all modules
        $modules = ModUtil::apiFunc('Extensions', 'admin', 'listmodules', array('state' => 0));

        foreach ($modules as $module) {
            $modulesNames[$module['id']] = $module['name'];
            $modulesArray[] = array('id' => $module['id'],
                'name' => $module['name']);
        }

        return $this->view->assign('records', $records)
                ->assign('users', $users)
                ->assign('pager', array('numitems' => $nRecords, 'itemsperpage' => $rpp))
                ->assign('modulesNames', $modulesNames)
                ->assign('modulesArray', $modulesArray)
                ->assign('moduleId', $moduleId)
                ->assign('url', System::getBaseUrl())
                ->assign('uname', $uname)
                ->assign('registered', $registered)
                ->assign('fromDate', $fromDate)
                ->assign('toDate', $toDate)
                ->assign('maxDate', date('Ymd', time()))
                ->fetch('IWstats_admin_view.htm');
    }

    public function reset($args) {
        $confirmation = FormUtil::getPassedValue('confirmation', isset($args['confirmation']) ? $args['confirmation'] : null, 'POST');
        $deletiondays = FormUtil::getPassedValue('deletiondays', isset($args['deletiondays']) ? $args['deletiondays'] : null, 'POST');

        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            throw new Zikula_Exception_Forbidden();
        }

        // Check for confirmation.
        if (empty($confirmation)) {
            $view = Zikula_View::getInstance('IWstats', false);
            return $view->fetch('IWstats_admin_reset.htm');
        }

        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('IWstats', 'admin', 'main'));
        }

        // reset the site statistics
        if (!ModUtil::apiFunc('IWstats', 'admin', 'reset', array('deletiondays' => $deletiondays))) {
            LogUtil::registerError($this->__('IWstats reset error.'));
            return System::redirect(ModUtil::url('IWstats', 'admin', 'main'));
        }
        // Success
        LogUtil::registerStatus($this->__('IWstats reset'));
        return System::redirect(ModUtil::url('IWstats', 'admin', 'main'));
    }

    /**
     * Modify configuration
     *
     * @author       The Zikula Development Team
     * @return       output       The configuration page
     */
    public function modifyconfig() {
        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            throw new Zikula_Exception_Forbidden();
        }

        // Assign all the module variables to the template
        return $this->view->assign('skipedIps', $this->getVar('skipedIps'))
                ->fetch('IWstats_admin_modifyconfig.htm');
    }

    /**
     * Update the configuration
     *
     * @author       Mark West
     * @param        bold           print items in bold
     * @param        itemsperpage   number of items per page
     */
    public function updateconfig($args) {
        $skipedIps = FormUtil::getPassedValue('skipedIps', isset($args['skipedIps']) ? $args['skipedIps'] : 1, 'POST');
        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            throw new Zikula_Exception_Forbidden();
        }

        // Confirm authorisation code
        $this->checkCsrfToken();

        $this->setVar('skipedIps', $skipedIps);

        // The configuration has been changed, so we clear all caches for this module.
        $this->view->clear_all_cache();

        // the module configuration has been updated successfuly
        LogUtil::registerStatus($this->__('Done! Module configuration updated.'));

        return System::redirect(ModUtil::url('IWstats', 'admin', 'modifyconfig'));
    }

    public function deleteIp($args) {
        $ip = FormUtil::getPassedValue('ip', isset($args['ip']) ? $args['ip'] : 1, 'GETPOST');
        $confirm = FormUtil::getPassedValue('confirm', isset($args['confirm']) ? $args['confirm'] : 0, 'POST');
        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            throw new Zikula_Exception_Forbidden();
        }

        if (!$confirm) {
            // Assign all the module variables to the template
            return $this->view->assign('ip', $ip)
                    ->fetch('IWstats_admin_deleteip.htm');
        }

        $this->checkCsrfToken();

        if (!ModUtil::apiFunc('IWstats', 'admin', 'deleteIp', array('ip' => $ip))) {
            LogUtil::registerError($this->__f('Error deleting the ip \'%s\'', array($ip)));
            return System::redirect(ModUtil::url('IWstats', 'admin', 'view'));
        }

        // Success
        LogUtil::registerStatus($this->__f('Ip \'%s\' deleted', array($ip)));
        return System::redirect(ModUtil::url('IWstats', 'admin', 'view'));
    }

    public function viewStats($args) {
        $statsSaved = unserialize(SessionUtil::getVar('statsSaved'));
        $startnum = FormUtil::getPassedValue('startnum', isset($args['startnum']) ? $args['startnum'] : 1, 'GETPOST');
        $moduleId = FormUtil::getPassedValue('moduleId', isset($args['moduleId']) ? $args['moduleId'] : $statsSaved['moduleId'], 'GETPOST');
        $uname = FormUtil::getPassedValue('uname', isset($args['uname']) ? $args['uname'] : $statsSaved['uname'], 'GETPOST');
        $ip = FormUtil::getPassedValue('ip', isset($args['ip']) ? $args['ip'] : $statsSaved['ip'], 'GETPOST');
        $registered = FormUtil::getPassedValue('registered', isset($args['registered']) ? $args['registered'] : $statsSaved['registered'], 'GETPOST');
        $reset = FormUtil::getPassedValue('reset', isset($args['reset']) ? $args['reset'] : 0, 'GET');
        $fromDate = FormUtil::getPassedValue('fromDate', isset($args['fromDate']) ? $args['fromDate'] : null, 'GETPOST');
        $toDate = FormUtil::getPassedValue('toDate', isset($args['toDate']) ? $args['toDate'] : null, 'GETPOST');
        SessionUtil::setVar('statsSaved', serialize(array('moduleId' => $moduleId,
                            'uname' => $uname,
                            'ip' => $ip,
                            'registered' => $registered,
                        )));

        if ($reset == 1) {
            $ip = null;
            $uname = null;
            $registered = 0;
            $moduleId = 0;
            SessionUtil::delVar('statsSaved');
        }

        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            throw new Zikula_Exception_Forbidden();
        }

        $uid = 0;
        $rpp = 50;
        $lastDays = 10;

        if ($uname != null && $uname != '') {
            // get user id from uname
            $uid = UserUtil::getIdFromName($uname);
            if (!$uid) {
                LogUtil::registerError(__f('User \'%s\' not found', array($uname)));
                $uname = '';
            }
        }

        $time = time();

        if ($fromDate != null) {
            $fromDate = mktime(0, 0, 0, substr($fromDate, 3, 2), substr($fromDate, 0, 2), substr($fromDate, 6, 4));
            $fromDate = date('Y-m-d 00:00:00', $fromDate);
            $fromDate = DateUtil::makeTimestamp($fromDate);
            $fromDate = date('d-m-Y', $fromDate);
        } else {
            $fromDate = date('d-m-Y', $time - $lastDays * 24 * 60 * 60);
        }

        if ($toDate != null) {
            $toDate = mktime(0, 0, 0, substr($toDate, 3, 2), substr($toDate, 0, 2), substr($toDate, 6, 4));
            $toDate = date('Y-m-d 00:00:00', $toDate);
            $toDate = DateUtil::makeTimestamp($toDate);
            $toDate = date('d-m-Y', $toDate);
        } else {
            $toDate = date('d-m-Y', $time);
        }

        // get last records
        $records = ModUtil::apiFunc('IWstats', 'user', 'getAllRecords', array('rpp' => -1,
                    'init' => -1,
                    'moduleId' => $moduleId,
                    'uid' => $uid,
                    'ip' => $ip,
                    'registered' => $registered,
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                ));

        $usersList = '';
        $usersIdsCounter = array();
        $usersIpCounter = array();
        foreach ($records as $record) {
            $usersIpCounter[$record['ip']] = (isset($usersIpCounter[$record['ip']])) ? $usersIpCounter[$record['ip']] + 1 : 1;
            $usersIdsCounter[$record['uid']] = (isset($usersIdsCounter[$record['uid']])) ? $usersIdsCounter[$record['uid']] + 1 : 1;
            $usersList .= $record['uid'] . '$$';
        }
        /*
          foreach ($records as $record) {
          if ($record['params'] != '') {
          $valueArray = array();
          $paramsArray = explode('&', $record['params']);
          foreach ($paramsArray as $param) {
          $value = explode('=', $param);
          $valueArray[$value[0]] = $value[1];
          }
          if ($record['moduleid'] > 0) {
          $records[$record['statsid']]['func'] = (isset($valueArray['func'])) ? $valueArray['func'] : 'main';
          $records[$record['statsid']]['type'] = (isset($valueArray['type'])) ? $valueArray['type'] : 'user';
          } else {
          $records[$record['statsid']]['func'] = '';
          $records[$record['statsid']]['type'] = '';
          }

          $params = '';
          foreach ($valueArray as $key => $v) {
          if ($key != 'module' && $key != 'func' && $key != 'type') {
          $params .= $key . '=' . $v . '&';
          }
          }
          } else {
          $params = '';
          if ($record['moduleid'] > 0) {
          $records[$record['statsid']]['func'] = 'main';
          $records[$record['statsid']]['type'] = 'user';
          } else {
          $records[$record['statsid']]['func'] = '';
          $records[$record['statsid']]['type'] = '';
          }
          }

          $params = str_replace('%3F', '?', $params);
          $params = str_replace('%3D', '=', $params);
          $params = str_replace('%2F', '/', $params);
          $params = str_replace('%26', '&', $params);
          $params = str_replace('%7E', '~', $params);

          $records[$record['statsid']]['params'] = substr($params, 0, -1);

          $usersList .= $record['uid'] . '$$';
          }
         */

        $sv = ModUtil::func('IWmain', 'user', 'genSecurityValue');
        $users = ModUtil::func('IWmain', 'user', 'getAllUsersInfo', array('info' => array('ncc', 'l'),
                    'sv' => $sv,
                    'list' => $usersList));

        // get all modules
        $modules = ModUtil::apiFunc('Extensions', 'admin', 'listmodules', array('state' => 0));

        foreach ($modules as $module) {
            $modulesNames[$module['id']] = $module['name'];
            $modulesArray[] = array('id' => $module['id'],
                'name' => $module['name']);
        }

        return $this->view->assign('records', $records)
                ->assign('users', $users)
                ->assign('usersIdsCounter', $usersIdsCounter)
                ->assign('usersIpCounter', $usersIpCounter)
                ->assign('modulesNames', $modulesNames)
                ->assign('modulesArray', $modulesArray)
                ->assign('moduleId', $moduleId)
                ->assign('url', System::getBaseUrl())
                ->assign('uname', $uname)
                ->assign('registered', $registered)
                ->assign('fromDate', $fromDate)
                ->assign('toDate', $toDate)
                ->assign('maxDate', date('Ymd', time()))
                ->fetch('IWstats_admin_stats.htm');
    }

}