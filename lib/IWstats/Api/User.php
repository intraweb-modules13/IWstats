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

        $ip = '';
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = ModUtil::apiFunc('IWstats', 'user', 'cleanremoteaddr', array('originaladdr' => $_SERVER['REMOTE_ADDR']));
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = ModUtil::apiFunc('IWstats', 'user', 'cleanremoteaddr', array('originaladdr' => $_SERVER['HTTP_X_FORWARDED_FOR']));
        }
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = ModUtil::apiFunc('IWstats', 'user', 'cleanremoteaddr', array('originaladdr' => $_SERVER['HTTP_CLIENT_IP']));
        }

        // remove skiped ips by range
        $skipedIps = $this->getVar('skipedIps');
        $skipedIpsArray = explode(',', $skipedIps);
        $skiped = 0;
        foreach ($skipedIpsArray as $range) {
            if ($this->ip_in_range($ip, $range) || $ip == $range) {
                $skiped = 1;
                break;
            }
        }

        $item = array('moduleid' => $modid,
            'params' => $params,
            'uid' => $uid,
            'ip' => $ip,
            'datetime' => date('Y-m-d H:i:s', time()),
            'isadmin' => $isadmin,
            'skiped' => $skiped,
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
            $and = ($where != '') ? ' AND ' : '';
            $where .= $and . "$c[moduleid] = $args[moduleId]";
        }

        if (isset($args['uid']) && $args['uid'] > 0) {
            $and = ($where != '') ? ' AND ' : '';
            $where .= $and . "$c[uid] = $args[uid]";
        }

        if (isset($args['ip']) && $args['ip'] != null) {
            $and = ($where != '') ? ' AND ' : '';
            $where .= $and . "$c[ip] = '$args[ip]'";
        }

        if (isset($args['registered']) && $args['registered'] == 1) {
            $and = ($where != '') ? ' AND ' : '';
            $where .= $and . "$c[uid] > 0";
        }

        $and = ($where == '') ? '' : ' AND';
        $where .= "$and $c[isadmin] = 0 AND $c[skiped] = 0";

        if ($args['fromDate'] != null) {
            $and = ($where == '') ? '' : ' AND';
            $from = mktime(0, 0, 0, substr($args['fromDate'], 3, 2), substr($args['fromDate'], 0, 2), substr($args['fromDate'], 6, 4));
            $to = mktime(23, 59, 59, substr($args['toDate'], 3, 2), substr($args['toDate'], 0, 2), substr($args['toDate'], 6, 4));
            $fromSQL = date('Y-m-d H:i:s', $from);
            $toSQL = date('Y-m-d H:i:s', $to);
            $where .= "$and ($c[datetime] BETWEEN '$fromSQL' AND '$toSQL')";
        }

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

    public function cleanremoteaddr($args) {
        $originaladdr = $args['originaladdr'];
        $matches = array();
        // first get all things that look like IP addresses.
        if (!preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $args['originaladdr'], $matches, PREG_SET_ORDER)) {
            return '';
        }
        $goodmatches = array();
        $lanmatches = array();
        foreach ($matches as $match) {
            // check to make sure it's not an internal address.
            // the following are reserved for private lans...
            // 10.0.0.0 - 10.255.255.255
            // 172.16.0.0 - 172.31.255.255
            // 192.168.0.0 - 192.168.255.255
            // 169.254.0.0 -169.254.255.255
            $bits = explode('.', $match[0]);
            if (count($bits) != 4) {
                // weird, preg match shouldn't give us it.
                continue;
            }
            if (($bits[0] == 10)
                    || ($bits[0] == 172 && $bits[1] >= 16 && $bits[1] <= 31)
                    || ($bits[0] == 192 && $bits[1] == 168)
                    || ($bits[0] == 169 && $bits[1] == 254)) {
                $lanmatches[] = $match[0];
                continue;
            }
            // finally, it's ok
            $goodmatches[] = $match[0];
        }
        if (!count($goodmatches)) {
            // perhaps we have a lan match, it's probably better to return that.
            if (!count($lanmatches)) {
                return '';
            } else {
                return array_pop($lanmatches);
            }
        }
        if (count($goodmatches) == 1) {
            return $goodmatches[0];
        }

        // We need to return something, so return the first
        return array_pop($goodmatches);
    }

    /*
     * ip_in_range.php - Function to determine if an IP is located in a
     *                   specific range as specified via several alternative
     *                   formats.
     *
     * Network ranges can be specified as:
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     *
     * Return value BOOLEAN : ip_in_range($ip, $range);
     *
     * Copyright 2008: Paul Gregg <pgregg@pgregg.com>
     * 10 January 2008
     * Version: 1.2
     *
     * Source website: http://www.pgregg.com/projects/php/ip_in_range/
     * Version 1.2
     *
     * This software is Donationware - if you feel you have benefited from
     * the use of this tool then please consider a donation. The value of
     * which is entirely left up to your discretion.
     * http://www.pgregg.com/donate/
     *
     * Please do not remove this header, or source attibution from this file.
     */

    // decbin32
    // In order to simplify working with IP addresses (in binary) and their
    // netmasks, it is easier to ensure that the binary strings are padded
    // with zeros out to 32 characters - IP addresses are 32 bit numbers
    function decbin32($dec) {
        return str_pad(decbin($dec), 32, '0', STR_PAD_LEFT);
    }

    // ip_in_range
    // This function takes 2 arguments, an IP address and a "range" in several
    // different formats.
    // Network ranges can be specified as:
    // 1. Wildcard format:     1.2.3.*
    // 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
    // 3. Start-End IP format: 1.2.3.0-1.2.3.255
    // The function will return true if the supplied IP is within the range.
    // Note little validation is done on the range inputs - it expects you to
    // use one of the above 3 formats.
    private function ip_in_range($ip, $range) {
        if (strpos($range, '/') !== false) {
            // $range is in IP/NETMASK format
            list($range, $netmask) = explode('/', $range, 2);
            if (strpos($netmask, '.') !== false) {
                // $netmask is a 255.255.0.0 format
                $netmask = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);
                return ( (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec) );
            } else {
                // $netmask is a CIDR size block
                // fix the range argument
                $x = explode('.', $range);
                while (count($x) < 4)
                    $x[] = '0';
                list($a, $b, $c, $d) = $x;
                $range = sprintf("%u.%u.%u.%u", empty($a) ? '0' : $a, empty($b) ? '0' : $b, empty($c) ? '0' : $c, empty($d) ? '0' : $d);
                $range_dec = ip2long($range);
                $ip_dec = ip2long($ip);

                # Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
                #$netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));
                # Strategy 2 - Use math to create it
                $wildcard_dec = pow(2, (32 - $netmask)) - 1;
                $netmask_dec = ~ $wildcard_dec;

                return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
            }
        } else {
            // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
            if (strpos($range, '*') !== false) { // a.b.*.* format
                // Just convert to A-B format by setting * to 0 for A and 255 for B
                $lower = str_replace('*', '0', $range);
                $upper = str_replace('*', '255', $range);
                $range = "$lower-$upper";
            }

            if (strpos($range, '-') !== false) { // A-B format
                list($lower, $upper) = explode('-', $range, 2);
                $lower_dec = (float) sprintf("%u", ip2long($lower));
                $upper_dec = (float) sprintf("%u", ip2long($upper));
                $ip_dec = (float) sprintf("%u", ip2long($ip));
                return ( ($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec) );
            }

            //echo 'Range argument is not in 1.2.3.4/24 or 1.2.3.4/255.255.255.0 format';
            return false;
        }
    }
}