<?php
namespace Adianti\Base\Lib\Widget\Container;

use Adianti\Base\Lib\Widget\Base\TElement;

/**
 * TableCell: Represents a cell inside a table
 *
 * @version    5.5
 * @package    widget
 * @subpackage container
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TTableCell extends TElement
{
    /**
     * Class Constructor
     * @param $value  TableCell content
     */
    public function __construct($value, $tag = 'td')
    {
        parent::__construct($tag);
        parent::add($value);
    }
}
