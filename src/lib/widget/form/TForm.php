<?php
namespace Adianti\Base\Lib\Widget\Form;

use Adianti\Base\Lib\Widget\Form\AdiantiWidgetInterface;
use Adianti\Base\Lib\Widget\Base\TElement;
use Adianti\Base\Lib\Widget\Base\TScript;
use Adianti\Base\Lib\Widget\Form\TField;
use Adianti\Base\Lib\Widget\Form\TButton;
use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Exception;
use ReflectionClass;

/**
 * Wrapper class to deal with forms
 *
 * @version    5.5
 * @package    widget
 * @subpackage form
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TForm implements AdiantiFormInterface
{
    protected $fields; // array containing the form fields
    protected $name;   // form name
    protected $children;
    protected $js_function;
    protected $element;
    static private $forms;
    
    /**
     * Class Constructor
     * @param $name Form Name
     */
    public function __construct($name = 'my_form')
    {
        if ($name)
        {
            $this->setName($name);
        }
        $this->children = array();
        $this->element  = new TElement('form');
    }
    
    /**
     * Intercepts whenever someones assign a new property's value
     * @param $name     Property Name
     * @param $value    Property Value
     */
    public function __set($name, $value)
    {
        $rc = new ReflectionClass( $this );
        $classname = $rc->getShortName();
        
        if (in_array($classname, array('TForm', 'TQuickForm', 'TQuickNotebookForm')))
        {
            // objects and arrays are not set as properties
            if (is_scalar($value))
            {              
                // store the property's value
                $this->element->$name = $value;
            }
        }
        else
        {
            $this->$name = $value;
        }
    }
    
    /**
     * Define a form property
     * @param $name  Property Name
     * @param $value Property Value
     */
    public function setProperty($name, $value, $replace = TRUE)
    {
        if ($replace)
        {
            // delegates the property assign to the composed object
            $this->element->$name = $value;
        }
        else
        {
            if ($this->element->$name)
            {
            
                // delegates the property assign to the composed object
                $this->element->$name = $this->element->$name . ';' . $value;
            }
            else
            {
                // delegates the property assign to the composed object
                $this->element->$name = $value;
            }
        }
    }
    
    /**
     * Returns the form object by its name
     */
    public static function getFormByName($name)
    {
        if (isset(self::$forms[$name]))
        {
            return self::$forms[$name];
        }
    }
    
    /**
     * Define the form name
     * @param $name A string containing the form name
     */
    public function setName($name)
    {
        $this->name = $name;
        // register this form
        self::$forms[$this->name] = $this;
    }
    
    /**
     * Returns the form name
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Send data for a form located in the parent window
     * @param $form_name  Form Name
     * @param $object     An Object containing the form data
     */
    public static function sendData($form_name, $object, $aggregate = FALSE, $fireEvents = TRUE)
    {
        $fire_param = $fireEvents ? 'true' : 'false';
        // iterate the object properties
        if ($object)
        {
            foreach ($object as $field => $value)
            {
                if (is_object($value))  // TMultiField
                {
                    foreach ($value as $property=>$data)
                    {
                        // if inside ajax request, then utf8_encode if isn't utf8
                        if (utf8_encode(utf8_decode($data)) !== $data )
                        {
                            $data = utf8_encode(addslashes($data));
                        }
                        else
                        {
                            $data = addslashes($data);
                        }
                        $data = str_replace(array("\n", "\r"), array( '\n', '\r'), $data );
                        // send the property value to the form
                        TScript::create( " tform_send_data('{$form_name}', '{$field}_{$property}', '$data', $fire_param); " );
                    }
                }
                else
                {
                    if (is_array($value))
                    {
                        $value = implode('|', $value);
                    }
                    
                    // if inside ajax request, then utf8_encode if isn't utf8
                    if (utf8_encode(utf8_decode($value)) !== $value )
                    {
                        $value = utf8_encode(addslashes($value));
                    }
                    else
                    {
                        $value = addslashes($value);
                    }
                    
                    $value = str_replace(array("\n", "\r"), array( '\n', '\r'), $value );
                    
                    // send the property value to the form
                    if ($aggregate)
                    {
                        TScript::create( " tform_send_data_aggregate('{$form_name}', '{$field}', '$value', $fire_param); " );
                    }
                    else
                    {
                        TScript::create( " tform_send_data('{$form_name}', '{$field}', '$value', $fire_param); " );
                        TScript::create( " tform_send_data_by_id('{$form_name}', '{$field}', '$value', $fire_param); " );
                    }
                }
            }
        }
    }
    
    /**
     * Define if the form will be editable
     * @param $bool A Boolean
     */
    public function setEditable($bool)
    {
        if ($this->fields)
        {
            foreach ($this->fields as $object)
            {
                $object->setEditable($bool);
            }
        }
    }
    
    /**
     * Add a Form Field
     * @param $field Object
     */
    public function addField(AdiantiWidgetInterface $field)
    {
        if ($field instanceof TField)
        {
            $name = $field->getName();
            if (isset($this->fields[$name]) AND substr($name,-2) !== '[]')
            {
                throw new Exception(AdiantiCoreTranslator::translate('You have already added a field called "^1" inside the form', $name));
            }
            
            if ($name)
            {
                $this->fields[$name] = $field;
                $field->setFormName($this->name);
                
                if ($field instanceof TButton)
                {
                    $field->addFunction($this->js_function);
                }
            }
        }
        if ($field instanceof TMultiField)
        {
            $fieldid = $field->getId();
            $this->js_function .= "multifields['$fieldid'].parseTableToJSON();";
            
            if ($this->fields)
            {
                // if the button was added before multifield
                foreach ($this->fields as $field)
                {
                    if ($field instanceof TButton)
                    {
                        $field->addFunction($this->js_function);
                    }
                }
            }
        }
    }
    
    /**
     * Remove a form field
     * @param $field Object
     */
    public function delField(AdiantiWidgetInterface $field)
    {
        if ($this->fields)
        {
            foreach($this->fields as $name => $object)
            {
                if ($field === $object)
                {
                    unset($this->fields[$name]);
                }
            }
        }
    }
    
    /**
     * Remove all form fields
     */
    public function delFields()
    {
        $this->fields = array();
    }
    
    /**
     * Define wich are the form fields
     * @param $fields An array containing a collection of TField objects
     */
    public function setFields($fields)
    {
        if (is_array($fields))
        {
            $this->fields = array();
            $this->js_function = '';
            // iterate the form fields
            foreach ($fields as $field)
            {
                $this->addField($field);
            }
        }
        else
        {
            throw new Exception(AdiantiCoreTranslator::translate('Method ^1 must receive a parameter of type ^2', __METHOD__, 'Array'));
        }
    }
    
    /**
     * Returns a form field by its name
     * @param $name  A string containing the field's name
     * @return       The Field object
     */
    public function getField($name)
    {
        if (isset($this->fields[$name]))
        {
            return $this->fields[$name];
        }
    }
    
    /**
     * Returns an array with the form fields
     * @return Array of form fields
     */
    public function getFields()
    {
        return $this->fields;
    }
    
    /**
     * clear the form Data
     */
    public function clear($keepDefaults = FALSE)
    {
        // iterate the form fields
        foreach ($this->fields as $name => $field)
        {
            // labels don't have name
            if ($name AND !$keepDefaults)
            {
                $field->setValue(NULL);
            }
        }
    }
    
    /**
     * Define the data of the form
     * @param $object An Active Record object
     */
    public function setData($object)
    {
        // iterate the form fields
        foreach ($this->fields as $name => $field)
        {
            if ($name) // labels don't have name
            {
                if (isset($object->$name))
                {
                    $field->setValue($object->$name);
                }
            }
        }
    }
    
    /**
     * Returns the form POST data as an object
     * @param $class A string containing the class for the returning object
     */
    public function getData($class = 'StdClass')
    {
        if (!class_exists($class))
        {
            throw new Exception(AdiantiCoreTranslator::translate('Class ^1 not found in ^2', $class, __METHOD__));
        }
        
        $object = new $class;
        foreach ($this->fields as $key => $fieldObject)
        {
            $key = str_replace(['[',']'], ['',''], $key);
            
            if (!$fieldObject instanceof TButton)
            {
                $object->$key = $fieldObject->getPostData();
            }
        }
        
        return $object;
    }
    
    /**
     * Returns the form start values as an object
     * @param $class A string containing the class for the returning object
     */
    public function getValues($class = 'StdClass', $withOptions = false)
    {
        if (!class_exists($class))
        {
            throw new Exception(AdiantiCoreTranslator::translate('Class ^1 not found in ^2', $class, __METHOD__));
        }
        
        $object = new $class;
        if ($this->fields)
        {
            foreach ($this->fields as $key => $field)
            {
                $key = str_replace(['[',']'], ['',''], $key);
                
                if (!$field instanceof TButton)
                {
                    if ($withOptions AND method_exists($field, 'getItems'))
                    {
                        $items = $field->getItems();
                        
                        if (is_array($field->getValue()))
                        {
                            $value = [];
                            foreach ($field->getValue() as $field_value)
                            {
                                if ($field_value)
                                {
                                    $value[] = $items[$field_value];
                                }
                            }
                            $object->$key = $value;
                        }
                    }
                    else
                    {
                        $object->$key = $field->getValue();
                    }
                }
            }
        }
        
        return $object;
    }
    
    /**
     * Validate form
     */
    public function validate()
    {
        // assign post data before validation
        // validation exception would prevent
        // the user code to execute setData()
        $this->setData($this->getData());
        
        $errors = array();
        foreach ($this->fields as $fieldObject)
        {
            try
            {
                $fieldObject->validate();
            }
            catch (Exception $e)
            {
                $errors[] = $e->getMessage() . '.';
            }
        }
        
        if (count($errors) > 0)
        {
            throw new Exception(implode("<br>", $errors));
        }
    }
    
    /**
     * Add a container to the form (usually a table or panel)
     * @param $object Any Object that implements the show() method
     */
    public function add($object)
    {
        if (!in_array($object, $this->children))
        {
            $this->children[] = $object;
        }
    }
    
    /**
     * Pack a container to the form (usually a table or panel)
     * @param mixed $object, ...Any Object that implements the show() method
     */
    public function pack()
    {
        $this->children = func_get_args();
    }
    
    /**
     * Returns the child object
     */
    public function getChild()
    {
        return $this->children[0];
    }
    
    /**
     * Shows the form at the screen
     */
    public function show()
    {
        // define form properties
        $this->element->{'enctype'} = "multipart/form-data";
        $this->element->{'name'}    = $this->name; // form name
        $this->element->{'id'}      = $this->name; // form id
        $this->element->{'method'}  = 'post';      // transfer method
        
        // add the container to the form
        if (isset($this->children))
        {
            foreach ($this->children as $child)
            {
                $this->element->add($child);
            }
        }
        // show the form
        $this->element->show();
    }
}
