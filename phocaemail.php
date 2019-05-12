<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  User.profile
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\Utilities\ArrayHelper;
require_once( JPATH_ADMINISTRATOR.'/components/com_phocaemail/helpers/phocaemail.php' );
/**
 * An example custom profile plugin.
 *
 * @since  1.6
 */
class PlgUserPhocaemail extends JPlugin
{

    /**
     * Date of birth.
     *
     * @var    string
     * @since  3.1
     */
    private $date = '';

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor
     *
     * @param   object  &$subject  The object to observe
     * @param   array   $config    An array that holds the plugin configuration
     *
     * @since   1.5
     */
    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
    }

    /**
     * Runs on content preparation
     *
     * @param   string  $context  The context for the data
     * @param   object  $data     An object containing the data for the form.
     *
     * @return  boolean
     *
     * @since   1.6
     */
    public function onContentPrepareData($context, $data)
    {
        // Check we are manipulating a valid form.



        return true;
    }

    /**
     * Adds additional fields to the user editing form
     *
     * @param   Form   $form  The form to be altered.
     * @param   mixed  $data  The associated data for the form.
     *
     * @return  boolean
     *
     * @since   1.6
     */
    public function onContentPrepareForm(Form $form, $data)
    {
        // Check we are manipulating a valid form.
        // com_users.registration cuando esta registrado.
        // com_users.user es cuando estas editando en administrador.
        // com_users.profile cuando ver perfil o editas en parte site.
        $respuesta = array();
        $name = $form->getName();
        if (!in_array($name, array('com_users.user', 'com_users.profile', 'com_users.registration')))
		{
            return true;
        }
        // Obtenemos parametros para saber que mostramos.
        $check_subcripcion= $this->params->get('register-phocaemail_subcripcion');
        $lista_subcripcion= $this->params->get('register-phocaemail_listas_subcripcion');

        // Cargamos form de plugin.
        JForm::addFormPath(__DIR__ . '/phocaemail');
        $form->loadFile('phocaemail');

        // Ahora debería consultar cuantas listas de subscripcion hay.
        /* De momento no lo hago..
          $results = $this->listasPhocaemail();
         */
        if ( $name === 'com_users.registration'){
            // Ahora ponemos el valor por defecto que tenemos en parametros a la hora hacer registro.
            $form->setValue('suscripcion','phocaemail',$check_subcripcion);
        }

        if ($name === 'com_users.profile' || $name === 'com_users.user') {
            $userId = $data->id;
            $r = $this->SiexisteSubcripcion($userId,'userId');
            if (count($r) ===1){
                // Hubo un resultado 
                $active = intval($r['0']->active);
                // Active puede tener 0 -> No activado, 1-> Subcripto, 2 -> Cancelo subcripcion.
                    $form->setValue('suscripcion','phocaemail',$active);
            }
        }

        return true;
    }

    /**
     * Method is called before user data is stored in the database
     *
     * @param   array    $user   Holds the old user data.
     * @param   boolean  $isnew  True if a new user is stored.
     * @param   array    $data   Holds the new user data.
     *
     * @return  boolean
     *
     * @since   3.1
     * @throws  InvalidArgumentException on invalid date.
     */
    //~ public function onUserBeforeSave($user, $isnew, $data)
    //~ {
    //~ // Check that the date is valid.
    //~ error_log('Entro en onUserBeforeSave');
    //~ return true;
    //~ }

    /**
     * Saves user profile data
     *
     * @param   array    $data    entered user data
     * @param   boolean  $isNew   true if this is a new user
     * @param   boolean  $result  true if saving the user worked
     * @param   string   $error   error message
     *
     * @return  boolean
     */
    public function onUserAfterSave($data, $isNew, $result, $error)
    {
        $userId = ArrayHelper::getValue($data, 'id', 0, 'int');
		if ($userId && $result )
		{
            // Compruebo si existe email.
            $r = $this->SiexisteSubcripcion($data['email'],'email');
            // Si acepto subcribirse entonces lo buscamos primero por si ya estaba subcripto.    
            if (count($r) ===1){
                // Hubo un resultado 
                $id = intval($r['0']->id);
                if ($id > 0 ){
                    // Comprobamos que tengamos valor suscripcion ya otras extensiones que utilizan registro y
                    // puede que no carguen el valor phocaemail.
                    if (isset($data['phocaemail']['suscripcion'])){
                        // Si existe email en subscripcion, añadimos usuario de Joomla creado y si esta o no subcripto.
                        $r2= $this->UpdateUsuarioJoomlaNews($userId,$id,$data['phocaemail']['suscripcion']);
                    }
                    // devuelve boreano.. true correcto
                }
            }
            if (count($r) === 0){

                // No existe email en subscripcion, añadimos registro con los datos usuario creado.
                $r3= $this->InsertarUsuarioJoomlaNews($userId,$data['email'],$data['name']);
            }
        }
        return true;
    }

    /**
     * Remove all user profile information for the given user ID
     *
     * Method is called after user data is deleted from the database
     *
     * @param   array    $user     Holds the user data
     * @param   boolean  $success  True if user was succesfully stored in the database
     * @param   string   $msg      Message
     *
     * @return  boolean
     */
    public function onUserBeforeDelete($user)
    {
        $userId = ArrayHelper::getValue($user, 'id', 0, 'int');
		if ($userId >0 )
		{
            // Añadimos usuario de joomla en el registro newsletter.
            $db = Factory::getDbo();
            $query= "DELETE FROM #__phocaemail_subscribers WHERE userid=".$userId;
            $db->setQuery($query);
            $resul = $db->execute();
            return $resul;
        }
    }

    public function listasPhocaemail(){
        // Ahora debería consultar cuantas listas de subscripcion hay.
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('id','title', 'alias', 'description','published', 'checked_out', 'checked_out_time', 'ordering', 'access', 'params', 'language')));
        $query->from($db->quoteName('#__phocaemail_lists'));
        $db->setQuery($query);
        $results = $db->loadObjectList();

        return $results;
    }

    public function SiexisteSubcripcion($dato,$tipo) {
        // Comprobamos si existe la subcripcion.
        // devolvemos 0 si no existe o id registro si existe.
        if ($tipo=== 'email'){
            $w='email="'.$dato.'"';
        }
        if ($tipo=== 'userId'){
            $w='userid="'.$dato.'"';
        }
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('id','active')));
        $query->from($db->quoteName('#__phocaemail_subscribers'));
        $query->where($w);
        $db->setQuery($query);
        $results = $db->loadObjectList();

        return $results;
    }

    public function UpdateUsuarioJoomlaNews($userid,$id,$active){
        // Añadimos usuario de joomla en el registro newsletter.
        $db = Factory::getDbo();
        $query = "UPDATE #__phocaemail_subscribers SET userid=" . $userid . ", active=" . $active . ",date_register=NOW()";
        if ($active == 2) {
            $query .= ", date_unsubscribe=NOW() ";
        }

        $query .= "        WHERE id ='" . $id . "'";
        $db->setQuery($query);
        $resul = $db->execute();
        return $resul;
    }

     public function InsertarUsuarioJoomlaNews($userid,$email,$nombre){
        // Ahora añadimos el token:
        $tokenArray = array('token');
        $token = PhocaEmailHelper::getToken($tokenArray);
        // Montamos array usuario.
        $usuario = array();
        $usuario['token'] = $token;
        $usuario['date'] = date("Y-m-d H:i:s");
        $usuario['date_active'] = date("Y-m-d H:i:s");

        $usuario['active'] = 1;
        $usuario['hits'] = 1;
        // No se comprueba si tiene check marcado ya que lo hicimos antes.
        $usuario['published'] = 1;

        // Añadimos usuario de joomla en el registro newsletter.
        $columns = array('name','email','userid','token','date','date_active','hits','published','active','access');
        $values = array();
        $values ='"'.$nombre.'","'.$email.'",'.$userid.',"'
							.$usuario['token'].'","'.$usuario['date'].'","'.$usuario['date_active'].'",'.
							'1,'.$usuario['published'].',1,1';

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__phocaemail_subscribers'));
        $query->columns($columns);
        $query->values($values);
        $db->setQuery($query);
        $db->execute();
        $num_rows = $db->getAffectedRows();
        $respuesta['Anhadidos'] = $num_rows;

        return $respuesta;
    }
}
