<?php

/**
 * Provides a login handler for openid single sign on.
 *
 * @copyright Christian Ackermann (c) 2010 - End of life
 * @author Christian Ackermann <prdatur@gmail.com>
 * @category Module
 */
class OpenIDLoginHandler extends AbstractLoginHandler implements LoginHandler
{
	/**
	 * The OpenID api.
	 *
	 * @var LightOpenID
	 */
	private $api = null;

	/**
	 * Holds the mapping between the openID Attribute exchange and a Soopfw user / user address.
	 *
	 * @var array
	 */
	private $mapping_ax_2_soopfw = array(
		'namePerson' => 'firstname',
		'namePerson/friendly' => 'username',
		'namePerson/prefix' => 'title',
		'namePerson/first' => 'firstname',
		'namePerson/last' => 'lastname',
		'language/pref' => 'language',
		'contact/internet/email' => 'email',
		'contact/emaiĺ' => 'email',
		'contact/phone/default' => 'phone',
		'contact/phone/cell' => 'mobile',
		'contact/phone/fax' => 'fax',
		'contact/postaladdress/home' => 'address',
		'contact/postaladdressadditional/home' => 'address2',
		'contact/city/home' => 'city',
		'contact/country/home' => 'nation',
		'contact/postalcode/home' => 'zip'
	);

	/**
	 * Constructor
	 *
	 * @param Core &$core
	 *   The core object. (optional, default = null)
	 */
	function __construct(Core &$core = null) {
		parent::__construct($core);

		// Initialize the api.
		$this->api = new LightOpenID($this->core->core_config('core', 'domain'));

		if ($this->core->get_dbconfig("openid", Openid::CONFIG_OPENID_VERIFY_CLIENT, 0)) {

			// Enable peer verification.
			$this->api->verify_peer = true;

			// Set ca info.
			$cainfo = $this->core->dbconfig("openid", Openid::CONFIG_OPENID_CAINFO);
			if (!empty($cainfo)) {
				$mainfile = new MainFileObj($cainfo);
				if ($mainfile->load_success()) {
					$this->api->cainfo = $mainfile->get_path();
				}
			}

			// Set ca path.
			$capath = $this->core->dbconfig("openid", Openid::CONFIG_OPENID_CAPATH);
			if (!empty($capath)) {
				$this->api->capath = $capath;
			}
		}

	}

	/**
	 * Returns the LightOpenID api.
	 *
	 * @return LightOpenID the api
	 */
	public function get_api() {
		return $this->api;
	}

	/**
	 * This is called within the login page without posting something and is used for Single Sign On's like openID, shibboleth or Facebook.
	 * This is a direct check if the user is logged in without a need to provide credentials.
	 *
	 * @return boolean returns true on successfully login else false.
	 */
	public function pre_validate_login() {

		$params = new ParamStruct();
		$params->add_required_param('openid_user', PDT_STRING);
		$params->fill();

		// Check if we provided an open id url. If not and we also does not come from the OpenID handler,
		// we skip the login handling for OpenID.
		if (!$params->is_valid() && !$this->api->mode) {
			return false;
		}

		// If we provided an OpenID we redirect the user to his provider.
		if ($params->is_valid()) {
			$this->api->identity = $params->openid_user;
			$this->api->required = array('contact/internet/email');
			$this->api->optional = array(
				'namePerson',
				'contact/email',
				'namePerson/friendly',
				'namePerson/prefix',
				'namePerson/first',
				'namePerson/last',
				'language/pref',
				'contact/phone/default',
				'contact/phone/cell',
				'contact/phone/fax',
				'contact/postaladdress/home',
				'contact/postaladdressadditional/home',
				'contact/city/home',
				'contact/country/home',
				'contact/postalcode/home',
			);
			$this->core->location($this->api->authUrl());
		}

		// Validate the OpenID user.
		$user_obj = $this->create_user();

		// If validation succeed tell the session that we have successfully logged in.
		if ($user_obj !== false) {
			return $this->session->validate_login($user_obj);
		}
		return false;
	}

	/**
	 * Creates a user based up on OpenID details.
	 *
	 * @return boolean|UserObj Returns the created user object on success, else boolean false.
	 */
	private function create_user() {

		// Try to validate the user.
		$valid = $this->api->validate();
		$user_id = $this->api->identity;

		// If both empty (the validated value which comes from the provider or the user id which is not empty if we
		// already logged in, return false.
		if (empty($valid) && empty($user_id)) {
			return false;
		}

		// Try to load the current user based up on the OpenID user id.
		$user_obj = new UserObj();
		$user_obj->db_filter
			->add_where("password", $user_id)
			->add_where("account_type", 'openid');
		$user_obj->load();


		// Get all attributes from the OpenID profile.
		$attributes = $this->api->getAttributes();


		// If we could not load it we maybe need to create a new account.
		if (!$user_obj->load_success()) {

			// Validate that the username is unique.
			$check_user_obj = new UserObj();
			$check_user_obj->db_filter ->add_where("password", $user_id);
			$check_user_obj->load();
			if ($check_user_obj->load_success()) {
				$this->core->message(t('This username is already taken from another login handler, Sorry you can not use this username anymore.'), Core::MESSAGE_TYPE_ERROR);
				return false;
			}

			// Validate that we have all mandatory attributes.
			if (!isset($attributes['contact/internet/email']) || empty($attributes['contact/internet/email'])) {
				return false;
			}

			// Maybe we got the username directly from the profile.
			if (isset($attributes['namePerson/friendly'])) {

				// Check if the provided nickname does already exist.
				$check_free_username = $this->db->query_slave_exists('SELECT 1 FROM `' . UserObj::TABLE . '` WHERE `username` = @username', array(
					'@username' => $attributes['namePerson/friendly'],
				));

				// Only set the username if it does NOT exist.
				if (!$check_free_username) {
					$user_obj->username = $attributes['namePerson/friendly'];
				}
			}
			else {
				$user_obj->username = '';
			}

			$user_obj->password = $user_id;
		}

		// Create fresh account or update current one.
		if (!$this->save_or_insert_data($user_obj, $attributes)) {
			return false;
		}

		// Return object.
		return $user_obj;
	}

	/**
	 * Creates or updates the given user data.
	 *
	 * @param UserObj $user_obj
	 *   The user which will be created / updated.
	 * @param array $ax_attributes
	 *   The attributes to be used for the UserObj and UserAddressObj object.
	 *
	 * @return boolean returns true if account could be created or updated, else false.
	 */
	public function save_or_insert_data(UserObj $user_obj, Array $ax_attributes) {
		if ($user_obj->load_success() && (int) $this->core->get_dbconfig("openid", Openid::CONFIG_OPENID_SYNC_DATA, 1) !== 1) {
			return true;
		}

		// Default values.
		$values = array(
			'language' => $this->core->current_language,
			'registered' => date(DB_DATETIME, TIME_NOW),
			'last_login' => date(DB_DATETIME, TIME_NOW),
			'account_type' => 'openid',
			'parent_id' => 0,
		);

		// If we have already an account, remove data which is only used while creating a fresh account.
		// Also we determine the user address or create a new one.
		if ($user_obj->load_success()) {
			unset($values['registered']);
			$address_id = $user_obj->get_address_by_group(UserAddressObj::USER_ADDRESS_GROUP_DEFAULT, 'id');
		}
		else {
			$address_id = 0;
		}

		// Get value array which can be used for set_fields.
		foreach ($ax_attributes AS $k => $v) {
			if (!isset($this->mapping_ax_2_soopfw[$k])) {
				continue;
			}
			$values[$this->mapping_ax_2_soopfw[$k]] = $v;
		}

		// User.
		$user_obj->set_fields($values);

		// User address.
		$user_address = new UserAddressObj($address_id);
		$user_address->set_fields($values);

		if (!$user_obj->load_success()) {
			if ($user_obj->create_account($user_address, false, false)) {
				SystemHelper::audit(t('User created from OpenID-LoginHandler "@username".', array('@username' => $user_obj->user_id)), 'session', SystemLogObj::LEVEL_NOTICE);
				return true;
			}
			return false;
		}

		return $user_obj->update_account($user_address, false);
	}
}