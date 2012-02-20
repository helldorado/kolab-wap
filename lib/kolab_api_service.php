<?php

require_once 'SQL.php';

/**
 * Interface class for Kolab Admin Services
 */
abstract class kolab_api_service
{
    protected $controller;
    protected $db;

    /**
     * Class constructor.
     *
     * @param kolab_api_controller Controller
     */
    public function __construct($ctrl)
    {
        $this->db         = SQL::get_instance();
        $this->controller = $ctrl;
    }

    /**
     * Advertise this service's capabilities
     */
    abstract public function capabilities($domain);

    /**
     * Returns attributes of specified user type.
     *
     * @param int  $type_id  User type identifier
     * @param bool $required Throws exception on empty ID
     *
     * @return array User type attributes
     */
    protected function user_type_attributes($type_id, $required = true)
    {
        if (empty($user_id)) {
            if ($required) {
                throw new Exception($this->controller->translate('user.notypeid'), 34);
            }

            return array();
        }

        $sql_result = $this->db->query("SELECT attributes FROM user_types WHERE id = ?", $type_id);
        $user_type  = $this->db->fetch_assoc($sql_result);

        if (empty($user_type)) {
            throw new Exception($this->controller->translate('user.invalidtypeid'), 35);
        }

        $uta = json_decode(unserialize($user_type['attributes']), true);

        return $uta;
    }

}
