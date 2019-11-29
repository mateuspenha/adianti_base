<?php
namespace Adianti\Base\Lib\Database;

use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Exception;
use Math\Parser;
use PDO;

/**
 * Base class for Active Records
 *
 * @version    5.5
 * @package    database
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
abstract class TRecord
{
    protected $data;  // array containing the data of the object
    protected $vdata; // array with virtual data (non-persistant properties)
    protected $attributes; // array of attributes
    
    /**
     * Class Constructor
     * Instantiates the Active Record
     * @param [$id] Optional Object ID, if passed, load this object
     */
    public function __construct($id = null, $callObjectLoad = true)
    {
        $this->attributes = array();
        
        if ($id) { // if the user has informed the $id
            // load the object identified by ID
            if ($callObjectLoad) {
                $object = $this->load($id);
            } else {
                $object = self::load($id);
            }
            
            if ($object) {
                $this->fromArray($object->toArray());
            } else {
                throw new Exception(AdiantiCoreTranslator::translate('Object ^1 not found in ^2', $id, constant(get_class($this).'::TABLENAME')));
            }
        }
    }
    
    /**
     * Create a new TRecord and returns the instance
     * @param $data indexed array
     */
    public static function create($data)
    {
        $object = new static;
        $object->fromArray($data);
        $object->store();
        return $object;
    }
    
    /**
     * Executed when the programmer clones an Active Record
     * In this case, we have to clear the ID, to generate a new one
     */
    public function __clone()
    {
        $pk = $this->getPrimaryKey();
        unset($this->$pk);
    }
    
    /**
     * Executed whenever an unknown method is executed
     * @param $method Method name
     * @param $parameter Method parameters
     */
    public static function __callStatic($method, $parameters)
    {
        $class_name = get_called_class();
        if (substr($method, -13) == 'InTransaction') {
            $method = substr($method, 0, -13);
            if (method_exists($class_name, $method)) {
                $database = array_shift($parameters);
                TTransaction::open($database);
                $content = forward_static_call_array(array($class_name, $method), $parameters);
                TTransaction::close();
                return $content;
            } else {
                throw new Exception(AdiantiCoreTranslator::translate('Method ^1 not found', $class_name.'::'.$method.'()'));
            }
        } else {
            throw new Exception(AdiantiCoreTranslator::translate('Method ^1 not found', $class_name.'::'.$method.'()'));
        }
    }
    
    /**
     * Executed whenever a property is accessed
     * @param $property Name of the object property
     * @return          The value of the property
     */
    public function __get($property)
    {
        // check if exists a method called get_<property>
        if (method_exists($this, 'get_'.$property)) {
            // execute the method get_<property>
            return call_user_func(array($this, 'get_'.$property));
        } else {
            if (strpos($property, '->') !== false) {
                $parts = explode('->', $property);
                $container = $this;
                foreach ($parts as $part) {
                    if (is_object($container)) {
                        $result = $container->$part;
                        $container = $result;
                    } else {
                        throw new Exception(AdiantiCoreTranslator::translate('Trying to access a non-existent property (^1)', $property));
                    }
                }
                return $result;
            } else {
                // returns the property value
                if (isset($this->data[$property])) {
                    return $this->data[$property];
                } elseif (isset($this->vdata[$property])) {
                    return $this->vdata[$property];
                }
            }
        }
    }
    
    /**
     * Executed whenever a property is assigned
     * @param $property Name of the object property
     * @param $value    Value of the property
     */
    public function __set($property, $value)
    {
        if ($property == 'data') {
            throw new Exception(AdiantiCoreTranslator::translate('Reserved property name (^1) in class ^2', $property, get_class($this)));
        }
        
        // check if exists a method called set_<property>
        if (method_exists($this, 'set_'.$property)) {
            // executed the method called set_<property>
            call_user_func(array($this, 'set_'.$property), $value);
        } else {
            if ($value === null) {
                $this->data[$property] = null;
            } elseif (is_scalar($value)) {
                // assign the property's value
                $this->data[$property] = $value;
                unset($this->vdata[$property]);
            } else {
                // other non-scalar properties that won't be persisted
                $this->vdata[$property] = $value;
                unset($this->data[$property]);
            }
        }
    }
    
    /**
     * Returns if a property is assigned
     * @param $property Name of the object property
     */
    public function __isset($property)
    {
        return isset($this->data[$property]) or
               isset($this->vdata[$property]) or
               method_exists($this, 'get_'.$property);
    }
    
    /**
     * Unset a property
     * @param $property Name of the object property
     */
    public function __unset($property)
    {
        unset($this->data[$property]);
        unset($this->vdata[$property]);
    }
    
    /**
     * Returns the cache control
     */
    public function getCacheControl()
    {
        $class = get_class($this);
        $cache_name = "{$class}::CACHECONTROL";
        
        if (defined($cache_name)) {
            $cache_control = constant($cache_name);
            $implements = class_implements($cache_control);
            
            if (in_array('Adianti\Registry\AdiantiRegistryInterface', $implements)) {
                if ($cache_control::enabled()) {
                    return $cache_control;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Returns the name of database entity
     * @return A String containing the name of the entity
     */
    protected function getEntity()
    {
        // get the Active Record class name
        $class = get_class($this);
        // return the TABLENAME Active Record class constant
        return constant("{$class}::TABLENAME");
    }
    
    /**
     * Returns the the name of the primary key for that Active Record
     * @return A String containing the primary key name
     */
    public function getPrimaryKey()
    {
        // get the Active Record class name
        $class = get_class($this);
        // returns the PRIMARY KEY Active Record class constant
        return constant("{$class}::PRIMARYKEY");
    }
    
    /**
     * Returns the the name of the sequence for primary key
     * @return A String containing the sequence name
     */
    private function getSequenceName()
    {
        // get the Active Record class name
        $class = get_class($this);
        
        if (defined("{$class}::SEQUENCE")) {
            return constant("{$class}::SEQUENCE");
        } else {
            return $this->getEntity().'_'. $this->getPrimaryKey().'_seq';
        }
    }
    
    /**
     * Fill the Active Record properties from another Active Record
     * @param $object An Active Record
     */
    public function mergeObject(TRecord $object)
    {
        $data = $object->toArray();
        foreach ($data as $key => $value) {
            $this->data[$key] = $value;
        }
    }
    
    /**
     * Fill the Active Record properties from an indexed array
     * @param $data An indexed array containing the object properties
     */
    public function fromArray($data)
    {
        if (count($this->attributes) > 0) {
            $pk = $this->getPrimaryKey();
            foreach ($data as $key => $value) {
                // set just attributes defined by the addAttribute()
                if ((in_array($key, $this->attributes) and is_string($key)) or ($key === $pk)) {
                    $this->data[$key] = $data[$key];
                }
            }
        } else {
            foreach ($data as $key => $value) {
                $this->data[$key] = $data[$key];
            }
        }
    }
    
    /**
     * Return the Active Record properties as an indexed array
     * @return An indexed array containing the object properties
     */
    public function toArray()
    {
        $data = array();
        if (count($this->attributes) > 0) {
            $pk = $this->getPrimaryKey();
            if (!empty($this->data)) {
                foreach ($this->data as $key => $value) {
                    if ((in_array($key, $this->attributes) and is_string($key)) or ($key === $pk)) {
                        $data[$key] = $this->data[$key];
                    }
                }
            }
        } else {
            $data = $this->data;
        }
        return $data;
    }
    
    /**
     * Return the Active Record properties as a json string
     * @return A JSON String
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
    
    /**
     * Render variables inside brackets
     */
    public function render($pattern, $cast = null)
    {
        $content = $pattern;
        if (preg_match_all('/\{(.*?)\}/', $pattern, $matches)) {
            foreach ($matches[0] as $match) {
                $property = substr($match, 1, -1);
                if (substr($property, 0, 1) == '$') {
                    $property = substr($property, 1);
                }
                $value = $this->$property;
                if ($cast) {
                    settype($value, $cast);
                }
                $content  = str_replace($match, $value, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Evaluate variables inside brackets
     */
    public function evaluate($pattern)
    {
        $content = $this->render($pattern, 'float');
        $content = str_replace('+', ' + ', $content);
        $content = str_replace('-', ' - ', $content);
        $content = str_replace('*', ' * ', $content);
        $content = str_replace('/', ' / ', $content);
        $content = str_replace('(', ' ( ', $content);
        $content = str_replace(')', ' ) ', $content);
        $parser = new Parser;
        $content = $parser->evaluate(substr($content, 1));
        return $content;
    }
    
    /**
     * Register an persisted attribute
     */
    public function addAttribute($attribute)
    {
        if ($attribute == 'data') {
            throw new Exception(AdiantiCoreTranslator::translate('Reserved property name (^1) in class ^2', $attribute, get_class($this)));
        }
        
        $this->attributes[] = $attribute;
    }
    
    /**
     * Return the persisted attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
    
    /**
     * Get attribute list
     */
    public function getAttributeList()
    {
        if (count($this->attributes) > 0) {
            $attributes = $this->attributes;
            $attributes[] = $this->getPrimaryKey();
            return implode(',', $attributes);
        }
        
        return '*';
    }
    
    /**
     * Store the objects into the database
     * @return      The number of affected rows
     * @exception   Exception if there's no active transaction opened
     */
    public function store()
    {
        // get the Active Record class name
        $class = get_class($this);
        
        // check if the object has an ID or exists in the database
        $pk = $this->getPrimaryKey();
        
        if (method_exists($this, 'onBeforeStore')) {
            $virtual_object = (object) $this->data;
            $this->onBeforeStore($virtual_object);
            $this->data = (array) $virtual_object;
        }
        
        if (empty($this->data[$pk]) or (!self::exists($this->$pk))) {
            // increments the ID
            if (empty($this->data[$pk])) {
                if ((defined("{$class}::IDPOLICY")) and (constant("{$class}::IDPOLICY") == 'serial')) {
                    unset($this->$pk);
                } else {
                    $this->$pk = $this->getLastID() +1;
                }
            }
            // creates an INSERT instruction
            $sql = new TSqlInsert;
            $sql->setEntity($this->getEntity());
            // iterate the object data
            foreach ($this->data as $key => $value) {
                // check if the field is a calculated one
                if (!method_exists($this, 'get_' . $key) or (count($this->attributes) > 0)) {
                    if (count($this->attributes) > 0) {
                        // set just attributes defined by the addAttribute()
                        if ((in_array($key, $this->attributes) and is_string($key)) or ($key === $pk)) {
                            // pass the object data to the SQL
                            $sql->setRowData($key, $this->data[$key]);
                        }
                    } else {
                        // pass the object data to the SQL
                        $sql->setRowData($key, $this->data[$key]);
                    }
                }
            }
        } else {
            // creates an UPDATE instruction
            $sql = new TSqlUpdate;
            $sql->setEntity($this->getEntity());
            // creates a select criteria based on the ID
            $criteria = new TCriteria;
            $criteria->add(new TFilter($pk, '=', $this->$pk));
            $sql->setCriteria($criteria);
            // interate the object data
            foreach ($this->data as $key => $value) {
                if ($key !== $pk) { // there's no need to change the ID value
                    // check if the field is a calculated one
                    if (!method_exists($this, 'get_' . $key) or (count($this->attributes) > 0)) {
                        if (count($this->attributes) > 0) {
                            // set just attributes defined by the addAttribute()
                            if ((in_array($key, $this->attributes) and is_string($key)) or ($key === $pk)) {
                                // pass the object data to the SQL
                                $sql->setRowData($key, $this->data[$key]);
                            }
                        } else {
                            // pass the object data to the SQL
                            $sql->setRowData($key, $this->data[$key]);
                        }
                    }
                }
            }
        }
        // get the connection of the active transaction
        if ($conn = TTransaction::get()) {
            // register the operation in the LOG file
            TTransaction::log($sql->getInstruction());
            
            $dbinfo = TTransaction::getDatabaseInfo(); // get dbinfo
            if (isset($dbinfo['prep']) and $dbinfo['prep'] == '1') { // prepared ON
                $result = $conn-> prepare($sql->getInstruction(true), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $result-> execute($sql->getPreparedVars());
            } else {
                // execute the query
                $result = $conn-> query($sql->getInstruction());
            }
            
            if ((defined("{$class}::IDPOLICY")) and (constant("{$class}::IDPOLICY") == 'serial')) {
                if (($sql instanceof TSqlInsert) and empty($this->data[$pk])) {
                    $this->$pk = $conn->lastInsertId($this->getSequenceName());
                }
            }
            
            if ($cache = $this->getCacheControl()) {
                $record_key = $class . '['. $this->$pk . ']';
                if ($cache::setValue($record_key, $this->toArray())) {
                    TTransaction::log($record_key . ' stored in cache');
                }
            }
            
            if (method_exists($this, 'onAfterStore')) {
                $this->onAfterStore((object) $this->toArray());
            }
            
            // return the result of the exec() method
            return $result;
        } else {
            // if there's no active transaction opened
            throw new Exception(AdiantiCoreTranslator::translate('No active transactions') . ': ' . __METHOD__ .' '. $this->getEntity());
        }
    }
    
    /**
     * Tests if an ID exists
     * @param $id  The object ID
     * @exception  Exception if there's no active transaction opened
     */
    public function exists($id)
    {
        if (empty($id)) {
            return false;
        }
        
        $class = get_class($this);     // get the Active Record class name
        $pk = $this->getPrimaryKey();  // discover the primary key name
        
        // creates a SELECT instruction
        $sql = new TSqlSelect;
        $sql->setEntity($this->getEntity());
        $sql->addColumn($this->getAttributeList());
        
        // creates a select criteria based on the ID
        $criteria = new TCriteria;
        $criteria->add(new TFilter($pk, '=', $id));
        $sql->setCriteria($criteria);
        
        // get the connection of the active transaction
        if ($conn = TTransaction::get()) {
            $dbinfo = TTransaction::getDatabaseInfo(); // get dbinfo
            if (isset($dbinfo['prep']) and $dbinfo['prep'] == '1') { // prepared ON
                $result = $conn-> prepare($sql->getInstruction(true), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $result-> execute($criteria->getPreparedVars());
            } else {
                $result = $conn-> query($sql->getInstruction());
            }
            
            // if there's a result
            if ($result) {
                // returns the data as an object of this class
                $object = $result-> fetchObject(get_class($this));
            }
            
            return is_object($object);
        } else {
            // if there's no active transaction opened
            throw new Exception(AdiantiCoreTranslator::translate('No active transactions') . ': ' . __METHOD__ .' '. $this->getEntity());
        }
    }
    
    /**
     * ReLoad an Active Record Object from the database
     */
    public function reload()
    {
        // discover the primary key name
        $pk = $this->getPrimaryKey();
        
        return $this->load($this->$pk);
    }
    
    /**
     * Load an Active Record Object from the database
     * @param $id  The object ID
     * @return     The Active Record Object
     * @exception  Exception if there's no active transaction opened
     */
    public function load($id)
    {
        $class = get_class($this);     // get the Active Record class name
        $pk = $this->getPrimaryKey();  // discover the primary key name
        
        if (method_exists($this, 'onBeforeLoad')) {
            $this->onBeforeLoad($id);
        }
        
        if ($cache = $this->getCacheControl()) {
            $record_key = $class . '['. $id . ']';
            if ($fetched_data = $cache::getValue($record_key)) {
                $fetched_object = (object) $fetched_data;
                $loaded_object  = clone $this;
                if (method_exists($this, 'onAfterLoad')) {
                    $this->onAfterLoad($fetched_object);
                    $loaded_object->fromArray((array) $fetched_object);
                } else {
                    $loaded_object->fromArray($fetched_data);
                }
                TTransaction::log($record_key . ' loaded from cache');
                return $loaded_object;
            }
        }
        
        // creates a SELECT instruction
        $sql = new TSqlSelect;
        $sql->setEntity($this->getEntity());
        // use *, once this is called before addAttribute()s
        $sql->addColumn($this->getAttributeList());
        
        // creates a select criteria based on the ID
        $criteria = new TCriteria;
        $criteria->add(new TFilter($pk, '=', $id));
        // define the select criteria
        $sql->setCriteria($criteria);
        // get the connection of the active transaction
        if ($conn = TTransaction::get()) {
            // register the operation in the LOG file
            TTransaction::log($sql->getInstruction());
            
            $dbinfo = TTransaction::getDatabaseInfo(); // get dbinfo
            if (isset($dbinfo['prep']) and $dbinfo['prep'] == '1') { // prepared ON
                $result = $conn-> prepare($sql->getInstruction(true), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $result-> execute($criteria->getPreparedVars());
            } else {
                // execute the query
                $result = $conn-> query($sql->getInstruction());
            }
            
            // if there's a result
            if ($result) {
                $activeClass = get_class($this);
                $fetched_object = $result-> fetchObject();
                if ($fetched_object) {
                    if (method_exists($this, 'onAfterLoad')) {
                        $this->onAfterLoad($fetched_object);
                    }
                    $object = new $activeClass;
                    $object->fromArray((array) $fetched_object);
                } else {
                    $object = null;
                }
                
                if ($object) {
                    if ($cache = $this->getCacheControl()) {
                        $record_key = $class . '['. $id . ']';
                        if ($cache::setValue($record_key, $object->toArray())) {
                            TTransaction::log($record_key . ' stored in cache');
                        }
                    }
                }
            }
            
            return $object;
        } else {
            // if there's no active transaction opened
            throw new Exception(AdiantiCoreTranslator::translate('No active transactions') . ': ' . __METHOD__ .' '. $this->getEntity());
        }
    }
    
    /**
     * Delete an Active Record object from the database
     * @param [$id]     The Object ID
     * @exception       Exception if there's no active transaction opened
     */
    public function delete($id = null)
    {
        $class = get_class($this);
        
        if (method_exists($this, 'onBeforeDelete')) {
            $this->onBeforeDelete((object) $this->toArray());
        }
        
        // discover the primary key name
        $pk = $this->getPrimaryKey();
        // if the user has not passed the ID, take the object ID
        $id = $id ? $id : $this->$pk;
        // creates a DELETE instruction
        $sql = new TSqlDelete;
        $sql->setEntity($this->getEntity());
        
        // creates a select criteria
        $criteria = new TCriteria;
        $criteria->add(new TFilter($pk, '=', $id));
        // assign the criteria to the delete instruction
        $sql->setCriteria($criteria);
        
        // get the connection of the active transaction
        if ($conn = TTransaction::get()) {
            // register the operation in the LOG file
            TTransaction::log($sql->getInstruction());
            
            $dbinfo = TTransaction::getDatabaseInfo(); // get dbinfo
            if (isset($dbinfo['prep']) and $dbinfo['prep'] == '1') { // prepared ON
                $result = $conn-> prepare($sql->getInstruction(true), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $result-> execute($criteria->getPreparedVars());
            } else {
                // execute the query
                $result = $conn-> query($sql->getInstruction());
            }
            
            if ($cache = $this->getCacheControl()) {
                $record_key = $class . '['. $id . ']';
                if ($cache::delValue($record_key)) {
                    TTransaction::log($record_key . ' deleted from cache');
                }
            }
            
            if (method_exists($this, 'onAfterDelete')) {
                $this->onAfterDelete((object) $this->toArray());
            }
            
            unset($this->data);
            
            // return the result of the exec() method
            return $result;
        } else {
            // if there's no active transaction opened
            throw new Exception(AdiantiCoreTranslator::translate('No active transactions') . ': ' . __METHOD__ .' '. $this->getEntity());
        }
    }
    
    /**
     * Returns the FIRST Object ID from database
     * @return      An Integer containing the FIRST Object ID from database
     * @exception   Exception if there's no active transaction opened
     */
    public function getFirstID()
    {
        $pk = $this->getPrimaryKey();
        
        // get the connection of the active transaction
        if ($conn = TTransaction::get()) {
            // instancia instrução de SELECT
            $sql = new TSqlSelect;
            $sql->addColumn("min({$pk}) as {$pk}");
            $sql->setEntity($this->getEntity());
            // register the operation in the LOG file
            TTransaction::log($sql->getInstruction());
            $result= $conn->Query($sql->getInstruction());
            // retorna os dados do banco
            $row = $result->fetch();
            return $row[0];
        } else {
            // if there's no active transaction opened
            throw new Exception(AdiantiCoreTranslator::translate('No active transactions') . ': ' . __METHOD__ .' '. $this->getEntity());
        }
    }
    
    /**
     * Returns the LAST Object ID from database
     * @return      An Integer containing the LAST Object ID from database
     * @exception   Exception if there's no active transaction opened
     */
    public function getLastID()
    {
        $pk = $this->getPrimaryKey();
        
        // get the connection of the active transaction
        if ($conn = TTransaction::get()) {
            // instancia instrução de SELECT
            $sql = new TSqlSelect;
            $sql->addColumn("max({$pk}) as {$pk}");
            $sql->setEntity($this->getEntity());
            // register the operation in the LOG file
            TTransaction::log($sql->getInstruction());
            $result= $conn->Query($sql->getInstruction());
            // retorna os dados do banco
            $row = $result->fetch();
            return $row[0];
        } else {
            // if there's no active transaction opened
            throw new Exception(AdiantiCoreTranslator::translate('No active transactions') . ': ' . __METHOD__ .' '. $this->getEntity());
        }
    }
    
    /**
     * Method getObjects
     * @param $criteria        Optional criteria
     * @param $callObjectLoad  If load() method from Active Records must be called to load object parts
     * @return                 An array containing the Active Records
     */
    public static function getObjects($criteria = null, $callObjectLoad = true)
    {
        // get the Active Record class name
        $class = get_called_class();
        
        // create the repository
        $repository = new TRepository($class);
        if (!$criteria) {
            $criteria = new TCriteria;
        }
        
        return $repository->load($criteria, $callObjectLoad);
    }
    
    /**
     * Method countObjects
     * @param $criteria        Optional criteria
     * @return                 An array containing the Active Records
     */
    public static function countObjects($criteria = null)
    {
        // get the Active Record class name
        $class = get_called_class();
        
        // create the repository
        $repository = new TRepository($class);
        if (!$criteria) {
            $criteria = new TCriteria;
        }
        
        return $repository->count($criteria);
    }
    
    /**
     * Load composite objects (parts in composition relationship)
     * @param $composite_class Active Record Class for composite objects
     * @param $foreign_key Foreign key in composite objects
     * @param $id Primary key of parent object
     * @returns Array of Active Records
     */
    public function loadComposite($composite_class, $foreign_key, $id = null, $order = null)
    {
        $pk = $this->getPrimaryKey(); // discover the primary key name
        $id = $id ? $id : $this->$pk; // if the user has not passed the ID, take the object ID
        $criteria = TCriteria::create([$foreign_key => $id ], ['order' => $order]);
        $repository = new TRepository($composite_class);
        return $repository->load($criteria);
    }
    
    /**
     * Load composite objects. Shortcut for loadComposite
     * @param $composite_class Active Record Class for composite objects
     * @param $foreign_key Foreign key in composite objects
     * @param $primary_key Primary key of parent object
     * @returns Array of Active Records
     */
    public function hasMany($composite_class, $foreign_key = null, $primary_key = null, $order = null)
    {
        $foreign_key = isset($foreign_key) ? $foreign_key : $this->underscoreFromCamelCase(get_class($this)) . '_id';
        $primary_key = $primary_key ? $primary_key : $this->getPrimaryKey();
        return $this->loadComposite($composite_class, $foreign_key, $this->$primary_key, $order);
    }
    
    /**
     * Create a criteria to load composite objects
     * @param $composite_class Active Record Class for composite objects
     * @param $foreign_key Foreign key in composite objects
     * @param $primary_key Primary key of parent object
     * @returns TRepository instance
     */
    public function filterMany($composite_class, $foreign_key = null, $primary_key = null, $order = null)
    {
        $foreign_key = isset($foreign_key) ? $foreign_key : $this->underscoreFromCamelCase(get_class($this)) . '_id';
        $primary_key = $primary_key ? $primary_key : $this->getPrimaryKey();
        $criteria = TCriteria::create([$foreign_key => $this->$primary_key ], ['order' => $order]);
        return new TRepository($composite_class);
    }
    
    /**
     * Delete composite objects (parts in composition relationship)
     * @param $composite_class Active Record Class for composite objects
     * @param $foreign_key Foreign key in composite objects
     * @param $id Primary key of parent object
     */
    public function deleteComposite($composite_class, $foreign_key, $id, $callObjectLoad = false)
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter($foreign_key, '=', $id));
        
        $repository = new TRepository($composite_class);
        return $repository->delete($criteria, $callObjectLoad);
    }
    
    /**
     * Save composite objects (parts in composition relationship)
     * @param $composite_class Active Record Class for composite objects
     * @param $foreign_key Foreign key in composite objects
     * @param $id Primary key of parent object
     * @param $objects Array of Active Records to be saved
     */
    public function saveComposite($composite_class, $foreign_key, $id, $objects, $callObjectLoad = false)
    {
        $this->deleteComposite($composite_class, $foreign_key, $id, $callObjectLoad);
        
        if ($objects) {
            foreach ($objects as $object) {
                $object-> $foreign_key  = $id;
                $object->store();
            }
        }
    }
    
    /**
     * Load aggregated objects (parts in aggregation relationship)
     * @param $aggregate_class Active Record Class for aggregated objects
     * @param $join_class Active Record Join Class (Parent / Aggregated)
     * @param $foreign_key_parent Foreign key in Join Class to parent object
     * @param $foreign_key_child Foreign key in Join Class to child object
     * @param $id Primary key of parent object
     * @returns Array of Active Records
     */
    public function loadAggregate($aggregate_class, $join_class, $foreign_key_parent, $foreign_key_child, $id = null)
    {
        // discover the primary key name
        $pk = $this->getPrimaryKey();
        // if the user has not passed the ID, take the object ID
        $id = $id ? $id : $this->$pk;
        
        $criteria   = new TCriteria;
        $criteria->add(new TFilter($foreign_key_parent, '=', $id));
        
        $repository = new TRepository($join_class);
        $objects = $repository->load($criteria);
        
        $aggregates = array();
        if ($objects) {
            foreach ($objects as $object) {
                $aggregates[] = new $aggregate_class($object-> $foreign_key_child);
            }
        }
        return $aggregates;
    }
    
    /**
     * Load aggregated objects. Shortcut to loadAggregate
     * @param $aggregate_class Active Record Class for aggregated objects
     * @param $join_class Active Record Join Class (Parent / Aggregated)
     * @param $foreign_key_parent Foreign key in Join Class to parent object
     * @param $foreign_key_child Foreign key in Join Class to child object
     * @returns Array of Active Records
     */
    public function belongsToMany($aggregate_class, $join_class = null, $foreign_key_parent = null, $foreign_key_child = null)
    {
        $class = get_class($this);
        $join_class = isset($join_class) ? $join_class : $class.$aggregate_class;
        $foreign_key_parent = isset($foreign_key_parent) ? $foreign_key_parent : $this->underscoreFromCamelCase($class) . '_id';
        $foreign_key_child  = isset($foreign_key_child)  ? $foreign_key_child  : $this->underscoreFromCamelCase($aggregate_class) . '_id';
        
        return $this->loadAggregate($aggregate_class, $join_class, $foreign_key_parent, $foreign_key_child);
    }
    
    /**
     * Save aggregated objects (parts in aggregation relationship)
     * @param $join_class Active Record Join Class (Parent / Aggregated)
     * @param $foreign_key_parent Foreign key in Join Class to parent object
     * @param $foreign_key_child Foreign key in Join Class to child object
     * @param $id Primary key of parent object
     * @param $objects Array of Active Records to be saved
     */
    public function saveAggregate($join_class, $foreign_key_parent, $foreign_key_child, $id, $objects)
    {
        $this->deleteComposite($join_class, $foreign_key_parent, $id);
        
        if ($objects) {
            foreach ($objects as $object) {
                $join = new $join_class;
                $join-> $foreign_key_parent = $id;
                $join-> $foreign_key_child  = $object->id;
                $join->store();
            }
        }
    }
    
    /**
     * Returns the first object
     */
    public static function first()
    {
        $object = new static;
        $id = $object->getFirstID();
        
        return self::find($id);
    }
    
    /**
     * Returns the last object
     */
    public static function last()
    {
        $object = new static;
        $id = $object->getLastID();
        
        return self::find($id);
    }
    
    /**
     * Find a Active Record and returns it
     * @return The Active Record itself or NULL when not found
     */
    public static function find($id)
    {
        $classname = get_called_class();
        $ar = new $classname;
        return $ar->load($id);
    }
    
    /**
     * Returns all objects
     */
    public static function all()
    {
        return self::getObjects(null, false);
    }
    
    /**
     * Save the object
     */
    public function save()
    {
        $this->store();
    }
    
    /**
     * Creates an indexed array
     * @returns the TRepository object with a filter
     */
    public static function getIndexedArray($indexColumn, $valueColumn, $criteria = null)
    {
        $sort_array = false;
        
        if (empty($criteria)) {
            $criteria = new TCriteria;
            $sort_array = true;
        }
        
        $indexedArray = array();
        $class = get_called_class(); // get the Active Record class name
        $repository = new TRepository($class); // create the repository
        $objects = $repository->load($criteria, false);
        if ($objects) {
            foreach ($objects as $object) {
                $key = (isset($object->$indexColumn)) ? $object->$indexColumn : $object->render($indexColumn);
                $val = (isset($object->$valueColumn)) ? $object->$valueColumn : $object->render($valueColumn);
                
                $indexedArray[ $key ] = $val;
            }
        }
        
        if ($sort_array) {
            asort($indexedArray);
        }
        return $indexedArray;
    }
    
    /**
     * Creates a Repository with filter
     * @returns the TRepository object with a filter
     */
    public static function select()
    {
        $class = get_called_class(); // get the Active Record class name
        $repository = new TRepository($class); // create the repository
        return $repository->select(func_get_args());
    }
    
    /**
     * Creates a Repository with filter
     * @returns the TRepository object with a filter
     */
    public static function where($variable, $operator, $value, $logicOperator = TExpression::AND_OPERATOR)
    {
        $class = get_called_class(); // get the Active Record class name
        $repository = new TRepository($class); // create the repository
        return $repository->where($variable, $operator, $value, $logicOperator);
    }
    
    /**
     * Creates a Repository with OR filter
     * @returns the TRepository object with an OR filter
     */
    public static function orWhere($variable, $operator, $value)
    {
        $class = get_called_class(); // get the Active Record class name
        $repository = new TRepository($class); // create the repository
        return $repository->orWhere($variable, $operator, $value);
    }
    
    /**
     * Creates an ordered repository
     * @param  $order = Order column
     * @param  $direction = Order direction (asc, desc)
     * @returns the ordered TRepository object
     */
    public static function orderBy($order, $direction = 'asc')
    {
        $class = get_called_class(); // get the Active Record class name
        $repository = new TRepository($class); // create the repository
        return $repository->orderBy($order, $direction);
    }
    
    private function underscoreFromCamelCase($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$'.'1_$'.'2', $string));
    }
}
