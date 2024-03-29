<?php
namespace Adianti\Base\Lib\Widget\Container;

use Adianti\Base\Lib\Widget\Base\TElement;
use Adianti\Base\Lib\Widget\Form\TLabel;

/**
 * Frame Widget: creates a bordered area with a title located at its top-left corner
 *
 * @version    5.5
 * @package    widget
 * @subpackage container
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TFrame extends TElement
{
    private $legend;
    private $width;
    private $height;
    
    /**
     * Class Constructor
     * @param  $value text label
     */
    public function __construct($width = null, $height = null)
    {
        parent::__construct('fieldset');
        $this->{'id'}    = 'tfieldset_' . mt_rand(1000000000, 1999999999);
        $this->{'class'} = 'tframe';
        
        $this->width  = $width;
        $this->height = $height;
        
        if ($width) {
            $this->{'style'} .= (strstr($width, '%') !== false) ? ";width:{$width}" : ";width:{$width}px";
        }
        
        if ($height) {
            $this->{'style'} .= (strstr($height, '%') !== false) ? ";height:{$height}" : ";height:{$height}px";
        }
    }
    
    /**
     * Returns the frame size
     * @return array(width, height)
     */
    public function getSize()
    {
        return array($this->width, $this->height);
    }
    
    /**
     * Set Legend
     * @param  $legend frame legend
     */
    public function setLegend($legend)
    {
        $obj = new TElement('legend');
        $obj->add(new TLabel($legend));
        parent::add($obj);
        $this->legend = $legend;
    }
    
    /**
     * Returns the inner legend
     */
    public function getLegend()
    {
        return $this->legend;
    }
    
    /**
     * Return the Frame ID
     * @ignore-autocomplete on
     */
    public function getId()
    {
        return $this->{'id'};
    }
}
