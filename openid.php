<?php

/**
 * OpenID action module
 *
 * @copyright Christian Ackermann (c) 2010 - End of life
 * @author Christian Ackermann <prdatur@gmail.com>
 * @category Module
 */
class Openid extends ActionModul {

	/**
	 * Default method.
	 *
	 * @var string
	 */
	protected $default_methode = self::NO_DEFAULT_METHOD;

	/**
	 * Config key for the open id CAPATH.
	 * Value is the path where the system can find files holding CA's.
	 *
	 * @var string
	 */
	const CONFIG_OPENID_CAPATH = 'openid_capath';

	/**
	 * Config key for the open id CAINFO.
	 * Value is the file id of the upload file which holds the CA infos.
	 *
	 * @var string
	 */
	const CONFIG_OPENID_CAINFO = 'openid_cainfo';

	/**
	 * Config key for the open id verify client.
	 * Value is an integer, 1 determines that only clients with valid certificates will be accepted.
	 *
	 * @var string
	 */
	const CONFIG_OPENID_VERIFY_CLIENT = 'openid_verify_client';

	/**
	 * Config key to enable / disable data sync.
	 * Value is an integer, true determines that the user / address data will be always be in sync.
	 *
	 * @var string
	 */
	const CONFIG_OPENID_SYNC_DATA = 'openid_sync_data';

	/**
	 * Implements hook: admin_menu
	 *
	 * Returns an array which includes all links and childs for the admin menu.
	 * There are some special categories in which the module can be injected.
	 * The following categories are current supported:
	 *   style, security, content, structure, authentication, system, other
	 *
	 * @return array the menu
	 */
	public function hook_admin_menu() {
		return array(
			AdminMenu::CATEGORY_AUTHENTICATION => array(
				'#id' => 'soopfw_openid', //A unique id which will be needed to generate the submenu
				'#title' => t("OpenID"), //The main title
				'#perm' => 'admin.openid', //Perm needed
				'#childs' => array(
					array(
						'#title' => t("Config"), //The main title
						'#link' => "/admin/openid/config", // The main link
						'#perm' => "admin.openid.config", // perms needed
					),
				)
			)
		);
	}

	/**
	 * Implements hook_alter_user_login_form().
	 *
	 * Please do only modifify $form if you really need it.
	 * Normally it is enough to return the new elements
	 * the "sections" are all optional so if you do not want
	 * to add elements before the buttons you do not need to provide the section "middle"
	 *
	 * valid sections are:
	 *   top = right after the form initializing
	 *   middle = between the last default input and the buttons
	 *   bottom = after the buttons
	 *
	 * @param Form $form
	 *   the user login form.
	 *
	 * @return array the new input fields.
	 *   the array must have the following format:
	 *   array(
	 *     'top' => array(elements),
	 *     'middle' => array(elements),
	 *     'bottom' => array(elements),
	 *  )
	 */
	public function hook_alter_user_login_form(Form &$form) {

		$return = array(
			'top' => array(),
			'middle' => array(),
			'bottom' => array(),
		);

		// Provide openid login textfield within the login form if OpenIDLoginHandler is enabled.
		if ($this->session->is_login_handler_enabled('OpenIDLoginHandler')) {
			$return['middle'][] = new Textfield('openid_user', '', t('OpenID'));
		}

		return $return;
	}

	/**
	 * Action: config
	 *
	 * Configurate the openid main settings.
	 */
	public function config() {
		//Check perms
		if (!$this->right_manager->has_perm('admin.openid.manage', true)) {
			throw new SoopfwNoPermissionException();
		}

		// Setting up title and description.
		$this->title(t("OpenID config"), t("Here we can configure the OpenID settings"));

		// Configurate the settings form.
		$form = new SystemConfigForm($this, "openid_config");

		$form->add(new Fieldset('main_config', t('Main')));

		// CA-Path.
		$form->add(new Textfield(
			self::CONFIG_OPENID_CAPATH,
			$this->core->dbconfig("openid", self::CONFIG_OPENID_CAPATH),
			t("OpenID CAPATH"),
			t('If provided openID will search all files within this directory for valid CA\'s.')
		));

		// CA-Info.
		$form->add(new Filefield(
			self::CONFIG_OPENID_CAINFO,
			$this->core->dbconfig("openid", self::CONFIG_OPENID_CAINFO),
			t("OpenID CAINFO"),
			t('If a file is provided openID will search within the file for valid CA\'s.')
		));

		// Verify-client.
		$form->add(new Checkbox(
			self::CONFIG_OPENID_VERIFY_CLIENT,
			1,
			(int) $this->core->get_dbconfig("openid", self::CONFIG_OPENID_VERIFY_CLIENT, 0),
			t('Enable client-verify?'),
			t('If enabled only valid certificates will be allowed.')
		));

		// Sync data.
		$form->add(new Checkbox(
			self::CONFIG_OPENID_SYNC_DATA,
			1,
			(int) $this->core->get_dbconfig("openid", self::CONFIG_OPENID_SYNC_DATA, 1),
			t('Always sync data?'),
			t('If enabled the user and user address will always be synchronized after he logs in, else it would be only one time synchronized after account creation.')
		));

		// Execute the settings form.
		$form->execute();
	}
}