<?php
/**
 * Copyright © 2015 ShopGo. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace ShopGo\AdvancedAcl\Model\Config;

use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Configuration reader model
 */
class Reader extends \Magento\Framework\Config\Reader\Filesystem
{
    /**
     * ACL file path
     */
    const ACL_CONFIG_DIRECTORY_PATH = 'shopgo/advanced_acl/';

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $_filesystem;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadInterface
     */
    protected $_varDirectory;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $_authSession;

    /**
     * @var string
     */
    protected $_fileName;

    /**
     * @var string
     */
    protected $_schemaFile;

    /**
     * @var \DomDocument
     */
    protected $_aclDom;

    /**
     * @var \DOMXPath
     */
    protected $_aclDomXpath;

    /**
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Framework\Config\FileResolverInterface $fileResolver
     * @param \Magento\Config\Model\Config\Structure\Converter $converter
     * @param \Magento\Framework\Config\SchemaLocatorInterface $schemaLocator
     * @param \Magento\Framework\Config\ValidationStateInterface $validationState
     * @param string $fileName
     * @param array $idAttributes
     * @param string $domDocumentClass
     * @param string $defaultScope
     */
    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\Config\FileResolverInterface $fileResolver,
        \Magento\Config\Model\Config\Structure\Converter $converter,
        \Magento\Framework\Config\SchemaLocatorInterface $schemaLocator,
        \Magento\Framework\Config\ValidationStateInterface $validationState,
        $fileName = '',
        $idAttributes = [],
        $domDocumentClass = 'Magento\Framework\Config\Dom',
        $defaultScope = 'global'
    ) {
        $this->_filesystem  = $filesystem;
        $this->_fileName    = $fileName;
        $this->_authSession = $authSession;

        $this->validationState   = $validationState;
        $this->_domDocumentClass = $domDocumentClass;

        $this->_setVarDirectory();

        if ($this->aclFileExists()) {
            $this->_setAclDom();
            $this->_setAclDomXpath();
        }

        $this->_schemaFile = $schemaLocator->getSchema();

        parent::__construct(
            $fileResolver,
            $converter,
            $schemaLocator,
            $validationState,
            $fileName,
            $idAttributes,
            $domDocumentClass,
            $defaultScope
        );
    }

    /**
     * Set Var directory
     */
    protected function _setVarDirectory()
    {
        $this->_varDirectory = $this->_filesystem
            ->getDirectoryRead(DirectoryList::VAR_DIR);
    }

    /**
     * Get ACL file absolute path
     *
     * @return string
     */
    protected function _getAclFileAbsolutePath()
    {
        return $this->_varDirectory->getAbsolutePath(
            self::ACL_CONFIG_DIRECTORY_PATH . $this->_fileName
        );
    }

    /**
     * Get ACL file absolute path
     *
     * @return string
     */
    protected function _getAclFileXmlContent()
    {
        return $this->_varDirectory->readFile(
            self::ACL_CONFIG_DIRECTORY_PATH . $this->_fileName
        );
    }

    /**
     * Set ACL DOM
     */
    protected function _setAclDom()
    {
        $this->_aclDom = new $this->_domDocumentClass(
            $this->_getAclFileXmlContent(),
            $this->validationState,
            [],
            null,
            null
        );
    }

    /**
     * Set ACL DOM XPath
     */
    protected function _setAclDomXpath()
    {
        $this->_aclDomXpath = new \DOMXPath($this->_aclDom->getDom());
    }

    /**
     * Check whether ACL file exists
     *
     * @return boolean
     */
    public function aclFileExists()
    {
        return $this->_varDirectory->isFile(
            self::ACL_CONFIG_DIRECTORY_PATH . $this->_fileName
        );
    }

    /**
     * Validate ACL DOM
     *
     * @return boolean
     */
    public function validateAclDom()
    {
        $result = true;
        $errors = [];

        if ($this->_aclDom && !$this->_aclDom->validate($this->_schemaFile, $errors)) {
            $result = false;
        }

        return $result;
    }

    /**
     * Get ACL DOM XPath value
     *
     * @param string $xpath
     * @return string
     */
    public function getAclDomXpathValue($xpath)
    {
        return $this->_aclDomXpath->query($xpath);
    }

    /**
     * Get ACL for current admin user
     *
     * @return string|boolean
     */
    public function getAdminUserAcl()
    {
        $user = $this->_authSession->getUser()->getUsername();

        $element = $this->getAclDomXpathValue(
            '//users/user[@name="*"]'
        );

        if ($element->item(0) !== null) {
            $excludedUsers = $this->getAclDomXpathValue(
                '//users/user[@name="*"]/exclude'
            );

            if ($excludedUsers->item(0) !== null) {
                $_excludedUsers = [];

                foreach ($excludedUsers->item(0)->childNodes as $excUser) {
                    $_excludedUsers[$excUser->nodeValue] = '';
                }

                if (isset($_excludedUsers[$user])) {
                    return false;
                }
            }
        } else {
            $element = $this->getAclDomXpathValue(
                '//users/user[@name="' . $user . '"]'
            );
        }

        return $element->item(0) !== null
            ? $element->item(0)->getAttribute('system')
            : false;
    }
}