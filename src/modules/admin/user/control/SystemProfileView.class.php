<?php
namespace Adianti\Base\Modules\Admin\User\Control;

use Adianti\Base\Lib\Control\TPage;
use Adianti\Base\Lib\Database\TTransaction;
use Adianti\Base\Lib\Registry\TSession;
use Adianti\Base\Lib\Widget\Container\TVBox;
use Adianti\Base\Lib\Widget\Dialog\TMessage;
use Adianti\Base\Lib\Widget\Template\THtmlRenderer;
use Adianti\Base\Modules\Admin\User\Model\SystemUser;
use Exception;

/**
 * SystemProfileView
 *
 * @version    1.0
 * @package    control
 * @subpackage admin
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class SystemProfileView extends TPage
{
    public function __construct()
    {
        parent::__construct();
        
        $html = new THtmlRenderer('app/resources/system_profile.html');
        $replaces = array();
        
        try {
            TTransaction::open('permission');
            
            $user= SystemUser::newFromLogin(TSession::getValue('login'));
            $replaces = $user->toArray();
            $replaces['frontpage'] = $user->frontpage_name;
            $replaces['groupnames'] = $user->getSystemUserGroupNames();
            $replaces['urlbase'] = URLBASE;

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
        
        $html->enableSection('main', $replaces);
        $html->enableTranslation();
        
        $container = TVBox::pack($html);
        $container->style = 'width:80%';
        parent::add($container);
    }
}
