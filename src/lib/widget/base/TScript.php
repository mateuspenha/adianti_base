<?php
namespace Adianti\Base\Lib\Widget\Base;

/**
 * Base class for scripts
 *
 * @version    5.5
 * @package    widget
 * @subpackage base
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TScript
{
    /**
     * Create a script
     * @param $code source code
     */
    public static function create( $code, $show = TRUE )
    {
        $script = new TElement('script');
        $script->{'language'} = 'JavaScript';
        $script->setUseSingleQuotes(TRUE);
        $script->setUseLineBreaks(FALSE);
        $script->add( str_replace( ["\n", "\r"], [' ', ' '], $code) );
        if ($show)
        {
            $script->show();
        }
        return $script;
    }
}
