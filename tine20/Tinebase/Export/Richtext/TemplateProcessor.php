<?php
/**
 * Tinebase Doc/Docx template processor class
 *
 * @package     Tinebase
 * @subpackage  Export
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Paul Mehrer <p.mehrer@metaways.de>
 * @copyright   Copyright (c) 2017-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Tinebase Doc/Docx template processor class
 *
 * @package     Tinebase
 * @subpackage    Export
 */


class Tinebase_Export_Richtext_TemplateProcessor extends \PhpOffice\PhpWord\TemplateProcessor
{
    const TYPE_STANDARD = 'standard';
    const TYPE_DATASOURCE = 'datasource';
    const TYPE_GROUP = 'group';
    const TYPE_SUBGROUP = 'subgroup';
    const TYPE_RECORD = 'record';
    const TYPE_SUBRECORD = 'subrecord';

    /**
     * Content of document rels (in XML format) of the temporary document.
     *
     * @var string
     */
    protected $_temporaryDocumentRels = null;

    protected $_tempHeaderRels = array();

    protected $_tempFooterRels = array();

    protected $_type = null;

    protected $_parent = null;

    protected $_config = array();

    /**
     * @param string $documentTemplate The fully qualified template filename.
     * @param bool   $inMemory
     * @param string $type
     * @param Tinebase_Export_Richtext_TemplateProcessor|null $parent
     *
     * @throws \PhpOffice\PhpWord\Exception\CreateTemporaryFileException
     * @throws \PhpOffice\PhpWord\Exception\CopyFileException
     */
    public function __construct($documentTemplate, $inMemory = false, $type = self::TYPE_STANDARD, Tinebase_Export_Richtext_TemplateProcessor $parent = null)
    {
        $this->_type = $type;
        $this->_parent = $parent;

        if (true === $inMemory) {
            $this->tempDocumentMainPart = $documentTemplate;
            return;
        }

        parent::__construct($documentTemplate);

        $index = 1;
        while (false !== $this->zipClass->locateName($this->getHeaderName($index))) {
            $fileName = 'word/_rels/header' . $index . '.xml.rels';
            if (false !== $this->zipClass->locateName($fileName)) {
                $this->_tempHeaderRels[$index] = $this->fixBrokenMacros(
                    $this->zipClass->getFromName($fileName)
                );
            }
            $index++;
        }
        $index = 1;
        while (false !== $this->zipClass->locateName($this->getFooterName($index))) {
            $fileName = 'word/_rels/footer' . $index . '.xml.rels';
            if (false !== $this->zipClass->locateName($fileName)) {
                $this->_tempFooterRels[$index] = $this->fixBrokenMacros(
                    $this->zipClass->getFromName($fileName)
                );
            }
            $index++;
        }

        if (false !== $this->zipClass->locateName('word/_rels/document.xml.rels')) {
            $this->_temporaryDocumentRels = $this->fixBrokenMacros(
                $this->zipClass->getFromName('word/_rels/document.xml.rels'));
        }
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @return Tinebase_Export_Richtext_TemplateProcessor|null
     */
    public function getParent()
    {
        return $this->_parent;
    }

    public function replaceTine20ImagePaths()
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))
            Tinebase_Core::getLogger()->debug(__METHOD__ . ' ' . __LINE__ . ' replacing images...');

        if (null !== $this->_temporaryDocumentRels) {
            $this->_replaceTine20ImagePaths($this->tempDocumentMainPart, $this->_temporaryDocumentRels);
        }
        foreach($this->_tempHeaderRels as $index => $data) {
            $this->_replaceTine20ImagePaths($this->tempDocumentHeaders[$index], $data);
        }
        foreach($this->_tempFooterRels as $index => $data) {
            $this->_replaceTine20ImagePaths($this->tempDocumentFooters[$index], $data);
        }
    }

    protected function _replaceTine20ImagePaths($xmlData, $relData)
    {
        if (preg_match_all('#<wp:docPr[^>]+"(\w+://[^"]+)".*?r:embed="([^"]+)"#is', $xmlData, $matches, PREG_SET_ORDER)) {
            foreach($matches as $match) {

                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))
                    Tinebase_Core::getLogger()->debug(__METHOD__ . ' ' . __LINE__ . ' found url: ' . $match[1]);

                if (!in_array(mb_strtolower(pathinfo($match[1], PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'png', 'gif'))) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::INFO))
                        Tinebase_Core::getLogger()->info(__METHOD__ . ' ' . __LINE__ . ' unsupported file extension: ' . $match[1]);
                    continue;
                }
                if (preg_match('#Relationship Id="' . $match[2] . '"[^>]+Target="(media/[^"]+)"#', $relData, $relMatch)) {
                    $fileContent = @file_get_contents($match[1]);
                    if (!empty($fileContent)) {
                        $this->zipClass->deleteName('word/' . $relMatch[1]);
                        $this->zipClass->addFromString('word/' . $relMatch[1], $fileContent);
                    } else {
                        if (Tinebase_Core::isLogLevel(Zend_Log::WARN))
                            Tinebase_Core::getLogger()->warn(__METHOD__ . ' ' . __LINE__ . ' could not get file content: ' . $match[1]);
                    }
                } else {
                    if (Tinebase_Core::isLogLevel(Zend_Log::INFO))
                        Tinebase_Core::getLogger()->info(__METHOD__ . ' ' . __LINE__ . ' could not find relation matching found url: ' . $match[1]);
                }
            }
        }
    }

    /**
     * Saves the result document.
     *
     * @return string
     *
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    public function save()
    {
        if (null !== $this->_temporaryDocumentRels) {
            $this->zipClass->addFromString('word/_rels/document.xml.rels', $this->_temporaryDocumentRels);
        }

        foreach($this->_tempHeaderRels as $index => $data) {
            $this->zipClass->addFromString('word/_rels/header' . $index . '.xml.rels', $data);
        }

        foreach($this->_tempFooterRels as $index => $data) {
            $this->zipClass->addFromString('word/_rels/footer' . $index . '.xml.rels', $data);
        }

        // replace newline placeholder vertical tab (ascii 11)
        foreach ($this->tempDocumentHeaders as &$xml) {
            $xml = join('</w:t><w:br /><w:t>', mb_split("\x0B", $xml));
        }
        $this->tempDocumentMainPart = join('</w:t><w:br /><w:t>', mb_split("\x0B", $this->tempDocumentMainPart));
        foreach ($this->tempDocumentFooters as &$xml) {
            $xml = join('</w:t><w:br /><w:t>', mb_split("\x0B", $xml));
        }

        return parent::save();
    }

    /**
     * @param string $data
     */
    public function setMainPart($data)
    {
        $this->tempDocumentMainPart = $data;
    }

    /**
     * @return string
     */
    public function getMainPart()
    {
        return $this->tempDocumentMainPart;
    }

    /**
     * replace a table row in a template document and return replaced row
     *
     * @param string $search
     * @param string $replacement
     *
     * @return string
     *
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    public function replaceRow($search, $replacement)
    {
        if ('${' !== substr($search, 0, 2) && '}' !== substr($search, -1)) {
            $search = '${' . $search . '}';
        }

        $tagPos = strpos($this->tempDocumentMainPart, $search);
        if (!$tagPos) {
            throw new \PhpOffice\PhpWord\Exception\Exception("Can not clone row, template variable not found or variable contains markup.");
        }

        $rowStart = $this->findRowStart($tagPos);
        $rowEnd = $this->findRowEnd($tagPos);
        $xmlRow = $this->getSlice($rowStart, $rowEnd);

        $result = $this->getSlice(0, $rowStart) . $replacement;
        $result .= $this->getSlice($rowEnd);

        $this->tempDocumentMainPart = $result;

        return $xmlRow;
    }

    /**
     * @param $data
     */
    public function append($data)
    {
        $this->tempDocumentMainPart .= $data;
    }

    /**
     * Find the start position of the nearest table row before $offset.
     *
     * @param integer $offset
     * @return integer
     */
    protected function findRowStart($offset)
    {
        return $this->findTag('<w:tr', $offset, false);
    }

    /**
     * @param string $tag
     * @param int $offset
     * @param bool $forward
     * @return int
     * @throws Tinebase_Exception_NotFound
     */
    protected function findTag($tag, $offset, $forward = true)
    {
        if (true === $forward) {
            $strpos = 'strpos';
            $minmax = 'min';
        } else {
            $strpos = 'strrpos';
            $minmax = 'max';
            $offset = (strlen($this->tempDocumentMainPart) - $offset) * -1;
        }

        $result1 = $strpos($this->tempDocumentMainPart, $tag . ' ', $offset);
        $result2 = $strpos($this->tempDocumentMainPart, $tag . '>', $offset);

        if (false === $result1) {
            if (false === $result2) {
                throw new Tinebase_Exception_NotFound('Can not find the start position of the tag: ' . $tag);
            }
            return (int)$result2;
        }
        if (false === $result2) {
            return (int)$result1;
        }

        return (int)$minmax($result1, $result2);
    }

    /**
     * Find a block (optionally replace it)
     *
     * @param string $blockName
     * @param string $replacement
     *
     * @return string|null
     */
    public function findBlock($blockName, $replacement = null)
    {
        $openBlock = '${' . $blockName . '}';
        if (false === ($openBlockPos = strpos($this->tempDocumentMainPart, $openBlock))) {
            return null;
        }
        $openBlockPos = $this->findTag('<w:p', $openBlockPos, false);
        if (false === ($endOpenBlockPos = strpos($this->tempDocumentMainPart, '</w:p>', $openBlockPos))) {
            return null;
        }
        $endOpenBlockPos += 6;

        $closeBlock = '${/' . $blockName . '}';
        if (false === ($closeBlockPos = strpos($this->tempDocumentMainPart, $closeBlock, $endOpenBlockPos))) {
            return null;
        }
        $closeBlockPos = $this->findTag('<w:p', $closeBlockPos, false);
        if (false === ($endCloseBlockPos = strpos($this->tempDocumentMainPart, '</w:p>', $closeBlockPos))) {
            return null;
        }
        $endCloseBlockPos += 6;

        $xmlBlock = substr($this->tempDocumentMainPart, $endOpenBlockPos, $closeBlockPos - $endOpenBlockPos);

        if (null !== $replacement) {
            $this->tempDocumentMainPart = substr($this->tempDocumentMainPart, 0, $openBlockPos) . $replacement .
                substr($this->tempDocumentMainPart, $endCloseBlockPos);
        }

        return $xmlBlock;
    }

    /**
     * Clone a block.
     *
     * @param string $blockname
     * @param integer $clones
     * @param boolean $replace
     * @return string|null
     * @throws Tinebase_Exception_NotImplemented
     */
    public function cloneBlock($blockname, $clones = 1, $replace = true)
    {
        throw new Tinebase_Exception_NotImplemented('do not use this function! ' . __METHOD__);
    }

    /**
     * Replace a block.
     *
     * @param string $blockname
     * @param string $replacement
     * @throws Tinebase_Exception_NotImplemented
     */
    public function replaceBlock($blockname, $replacement)
    {
        throw new Tinebase_Exception_NotImplemented('do not use this function! ' . __METHOD__);
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->_config = $config;
        if (Tinebase_Export_Richtext_TemplateProcessor::TYPE_RECORD === $this->_type &&
                isset($this->_config['recordXml'])) {

        }
    }

    /**
     * @param $key
     * @return bool
     */
    public function hasConfig($key)
    {
        return isset($this->_config[$key]);
    }

    /**
     * @param string|null $key
     * @return mixed
     */
    public function getConfig($key = null)
    {
        if (null === $key) {
            return $this->_config;
        }
        return $this->_config[$key];
    }

    /**
     * Returns array of all variables in template.
     *
     * @return string[]
     */
    public function getVariables()
    {
        $result = parent::getVariables();

        switch($this->_type) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case self::TYPE_DATASOURCE:
                if (isset($this->_config['group'])) {
                    $result = array_merge($result, $this->_config['group']->getVariables());
                }
            case self::TYPE_GROUP:
                if (isset($this->_config['record'])) {
                    $result = array_merge($result, $this->_config['record']->getVariables());
                }
                if (isset($this->_config['recordRow']) && isset($this->_config['recordRow']['recordRowProcessor'])) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $result = array_merge($result, $this->_config['recordRow']['recordRowProcessor']->getVariables());
                }
                // DO NOT return variables of sub groups or sub records
                break;
            case self::TYPE_SUBGROUP:
            case self::TYPE_SUBRECORD:
                if (isset($this->_config['recordXml'])) {
                    $result = array_merge($result, $this->getVariablesForPart($this->_config['recordXml']));
                }
                break;
        }

        return array_unique($result);
    }
}