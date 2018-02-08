<?php
namespace Adianti\Base\Modules\Admin\Model;

use Adianti\Base\Lib\Database\TRecord;

/**
 * SystemUserGroup
 *
 * @version    1.0
 * @package    model
 * @subpackage admin
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class SystemUserGroup extends TRecord
{
    const TABLENAME = 'sys_user_group';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}
    
    
    /**
     * Constructor method
     */
    public function __construct($id = null)
    {
        parent::__construct($id);
        parent::addAttribute('system_user_id');
        parent::addAttribute('system_group_id');
    }
}
