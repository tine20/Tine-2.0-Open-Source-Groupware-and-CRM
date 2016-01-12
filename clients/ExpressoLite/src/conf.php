<?php
/*!
 * Expresso Lite
 * Global configuration file.
 *
 * @package   Lite
 * @license   http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author    Rodrigo Dias <rodrigo.dias@serpro.gov.br>
 * @copyright Copyright (c) 2013-2015 Serpro (http://www.serpro.gov.br)
 */

define('BACKEND_URL', 'https://expressobr.serpro.gov.br/index.php');
define('CLASSIC_URL', 'https://expressobr.serpro.gov.br');
define('ANDROID_URL', '');
define('IOS_URL', ''); // app download links

define('MAIL_BATCH', 50); // how many email entries loaded by default

/* It is possible to make ExpressoLite activate PHP XDebug in the target
 * Tine backend server. To do this, configure a constant named
 * ACTIVATE_TINE_XDEBUG with the value true, like:
 *
 * define('ACTIVATE_TINE_XDEBUG', true);
 *
 * WARNING: DO NOT DO THIS IN A PRODUCTION ENVIRONMENT. It is meant only for
 * debug purposes!
 *
 */
