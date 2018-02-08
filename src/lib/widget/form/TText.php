<?php
namespace Adianti\Base\Lib\Widget\Form;

use Adianti\Base\Lib\Control\TAction;
use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Adianti\Base\Lib\Widget\Base\TElement;
use Exception;

/**
 * Text Widget (also known as Memo)
 *
 * @version    5.0
 * @package    widget
 * @subpackage form
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TText extends TField implements AdiantiWidgetInterface
{
    private $height;
    private $exitAction;
    private $exitFunction;
    protected $formName;
    protected $size;
    
    /**
     * Class Constructor
     * @param $name Widet's name
     */
    public function __construct($name)
    {
        parent::__construct($name);
        
        // creates a <textarea> tag
        $this->tag = new TElement('textarea');
        $this->tag->{'class'} = 'tfield';       // CSS
        $this->tag->{'widget'} = 'ttext';
        // defines the text default height
        $this->height= 100;
    }
    
    /**
     * Define the widget's size
     * @param  $width   Widget's width
     * @param  $height  Widget's height
     */
    public function setSize($width, $height = null)
    {
        $this->size   = $width;
        if ($height) {
            $this->height = $height;
        }
    }
    
    /**
     * Returns the size
     * @return array(width, height)
     */
    public function getSize()
    {
        return array( $this->size, $this->height );
    }
    
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
     * Set exit function
     */
    public function setExitFunction($function)
    {
        $this->exitFunction = $function;
    }
    
    /**
     * Show the widget
     */
    public function show()
    {
        $this->tag->{'name'}  = $this->name;   // tag name
        
        if ($this->size) {
            $size = (strstr($this->size, '%') !== false) ? $this->size : "{$this->size}px";
            $this->setProperty('style', "width:{$size};", false); //aggregate style info
        }
        
        if ($this->height) {
            $height = (strstr($this->height, '%') !== false) ? $this->height : "{$this->height}px";
            $this->setProperty('style', "height:{$height}", false); //aggregate style info
        }
        
        // check if the field is not editable
        if (!parent::getEditable()) {
            // make the widget read-only
            $this->tag->{'readonly'} = "1";
            $this->tag->{'class'} = $this->tag->{'class'} == 'tfield' ? 'tfield_disabled' : $this->tag->{'class'} . ' tfield_disabled'; // CSS
        }
        
        if (isset($this->exitAction)) {
            if (!TForm::getFormByName($this->formName) instanceof TForm) {
                throw new Exception(AdiantiCoreTranslator::translate('You must pass the ^1 (^2) as a parameter to ^3', __CLASS__, $this->name, 'TForm::setFields()'));
            }
            $string_action = $this->exitAction->serialize(false);
            $this->setProperty('exitaction', "__adianti_post_lookup('{$this->formName}', '{$string_action}', this, 'callback')");
            $this->setProperty('onBlur', $this->getProperty('exitaction'), false);
        }
        
        if (isset($this->exitFunction)) {
            $this->setProperty('onBlur', $this->exitFunction, false);
        }
        
        // add the content to the textarea
        $this->tag->add(htmlspecialchars($this->value));
        // show the tag
        $this->tag->show();
    }
}