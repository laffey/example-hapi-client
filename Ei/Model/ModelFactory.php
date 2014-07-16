<?php
/**
 * ModelFactory
 * build a hapi response model
 */
namespace Ei\Model;

use \Ei\Model\Exception\ModelException;
use \Ei\Model\Factory\FactoryInterface;

class ModelFactory implements FactoryInterface
{
    /**
     * namespace root for our model classes
     * @var string
     */
    protected $_classRoot = '\Ei\Model';

    /**
     * map a model type to its class
     * @var array
     */
    protected $_classMap = array(
            'account'                        => '\Hapi\Account',
            'agile_create'                   => '\Agile\Create',
            'agile_credentials'              => '\Agile\Credentials',
            'agile_facility'                 => '\Agile\Facility',
            'agile_network'                  => '\Agile\Network',
            'agile_service'                  => '\Agile\Service',
            'agile_status'                   => '\Agile\Status',
            'agile_universal_tranfer_check'  => '\Agile\UniversalTransferCheck',
            'authkey'                        => '\Hapi\Authkey',
            'client_msa_detail'              => '\Uber\ClientMsaDetail',
            'contact'                        => '\Ubersmith\Contact',
            'cdn'                            => '\Device\CDN',
            'device'                         => '\Device\Device',      // sub of model
            'device_event'                   => '\Device\DeviceEvent',
            'device_model'                   => '\Device\DeviceModel', // sub of type
            'device_type'                    => '\Device\DeviceType',  // super of model
            'device_monitor'                 => '\Device\Monitor',
            'device_monitor_log'             => '\Device\Monitor\Log',
            'device_metric'                  => '\Device\Metric',
            'device_type_list'               => '\Device\DeviceTypeList',
            'device_power_port'              => '\Device\DevicePowerPort',
            'domain'                         => '\Device\Domain',
            'domain_record'                  => '\Device\DomainRecord',
            'facility'                       => '\Device\Facility',
            'http_request'                   => '\File\HTTPRequest',
            'hapi_category'                  => '\Hapi\HapiCategory',
            'hapi_method'                    => '\Hapi\HapiMethod',
            'file'                           => '\File\File',
            'invoice'                        => '\Billing\Invoice',
            'ip'                             => '\Network\Ip',
            'ip_group'                       => '\Network\IpGroup',
            'job_status'                     => '\Hapi\JobStatus',
            'job'                            => '\Hapi\Job',
            'lb_facility'                    => '\Device\LBFacility',
            'loadbalancer'                   => '\Device\Loadbalancer',
            'module'                         => '\Device\Module',
            'orders'                         => '\Billing\Orders',
            'payment_method'                 => '\Billing\PaymentMethod',
            'pdu_power_port'                 => '\Device\PDUPowerPort',
            'pdu_report'                     => '\Device\PDUReport',
            'rack'                           => '\Device\Rack',
            'services'                       => '\Billing\Services', // from billing.services.list
            'service'                        => '\Billing\Service',  // from billing.services.get
            'service_plans'                  => '\Billing\ServicePlans',
            'support_ticket'                 => '\Support\Ticket',
            'support_ticket_post'            => '\Support\Post',
            'uber_client'                    => '\Uber\Client',
            'uber_permission_list'           => '\Uber\PermissionList',
            'user'                           => '\Hapi\User',
            'user.facility_permission'       => '\User\Facility\FacilityPermission',
            'voxfacility'                    => '\Device\VoxProvisioning\VoxFacility',
            'voxserverinventory'             => '\Device\VoxProvisioning\VoxServerInventory',
            'voxserveros'                    => '\Device\VoxProvisioning\VoxServerOS',
            'voxstatus'                      => '\Device\VoxProvisioning\VoxStatus',
    );

    /**
     * create a hapi model object
     *
     * @param string $modelType
     * @param array $properties         *optional
     * @param boolean $silent
     * @throws ModelException
     * @return \Ei\Model\AbstractModel
     */
    public function create($modelType, $properties = array(), $silent = true)
    {
        if (!isset($this->_classMap[$modelType])) {
            throw new ModelException('Undefined model type, ' . $modelType . '. Hapi model cannot be created via the factory.');
        }
        $modelClass = $this->_classRoot . $this->_classMap[$modelType];
        return new $modelClass($properties, $silent);
    }

    /**
     * Convert data into an array compatible with the
     *    given modelType, then use create() with that new array
     *
     * @param string $modelType
     * @param array $data
     * @return array
     * @throws \Ei\Model\Exception\ModelException
     */
    public function convertToModelProperties($modelType, $data)
    {
        if (!isset($this->_classMap[$modelType])) {
            throw new ModelException('Undefined model type, ' . $modelType . '. Hapi model cannot be created via the factory.');
        }
        $modelClass = $this->_classRoot . $this->_classMap[$modelType];
        $properties = $modelClass::hydrate($data);
        return $properties;
    }

}
