<?php

namespace Osp\Model;

use Zend\InputFilter\Factory as InputFactory;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterInterface;

use Zend\Db\Sql\Sql,
    Zend\Db\Adapter\Adapter;
 
    public function getList($adapter, $search_email, $search_lastname, $search_firstname, $search_login, $search_id, $search_group) {
        $sql = new Sql($adapter);
        $select = $sql->select()->columns(array('Checked', 'Email', 'Login', 'LastName', 'FirstName', 'User_ID'))->from('User')->order('User_ID DESC');

        // Поиск

        // По почте
        // $search_email = $this->params()->fromQuery('search_email');
        if (isset($search_email) && $search_email != '') {
            $where = new \Zend\Db\Sql\Where();
            $where->addPredicate(
                new \Zend\Db\Sql\Predicate\Like('Email', '%'.$search_email.'%')
            );
            $select->where($where);
        }
        
        // По фамилии
        // $search_lastname = $this->params()->fromQuery('search_lastname');
        if (isset($search_lastname) && $search_lastname != '') {
            $where = new \Zend\Db\Sql\Where();
            $where->addPredicate(
                new \Zend\Db\Sql\Predicate\Like('LastName', '%'.$search_lastname.'%')
            );
            $select->where($where);
        }

        // По имени
        // $search_firstname = $this->params()->fromQuery('search_firstname');
        if (isset($search_firstname) && $search_firstname != '') {
            $where = new \Zend\Db\Sql\Where();
            $where->addPredicate(
                new \Zend\Db\Sql\Predicate\Like('FirstName', '%'.$search_firstname.'%')
            );
            $select->where($where);
        }

        // По логину
        // $search_login = $this->params()->fromQuery('search_login');
        if (isset($search_login) && $search_login != '') {
            $where = new \Zend\Db\Sql\Where();
            $where->addPredicate(
                new \Zend\Db\Sql\Predicate\Like('Login', '%'.$search_login.'%')
            );
            $select->where($where);
        }

        // По ID
        // $search_id = $this->params()->fromQuery('search_id');
        if (isset($search_id) && $search_id != '') {
            $where = new \Zend\Db\Sql\Where();
            $where->addPredicate(
                new \Zend\Db\Sql\Predicate\Like('User_ID', '%'.$search_id.'%')
            );
            $select->where($where);
        }

        // По группам 
        // $search_group = $this->params()->fromQuery('search_group');
        if (isset($search_group) && $search_group != '') {
            $select->join('User_Group', 'User_Group.User_ID  = User.User_ID', array('PermissionGroup_ID'))->join('PermissionGroup', 'PermissionGroup.PermissionGroup_ID = User_Group.PermissionGroup_ID', array('PermissionGroup_Name'));

            $where = new \Zend\Db\Sql\Where();
            $where->addPredicate(
                new \Zend\Db\Sql\Predicate\Like('PermissionGroup_Name', '%'.$search_group.'%')
            );
            $select->where($where);
        }

        return $select;
    }

    // Функция возвращающая список групп для пользователя. 
    public function getGroups($id, $adapter)
    {
        $sql = new Sql($adapter);
        $select = $sql->select()->columns(array('PermissionGroup_ID'))->from('User_Group')->where(array('User_ID' => $id));
        $select->join('PermissionGroup', 'PermissionGroup.PermissionGroup_ID = User_Group.PermissionGroup_ID', array('PermissionGroup_Name'));

        $sqlString = $sql->getSqlStringForSqlObject($select);
        $results = $adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        return $results->toArray();
    }