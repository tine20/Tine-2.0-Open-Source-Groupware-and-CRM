<?php
/**
 * convert functions for records from/to json (array) format
 * 
 * @package     Addressbook
 * @subpackage  Convert
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * @copyright   Copyright (c) 2012-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * convert functions for records from/to json (array) format
 *
 * @package     Addressbook
 * @subpackage  Convert
 */
class Addressbook_Convert_Contact_Json extends Tinebase_Convert_Json
{
   /**
    * parent converts Tinebase_Record_RecordSet to external format
    * this resolves Image Paths
    *
    * @param Tinebase_Record_RecordSet  $_records
    * @param Tinebase_Model_Filter_FilterGroup $_filter
    * @param Tinebase_Model_Pagination $_pagination
    * @return mixed
    */
    public function fromTine20RecordSet(Tinebase_Record_RecordSet $_records = NULL, $_filter = NULL, $_pagination = NULL)
    {
        if (count($_records) == 0) {
            return array();
        }

        // TODO: Can be removed when "0000284: modlog of contact images / move images to vfs" is resolved.
        Addressbook_Frontend_Json::resolveImages($_records);

        $this->_appendRecordPaths($_records, $_filter);

        $result = parent::fromTine20RecordSet($_records, $_filter, $_pagination);

        return $result;
    }

    /**
     * append record paths (if path filter is set)
     *
     * @param Tinebase_Record_RecordSet $_records
     * @param Tinebase_Model_Filter_FilterGroup $_filter
     *
     * TODO move to generic json converter
     */
    protected function _appendRecordPaths($_records, $_filter)
    {
        if ($_filter && $_filter->getFilter('path', /* $_getAll = */ false, /* $_recursive = */ true) !== null &&
                true === Tinebase_Config::getInstance()->featureEnabled(Tinebase_Config::FEATURE_SEARCH_PATH)) {
            $pathController = Tinebase_Record_Path::getInstance();
            foreach ($_records as $record) {
                $record->paths = $pathController->getPathsForRecord($record);
            }
        }
    }
}
