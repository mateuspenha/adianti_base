<?php
namespace Adianti\Base\Modules\Communication\Model;

use Adianti\Base\Lib\Database\TRecord;

/**
 * SystemNotification
 *
 * @version    1.0
 * @package    model
 * @subpackage communication
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class SystemNotification extends TRecord
{
    const TABLENAME = 'sys_notification';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}
    
    
    /**
     * Constructor method
     */
    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('system_user_id');
        parent::addAttribute('system_user_to_id');
        parent::addAttribute('subject');
        parent::addAttribute('message');
        parent::addAttribute('dt_message');
        parent::addAttribute('action_url');
        parent::addAttribute('action_label');
        parent::addAttribute('icon');
        parent::addAttribute('checked');
    }

    /**
     * Register notification
     */
    public static function register($user_to, $subject, $message, $action, $label, $icon = null)
    {
        TTransaction::open('communication');
        $object = new self;
        $object->system_user_id    = TSession::getValue('userid');
        $object->system_user_to_id = $user_to;
        $object->subject           = $subject;
        $object->message           = $message;
        $object->dt_message        = date("Y-m-d H:i:s");
        $object->action_url        = $action;
        $object->action_label      = $label;
        $object->icon              = $icon;
        $object->checked           = 'N';
        $object->store();
        TTransaction::close();
    }
}
