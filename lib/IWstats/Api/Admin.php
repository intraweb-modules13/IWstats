<?php

class IWstats_Api_Admin extends Zikula_AbstractApi {

    public function reset($args) {
        // Security check
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_DELETE)) {
            return LogUtil::registerError($this->__('Sorry! No authorization to access this module.'));
        }

        $deletiondays = $args['deletiondays'];

        // TODO: delete depending on the number of days not all the table like now
        if (!DBUtil::executeSQL('Truncate table ' . System::getVar('prefix') . '_IWstats')) {
            return LogUtil::registerError($this->__('Error! Sorry! Deletion attempt failed.'));
        }

        // Return the id of the newly created item to the calling process
        return true;
    }

    public function deleteIp($args) {

        $table = DBUtil::getTables();
        $where = "";
        $c = $table['IWstats_column'];
        $where = "$c[ip] = '$args[ip]'";
        if (!DBUtil::deleteWhere('IWstats', $where)) {
            return LogUtil::registerError ($this->__('Error! Sorry! Deletion attempt failed.'));
        }

        return true;
    }

    /**
     * get available admin panel links
     *
     * @author Mark West
     * @return array array of admin links
     */
    public function getlinks() {
        $links = array();

        if (SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            $links[] = array('url' => ModUtil::url('IWstats', 'admin', 'view'), 'text' => $this->__('View'));
        }
        if (SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            $links[] = array('url' => ModUtil::url('IWstats', 'admin', 'reset'), 'text' => $this->__('Reset'));
        }
        if (SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            $links[] = array('url' => ModUtil::url('IWstats', 'admin', 'modifyconfig'), 'text' => $this->__('Settings'));
        }

        return $links;
    }

}