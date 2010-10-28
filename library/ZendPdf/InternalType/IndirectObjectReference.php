<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_PDF
 * @subpackage Zend_PDF_Internal
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @namespace
 */
namespace Zend\Pdf\InternalType;
use Zend\Pdf\Exception;
use Zend\Pdf;

/**
 * PDF file 'reference' element implementation
 *
 * @uses       \Zend\Pdf\InternalType\AbstractTypeObject
 * @uses       \Zend\Pdf\InternalType\NullObject
 * @uses       \Zend\Pdf\Exception
 * @category   Zend
 * @package    Zend_PDF
 * @subpackage Zend_PDF_Internal
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class IndirectObjectReference extends AbstractTypeObject
{
    /**
     * Object value
     * The reference to the object
     *
     * @var mixed
     */
    private $_ref;

    /**
     * Object number within PDF file
     *
     * @var integer
     */
    private $_objNum;

    /**
     * Generation number
     *
     * @var integer
     */
    private $_genNum;

    /**
     * Reference context
     *
     * @var \Zend\Pdf\InternalType\IndirectObjectReference\Context
     */
    private $_context;


    /**
     * Reference to the factory.
     *
     * It's the same as referenced object factory, but we save it here to avoid
     * unnecessary dereferencing, whech can produce cascade dereferencing and parsing.
     * The same for duplication of getFactory() function. It can be processed by __call()
     * method, but we catch it here.
     *
     * @var \Zend\Pdf\ObjectFactory
     */
    private $_factory;

    /**
     * Object constructor:
     *
     * @param integer $objNum
     * @param integer $genNum
     * @param \Zend\Pdf\InternalType\IndirectObjectReference\Context $context
     * @param \Zend\Pdf\ObjectFactory $factory
     * @throws \Zend\Pdf\Exception\CorruptedPdfException
     */
    public function __construct($objNum,
                                $genNum = 0,
                                IndirectObjectReference\Context $context,
                                Pdf\ObjectFactory $factory)
    {
        if ( !(is_integer($objNum) && $objNum > 0) ) {
            throw new Exception\RuntimeException('Object number must be positive integer');
        }
        if ( !(is_integer($genNum) && $genNum >= 0) ) {
            throw new Exception\RuntimeException('Generation number must be non-negative integer');
        }

        $this->_objNum  = $objNum;
        $this->_genNum  = $genNum;
        $this->_ref     = null;
        $this->_context = $context;
        $this->_factory = $factory;
    }

    /**
     * Check, that object is generated by specified factory
     *
     * @return \Zend\Pdf\ObjectFactory
     */
    public function getFactory()
    {
        return $this->_factory;
    }


    /**
     * Return type of the element.
     *
     * @return integer
     */
    public function getType()
    {
        if ($this->_ref === null) {
            $this->_dereference();
        }

        return $this->_ref->getType();
    }


    /**
     * Return reference to the object
     *
     * @param \Zend\Pdf\ObjectFactory $factory
     * @return string
     */
    public function toString(Pdf\ObjectFactory $factory = null)
    {
        if ($factory === null) {
            $shift = 0;
        } else {
            $shift = $factory->getEnumerationShift($this->_factory);
        }

        return $this->_objNum + $shift . ' ' . $this->_genNum . ' R';
    }


    /**
     * Dereference.
     * Take inderect object, take $value member of this object (must be \Zend\Pdf\InternalType\AbstractTypeObject),
     * take reference to the $value member of this object and assign it to
     * $value member of current PDF Reference object
     * $obj can be null
     *
     * @throws \Zend\Pdf\Exception\CorruptedPdfException
     */
    private function _dereference()
    {
        if (($obj = $this->_factory->fetchObject($this->_objNum . ' ' . $this->_genNum)) === null) {
            $obj = $this->_context->getParser()->getObject(
                           $this->_context->getRefTable()->getOffset($this->_objNum . ' ' . $this->_genNum . ' R'),
                           $this->_context
                                                          );
        }

        if ($obj === null ) {
            $this->_ref = new NullObject();
            return;
        }

        if ($obj->toString() != $this->_objNum . ' ' . $this->_genNum . ' R') {
            throw new Exception\RuntimeException('Incorrect reference to the object');
        }

        $this->_ref = $obj;
    }

    /**
     * Detach PDF object from the factory (if applicable), clone it and attach to new factory.
     *
     * @param \Zend\Pdf\ObjectFactory $factory  The factory to attach
     * @param array &$processed  List of already processed indirect objects, used to avoid objects duplication
     * @param integer $mode  Cloning mode (defines filter for objects cloning)
     * @returns \Zend\Pdf\InternalType\AbstractTypeObject
     */
    public function makeClone(Pdf\ObjectFactory $factory, array &$processed, $mode)
    {
        if ($this->_ref === null) {
            $this->_dereference();
        }

        // This code duplicates code in \Zend\Pdf\InternalType\IndirectObject class,
        // but allows to avoid unnecessary method call in most cases
        $id = spl_object_hash($this->_ref);
        if (isset($processed[$id])) {
            // Do nothing if object is already processed
            // return it
            return $processed[$id];
        }

        return $this->_ref->makeClone($factory, $processed, $mode);
    }

    /**
     * Mark object as modified, to include it into new PDF file segment.
     */
    public function touch()
    {
        if ($this->_ref === null) {
            $this->_dereference();
        }

        $this->_ref->touch();
    }

    /**
     * Return object, which can be used to identify object and its references identity
     *
     * @return \Zend\Pdf\InternalType\IndirectObject
     */
    public function getObject()
    {
        if ($this->_ref === null) {
            $this->_dereference();
        }

        return $this->_ref;
    }

    /**
     * Get handler
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        if ($this->_ref === null) {
            $this->_dereference();
        }

        return $this->_ref->$property;
    }

    /**
     * Set handler
     *
     * @param string $property
     * @param  mixed $value
     */
    public function __set($property, $value)
    {
        if ($this->_ref === null) {
            $this->_dereference();
        }

        $this->_ref->$property = $value;
    }

    /**
     * Call handler
     *
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if ($this->_ref === null) {
            $this->_dereference();
        }

        return call_user_func_array(array($this->_ref, $method), $args);
    }

    /**
     * Clean up resources
     */
    public function cleanUp()
    {
        $this->_ref = null;
    }

    /**
     * Convert PDF element to PHP type.
     *
     * @return mixed
     */
    public function toPhp()
    {
        if ($this->_ref === null) {
            $this->_dereference();
        }

        return $this->_ref->toPhp();
    }
}
