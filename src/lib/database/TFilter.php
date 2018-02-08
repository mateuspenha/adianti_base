<?php
namespace Adianti\Base\Lib\Database;

/**
 * Provides an interface to define filters to be used inside a criteria
 *
 * @version    5.0
 * @package    database
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TFilter extends TExpression
{
    private $variable;
    private $operator;
    private $value;
    private $value2;
    private $preparedVars;
    
    /**
     * Class Constructor
     *
     * @param  $variable = variable
     * @param  $operator = operator (>, <, =, BETWEEN)
     * @param  $value    = value to be compared
     * @param  $value2   = second value to be compared (between)
     */
    public function __construct($variable, $operator, $value, $value2 = null)
    {
        // store the properties
        $this->variable = $variable;
        $this->operator = $operator;
        $this->preparedVars = array();
        
        // transform the value according to its type
        $this->value    = $value;
        
        if ($value2) {
            $this->value2 = $value2;
        }
    }
    
    /**
     * Transform the value according to its PHP type
     * before send it to the database
     * @param $value    Value to be transformed
     * @param $prepared If the value will be prepared
     * @return       Transformed Value
     */
    private function transform($value, $prepared = false)
    {
        // if the value is an array
        if (is_array($value)) {
            $foo = array();
            // iterate the array
            foreach ($value as $x) {
                // if the value is an integer
                if (is_numeric($x)) {
                    if ($prepared) {
                        $preparedVar = ':par_'.$this->getRandomParameter();
                        $this->preparedVars[ $preparedVar ] = $x;
                        $foo[] = $preparedVar;
                    } else {
                        $foo[] = $x;
                    }
                } elseif (is_string($x)) {
                    // if the value is an string, add quotes
                    if ($prepared) {
                        $preparedVar = ':par_'.$this->getRandomParameter();
                        $this->preparedVars[ $preparedVar ] = $x;
                        $foo[] = $preparedVar;
                    } else {
                        $foo[] = "'$x'";
                    }
                } elseif (is_bool($x)) {
                    $foo[] = ($x) ? 'TRUE' : 'FALSE';
                }
            }
            // convert the array into a string, splitted by ","
            $result = '(' . implode(',', $foo) . ')';
        }
        // if the value is a subselect (must not be escaped as string)
        elseif (substr(strtoupper($value), 0, 7) == '(SELECT') {
            $result = "$value";
        }
        // if the value must not be escaped (NOESC in front)
        elseif (substr($value, 0, 6) == 'NOESC:') {
            $result = substr($value, 6);
        }
        // if the value is a string
        elseif (is_string($value)) {
            if ($prepared) {
                $preparedVar = ':par_'.$this->getRandomParameter();
                $this->preparedVars[ $preparedVar ] = $value;
                $result = $preparedVar;
            } else {
                // add quotes
                $result = "'$value'";
            }
        }
        // if the value is NULL
        elseif (is_null($value)) {
            // the result is 'NULL'
            $result = 'NULL';
        }
        // if the value is a boolean
        elseif (is_bool($value)) {
            // the result is 'TRUE' of 'FALSE'
            $result = $value ? 'TRUE' : 'FALSE';
        }
        // if the value is a TSqlStatement object
        elseif ($value instanceof TSqlStatement) {
            // the result is the return of the getInstruction()
            $result = '(' . $value->getInstruction() . ')';
        } else {
            if ($prepared) {
                $preparedVar = ':par_'.$this->getRandomParameter();
                $this->preparedVars[ $preparedVar ] = $value;
                $result = $preparedVar;
            } else {
                $result = $value;
            }
        }
        
        // returns the result
        return $result;
    }
    
    /**
     * Return the prepared vars
     */
    public function getPreparedVars()
    {
        return $this->preparedVars;
    }
    
    /**
     * Return the filter as a string expression
     * @return  A string containing the filter
     */
    public function dump($prepared = false)
    {
        $this->preparedVars = array();
        $value = $this->transform($this->value, $prepared);
        if ($this->value2) {
            $value2 = $this->transform($this->value2, $prepared);
            // concatenated the expression
            return "{$this->variable} {$this->operator} {$value} AND {$value2}";
        } else {
            // concatenated the expression
            return "{$this->variable} {$this->operator} {$value}";
        }
    }
    
    /**
     * Returns a random parameter
     */
    private function getRandomParameter()
    {
        return mt_rand(1000000000, 1999999999);
    }
}