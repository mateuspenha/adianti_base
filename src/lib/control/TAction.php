<?php
namespace Adianti\Base\Lib\Control;

use Adianti\Base\Lib\Core\AdiantiCoreApplication;
use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Exception;
use ReflectionMethod;

/**
 * Structure to encapsulate an action
 *
 * @version    5.0
 * @package    control
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TAction
{
    protected $action;
    protected $param;
    protected $properties;
    
    /**
     * Class Constructor
     * @param $action Callback to be executed
     * @param $parameters = array of parameters
     */
    public function __construct($action, $parameters = null)
    {
        $this->action = $action;
        if (!is_callable($this->action)) {
            $action_string = $this->toString();
            throw new Exception(AdiantiCoreTranslator::translate('Method ^1 must receive a parameter of type ^2', __METHOD__, 'Callback'). ' <br> '.
                                AdiantiCoreTranslator::translate('Check if the action (^1) exists', $action_string));
        }
        
        if (!empty($parameters)) {
            $this->setParameters($parameters);
        }
    }
    
    /**
     * Returns the action as a string
     */
    public function toString()
    {
        $action_string = '';
        if (is_string($this->action)) {
            $action_string = $this->action;
        } elseif (is_array($this->action)) {
            if (is_object($this->action[0])) {
                $action_string = get_class($this->action[0]) . '::' . $this->action[1];
            } else {
                $action_string = $this->action[0] . '::' . $this->action[1];
            }
        }
        return $action_string;
    }
    
    /**
     * Adds a parameter to the action
     * @param  $param = parameter name
     * @param  $value = parameter value
     */
    public function setParameter($param, $value)
    {
        $this->param[$param] = $value;
    }
    
    /**
     * Set the parameters for the action
     * @param  $parameters = array of parameters
     */
    public function setParameters($parameters)
    {
        // does not override the action
        unset($parameters['class']);
        unset($parameters['method']);
        $this->param = $parameters;
    }
    
    /**
     * Returns a parameter
     * @param  $param = parameter name
     */
    public function getParameter($param)
    {
        if (isset($this->param[$param])) {
            return $this->param[$param];
        }
        return null;
    }
    
    /**
     * Return the Action Parameters
     */
    public function getParameters()
    {
        return $this->param;
    }
    
    /**
     * Returns the current calback
     */
    public function getAction()
    {
        return $this->action;
    }
    
    /**
     * Set property
     */
    public function setProperty($property, $value)
    {
        $this->properties[$property] = $value;
    }
    
    /**
     * Get property
     */
    public function getProperty($property)
    {
        return $this->properties[$property];
    }
    
    /**
     * Prepare action for use over an object
     * @param $object Data Object
     */
    public function prepare($object)
    {
        $parameters = $this->param;
        $action     = clone $this;
        
        if ($parameters) {
            foreach ($parameters as $parameter => $value) {
                // replace {attribute}s
                $action->setParameter($parameter, $this->replace($value, $object));
            }
        }
        
        return $action;
    }
    
    /**
     * Replace a string with object properties within {pattern}
     * @param $content String with pattern
     * @param $object  Any object
     */
    private function replace($content, $object)
    {
        if (preg_match_all('/\{(.*?)\}/', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $property = substr($match, 1, -1);
                $value    = isset($object->$property)? $object->$property : null;
                $content  = str_replace($match, $value, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Converts the action into an URL
     * @param  $format_action = format action with document or javascript (ajax=no)
     */
    public function serialize($format_action = true)
    {
        // check if the callback is a method of an object
        if (is_array($this->action)) {
            $class = $this->action[0];
            // get the class name
            $url['class'] = is_object($class) ? (new \ReflectionClass(get_class($class)))->getName() : (new \ReflectionClass($this->action[0]))->getName();
            // get the method name
            $url['method'] = $this->action[1];
        }
        // otherwise the callback is a function
        elseif (is_string($this->action)) {
            // get the function name
            $url['method'] = $this->action;
        }
        
        // check if there are parameters
        if ($this->param) {
            $url = array_merge($url, $this->param);
        }
        
        if ($format_action) {
            if ($router = AdiantiCoreApplication::getRouter()) {
                return $router(http_build_query($url));
            } else {
                return 'index.php?'.http_build_query($url);
            }
        } else {
            return http_build_query($url);
        }
    }
    
    /**
     * Returns if the action is static
     */
    public function isStatic()
    {
        if (is_callable($this->action) and is_array($this->action)) {
            $class  = is_string($this->action[0])? $this->action[0]: get_class($this->action[0]);
            $method = $this->action[1];
            
            if (method_exists($class, $method)) {
                $rm = new ReflectionMethod($class, $method);
                return $rm-> isStatic();
            } else {
                return false;
            }
        }
        return false;
    }
}
