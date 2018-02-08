<?php
namespace Adianti\Base\Lib\Widget\Container;

use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Adianti\Base\Lib\Widget\Base\TElement;
use Exception;

/**
 * TableRow: Represents a row inside a table
 *
 * @version    5.0
 * @package    widget
 * @subpackage container
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TTableRow extends TElement
{
    /**
     * Class Constructor
     */
    public function __construct()
    {
        parent::__construct('tr');
    }
    
    /**
     * Add a new cell (TTableCell) to the Table Row
     * @param  $value Cell Content
     * @return TTableCell
     */
    public function addCell($value)
    {
        if (is_null($value)) {
            throw new Exception(AdiantiCoreTranslator::translate('Method ^1 does not accept null values', __METHOD__));
        } else {
            // creates a new Table Cell
            $cell = new TTableCell($value);
            parent::add($cell);
            // returns the cell object
            return $cell;
        }
    }
    
    /**
     * Add a multi-cell content to a table cell
     * @param $cells Each argument is a row cell
     */
    public function addMultiCell()
    {
        $wrapper = new THBox;
        
        $args = func_get_args();
        if ($args) {
            foreach ($args as $arg) {
                $wrapper->add($arg);
            }
        }
        
        return $this->addCell($wrapper);
    }
    
    /**
     * Clear any child elements
     */
    public function clearChildren()
    {
        $this->children = array();
    }
}
