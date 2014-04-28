<?php
namespace Osp\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Osp\Model\User;

use Zend\Db\Sql\Sql,
    Zend\Db\Adapter\Adapter;

class ListController extends AbstractActionController
{
    protected $usersTable;
    
    protected $_uTable;

    /**
     * getUsersTable() Метод получения модели пользователей
     * 
     * @return object Объект таблицы пользователей
     */
    public function getUsersTable() {
        if(!$this->usersTable) {
            $sm = $this->getServiceLocator();
            $this->usersTable = $sm->get('Osp\Model\UsersTable');
        }
        
        return $this->usersTable;
    }

    public function indexAction()
    {
        $user = $this->identity();
        // если не было входа - редирект на страницу /user
        if(!$user) {
            return $this->redirect()->toRoute('user', array(
                'controller' => 'user',
                'action'
            ));
        }

        if (!$this->isAdmin($user->Email)) {
            // Если пользователь не является членом группы "Администраторы", делаем выход
            $auth = new AuthenticationService();
            if($auth->hasIdentity()) {
                $auth->clearIdentity();
            }
        }

        $adapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $user_model = new User();
        $select = $user_model->getList($adapter,
                                    $this->params()->fromQuery('search_email'),
                                    $this->params()->fromQuery('search_lastname'),
                                    $this->params()->fromQuery('search_firstname'),
                                    $this->params()->fromQuery('search_login'),
                                    $this->params()->fromQuery('search_id'),
                                    $this->params()->fromQuery('search_group'));

        $paginator = new \Zend\Paginator\Paginator(new \Zend\Paginator\Adapter\DbSelect($select, $adapter));
        $paginator->setCurrentPageNumber((int) $this->params()->fromQuery('page', 1));
        $paginator->setItemCountPerPage(20);

        $users = array();
        foreach ($paginator as $item)
        {
            $user = $item->getArrayCopy();
            // Дополнить списком групп
            $user['Groups'] = $user_model->getGroups($user['User_ID'], $adapter);
            $users[] = $user;
        }

        return new ViewModel(array(
            'paginator' => $paginator,
            'users' => $users,
        ));
    }


    public function isAdmin($email)
    {
        $sm = $this->getServiceLocator();
        $db = $sm->get('Zend\Db\Adapter\Adapter');

        $id = $this->getUserData($email, 'User_ID');
        $sql = new Sql($db);
        $select = $sql->select()->from('User_Group')->where(array('User_ID' => $id, 'PermissionGroup_ID' => 1));

        $sqlString = $sql->getSqlStringForSqlObject($select);
        $results = $db->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $array = $results->toArray();

        if (!empty($array)) {
            return true;
        } else {
            return false;
        }
    }

    public function getUserData($email, $field)
    {
        $adapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($adapter);
        $select = $sql->select()->columns(array($field))->from('User');
        $select->where(array('Email' => $email));

        $sqlString = $sql->getSqlStringForSqlObject($select);
        $results = $adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $r = $results->toArray();
        if (isset($r[0][$field])) {
            return $r[0][$field];
        } else {
            return '';
        }
    }

}