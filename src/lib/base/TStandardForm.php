<?php
namespace Adianti\Base\Lib\Base;

use Adianti\Base\Lib\Control\TPage;

/**
 * Standard page controller for forms
 *
 * @version    5.0
 * @package    base
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TStandardForm extends TPage
{
    protected $form;
    
    use AdiantiStandardFormTrait;
}