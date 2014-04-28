<?php

namespace Osp\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend\Db\Sql\Sql;
use Zend\Db\Adapter\Adapter;

class ApiController extends AbstractActionController
{

    public function createAction()
    {
        $adapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($adapter);

        $arr = array(); // Массив с результатами.

        // Получение данных с POST.
        $reg = $this->params()->fromPost();

        $view = new ViewModel();
        $view->setTerminal(true);

        // Вывести ошибку, если не заданы основные поля.
        if (!isset($reg['Login'], $reg['Email'], $reg['Password'])) {
            $arr['result'] = false;
            $arr['comment'] = 'Не заполнены основные поля: логин, почта, пароль.';
            echo json_encode($arr); 
            return $view;
        }
        // Вывести ошибку для неверной почты
        $email = $reg['Email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $arr['result'] = false;
            $arr['comment'] = 'Неверный формат почты.';
            echo json_encode($arr); 
            return $view;
        }
        // Вывести ошибку, если одно из полей пустое
        if ($reg['Login'] == '' || $reg['Email'] == '' || $reg['Password'] == '') {
            $arr['result'] = false;
            $arr['comment'] = 'Основные поля не могут быть пустыми.';
            echo json_encode($arr);
            return $view;
        }
        // Вывести ошибку, если такая почта уже используется
        $select = $sql->select()->columns(array('User_ID'))->from('User');
        $select->where(array('Email' => $email));
            
        $sqlString = $sql->getSqlStringForSqlObject($select);
        $results = $adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $email_array = $results->toArray();
        if (!empty($email_array)) {
            $arr['result'] = false;
            $arr['comment'] = 'Такая почта уже используется.';
            echo json_encode($arr);
            return $view;
        }

        // Захешировать пароль, предварительно сохранив егов переменной. (для отправки письма)
        $password = $reg['Password'];
        $reg['Password'] = mysql_hash($reg['Password']);

        // Активировать пользователя
        if (!isset($reg['Checked'])) {
            $reg['Checked'] = 1;
        }

        // заполнять при создании пользователя поле Created и PermissionGroup_ID
        $date = new \DateTime();
        $reg['Created'] = $date->format('Y-m-d H:i:s');
        $reg['PermissionGroup_ID'] = 2;
        // заполнять при создании пользователя come_from 
        $reg['come_from'] = $this->getRequest()->getServer('HTTP_REFERER');

        // Удалить поле send_email, если оно задано, сохранив в переменной
        if (isset($reg['send_email'])) {
            $send_email = true;
            unset($reg['send_email']);
        }

        // Добавить запись о новом пользователе
        $insert = $sql->insert('User');
        $insert->values($reg);
        $sqlString = $sql->getSqlStringForSqlObject($insert);
        $results = $adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        // Получить ID пользователя
        $msgID = $adapter->getDriver()->getConnection()->getLastGeneratedValue();

        if (isset($msgID) && $msgID > 0) {
            // Добавить запись с группой пользователя. 
            $insert = $sql->insert('User_Group');
            $insert->values(array('User_ID' => $msgID, 'PermissionGroup_ID' => 2));
            $sqlString = $sql->getSqlStringForSqlObject($insert);
            $results = $adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

            $arr['result'] = $msgID;
            $arr['comment'] = 'Пользователь зарегистрирован.';
            

            // При необходимость отправитть письмо с логином и паролем.
            if ($send_email) {
                /*
                ** Получить код шаблона
                */
                $sm = $this->getServiceLocator();
                $db = $sm->get('Zend\Db\Adapter\Adapter');

                $sql = new Sql($db);
                $id = 18; // номер шаблона
                $select = $sql->select()->from('aecms_mail_templates')->where(array('id' => $id));

                $sqlString = $sql->getSqlStringForSqlObject($select);
                $results = $db->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

                $array = $results->toArray();
                if (!empty($array[0]['text'])) {
                    $text = $array[0]['text'];
                    $text = str_replace('%%password%%', $password, $text);
                    $text = str_replace('%%login%%', $reg['Login'], $text);
                    $text = str_replace('%%email%%', $reg['Email'], $text);
                }

                /*
                ** Отправить письмо. 
                */

                $insert = $sql->insert('aecms_mail_send');
                $date = new \DateTime();
                $created = $date->format('Y-m-d H:i:s');
                $insert->values(array('subject' => $array[0]['subject'], 'html' => $text, 'priority' => 1, 'created' => $created));

                $sqlString = $sql->getSqlStringForSqlObject($insert);
                $results = $db->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

                $mailID = $db->getDriver()->getLastGeneratedValue();

                $insert = $sql->insert('aecms_mail_send_queue');
                $insert->values(array('mailID' => $mailID, 'parentID' => 0, 'queuePriority' => 1, 'toEmail' => $reg['Email'], 'fromEmail' => 'noreply@osp.ru',
                                      'toID' => $msgID, 'fromID' => 0, 'sendAsHtml' => 1, 'created' => $created));

                $sqlString = $sql->getSqlStringForSqlObject($insert);
                $results = $db->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

                // Дописать в текст ответа про успешную отправку письма
                $arr['comment'] .= ' На почту было отправлено письмо с логином/паролем';
            }
            echo json_encode($arr);
        } else {
            $arr['result'] = false;
            $arr['comment'] = 'Неизвестная ошибка.';
            echo json_encode($arr);
        }

        return $view;
    }

}