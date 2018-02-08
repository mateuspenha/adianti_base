<?php
namespace Adianti\Base\Lib\Widget\Form;

use Adianti\Base\Lib\Control\TAction;
use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Exception;

/**
 * Password Widget
 *
 * @version    5.0
 * @package    widget
 * @subpackage form
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TPassword extends TField implements AdiantiWidgetInterface
{
    private $exitAction;
    private $exitFunction;
    protected $formName;
    
    /**
     * Define the action to be executed when the user leaves the form field
     * @param $action TAction object
     */
    public function setExitAction(TAction $action)
    {
        if ($action->isStatic()) {
            $this->exitAction = $action;
        } else {
            $string_action = $action->toString();
            throw new Exception(AdiantiCoreTranslator::translate('Action (^1) must be static to be used in ^2', $string_action, __METHOD__));
        }
    }
    
    /**
     * Define the javascript function to be executed when the user leaves the form field
     * @param $function Javascript function
     */
    public function setExitFunction($function)
    {
        $this->exitFunction = $function;
    }
    
    /**
     * Show the widget at the screen
     */
    public function show()
    {
        // define the tag properties
        $this->tag-> name  =  $this->name;   // tag name
        $this->tag-> value =  $this->value;  // tag value
        $this->tag-> type  =  'password';    // input type
        if (strstr($this->size, '%') !== false) {
            $this->setProperty('style', "width:{$this->size};", false); //aggregate style info
        } else {
            $this->setProperty('style', "width:{$this->size}px;", false); //aggregate style info
        }
        
        // verify if the field is not editable
        if (parent::getEditable()) {
            if (isset($this->exitAction)) {
                if (!TForm::getFormByName($this->formName) instanceof TForm) {
                    throw new Exception(AdiantiCoreTranslator::translate('You must pass the ^1 (^2) as a parameter to ^3', __CLASS__, $this->name, 'TForm::setFields()'));
                }
                
                $string_action = $this->exitAction->serialize(false);
                $this->setProperty('onBlur', "__adianti_post_lookup('{$this->formName}', '{$string_action}', this, 'callback')");
            }
            
            if (isset($this->exitFunction)) {
                $this->setProperty('onBlur', $this->exitFunction, false);
            }
        } else {
            // make the field read-only
            $this->tag-> readonly = "1";
            $this->tag->{'class'} = 'tfield_disabled'; // CSS
        }
        
        // show the tag
        $this->tag->show();
    }
}
