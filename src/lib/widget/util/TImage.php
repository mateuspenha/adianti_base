<?php
namespace Adianti\Base\Lib\Widget\Util;

use Adianti\Base\Lib\Widget\Base\TElement;

/**
 * Image Widget
 *
 * @version    5.5
 * @package    widget
 * @subpackage util
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TImage extends TElement
{
    private $source; // image path
    
    /**
     * Class Constructor
     * @param $source Image path, of bs:bs-glyphicon, fa:font-awesome
     */
    public function __construct($source)
    {
        if (substr($source,0,3) == 'bs:')
        {
            parent::__construct('i');
            $this->{'class'} = 'glyphicon glyphicon-'.substr($source,3);
            parent::add('');
        }
        else if (substr($source,0,3) == 'fa:')
        {
            parent::__construct('i');
            
            $fa_class = substr($source,3);
            if (strstr($source, '#') !== FALSE)
            {
                $pieces = explode('#', $fa_class);
                $fa_class = $pieces[0];
                $fa_color = $pieces[1];
            }
            $this->{'style'} = 'padding-right:4px;';
            $this->{'class'} = 'fa fa-'.$fa_class;
            if (isset($fa_color))
            {
                $this->{'style'} .= "; color: #{$fa_color};";
            }
            parent::add('');
        }
        else if (substr($source,0,3) == 'mi:')
        {
            parent::__construct('i');
            
            $mi_class = substr($source,3);
            if (strstr($source, '#') !== FALSE)
            {
                $pieces = explode('#', $mi_class);
                $mi_class = $pieces[0];
                $mi_color = $pieces[1];
            }
            $this->{'class'} = 'material-icons';
            
            $pieces = explode(' ', $mi_class);
            
            if (count($pieces)>1)
            {
                $mi_class = array_shift($pieces);
                $this->{'class'} = 'material-icons ' . implode(' ', $pieces);
            }
            
            if (isset($mi_color))
            {
                $this->{'style'} = "color: #{$mi_color};";
            }
            parent::add($mi_class);
        }
        else if (file_exists($source))
        {
            parent::__construct('img');
            // assign the image path
            $this->{'src'} = $source;
            $this->{'border'} = 0;
        }
        else if (file_exists("app/images/{$source}"))
        {
            parent::__construct('img');
            // assign the image path
            $this->{'src'} = "app/images/{$source}";
            $this->{'border'} = 0;
        }
        else if (file_exists("lib/adianti/images/{$source}"))
        {
            parent::__construct('img');
            // assign the image path
            $this->{'src'} = "lib/adianti/images/{$source}";
            $this->{'border'} = 0;
        }
        else
        {
            parent::__construct('i');
        }
    }
}
