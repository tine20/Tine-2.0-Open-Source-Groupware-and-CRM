<?php
/**
 * this is the general file any request should be routed through
 *
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2007-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

$time_start = microtime(true);
$pid = getmypid();

require_once 'bootstrap.php';

Tinebase_Core::set(Tinebase_Core::STARTTIME, $time_start);

if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
    Tinebase_Core::getLogger()->info('index.php ('. __LINE__ . ')' . ' Start processing request ('
        . 'PID: ' . $pid . ')');
}

Tinebase_Core::dispatchRequest();

if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
    // log profiling information
    $time_end = microtime(true);
    $time = $time_end - $time_start;

    Tinebase_Core::getLogger()->info('index.php ('. __LINE__ . ')' .
        ' METHOD: ' . Tinebase_Core::get(Tinebase_Core::METHOD)
        . ' / TIME: ' . Tinebase_Helper::formatMicrotimeDiff($time)
        . ' / ' . Tinebase_Core::logMemoryUsage() . ' / ' . Tinebase_Core::logCacheSize()
        . ' / PID: ' . $pid
    );
}
