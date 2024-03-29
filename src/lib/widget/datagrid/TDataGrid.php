<?php
namespace Adianti\Base\Lib\Widget\Datagrid;

use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Adianti\Base\Lib\Math\Parser;
use Adianti\Base\Lib\Widget\Base\TElement;
use Adianti\Base\Lib\Widget\Base\TScript;
use Adianti\Base\Lib\Widget\Container\TTable;
use Adianti\Base\Lib\Widget\Util\TDropDown;
use Adianti\Base\Lib\Widget\Util\TImage;
use Dvi\Support\Http\Request;
use Exception;

/**
 * DataGrid Widget: Allows to create datagrids with rows, columns and actions
 *
 * @version    5.0
 * @package    widget
 * @subpackage datagrid
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TDataGrid extends TTable
{
    protected $columns;
    protected $actions;
    protected $action_groups;
    protected $rowcount;
    protected $thead;
    protected $tbody;
    protected $height;
    protected $scrollable;
    protected $modelCreated;
    protected $pageNavigation;
    protected $defaultClick;
    protected $groupColumn;
    protected $groupContent;
    protected $groupMask;
    protected $popover;
    protected $poptitle;
    protected $popcontent;
    protected $objects;
    protected $actionWidth;
    protected $groupCount;
    protected $groupRowCount;
    protected $columnValues;
    
    /**
     * Class Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->modelCreated = false;
        $this->defaultClick = true;
        $this->popover = false;
        $this->groupColumn = null;
        $this->groupContent = null;
        $this->groupMask = null;
        $this->groupCount = 0;
        $this->actions = array();
        $this->action_groups = array();
        $this->actionWidth = '28px';
        $this->objects = array();
        $this->columnValues = array();
        $this->{'class'} = 'tdatagrid_table';
        $this->{'id'}    = 'tdatagrid_' . mt_rand(1000000000, 1999999999);
    }
    
    /**
     * Set id
     */
    public function setId($id)
    {
        $this->{'id'} = $id;
    }
    
    /**
     * Enable popover
     * @param $title Title
     * @param $content Content
     */
    public function enablePopover($title, $content)
    {
        $this->popover = true;
        $this->poptitle = $title;
        $this->popcontent = $content;
    }
    
    /**
     * Make the datagrid scrollable
     */
    public function makeScrollable()
    {
        $this->scrollable = true;
        
        if (isset($this->thead)) {
            $this->thead->style = 'display: block';
        }
    }
    
    /**
     * Returns if datagrid is scrollable
     */
    public function isScrollable()
    {
        return $this->scrollable;
    }
    
    /**
     * Returns true if has custom width
     * updated to protected by Dvi.DaviMenezes(davimenezes.dev@gmail.com)
     */
    protected function hasCustomWidth()
    {
        return ((strpos($this->getProperty('style'), 'width') !== false) or !empty($this->getProperty('width')));
    }
    
    /**
     * Set the column action width
     */
    public function setActionWidth($width)
    {
        $this->actionWidth = $width;
    }
    
    /**
     * disable the default click action
     */
    public function disableDefaultClick()
    {
        $this->defaultClick = false;
    }
    
    /**
     * Define the Height
     * @param $height An integer containing the height
     */
    public function setHeight($height)
    {
        $this->height = $height;
    }
    
    /**
     * Add a Column to the DataGrid
     * @param $object A TDataGridColumn object
     */
    public function addColumn(TDataGridColumn $object)
    {
        if ($this->modelCreated) {
            throw new Exception(AdiantiCoreTranslator::translate('You must call ^1 before ^2', __METHOD__, 'createModel'));
        } else {
            $this->columns[] = $object;
        }
    }
    
    /**
     * Returns an array of TDataGridColumn
     */
    public function getColumns()
    {
        return $this->columns;
    }
    
    /**
     * Add an Action to the DataGrid
     * @param $object A TDataGridAction object
     */
    public function addAction(TDataGridAction $action)
    {
        if (!$action->fieldDefined()) {
            throw new Exception(AdiantiCoreTranslator::translate('You must define the field for the action (^1)', $action->toString()));
        }
        
        if ($this->modelCreated) {
            throw new Exception(AdiantiCoreTranslator::translate('You must call ^1 before ^2', __METHOD__, 'createModel'));
        } else {
            $this->actions[] = $action;
        }
    }
    
    /**
     * Add an Action Group to the DataGrid
     * @param $object A TDataGridActionGroup object
     */
    public function addActionGroup(TDataGridActionGroup $object)
    {
        if ($this->modelCreated) {
            throw new Exception(AdiantiCoreTranslator::translate('You must call ^1 before ^2', __METHOD__, 'createModel'));
        } else {
            $this->action_groups[] = $object;
        }
    }
    
    /**
     * Returns the total columns
     */
    public function getTotalColumns()
    {
        return count($this->columns) + count($this->actions) + count($this->action_groups);
    }
    
    /**
     * Set the group column for break
     */
    public function setGroupColumn($column, $mask)
    {
        $this->groupColumn = $column;
        $this->groupMask   = $mask;
    }
    
    /**
     * Clear the DataGrid contents
     */
    public function clear($preserveHeader = true)
    {
        if ($this->modelCreated) {
            if ($preserveHeader) {
                // copy the headers
                $copy = $this->children[0];
                // reset the row array
                $this->children = array();
                // add the header again
                $this->children[] = $copy;
            } else {
                // reset the row array
                $this->children = array();
            }
            
            // add an empty body
            $this->tbody = new TElement('tbody');
            $this->tbody->{'class'} = 'tdatagrid_body';
            if ($this->scrollable) {
                $this->tbody->{'style'} = "height: {$this->height}px; display: block; overflow-y:scroll; overflow-x:hidden;";
            }
            parent::add($this->tbody);
            
            // restart the row count
            $this->rowcount = 0;
            $this->objects = array();
            $this->columnValues = array();
            $this->groupContent = null;
        }
    }
    
    /**
     * Creates the DataGrid Structure
     */
    public function createModel($create_header = true)
    {
        $request = Request::instance();

        if (!$this->columns) {
            return;
        }
        
        if ($create_header) {
            $this->thead = new TElement('thead');
            $this->thead->{'class'} = 'tdatagrid_head';
            parent::add($this->thead);
            
            $row = new TElement('tr');
            if ($this->scrollable) {
                $this->thead->{'style'} = 'display:block';
                if ($this->hasCustomWidth()) {
                    $row->{'style'} = 'display: inline-table; width: 100%';
                }
            }
            $this->thead->add($row);
            
            $actions_count = count($this->actions) + count($this->action_groups);
            
            if ($actions_count >0) {
                for ($n=0; $n < $actions_count; $n++) {
                    $cell = new TElement('th');
                    $row->add($cell);
                    $cell->add('<span style="width:calc('.$this->actionWidth.' - 2px);display:block"></span>');
                    $cell->{'class'} = 'tdatagrid_action';
                    $cell->{'style'} = 'padding:0';
                    $cell->{'width'} = $this->actionWidth;
                }
                
                $cell->{'class'} = 'tdatagrid_col';
            }
            
            // add some cells for the data
            if ($this->columns) {
                // iterate the DataGrid columns
                foreach ($this->columns as $column) {
                    // get the column properties
                    $name  = $column->getName();
                    $label = '&nbsp;'.$column->getLabel().'&nbsp;';
                    $align = $column->getAlign();
                    $width = $column->getWidth();
                    $props = $column->getProperties();
                    
                    if ($request->get('order')) {
                        if ($request->get('order') == $name) {
                            if ($request->get('direction') == 'asc') {
                                $label .= '<span class="glyphicon glyphicon-chevron-down blue" aria-hidden="true"></span>';
                            } else {
                                $label .= '<span class="glyphicon glyphicon-chevron-up blue" aria-hidden="true"></span>';
                            }
                        }
                    }
                    // add a cell with the columns label
                    $cell = new TElement('th');
                    $row->add($cell);
                    $cell->add($label);
                    
                    $cell->{'class'} = 'tdatagrid_col';
                    $cell->{'style'} = "text-align:$align;user-select:none";
                    
                    if ($props) {
                        foreach ($props as $prop_name => $prop_value) {
                            $cell->$prop_name = $prop_value;
                        }
                    }
                    
                    if ($width) {
                        $cell->{'width'} = (strpos($width, '%') !== false || strpos($width, 'px') !== false) ? $width : ($width + 8).'px';
                    }
                    
                    // verify if the column has an attached action
                    if ($column->getAction()) {
                        $action = $column->getAction();
                        if ($request->has('order') and $request->get('order') == $name) {
                            $array = ['asc' => 'desc', 'desc' => 'asc'];
                            $action->setParameter('direction', $array[$request->get('direction')]);
                        }
                        $url    = $action->serialize();
                        $cell->{'href'}        = $url;
                        $cell->{'style'}      .= ";cursor:pointer;";
                        $cell->{'generator'}   = 'adianti';
                    }
                }
                
                if ($this->scrollable) {
                    $cell = new TElement('td');
                    $cell->{'class'} = 'tdatagrid_col';
                    $row->add($cell);
                    $cell->add('<span style="width:20px;display:block"></span>');
                    $cell->{'style'} = 'padding:0';
                }
            }
        }
        
        // add one row to the DataGrid
        $this->tbody = new TElement('tbody');
        $this->tbody->{'class'} = 'tdatagrid_body';
        if ($this->scrollable) {
            $this->tbody->{'style'} = "height: {$this->height}px; display: block; overflow-y:scroll; overflow-x:hidden;";
        }
        parent::add($this->tbody);
        
        $this->modelCreated = true;
    }
    
    /**
     * Return thead
     */
    public function getHead()
    {
        return $this->thead;
    }
    
    /**
     * Return tbody
     */
    public function getBody()
    {
        return $this->tbody;
    }
    
    /**
     * insert content
     */
    public function insert($position, $content)
    {
        $this->tbody->insert($position, $content);
    }
    
    /**
     * Add objects to the DataGrid
     * @param $objects An array of Objects
     */
    public function addItems($objects)
    {
        if ($objects) {
            foreach ($objects as $object) {
                $this->addItem($object);
            }
        }
    }
    
    /**
     * Add an object to the DataGrid
     * @param $object An Active Record Object
     */
    public function addItem($object)
    {
        if ($this->modelCreated) {
            if ($this->groupColumn and
                (is_null($this->groupContent) or $this->groupContent !== $object->{$this->groupColumn})) {
                $row = new TElement('tr');
                $row->{'class'} = 'tdatagrid_group';
                $row->{'level'} = ++ $this->groupCount;
                $this->groupRowCount = 0;
                if ($this->isScrollable() and $this->hasCustomWidth()) {
                    $row->{'style'} = 'display: inline-table; width: 100%';
                }
                $this->tbody->add($row);
                $cell = new TElement('td');
                $cell->add($this->replace($this->groupMask, $object));
                $cell->colspan = count($this->actions)+count($this->action_groups)+count($this->columns);
                $row->add($cell);
                $this->groupContent = $object->{$this->groupColumn};
            }
            
            // define the background color for that line
            $classname = ($this->rowcount % 2) == 0 ? 'tdatagrid_row_even' : 'tdatagrid_row_odd';
            
            $row = new TElement('tr');
            $this->tbody->add($row);
            $row->{'class'} = $classname;
            
            if ($this->isScrollable() and $this->hasCustomWidth()) {
                $row->{'style'} = 'display: inline-table; width: 100%';
            }
            
            if ($this->groupColumn) {
                $this->groupRowCount ++;
                $row->{'childof'} = $this->groupCount;
                $row->{'level'}   = $this->groupCount . '.'. $this->groupRowCount;
            }
            
            if ($this->actions) {
                // iterate the actions
                foreach ($this->actions as $action_template) {
                    // validate, clone, and inject object parameters
                    $action = $action_template->prepare($object);
                    
                    // get the action properties
                    $label     = $action->getLabel();
                    $image     = $action->getImage();
                    $condition = $action->getDisplayCondition();
                    
                    if (empty($condition) or call_user_func($condition, $object)) {
                        $url       = $action->serialize();
                        $first_url = isset($first_url) ? $first_url : $url;
                        
                        // creates a link
                        $link = new TElement('a');
                        $link->{'href'}      = $url;
                        $link->{'generator'} = 'adianti';
                        
                        // verify if the link will have an icon or a label
                        if ($image) {
                            $image_tag = is_object($image) ? clone $image : new TImage($image);
                            $image_tag->{'title'} = $label;
                            
                            if ($action->getUseButton()) {
                                // add the label to the link
                                $span = new TElement('span');
                                $span->{'class'} = $action->getButtonClass() ? $action->getButtonClass() : 'btn btn-default';
                                $span->add($image_tag);
                                $span->add($label);
                                $link->add($span);
                            } else {
                                $link->add($image_tag);
                            }
                        } else {
                            // add the label to the link
                            $span = new TElement('span');
                            $span->{'class'} = $action->getButtonClass() ? $action->getButtonClass() : 'btn btn-default';
                            $span->add($label);
                            $link->add($span);
                        }
                    } else {
                        $link = '';
                    }
                    
                    // add the cell to the row
                    $cell = new TElement('td');
                    $row->add($cell);
                    $cell->add($link);
                    $cell->{'width'} = $this->actionWidth;
                    $cell->{'class'} = 'tdatagrid_cell action';
                }
            }
            
            if ($this->action_groups) {
                foreach ($this->action_groups as $action_group) {
                    $actions    = $action_group->getActions();
                    $headers    = $action_group->getHeaders();
                    $separators = $action_group->getSeparators();
                    
                    if ($actions) {
                        $dropdown = new TDropDown($action_group->getLabel(), $action_group->getIcon());
                        $last_index = 0;
                        foreach ($actions as $index => $action_template) {
                            $action = $action_template->prepare($object);
                            
                            // add intermediate headers and separators
                            for ($n=$last_index; $n<$index; $n++) {
                                if (isset($headers[$n])) {
                                    $dropdown->addHeader($headers[$n]);
                                }
                                if (isset($separators[$n])) {
                                    $dropdown->addSeparator();
                                }
                            }
                            
                            // get the action properties
                            $label  = $action->getLabel();
                            $image  = $action->getImage();
                            $condition = $action->getDisplayCondition();

                            if (empty($condition) or call_user_func($condition, $object)) {
                                $url       = $action->serialize();
                                $first_url = isset($first_url) ? $first_url : $url;
                                $dropdown->addAction($label, $action, $image);
                            }
                            $last_index = $index;
                        }
                        // add the cell to the row
                        $cell = new TElement('td');
                        $row->add($cell);
                        $cell->add($dropdown);
                        $cell->{'class'} = 'tdatagrid_cell action';
                    }
                }
            }
            
            if ($this->columns) {
                // iterate the DataGrid columns
                foreach ($this->columns as $column) {
                    // get the column properties
                    $name     = $column->getName();
                    $align    = $column->getAlign();
                    $width    = $column->getWidth();
                    $function = $column->getTransformer();
                    $props    = $column->getDataProperties();
                    
                    // calculated column
                    if (substr($name, 0, 1) == '=') {
                        $content = $this->replace($name, $object, 'float');
                        $content = str_replace('+', ' + ', $content);
                        $content = str_replace('-', ' - ', $content);
                        $content = str_replace('*', ' * ', $content);
                        $content = str_replace('/', ' / ', $content);
                        $content = str_replace('(', ' ( ', $content);
                        $content = str_replace(')', ' ) ', $content);
                        $parser = new Parser;
                        $content = $parser->evaluate(substr($content, 1));
                        $object->$name = $content;
                    } else {
                        try {
                            $content  = $object->$name;
                            
                            if (is_null($content)) {
                                $content = $this->replace($name, $object);
                                
                                if ($content === $name) {
                                    $content = '';
                                }
                            }
                        } catch (Exception $e) {
                            $content = $this->replace($name, $object);
                            
                            if (empty(trim($content)) or $content === $name) {
                                $content = $e->getMessage();
                            }
                        }
                    }
                    
                    if (isset($this->columnValues[$name])) {
                        $this->columnValues[$name][] = $content;
                    } else {
                        $this->columnValues[$name] = [$content];
                    }
                    
                    $data = is_null($content) ? '' : $content;
                    
                    // verify if there's a transformer function
                    if ($function) {
                        // apply the transformer functions over the data
                        $data = call_user_func($function, $data, $object, $row);
                    }
                    
                    if ($editaction = $column->getEditAction()) {
                        $editaction_field = $editaction->getField();
                        $div = new TElement('div');
                        $div->{'class'}  = 'inlineediting';
                        $div->{'style'}  = 'padding-left:5px;padding-right:5px';
                        $div->{'action'} = $editaction->serialize();
                        $div->{'field'}  = $name;
                        $div->{'key'}    = isset($object->{$editaction_field}) ? $object->{$editaction_field} : null;
                        $div->{'pkey'}   = $editaction_field;
                        $div->add($data);
                        $cell = new TElement('td');
                        $row->add($cell);
                        $cell->add($div);
                        $cell->{'class'} = 'tdatagrid_cell';
                    } else {
                        // add the cell to the row
                        $cell = new TElement('td');
                        $row->add($cell);
                        $cell->add($data);
                        $cell->{'class'} = 'tdatagrid_cell';
                        $cell->{'align'} = $align;
                        
                        if (isset($first_url) and $this->defaultClick) {
                            $cell->{'href'}      = $first_url;
                            $cell->{'generator'} = 'adianti';
                            $cell->{'class'}     = 'tdatagrid_cell';
                        }
                    }
                    
                    if ($props) {
                        foreach ($props as $prop_name => $prop_value) {
                            $cell->$prop_name = $prop_value;
                        }
                    }
                    
                    if ($width) {
                        $cell->{'width'} = (strpos($width, '%') !== false || strpos($width, 'px') !== false) ? $width : ($width + 8).'px';
                    }
                }
            }
            
            if ($this->popover) {
                $poptitle   = $this->poptitle;
                $popcontent = $this->popcontent;
                $poptitle   = $this->replace($poptitle, $object);
                $popcontent = $this->replace($popcontent, $object);
                
                // replace methods
                $methods = get_class_methods($object);
                if ($methods) {
                    foreach ($methods as $method) {
                        if (stristr($popcontent, "{$method}()") !== false) {
                            $popcontent = str_replace('{'.$method.'()}', $object->$method(), $popcontent);
                        }
                    }
                }
                $row->{'popover'} = 'true';
                $row->{'poptitle'} = $poptitle;
                $row->{'popcontent'} = htmlspecialchars(str_replace("\n", '', nl2br($popcontent)));
            }
            
            $this->objects[ $this->rowcount ] = $object;
            
            // increments the row counter
            $this->rowcount ++;
            
            return $row;
        } else {
            throw new Exception(AdiantiCoreTranslator::translate('You must call ^1 before ^2', 'createModel', __METHOD__));
        }
    }
    
    /**
     * Return datagrid items
     */
    public function getItems()
    {
        return $this->objects;
    }
    
    /**
     * Process column totals
     * updated to protected by Dvi.DaviMenezes (davimenezes.dev@gmail.com)
     */
    protected function processTotals()
    {
        if (count($this->objects) == 0) {
            return;
        }
        
        $has_total = false;
        
        $tfoot = new TElement('tfoot');
        $tfoot->{'class'} = 'tdatagrid_footer';
        
        if ($this->scrollable) {
            $tfoot->{'style'} = "display: block";
        }
        
        $row = new TElement('tr');
        
        if ($this->isScrollable() and $this->hasCustomWidth()) {
            $row->{'style'} = 'display: inline-table; width: 100%';
        }
        $tfoot->add($row);
        
        if ($this->actions) {
            // iterate the actions
            foreach ($this->actions as $action) {
                $cell = new TElement('td');
                $row->add($cell);
            }
        }
        
        if ($this->action_groups) {
            foreach ($this->action_groups as $action_group) {
                $cell = new TElement('td');
                $row->add($cell);
            }
        }
        
        if ($this->columns) {
            // iterate the DataGrid columns
            foreach ($this->columns as $column) {
                $cell = new TElement('td');
                $row->add($cell);
                
                // get the column total function
                $totalFunction = $column->getTotalFunction();
                $transformer   = $column->getTransformer();
                $name          = $column->getName();
                $align         = $column->getAlign();
                $width         = $column->getWidth();
                $cell->{'style'} = "text-align:$align";
                
                if ($width) {
                    $cell->{'width'} = (strpos($width, '%') !== false || strpos($width, 'px') !== false) ? $width : ($width + 8).'px';
                }
                
                if ($totalFunction) {
                    $has_total = true;
                    $content   = $totalFunction($this->columnValues[$name]);
                    
                    if ($transformer) {
                        // apply the transformer functions over the data
                        $content = call_user_func($transformer, $content, null, null);
                    }
                    $cell->add($content);
                } else {
                    $cell->add('&nbsp;');
                }
            }
        }
        
        if ($has_total) {
            parent::add($tfoot);
        }
    }
    
    /**
     * Replace a string with object properties within {pattern}
     * @param $content String with pattern
     * @param $object  Any object
     */
    private function replace($content, $object, $cast = null)
    {
        if (preg_match_all('/\{(.*?)\}/', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $property = substr($match, 1, -1);
                $value    = $object->$property;
                if ($cast) {
                    settype($value, $cast);
                }
                
                $content  = str_replace($match, $value, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Find the row index by object attribute
     * @param $attribute Object attribute
     * @param $value Object value
     */
    public function getRowIndex($attribute, $value)
    {
        foreach ($this->objects as $pos => $object) {
            if ($object->$attribute == $value) {
                return $pos;
            }
        }
        return null;
    }
    
    /**
     * Return the row by position
     * @param $position Row position
     */
    public function getRow($position)
    {
        return $this->tbody->get($position);
    }
    
    /**
     * Returns the DataGrid's width
     * @return An integer containing the DataGrid's width
     */
    public function getWidth()
    {
        $width=0;
        if ($this->actions) {
            // iterate the DataGrid Actions
            foreach ($this->actions as $action) {
                $width += 22;
            }
        }
        
        if ($this->columns) {
            // iterate the DataGrid Columns
            foreach ($this->columns as $column) {
                if (is_numeric($column->getWidth())) {
                    $width += $column->getWidth();
                }
            }
        }
        return $width;
    }
    
    /**
     * Shows the DataGrid
     */
    public function show()
    {
        $this->processTotals();
        
        if (!$this->hasCustomWidth()) {
            $this->{'style'} .= ';width:unset';
        }
        
        // shows the datagrid
        parent::show();
        
        $params = $_REQUEST;
        unset($params['class']);
        unset($params['method']);
        // to keep browsing parameters (order, page, first_page, ...)
        $urlparams='&'.http_build_query($params);
        
        // inline editing treatment
        TScript::create(" tdatagrid_inlineedit( '{$urlparams}' );");
        TScript::create(" tdatagrid_enable_groups();");
    }
    
    /**
     * Assign a PageNavigation object
     * @param $pageNavigation object
     */
    public function setPageNavigation($pageNavigation)
    {
        $this->pageNavigation = $pageNavigation;
    }
    
    /**
     * Return the assigned PageNavigation object
     * @return $pageNavigation object
     */
    public function getPageNavigation()
    {
        return $this->pageNavigation;
    }
}
