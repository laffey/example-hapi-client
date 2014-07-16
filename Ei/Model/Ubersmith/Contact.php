<?php
/**
 * Contact model
 */
namespace Ei\Model\Ubersmith;

use \Ei\Model\AbstractModel;

class Contact extends AbstractModel
{

    const AGILE_CUSTOMER = 0;
    const COLO_CUSTOMER = 1;
    const CLOUDY_COLO_CUSTOMER = 2;

    /**
     * we want this object to be consistent, requiring this data
     *     and throwing notices when other indexes are set
     *  this also sets the defaults for each value
     *
     * @var array
     */
    protected $_data = array(

        // these keys are actually populated by get contact
        'super_contact_id'      =>      0,
        'contact_id'            =>      0,
        'client_id'             =>      0,
        'username'              =>      null,
        'real_name'             =>      null,
        'email_name'            =>      null,
        'email_domain'          =>      null,
        // 'password_timeout'   =>      0,
        // 'password_changed'   =>      null,
        'description'           =>      null,
        'phone'                 =>      null,
        'prefer_lang'           =>      null,
        'audit_tickets'         =>      null,
        // 'priority'           =>      null,
        'rwhois_contact'        =>      null,
        'listed_company'        =>      null,
        // 'class_id'           =>      0,
        // 'client_active'      =>      null,
        'email'                 =>      null,
        'first'                 =>      null,
        'last'                  =>      null,
        'access'                =>      array(),
        'client_access'         =>      array(),
        'permission_groups'     =>      array(),
        'is_lead'               =>      false,

        //added by a call to hapi.authkeys.read
        'key'                   =>      null,
        'secret'                =>      null,
        'user_type'             =>      null,

        // added by a call to client.get
        'default_payment_method_id' =>   0,
        // added by a call to client.get with metadata
        'is_labs'               =>      false,
        'portal_demo'           =>      false,
        'colo_plus'             =>      0, // values defined above
        'managed'               => '',

        // determined by counting invoices
        'is_agile'              =>      false,

        // did user select Remember Me at login
        'remember_me'           =>      false,

        'login_timestamp'       =>      0,

        /*
        * save the time of the last authentication call to hapi
        * so we can expire or re-check pw when needed
        */
        'password_checked_timestamp' => 0,

        /*
        * save the time of the last authorization call to hapi
        *   so we can refresh our access array as needed
        * strictly speaking, this should always be "time at which
        *   the 'access' array was last retrieved."
        */
        'heartbeat_timestamp'    =>     0,
    );

    /**
     * key is received param
     * value is portal model property
     * @var array
     */
    protected static $_hydrator = array (
        'contact_id'      => 'contact_id',
        // 'priority'        => 'priority',
        'email'           => 'email',
        'email_name'      => 'email_name',
        'email_domain'    => 'email_domain',
        // 'password_changed'=> 'password_changed',
        // 'password_timeout'=> 'password_timeout',
        // 'active'          => 'client_active',
        'listed_company'  => 'listed_company',
        'real_name'       => 'real_name',
        'first'           => 'first',
        'access'          => 'access',
        'rwhois_contact'  => 'rwhois_contact',
        'last'            => 'last',
        'client_id'       => 'client_id',
        'description'     => 'description',
        'phone'           => 'phone',
        'login'           => 'username',
        'audit_tickets'   => 'audit_tickets',
        // 'class_id'        => 'class_id',
        'prefer_lang'     => 'prefer_lang',
    );

    /**
     * Returns the bare minimum amount of info
     *   necessary to load a hAPI client.
     * @return array
     */
    public function getAuthInfo()
    {
        return array (
            'client_id' => $this->_data['client_id'],
            'username' => $this->_data['username'],
            'usertype' => $this->_data['user_type'],
            'hapi_key' => $this->_data['key'],
            'hapi_secret' => $this->_data['secret'],
            'persist' => $this->_data['remember_me'],
            'timestamp' => $this->_data['login_timestamp'],
            'timestamp_checked' => $this->_data['password_checked_timestamp'],
        );
    }

    public function getClientId()
    {
        return $this->_data['client_id'];
    }

    public function getContactId()
    {
        return $this->_data['contact_id'];
    }

    public function getUserId()
    {
        return $this->_data['contact_id'] == -1
            ? $this->_data['super_contact_id']
            : $this->_data['contact_id'];
    }

    public function getHapiKey()
    {
        return $this->_data['key'];
    }

    public function getHapiSecret()
    {
        return $this->_data['secret'];
    }
}
