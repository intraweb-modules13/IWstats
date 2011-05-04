<?php

class IWstats_Controller_Admin extends Zikula_AbstractController {

    protected function postInitialize() {
        // Set caching to false by default.
        $this->view->setCaching(false);
    }

    public function main() {
        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        return System::redirect(ModUtil::url('IWstats', 'admin', 'view'));
    }

    public function view($args) {
        $startpage = FormUtil::getPassedValue('startpage', isset($args['startpage']) ? $args['startpage'] : 1, 'POST');
        $moduleId = FormUtil::getPassedValue('moduleId', isset($args['moduleId']) ? $args['moduleId'] : 0, 'POST');
        $uname = FormUtil::getPassedValue('uname', isset($args['uname']) ? $args['uname'] : null, 'POST');

        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }
        $rpp = 25;

        if ($uname != null && $uname != '') {
            // get user id from uname
            $uid = UserUtil::getIdFromName($uname);
            if (!$uid) {
                LogUtil::registerError(__f('User \'%s\' not found', array($uname)));
                $uname = '';
            }
        }
        // get last records
        $records = ModUtil::apiFunc('IWstats', 'user', 'getAllRecords', array('rpp' => $rpp,
                    'init' => $startpage,
                    'moduleId' => $moduleId,
                    'uid' => $uid,
                ));

        // get last records
        $nRecords = ModUtil::apiFunc('IWstats', 'user', 'getAllRecords', array('onlyNumber' => 1,
                    'moduleId' => $moduleId,
                    'uid' => $uid,
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
                $records[$record['statsid']]['func'] = (isset($valueArray['func'])) ? $valueArray['func'] : 'main';
                $records[$record['statsid']]['type'] = (isset($valueArray['type'])) ? $valueArray['type'] : 'user';

                $params = '';
                foreach ($valueArray as $key => $v) {
                    if ($key != 'module' && $key != 'func' && $key != 'type') {
                        $params .= $key . '=' . $v . '&';
                    }
                }
            } else
                $params = '';

            $records[$record['statsid']]['params'] = substr($params, 0, -1);

            $usersList .= $record['uid'] . '$$';
        }

        $sv = ModUtil::func('IWmain', 'user', 'genSecurityValue');
        $users = ModUtil::func('IWmain', 'user', 'getAllUsersInfo', array('info' => 'ncc',
                    'sv' => $sv,
                    'list' => $usersList));
        $users[0] = $this->__('Unregistered');

        // get all modules
        $modules = ModUtil::apiFunc('Extensions', 'admin', 'listmodules');

        foreach ($modules as $module) {
            $modulesNames[$module['id']] = $module['name'];
            $modulesArray[] = array('id' => $module['id'],
                'name' => $module['name']);
        }
        
        return $this->View->assign('records', $records)
                ->assign('users', $users)
                ->assign('pager', array('numitems' => $nRecords, 'itemsperpage' => $rpp))
                ->assign('modulesNames', $modulesNames)
                ->assign('modulesArray', $modulesArray)
                ->assign('moduleId', $moduleId)
                ->assign('uname', $uname)
                ->fetch('IWstats_admin_view.htm');
    }

    public function reset($args) {
        $dom = ZLanguage::getModuleDomain('IWstats');
        $confirmation = FormUtil::getPassedValue('confirmation', isset($args['confirmation']) ? $args['confirmation'] : null, 'POST');
        $deletiondays = FormUtil::getPassedValue('deletiondays', isset($args['deletiondays']) ? $args['deletiondays'] : null, 'POST');

        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
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
            return LogUtil::registerPermissionError();
        }

        // Create output object
        $view = Zikula_View::getInstance('IWstats', false);

        // Assign all the module variables to the template
        $view->assign(ModUtil::getVar('IWstats'));

        // Return the output that has been generated by this function
        return $view->fetch('IWstats_admin_modifyconfig.htm');
    }

    /**
     * Update the configuration
     *
     * @author       Mark West
     * @param        bold           print items in bold
     * @param        itemsperpage   number of items per page
     */
    public function updateconfig() {
        $dom = ZLanguage::getModuleDomain('IWstats');
        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('IWstats', 'admin', 'view'));
        }

        // The configuration has been changed, so we clear all caches for this module.
        $view = Zikula_View::getInstance('IWstats');
        $view->clear_all_cache();

        // the module configuration has been updated successfuly
        LogUtil::registerStatus($this->__('Done! Module configuration updated.'));

        return System::redirect(ModUtil::url('IWstats', 'admin'));
    }

}