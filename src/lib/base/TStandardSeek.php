<?php
namespace Adianti\Base\Lib\Base;

use Adianti\Base\Lib\Control\TWindow;
use Adianti\Base\Lib\Core\AdiantiApplicationConfig;
use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Adianti\Base\Lib\Database\TCriteria;
use Adianti\Base\Lib\Database\TFilter;
use Adianti\Base\Lib\Database\TRepository;
use Adianti\Base\Lib\Database\TTransaction;
use Adianti\Base\Lib\Registry\TSession;
use Adianti\Base\Lib\Widget\Container\TPanelGroup;
use Adianti\Base\Lib\Widget\Container\TTable;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Base\Lib\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Base\Lib\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Control\TAction;
use Dvi\Adianti\Helpers\Reflection;
use Exception;
use StdClass;

/**
 * Standard Page controller for Seek buttons
 *
 * @version    5.5
 * @package    base
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TStandardSeek extends TWindow
{
    private $form;      // search form
    private $datagrid;  // listing
    private $pageNavigation;
    private $parentForm;
    private $loaded;
    private $items;

    /**
     * Constructor Method
     * Creates the page, the search form and the listing
     */
    public function __construct()
    {
        parent::__construct();
        parent::setTitle(\Adianti\Core\AdiantiCoreTranslator::translate('Search record'));
        parent::setSize(0.7, null);

        // creates a new form
        $this->form = new TForm('form_standard_seek');
        // creates a new table
        $table = new TTable;
        $table->{'width'} = '100%';
        // adds the table into the form
        $this->form->add($table);

        // create the form fields
        $display_field= new TEntry('display_field');
        $display_field->setSize('90%');

        // keeps the field's value
        $display_field->setValue(TSession::getValue('tstandardseek_display_value'));

        // create the action button
        $find_button = new TButton('busca');
        // define the button action
        $find_action = new TAction(array($this, 'onSearch'));
        $find_action->setParameter('register_state', 'false');
        $find_button->setAction($find_action, AdiantiCoreTranslator::translate('Search'));
        $find_button->setImage('fa:search blue');

        // add a row for the filter field
        $table->addRowSet(new TLabel(_t('Search').': '), $display_field, $find_button);

        // define wich are the form fields
        $this->form->setFields(array($display_field, $find_button));

        // creates a new datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->{'style'} = 'width: 100%';

        // creates the paginator
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction(array($this, 'onReload')));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        $panel = new TPanelGroup($this->form);
        $panel->{'style'} = 'width: 100%;margin-bottom:0;border-radius:0';
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        // add the container to the page
        parent::add($panel);
    }

    /**
     * Render datagrid
     */
    public function render()
    {
        // create two datagrid columns
        $id      = new TDataGridColumn('id', 'Id', 'center', '50');
        $display = new TDataGridColumn('display_field', TSession::getValue('standard_seek_label'), 'left');

        // add the columns to the datagrid
        $this->datagrid->addColumn($id);
        $this->datagrid->addColumn($display);

        // order by PK
        $order_id = new TAction([$this, 'onReload']);
        $order_id->setParameter('order', 'id');
        $id->setAction($order_id);

        // order by Display field
        $order_display = new TAction([$this, 'onReload']);
        $order_display->setParameter('order', 'display_field');
        $display->setAction($order_display);

        // create a datagrid action
        $action1 = new TDataGridAction(array($this, 'onSelect'));
        $action1->setLabel('');
        $action1->setImage('fa:hand-pointer-o green');
        $action1->setUseButton(true);
        $action1->setButtonClass('nopadding');
        $action1->setField('id');

        // add the actions to the datagrid
        $this->datagrid->addAction($action1);

        // create the datagrid model
        $this->datagrid->createModel();
    }

    /**
     * Fill datagrid
     */
    public function fill()
    {
        $this->datagrid->clear();
        if ($this->items) {
            foreach ($this->items as $item) {
                $this->datagrid->addItem($item);
            }
        }
    }

    /**
     * Search datagrid
     */
    public function onSearch()
    {
        // get the form data
        $data = $this->form->getData();

        // check if the user has filled the form
        if (isset($data-> display_field) and ($data-> display_field)) {
            $operator = TSession::getValue('standard_seek_operator');

            // creates a filter using the form content
            $display_field = TSession::getValue('standard_seek_display_field');
            $filter = new TFilter($display_field, $operator, "%{$data-> display_field}%");

            // store the filter in section
            TSession::setValue('tstandardseek_filter', $filter);
            TSession::setValue('tstandardseek_display_value', $data-> display_field);
        } else {
            TSession::setValue('tstandardseek_filter', null);
            TSession::setValue('tstandardseek_display_value', '');
        }

        TSession::setValue('tstandardseek_filter_data', $data);

        // set the data back to the form
        $this->form->setData($data);

        $param=array();
        $param['offset']    =0;
        $param['first_page']=1;
        $this->onReload($param);
    }

    /**
     * Load the datagrid with objects
     */
    public function onReload($param = null)
    {
        try {
            $model    = TSession::getValue('standard_seek_model');
            $database = TSession::getValue('standard_seek_database');
            $display_field = TSession::getValue('standard_seek_display_field');

            $pk   = constant("{$model}::PRIMARYKEY");

            // begins the transaction with database
            TTransaction::open($database);

            // creates a repository for the model
            $repository = new TRepository($model);
            $limit = 10;

            // creates a criteria
            if (TSession::getValue('standard_seek_criteria')) {
                $criteria = clone TSession::getValue('standard_seek_criteria');
            } else {
                $criteria = new TCriteria;

                // default order
                if (empty($param['order'])) {
                    $param['order'] = $pk;
                    $param['direction'] = 'asc';
                }
            }

            if ($param['order'] == 'display_field') {
                $param['order'] = $display_field;
            }

            $criteria->setProperties($param); // order, offset
            $criteria->setProperty('limit', $limit);

            if (TSession::getValue('tstandardseek_filter')) {
                // add the filter to the criteria
                $criteria->add(TSession::getValue('tstandardseek_filter'));
            }

            // load all objects according with the criteria
            $objects = $repository->load($criteria, false);
            if ($objects) {
                foreach ($objects as $object) {
                    $item = $object;
                    $item->{'id'} = $object->$pk;

                    if (!empty(TSession::getValue('standard_seek_mask'))) {
                        $item->{'display_field'} = $object->render(TSession::getValue('standard_seek_mask'));
                    } else {
                        $item->{'display_field'} = $object->$display_field;
                    }

                    $this->items[] = $item;
                }
            }

            // clear the crieteria to count the records
            $criteria->resetProperties();
            $count= $repository->count($criteria);

            $this->pageNavigation->setCount($count); // count of records
            $this->pageNavigation->setProperties($param); // order, page
            $this->pageNavigation->setLimit($limit); // limit

            // closes the transaction
            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) { // in case of exception
            // shows the exception genearated message
            new \Adianti\Widget\Dialog\TMessage('error', $e->getMessage());
            // rollback all the database operations
            TTransaction::rollback();
        }
    }

    /**
     * Setup seek parameters
     */
    public function onSetup($param=null)
    {
        $ini  = \Adianti\Core\AdiantiApplicationConfig::get();
        $seed = APPLICATION_NAME . (!empty($ini['general']['seed']) ? $ini['general']['seed'] : 's8dkld83kf73kf094');

        if (isset($param['hash']) and $param['hash'] == md5($seed.$param['database'].$param['model'].$param['display_field'])) {
            // store the parameters in the section
            TSession::setValue('tstandardseek_filter', null);
            TSession::setValue('tstandardseek_display_value', null);
            TSession::setValue('standard_seek_receive_key', $param['receive_key']);
            TSession::setValue('standard_seek_receive_field', $param['receive_field']);
            TSession::setValue('standard_seek_display_field', $param['display_field']);
            TSession::setValue('standard_seek_model', $param['model']);
            TSession::setValue('standard_seek_database', $param['database']);
            TSession::setValue('standard_seek_parent', $param['parent']);
            TSession::setValue('standard_seek_operator', $param['operator']);
            TSession::setValue('standard_seek_mask', $param['mask']);
            TSession::setValue('standard_seek_label', $param['label']);

            if (isset($param['criteria']) and $param['criteria']) {
                TSession::setValue('standard_seek_criteria', unserialize(base64_decode($param['criteria'])));
            }
            $this->onReload();
        }
    }

    /**
     * Send the selected register to parent form
     */
    public static function onSelect($param)
    {
        $key = $param['key'];
        $database      = isset($param['database'])      ? $param['database']      : TSession::getValue('standard_seek_database');
        $receive_key   = isset($param['receive_key'])   ? $param['receive_key']   : TSession::getValue('standard_seek_receive_key');
        $receive_field = isset($param['receive_field']) ? $param['receive_field'] : TSession::getValue('standard_seek_receive_field');
        $display_field = isset($param['display_field']) ? $param['display_field'] : TSession::getValue('standard_seek_display_field');
        $parent        = isset($param['parent'])        ? $param['parent']        : TSession::getValue('standard_seek_parent');
        $seek_mask     = isset($param['mask'])          ? $param['mask']          : TSession::getValue('standard_seek_mask');

        try {
            TTransaction::open($database);
            // load the active record
            $model = isset($param['model']) ? $param['model'] : TSession::getValue('standard_seek_model');
            $activeRecord = new $model($key);

            $pk = constant("{$model}::PRIMARYKEY");

            $object = new StdClass;
            $object->$receive_key   = isset($activeRecord->$pk) ? $activeRecord->$pk : '';

            if (!empty($seek_mask)) {
                $object->$receive_field = $activeRecord->render($seek_mask);
            } else {
                $object->$receive_field = isset($activeRecord->$display_field) ? $activeRecord->$display_field : '';
            }

            TTransaction::close();

            TForm::sendData($parent, $object);
            parent::closeWindow(); // closes the window
        } catch (Exception $e) { // in case of exception
            // clear fields
            $object = new StdClass;
            $object->$receive_key   = '';
            $object->$receive_field = '';
            TForm::sendData($parent, $object);

            // undo all pending operations
            TTransaction::rollback();
        }
    }

    /**
     * Show page
     */
    public function show()
    {
        parent::setIsWrapped(true);
        $this->run();
        $this->render();
        $this->fill();
        parent::show();
    }
}
