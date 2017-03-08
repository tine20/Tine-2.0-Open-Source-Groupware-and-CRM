<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Setup
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Matthias Greiling <m.greiling@metaways.de>
 */


class Setup_Backend_Schema_Table_Xml extends Setup_Backend_Schema_Table_Abstract
{
    public function __construct($_tableDefinition = NULL)
    {
        if($_tableDefinition !== NULL) {
            if(!$_tableDefinition instanceof SimpleXMLElement) {
                $_tableDefinition = new SimpleXMLElement($_tableDefinition);
            }
            
            $this->setName($_tableDefinition->name);
            $this->comment = (string) $_tableDefinition->comment;
            $this->version = (string) $_tableDefinition->version;

            $this->requirements = array();
            if ($_tableDefinition->requirements) {
                $requirements = $_tableDefinition->requirements->xpath('./required');
                if (is_array($requirements)) {
                    foreach($requirements as $requirement) {
                        $this->requirements[] = (string)$requirement;
                    }
                }
            }
            
            foreach ($_tableDefinition->declaration->field as $field) {
                $this->addField(Setup_Backend_Schema_Field_Factory::factory('Xml', $field));
            }
    
            foreach ($_tableDefinition->declaration->index as $index) {
                $this->addIndex(Setup_Backend_Schema_Index_Factory::factory('Xml', $index));
            }
        }
    }    
}
