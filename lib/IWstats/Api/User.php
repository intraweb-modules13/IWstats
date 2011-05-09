<?php

class IWstats_Api_User extends Zikula_AbstractApi {

    public function collect($args) {
        // prepare data
        $uid = (UserUtil::isLoggedIn()) ? UserUtil::getVar('uid') : 0;

        // get module identity
        $modid = ModUtil::getIdFromName(ModUtil::getName());

        $params = $_SERVER['QUERY_STRING'];

        if (strpos($params, '&') === false && $params != '')
            return true;

        $isadmin = (SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) ? 1 : 0;

        $item = array('moduleid' => $modid,
            'params' => $params,
            'uid' => $uid,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'datetime' => date('Y-m-d H:i:s', time()),
            'isadmin' => $isadmin,
        );

        if (!DBUtil::insertObject($item, 'IWstats')) {
            return LogUtil::registerError($this->__('Error! Creation attempt failed.', $dom));
        }

        return true;
    }

    public function getAllRecords($args) {
        if (!SecurityUtil::checkPermission('IWstats::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }
        $items = array();
        $init = (isset($args['init'])) ? $args['init'] - 1 : -1;
        $rpp = (isset($args['rpp'])) ? $args['rpp'] : -1;
        $table = DBUtil::getTables();
        $where = "";
        $c = $table['IWstats_column'];

        if (isset($args['moduleId']) && $args['moduleId'] > 0) {
            $where = "$c[moduleid] = $args[moduleId]";
        }

        if (isset($args['uid']) && $args['uid'] > 0) {
            $where = "$c[uid] = $args[uid]";
        }

        if (isset($args['ip']) && $args['ip'] != null) {
            $where = "$c[ip] = '$args[ip]'";
        }

        if (isset($args['registered']) && $args['registered'] == 1) {
            $where = "$c[uid] > 0";
        }

        $and = ($where == '') ? '' : ' AND';
        $where .= "$and $c[isadmin] = 0";

        $orderby = "$c[statsid] desc";

        if (isset($args['onlyNumber']) && $args['onlyNumber'] == 1) {
            $items = DBUtil::selectObjectCount('IWstats', $where);
        } else {
            $items = DBUtil::selectObjectArray('IWstats', $where, $orderby, $init, $rpp, 'statsid');
        }

        // Check for an error with the database code, and if so set an appropriate
        // error message and return
        if ($items === false) {
            return LogUtil::registerError($this->__('No s\'han pogut carregar els registres.'));
        }
        // Return the items
        return $items;
    }

}