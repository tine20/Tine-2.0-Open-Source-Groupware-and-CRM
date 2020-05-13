<?php
/**
 * Tine 2.0
 *
 * @package     HumanResources
 * @subpackage  Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * @copyright   Copyright (c) 2012-2019 Metaways Infosystems GmbH (http://www.metaways.de)
 */


/**
 * backend for HumanResources
 *
 * @package     HumanResources
 * @subpackage  Backend
 */
class HumanResources_Backend_WorkingTimeScheme extends Tinebase_Backend_Sql_Abstract
{
    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'humanresources_workingtime';

    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'HumanResources_Model_WorkingTimeScheme';

    /**
     * if modlog is active, we add 'is_deleted = 0' to select object in _getSelect()
     *
     * @var boolean
     */
    protected $_modlogActive = true;
}
