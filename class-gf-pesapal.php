<?php

add_action('wp', array('GFPesaPal', 'maybe_thankyou_page'), 5);
define('DB_UPDATE_IS_SUCCESSFUL',true);
GFForms::include_payment_addon_framework();

class GFPesaPal extends GFPaymentAddOn
{

    protected $_version = GF_PESAPAL_VERSION;
    protected $_min_gravityforms_version = '1.8.12';
    protected $_slug = 'gravityformspesapal';
    protected $_path = 'gravityformspesapal/pesapal.php';
    protected $_full_path = __FILE__;
    protected $_url = 'http://www.faharistudio.com';
    protected $_title = 'Gravity Forms PesaPal Addon';
    protected $_short_title = 'Pesapal';
    protected $_supports_callbacks = true;
    private $production_url = 'https://pesapal.com/api/PostPesapalDirectOrderV4';
    private $sandbox_url = 'https://demo.pesapal.com/api/PostPesapalDirectOrderV4';

    // Members plugin integration
    protected $_capabilities = array('gravityforms_pesapal', 'gravityforms_pesapal_uninstall');

    // Permissions
    protected $_capabilities_settings_page = 'gravityforms_pesapal';
    protected $_capabilities_form_settings = 'gravityforms_pesapal';
    protected $_capabilities_uninstall = 'gravityforms_pesapal_uninstall';

    // Automatic upgrade enabled
    protected $_enable_rg_autoupgrade = true;

    private static $_instance = null;

    public function init()
    {

        parent::init();
        add_shortcode('komupesapalfull', array('GFPesaPal', 'komupesapalfull'));

    }


    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new GFPesaPal();
        }

        return self::$_instance;
    }

    private function __clone()
    {
    } /* do nothing */

    public function init_frontend()
    {
        parent::init_frontend();

        add_filter('gform_disable_post_creation', array($this, 'delay_post'), 10, 3);
        add_filter('gform_disable_notification', array($this, 'delay_notification'), 10, 4);

        // add_action( 'gform_after_submission', array( 'GFPesaPal', 'after_submission' ), 10,2 );

        //add_action( 'wp_ajax_pesapalpay_ipn_return', array(&$this,'ipn_return'));
    }

    public function komupesapalfull($attrs)
    {
        return "none";
    }

    //----- SETTINGS PAGES ----------//

    public function plugin_settings_fields()
    {
        $description = '
			<p style="text-align: left;">' .
            __('Set the url below as the IPN . Use this url <a href="https://demo.pesapal.com/merchantipn">IPN Settings </a> for demo accounts .
                Or this url <a href="https://pesapal.com/merchantipn">Live IPN Settings </a>  for Live acounts
                ', 'gravityformspesapal') .
            '</p>
			<ul>
			<li>' . sprintf(__('Your Merchant Post Url is : %s', 'gravityformspesapal'), '<strong>' . esc_url(add_query_arg('page', 'gf_pesapal_ipn', get_bloginfo('url') . '/')) . '</strong>') . '</li>' .
            '</ul>
				<br/>';

        return array(
            array(
                'title' => '',
                'description' => $description,
                'fields' => array(
                    array(
                        'name' => 'mode',
                        'horizontal' => true,
                        'label' => __('Mode', 'gravityformspesapal'),
                        'type' => 'radio',
                        'choices' => array(
                            array('id' => 'gf_pesapal_mode_production', 'label' => __('Production', 'gravityformspesapal'), 'value' => 'production'),
                            array('id' => 'gf_pesapal_mode_test', 'label' => __('Test', 'gravityformspesapal'), 'value' => 'test'),

                        ),
                    ),
                    array(
                        'name' => 'pesapal_consumer_key',
                        'label' => __('PesaPal Consumer Key', 'gravityformspesapal'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'tooltip' => '<h6>' . __('PesaPal Consumer Key', 'gravityformspesapal') . '</h6>' . __('Check your profile for this or your email for live accounts.', 'gravityformspesapal')
                    ),
                    array(
                        'name' => 'pesapal_consumer_secret',
                        'label' => __('PesaPal Consumer Secret ', 'gravityformspesapal'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'tooltip' => '<h6>' . __('PesaPal Consumer Secret', 'gravityformspesapal') . '</h6>' . __('Check your profile for this or your email for live accounts.', 'gravityformspesapal')
                    ),
                    array(
                        'name' => 'pesapal_description',
                        'label' => __('PesaPal Description ', 'gravityformspesapal'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'tooltip' => '<h6>' . __('PesaPal Description', 'gravityformspesapal') . '</h6>' . __('A descripotion for this transaction eg, donation, membership, beer....', 'gravityformspesapal')
                    ),
                    array(
                        'type' => 'save',
                        'messages' => array(
                            'success' => __('Settings have been updated.', 'gravityformspesapal')
                        ),
                    ),
                ),
            ),
        );
    }

    //end plugin settings Komu

    public function feed_list_no_item_message()
    {
        return parent::feed_list_no_item_message();
        exit;
        $settings = $this->get_plugin_settings();
        if (!rgar($settings, 'gf_pesapal_configured')) {
            return sprintf(__('To get started, let\'s go configure your %sPesaPal Settings%s!', 'gravityformspesapal'), '<a href="' . admin_url('admin.php?page=gf_settings&subview=' . $this->_slug) . '">', '</a>');
        } else {
            return parent::feed_list_no_item_message();
        }
    }

    public function billing_info_fields() {

        $fields = array(
            
            array( 'name' => 'email', 'label' => esc_html__( 'Email', 'gravityforms' ), 'required' => true ),
            array( 'name' => 'address', 'label' => esc_html__( 'Address', 'gravityforms' ), 'required' => false ),
            array( 'name' => 'address2', 'label' => esc_html__( 'Address 2', 'gravityforms' ), 'required' => false ),
            array( 'name' => 'city', 'label' => esc_html__( 'City', 'gravityforms' ), 'required' => false ),
            array( 'name' => 'state', 'label' => esc_html__( 'State', 'gravityforms' ), 'required' => false ),
            array( 'name' => 'zip', 'label' => esc_html__( 'Zip', 'gravityforms' ), 'required' => false ),
            array( 'name' => 'country', 'label' => esc_html__( 'Country', 'gravityforms' ), 'required' => false ),
            array( 'name' => 'pesapal_currency', 'label' => esc_html__( 'Currency', 'gravityforms' ), 'required' => true ),
        );

        return $fields;
    }
    public function feed_settings_fields()
    {
        $default_settings = parent::feed_settings_fields();

        //--add PesaPal Email Address field
        $fields = array();

        $default_settings = parent::add_field_after('feedName', $fields, $default_settings);
       // $default_settings = parent::add_field_after('paymentDescription', $fields, $default_settings);
        //--------------------------------------------------------------------------------------

        //--add donation to transaction type drop down
        $transaction_type = parent::get_field('transactionType', $default_settings);
        $choices = $transaction_type['choices'];
        $transaction_type['choices'] = $choices;
        $default_settings = $this->replace_field('transactionType', $transaction_type, $default_settings);
        //-------------------------------------------------------------------------------------------------

        //--add Page Style, Continue Button Label, Cancel URL
        $fields = array(
            array(
                'name' => 'notifications',
                'label' => __('Notifications', 'gravityformspesapal'),
                'type' => 'notifications',
                'tooltip' => '<h6>' . __('Notifications', 'gravityformspesapal') . '</h6>' . __("Enable this option if you would like to only send out this form's notifications after payment has been received. Leaving this option disabled will send notifications immediately after the form is submitted.", 'gravityformspesapal')
            ),

        );

        //Add post fields if form has a post
        $form = $this->get_current_form();
        if (GFCommon::has_post_field($form['fields'])) {
            $post_settings = array(
                'name' => 'post_checkboxes',
                'label' => __('Posts', 'gravityformspesapal'),
                'type' => 'checkbox',
                'tooltip' => '<h6>' . __('Posts', 'gravityformspesapal') . '</h6>' . __('Enable this option if you would like to only create the post after payment has been received.', 'gravityformspesapal'),
                'choices' => array(
                    array('label' => __('Create post only when payment is received.', 'gravityformspesapal'), 'name' => 'delayPost'),
                ),
            );


            $fields[] = $post_settings;
        }

        //Adding custom settings for backwards compatibility with hook 'gform_pesapal_add_option_group'
        $fields[] = array(
            'name' => 'custom_options',
            'label' => '',
            'type' => 'custom',
        );

        $default_settings = $this->add_field_after('billingInformation', $fields, $default_settings);
        //-----------------------------------------------------------------------------------------

        //--get billing info section and add customer first/last name
        $billing_info = parent::get_field('billingInformation', $default_settings);
        $billing_fields = $billing_info['field_map'];
        $add_first_name = true;
        $add_last_name = true;
        foreach ($billing_fields as $mapping) {
            //add first/last name if it does not already exist in billing fields
            if ($mapping['name'] == 'firstName') {
                $add_first_name = false;
            } else if ($mapping['name'] == 'lastName') {
                $add_last_name = false;
            }
        }

        if ($add_last_name) {
            //add last name
            array_unshift($billing_info['field_map'], array('name' => 'lastName', 'label' => __('Last Name', 'gravityformspesapal'), 'required' => false));
        }
        if ($add_first_name) {
            array_unshift($billing_info['field_map'], array('name' => 'firstName', 'label' => __('First Name', 'gravityformspesapal'), 'required' => false));
        }
        array_unshift($billing_info['field_map'], array('name' => 'company', 'label' => __('Company', 'gravityformspesapal'), 'required' => false));
        $default_settings = parent::replace_field('billingInformation', $billing_info, $default_settings);
        //$default_settings = parent::add_field('billingInformation', $fields, $default_settings);
        //----------------------------------------------------------------------------------------------------

        //hide default display of setup fee, not used by PesaPal Standard
        $default_settings = parent::remove_field('setupFee', $default_settings);
       // array_push($default_settings,$fields1);

        return apply_filters('gform_pesapal_feed_settings_fields', $default_settings, $form);
    }

    public function supported_billing_intervals()
    {

        $billing_cycles = array(
            'day' => array('label' => __('day(s)', 'gravityformspesapal'), 'min' => 1, 'max' => 90),
            'week' => array('label' => __('week(s)', 'gravityformspesapal'), 'min' => 1, 'max' => 52),
            'month' => array('label' => __('month(s)', 'gravityformspesapal'), 'min' => 1, 'max' => 24),
            'year' => array('label' => __('year(s)', 'gravityformspesapal'), 'min' => 1, 'max' => 5)
        );

        return $billing_cycles;
    }

    public function field_map_title()
    {
        return __('PesaPal Field', 'gravityformspesapal');
    }

    public function settings_custom($field, $echo = true)
    {

        ob_start();
        ?>
        <div id='gf_pesapal_custom_settings'>
            <?php
            do_action('gform_pesapal_add_option_group', $this->get_current_feed(), $this->get_current_form());
            ?>
        </div>

        <script type='text/javascript'>
            jQuery(document).ready(function () {
                jQuery('#gf_pesapal_custom_settings label.left_header').css('margin-left', '-200px');
            });
        </script>

        <?php

        $html = ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_notifications($field, $echo = true)
    {
        $checkboxes = array(
            'name' => 'delay_notification',
            'type' => 'checkboxes',
            'onclick' => 'ToggleNotifications();',
            'choices' => array(
                array(
                    'label' => __('Send notifications only when payment is received.', 'gravityformspesapal'),
                    'name' => 'delayNotification',
                ),
            )
        );

        $html = $this->settings_checkbox($checkboxes, false);

        $html .= $this->settings_hidden(array('name' => 'selectedNotifications', 'id' => 'selectedNotifications'), false);

        $form = $this->get_current_form();
        $has_delayed_notifications = $this->get_setting('delayNotification');
        ob_start();
        ?>
        <ul id="gf_pesapal_notification_container"
            style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;' ?>">
            <?php
            if (!empty($form) && is_array($form['notifications'])) {
                $selected_notifications = $this->get_setting('selectedNotifications');
                if (!is_array($selected_notifications)) {
                    $selected_notifications = array();
                }

                //$selected_notifications = empty($selected_notifications) ? array() : json_decode($selected_notifications);

                $notifications = GFCommon::get_notifications('form_submission', $form);

                foreach ($notifications as $notification) {
                    ?>
                    <li class="gf_pesapal_notification">
                        <input type="checkbox" class="notification_checkbox" value="<?php echo $notification['id'] ?>"
                               onclick="SaveNotifications();" <?php checked(true, in_array($notification['id'], $selected_notifications)) ?> />
                        <label class="inline"
                               for="gf_pesapal_selected_notifications"><?php echo $notification['name']; ?></label>
                    </li>
                    <?php
                }
            }
            ?>
        </ul>
        <script type='text/javascript'>
            function SaveNotifications() {
                var notifications = [];
                jQuery('.notification_checkbox').each(function () {
                    if (jQuery(this).is(':checked')) {
                        notifications.push(jQuery(this).val());
                    }
                });
                jQuery('#selectedNotifications').val(jQuery.toJSON(notifications));
            }

            function ToggleNotifications() {

                var container = jQuery('#gf_pesapal_notification_container');
                var isChecked = jQuery('#delaynotification').is(':checked');

                if (isChecked) {
                    container.slideDown();
                    jQuery('.gf_pesapal_notification input').prop('checked', true);
                }
                else {
                    container.slideUp();
                    jQuery('.gf_pesapal_notification input').prop('checked', false);
                }

                SaveNotifications();
            }
        </script>
        <?php

        $html .= ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function checkbox_input_change_post_status($choice, $attributes, $value, $tooltip)
    {
        $markup = $this->checkbox_input($choice, $attributes, $value, $tooltip);

        $dropdown_field = array(
            'name' => 'update_post_action',
            'choices' => array(
                array('label' => ''),
                array('label' => __('Mark Post as Draft', 'gravityformspesapal'), 'value' => 'draft'),
                array('label' => __('Delete Post', 'gravityformspesapal'), 'value' => 'delete'),

            ),
            'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
        );
        $markup .= '&nbsp;&nbsp;' . $this->settings_select($dropdown_field, false);

        return $markup;
    }

    public function option_choices()
    {
        return false;
        $option_choices = array(
            array('label' => __('Do not prompt buyer to include a shipping address.', 'gravityformspesapal'), 'name' => 'disableShipping', 'value' => ''),
            array('label' => __('Do not prompt buyer to include a note with payment.', 'gravityformspesapal'), 'name' => 'disableNote', 'value' => ''),
        );

        return $option_choices;
    }

    public function save_feed_settings($feed_id, $form_id, $settings)
    {

        //--------------------------------------------------------
        //For backwards compatibility
        $feed = $this->get_feed($feed_id);

        //Saving new fields into old field names to maintain backwards compatibility for delayed payments
        $settings['type'] = $settings['transactionType'];

        if (isset($settings['recurringAmount'])) {
            $settings['recurring_amount_field'] = $settings['recurringAmount'];
        }

        $feed['meta'] = $settings;
        $feed = apply_filters('gform_pesapal_save_config', $feed);

        //call hook to validate custom settings/meta added using gform_pesapal_action_fields or gform_pesapal_add_option_group action hooks
        $is_validation_error = apply_filters('gform_pesapal_config_validation', false, $feed);
        if ($is_validation_error) {
            //fail save
            return false;
        }

        $settings = $feed['meta'];

        //--------------------------------------------------------

        return parent::save_feed_settings($feed_id, $form_id, $settings);
    }


    //------ SENDING TO PesaPal -----------//

    public function get_a_setting($setting)
    {
        $plugin_settings = $this->get_plugin_settings();
        return isset($plugin_settings[$setting]) ? $plugin_settings[$setting] : false;
    }

    //--------- Submission Process ------
    public function confirmation($confirmation, $form, $entry, $ajax)
    {
        //show the iframe
        $submitScript = '';
        $submitScript .= '<script type="text/javascript">';
        $submitScript .= 'window.onload = function() {';
        $submitScript .= 'var form = document.getElementById("pesapal_form");';
        $submitScript .= 'form.submit();';
        $submitScript .= '}';
        $submitScript .= '</script>';

        if (empty($this->redirect_url)) {
            return $confirmation;
        }
        $toReturn = $submitScript . $this->redirect_url;

        //echo $submitScript;
        //echo($this->redirect_url);
        //die();

        $confirmation = array('redirect' => get_bloginfo('url') . '/?page=gf_pesapal_process&id=' . $entry["id"]);

        return $confirmation;
        //echo $toReturn;

    }


    public function redirect_url($feed, $submission_data, $form, $entry)
    {

        //Don't process redirect url if request is a PesaPal return
        /*
		if ( ! rgempty( 'gf_pesapal_return', $_GET ) ) {
			return false;
		}
		 * */

        //updating lead's payment_status to Processing
        GFAPI::update_entry_property($entry['id'], 'payment_status', 'Processing');

        //Getting Url (Production or Sandbox)

        $url = self::get_a_setting('mode') == 'test' ? $this->sandbox_url : $this->production_url;

        $invoice_id = apply_filters('gform_pesapal_invoice', '', $form, $entry);

        $invoice = empty($invoice_id) ? '' : "&invoice={$invoice_id}";

        //Current Currency
        $currency = GFCommon::get_currency();

        //Customer fields
        $customer_fields = $this->customer_query_string($feed, $entry);
        //Set return mode to 2 (PesaPal will post info back to page). rm=1 seems to create lots of problems with the redirect back to the site. Defaulting it to 2.
        $return_mode = '2';

        $return_url = '&return=' . urlencode($this->return_url($form['id'], $entry['id'])) . "&rm={$return_mode}";

        //Cancel URL
        $cancel_url = !empty($feed['meta']['cancelUrl']) ? '&cancel_return=' . urlencode($feed['meta']['cancelUrl']) : '';

        //Don't display note section
        $disable_note = !empty($feed['meta']['disableNote']) ? '&no_note=1' : '';

        //Don't display shipping section
        $disable_shipping = !empty($feed['meta']['disableShipping']) ? '&no_shipping=1' : '';

        //URL that will listen to notifications from PesaPal
        $ipn_url = urlencode(get_bloginfo('url') . '/?page=gf_pesapal_ipn');

        $custom_field = $entry['id'] . '|' . wp_hash($entry['id']);

        //$url .= "?notify_url={$ipn_url}&charset=UTF-8&currency_code={$currency}&custom={$custom_field}{$invoice}{$customer_fields}{$page_style}{$continue_text}{$cancel_url}{$disable_note}{$disable_shipping}{$return_url}";
        //var_dump
        //$query_string = $this->get_product_query_string($submission_data, $entry, $form, $customer_fields);
        return $ipn_url;
        //return $query_string;
        $query_string = apply_filters("gform_pesapal_query_{$form['id']}", apply_filters('gform_pesapal_query', $query_string, $form, $entry, $feed), $form, $entry, $feed);

        if (!$query_string) {
            $this->log_debug(__METHOD__ . '(): NOT sending to Pesapal: The price is either zero or the gform_pesapal_query filter was used to remove the querystring that is sent to Pesapal.');

            return '';
        }
        $url .= "?";
        $url .= $query_string;

        $url = apply_filters("gform_pesapal_request_{$form['id']}", apply_filters('gform_pesapal_request', $url, $form, $entry, $feed), $form, $entry, $feed);

        //add the bn code (build notation code)
        $url .= '&bn=Rocketgenius_SP';

        $this->log_debug(__METHOD__ . "(): Sending to Pesapal: {$url}");

       // dd($url);
        return $url;
    }

    public function get_iframe_link($submission_data, $entry, $form, $customer_fields)
    {
        //wp_die();
        $query_string = '';
        $payment_amount = rgar($submission_data, 'payment_amount');
        $setup_fee = rgar($submission_data, 'setup_fee');
        $trial_amount = rgar($submission_data, 'trial');
        $line_items = rgar($submission_data, 'line_items');
        $discounts = rgar($submission_data, 'discounts');
        $token = $params = NULL;
        $params = array();
        $new_params = array();

        $consumer_key = self::get_a_setting('pesapal_consumer_key');//Register a merchant account on

        $consumer_secret = self::get_a_setting('pesapal_consumer_secret');// Use the secret from your test
        $consumer_description = self::get_a_setting('pesapal_description');// Use the secret from your test

        $signature_method = new OAuthSignatureMethod_HMAC_SHA1();
        $mode = self::get_a_setting('mode');
        //'test' ? $this->sandbox_url : $this->production_url;
        if ($mode == 'test') {

            $iframelink = 'http://demo.pesapal.com/api/PostPesapalDirectOrderV4';
        } else {
            $iframelink = 'https://www.pesapal.com/API/PostPesapalDirectOrderV4';
        }

        $amount = number_format($payment_amount, 2);

       //dd($submission_data);
        $desc = $consumer_description;
        $type = 'MERCHANT'; //default value = MERCHANT
        $reference = $entry['id'];//unique order id of the transaction, generated by merchant
        $first_name = $customer_fields['first_name'];
        $last_name = $_POST['last_name'];
        $email = $customer_fields['email'];
        $phonenumber = '';
        $currency = $submission_data['pesapal_currency'];
        $callback_url = get_bloginfo('url') . '/?page=gf_pesapal_callback';
        $post_xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?><PesapalDirectOrderInfo xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" Amount=\"" . $amount . "\" Description=\"" . $desc . "\" Type=\"" . $type . "\" Reference=\"" . $reference . "\" FirstName=\"" . $first_name . "\" LastName=\"" . $last_name . "\" Email=\"" . $email . "\" PhoneNumber=\"" . $phonenumber . "\"  Currency=\"" . $currency . "\" xmlns=\"http://www.pesapal.com\" />";
        $post_xml = htmlentities($post_xml);

        $consumer = new OAuthConsumer($consumer_key, $consumer_secret);

//post transaction to pesapal
        $iframe_src = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $iframelink, $params);
        $iframe_src->set_parameter("oauth_callback", $callback_url);
        $iframe_src->set_parameter("pesapal_request_data", $post_xml);
        $iframe_src->sign_request($signature_method, $consumer, $token);

        //dd($iframe_src);

        gform_update_meta($entry['id'], 'payment_amount', $payment_amount);
        return $iframe_src;

    }

    public function customer_query_string($feed, $lead)
    {
        $fields = '';
        $params = array();
        foreach ($this->get_customer_fields() as $field) {
            $field_id = $feed['meta'][$field['meta_name']];
            $value = rgar($lead, $field_id);

            if ($field['name'] == 'country') {
                $value = class_exists('GF_Field_Address') ? GF_Fields::get('address')->get_country_code($value) : GFCommon::get_country_code($value);
            } elseif ($field['name'] == 'state') {
                $value = class_exists('GF_Field_Address') ? GF_Fields::get('address')->get_us_state_code($value) : GFCommon::get_us_state_code($value);
            }

            if (!empty($value)) {
                $fields .= "&{$field['name']}=" . urlencode($value);

                $params[$field['name']] = $value;
            }
        }

        return $params;
        //return $fields;
    }

    public function return_url($form_id, $lead_id)
    {
        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        $server_port = apply_filters('gform_pesapal_return_url_port', $_SERVER['SERVER_PORT']);

        if ($server_port != '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $ids_query = "ids={$form_id}|{$lead_id}";
        $ids_query .= '&hash=' . wp_hash($ids_query);

        return add_query_arg('gf_pesapal_return', base64_encode($ids_query), $pageURL);
    }

    public static function send_notifications($form, $entry, $event = 'form_submission', $data = array())
    {

        if (rgempty('notifications', $form) || !is_array($form['notifications'])) {
            return array();
        }

        $entry_id = rgar($entry, 'id');
        GFCommon::log_debug("GFAPI::send_notifications(): Gathering notifications for {$event} event for entry #{$entry_id}.");

        $notifications_to_send = array();

        //running through filters that disable form submission notifications
        foreach ($form['notifications'] as $notification) {
            if (rgar($notification, 'event') != $event) {
                continue;
            }

            if ($event == 'form_submission') {
                if (rgar($notification, 'type') == 'user' && gf_apply_filters(array('gform_disable_user_notification', $form['id']), false, $form, $entry)) {
                    GFCommon::log_debug("GFAPI::send_notifications(): Notification is disabled by gform_disable_user_notification hook, not including notification (#{$notification['id']} - {$notification['name']}).");
                    //skip user notification if it has been disabled by a hook
                    continue;
                } elseif (rgar($notification, 'type') == 'admin' && gf_apply_filters(array('gform_disable_admin_notification', $form['id']), false, $form, $entry)) {
                    GFCommon::log_debug("GFAPI::send_notifications(): Notification is disabled by gform_disable_admin_notification hook, not including notification (#{$notification['id']} - {$notification['name']}).");
                    //skip admin notification if it has been disabled by a hook
                    continue;
                }
            }

            $notifications_to_send[] = $notification['id'];
        }

        GFCommon::send_notifications($notifications_to_send, $form, $entry, true, $event, $data);
    }

    public static function maybe_thankyou_page()
    {
        $instance = self::get_instance();

        if (!$instance->is_gravityforms_supported()) {
            return;
        }

        if ($str = rgget('gf_pesapal_return')) {
            $str = base64_decode($str);

            parse_str($str, $query);
            if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
                list($form_id, $lead_id) = explode('|', $query['ids']);

                $form = GFAPI::get_form($form_id);
                $lead = GFAPI::get_entry($lead_id);
                //send notifications that were earlier disabled
                self::send_notifications($form, $lead);

                if (!class_exists('GFFormDisplay')) {
                    require_once(GFCommon::get_base_path() . '/form_display.php');
                }

                $confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);

                if (is_array($confirmation) && isset($confirmation['redirect'])) {
                    header("Location: {$confirmation['redirect']}");
                    exit;
                }

                GFFormDisplay::$submission[$form_id] = array('is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead);
            }
        }
    }

    public function get_customer_fields()
    {
        return array(
            array('name' => 'first_name', 'label' => 'First Name', 'meta_name' => 'billingInformation_firstName'),
            array('name' => 'last_name', 'label' => 'Last Name', 'meta_name' => 'billingInformation_lastName'),
            array('name' => 'email', 'label' => 'Email', 'meta_name' => 'billingInformation_email'),
            array('name' => 'address1', 'label' => 'Address', 'meta_name' => 'billingInformation_address'),
            array('name' => 'company', 'label' => 'Company', 'meta_name' => 'billingInformation_company'),
            array('name' => 'address2', 'label' => 'Address 2', 'meta_name' => 'billingInformation_address2'),
            array('name' => 'city', 'label' => 'City', 'meta_name' => 'billingInformation_city'),
            array('name' => 'state', 'label' => 'State', 'meta_name' => 'billingInformation_state'),
            array('name' => 'zip', 'label' => 'Zip', 'meta_name' => 'billingInformation_zip'),
            array('name' => 'country', 'label' => 'Country', 'meta_name' => 'billingInformation_country'),
        );
    }

    public function convert_interval($interval, $to_type)
    {
        //convert single character into long text for new feed settings or convert long text into single character for sending to pesapal
        //$to_type: text (change character to long text), OR char (change long text to character)
        if (empty($interval)) {
            return '';
        }

        $new_interval = '';
        if ($to_type == 'text') {
            //convert single char to text
            switch (strtoupper($interval)) {
                case 'D' :
                    $new_interval = 'day';
                    break;
                case 'W' :
                    $new_interval = 'week';
                    break;
                case 'M' :
                    $new_interval = 'month';
                    break;
                case 'Y' :
                    $new_interval = 'year';
                    break;
                default :
                    $new_interval = $interval;
                    break;
            }
        } else {
            //convert text to single char
            switch (strtolower($interval)) {
                case 'day' :
                    $new_interval = 'D';
                    break;
                case 'week' :
                    $new_interval = 'W';
                    break;
                case 'month' :
                    $new_interval = 'M';
                    break;
                case 'year' :
                    $new_interval = 'Y';
                    break;
                default :
                    $new_interval = $interval;
                    break;
            }
        }

        return $new_interval;
    }

    public function delay_post($is_disabled, $form, $entry)
    {

        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (!$feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        return !rgempty('delayPost', $feed['meta']);
    }

    public function delay_notification($is_disabled, $notification, $form, $entry)
    {

        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (!$feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        $selected_notifications = is_array(rgar($feed['meta'], 'selectedNotifications')) ? rgar($feed['meta'], 'selectedNotifications') : array();

        return isset($feed['meta']['delayNotification']) && in_array($notification['id'], $selected_notifications) ? true : $is_disabled;
    }


    //------- PROCESSING PesaPal IPN (Callback) -----------//

    public function callback()
    {

        if (!$this->is_gravityforms_supported()) {
            return false;
        }

        $this->log_debug(__METHOD__ . '(): IPN request received. Starting to process => ' . print_r($_REQUEST, true));


        //------- Send request to pesapal and verify it has not been spoofed ---------------------//
        $is_verified = $this->verify_pesapal_ipn();
        if (is_wp_error($is_verified)) {
            $this->log_error(__METHOD__ . '(): IPN verification failed with an error. Aborting with a 500 error so that IPN is resent.');
            return new WP_Error('IPNVerificationError', 'There was an error when verifying the IPN message with PesaPal', array('status_header' => 500));
        } else if (!$is_verified) {
            $this->log_error(__METHOD__ . '(): IPN request could not be verified by PesaPal. Aborting.');
            return false;
        }

        $this->log_debug(__METHOD__ . '(): IPN message successfully verified by PesaPal');


        //------ Getting entry related to this IPN ----------------------------------------------//
        //dd(rgpost( 'req_reference_number' ));
        $entry_id = rgget('pesapal_merchant_reference');

        /** @var  $entry GFEntryDetail*/
        $entry = GFAPI::get_entry($entry_id);

        //Ignore orphan IPN messages (ones without an entry)
        if (!$entry) {
            $this->log_error(__METHOD__ . '(): Entry could not be found. Aborting.');

            return false;
        }
        $this->log_debug(__METHOD__ . '(): Entry has been found => ' . print_r($entry, true));

        if ($entry['status'] == 'spam') {
            $this->log_error(__METHOD__ . '(): Entry is marked as spam. Aborting.');

            return false;
        }


        //------ Getting feed related to this IPN ------------------------------------------//
        $feed = $this->get_payment_feed($entry);

        //Ignore IPN messages from forms that are no longer configured with the PesaPal add-on
        if (!$feed || !rgar($feed, 'is_active')) {
            $this->log_error(__METHOD__ . "(): Form no longer is configured with PesaPal Addon. Form ID: {$entry['form_id']}. Aborting.");

            return false;
        }
        $this->log_debug(__METHOD__ . "(): Form {$entry['form_id']} is properly configured.");


        //----- Making sure this IPN can be processed -------------------------------------//
        if (!$this->can_process_ipn($feed, $entry)) {
            $this->log_debug(__METHOD__ . '(): IPN cannot be processed.');

            return false;
        }

        //$action =$this->process_pesapal_ipn( $feed,$entry);

        //----- Processing IPN ------------------------------------------------------------//
        $this->log_debug(__METHOD__ . '(): Processing IPN...');
        /**
         *
         */

        $action =$this->process_pesapal_ipn( $feed,$entry);

        $this->log_debug(__METHOD__ . '(): IPN processing complete.');


        if (rgempty('entry_id', $action)) {
            $this->log_debug(__METHOD__ . '(): empty action retuned => ');
            return false;
        }
        $this->log_debug(__METHOD__ . '(): action returned => ' . print_r($action, true));
        return $action;

    }

    public function get_payment_feed($entry, $form = false)
    {

        $feed = parent::get_payment_feed($entry, $form);

        if (empty($feed) && !empty($entry['id'])) {
            //looking for feed created by legacy versions
            $feed = $this->get_pesapal_feed_by_entry($entry['id']);
        }

        $feed = apply_filters('gform_pesapal_get_payment_feed', $feed, $entry, $form);

        return $feed;
    }

    private function get_pesapal_feed_by_entry($entry_id)
    {

        $feed_id = gform_get_meta($entry_id, 'pesapal_feed_id');
        $feed = $this->get_feed($feed_id);

        return !empty($feed) ? $feed : false;
    }

    public function post_callback($callback_action, $callback_result)
    {
        if (is_wp_error($callback_action) || !$callback_action) {
            return false;
        }

        //run the necessary hooks
        $entry = GFAPI::get_entry($callback_action['entry_id']);
        $feed = $this->get_payment_feed($entry);
        $transaction_id = rgar($callback_action, 'transaction_id');
        $amount = rgar($callback_action, 'amount');
        $subscriber_id = rgar($callback_action, 'subscriber_id');
        $pending_reason = rgpost('pending_reason');
        $reason = rgpost('reason_code');
        $status = rgpost('payment_status');
        $txn_type = rgpost('txn_type');
        $parent_txn_id = rgpost('parent_txn_id');

        //run gform_pesapal_fulfillment only in certain conditions
        if (rgar($callback_action, 'ready_to_fulfill') && !rgar($callback_action, 'abort_callback')) {
            $this->log_debug(__METHOD__ . '(): Callback processing send ing to fulfill_order.');
            $this->fulfill_order($entry, $transaction_id, $amount, $feed);
        } else {
            if (rgar($callback_action, 'abort_callback')) {
                $this->log_debug(__METHOD__ . '(): Callback processing was aborted. Not fulfilling entry.');
            } else {
                $this->log_debug(__METHOD__ . '(): Entry is already fulfilled or not ready to be fulfilled, not running gform_pesapal_fulfillment hook.');
            }
        }

        do_action('gform_post_payment_status', $feed, $entry, $status, $transaction_id, $subscriber_id, $amount, $pending_reason, $reason);
        if (has_filter('gform_post_payment_status')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_post_payment_status.');
        }

        do_action('gform_pesapal_ipn_' . $txn_type, $entry, $feed, $status, $txn_type, $transaction_id, $parent_txn_id, $subscriber_id, $amount, $pending_reason, $reason);
        if (has_filter('gform_pesapal_ipn_' . $txn_type)) {
            $this->log_debug(__METHOD__ . "(): Executing functions hooked to gform_pesapal_ipn_{$txn_type}.");
        }

        do_action('gform_pesapal_post_ipn', $_POST, $entry, $feed, false);
        if (has_filter('gform_pesapal_post_ipn')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_pesapal_post_ipn.');
        }
    }

    private function verify_pesapal_ipn()
    {
        return true;
    }

    public function maybe_process_callback()
    {
        //dd($_POST);
        $page_slice = rgget('page');


        // ignoring requests that are not this addon's callbacks
        if ($page_slice != 'gf_pesapal_ipn' && $page_slice != 'gf_pesapal_process' && $page_slice != 'gf_pesapal_callback') {

            return;
        }
        if ($page_slice == 'gf_pesapal_ipn') {
            // returns either false or an array of data about the callback request which payment add-on will then use
            // to generically process the callback data
            $this->log_debug(__METHOD__ . '(): Initializing callback processing for: ' . $this->_slug);
            $callback_action = $this->callback();
            $this->post_callback($callback_action, $_POST);
            die();
        } elseif ($page_slice == 'gf_pesapal_callback') {

            $this->process_callback();

            return;
        } elseif ($page_slice == 'gf_pesapal_process') {

            $this->showIframe();
            die;
        } else {

        }
    }

    public function showIframe()
    {
        $entry = RGFormsModel::get_lead($_GET['id']);
        $form = GFFormsModel::get_form_meta($entry['form_id']);
        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);
        $customer_fields = $this->customer_query_string($feed, $entry);
        //['pesapal_currency']
	    GFAPI::update_entry_property($entry['id'], 'currency', $submission_data['pesapal_currency']);
        $iframe_src = $this->get_iframe_link($submission_data, $entry, $form, $customer_fields);
        include_once('iframe.php');
    }

    public function process_callback()
    {
        // dd($_REQUEST);
        //------ Getting entry related to this Pesapal return ----------------------------------------------//
        //dd(rgpost( 'req_reference_number' ));
        $entry_id = rgget('pesapal_merchant_reference');
        $pesapal_transaction_tracking_id = rgget('pesapal_transaction_tracking_id');
        gform_update_meta($entry_id, 'pesapal_transaction_tracking_id', $pesapal_transaction_tracking_id);

        get_template_part('header');
        include_once 'return_message.php';
        get_template_part('footer');
        die();

    }
    public function process_pesapal_ipn($feed, $entry)
    {

        $action = array();
        $transaction_id=uniqid();
        $amount=$amount_sent = gform_get_meta($entry['id'], 'payment_amount');

        $consumer_key = self::get_a_setting('pesapal_consumer_key');//Register a merchant account on

        $consumer_secret = self::get_a_setting('pesapal_consumer_secret');// Use the secret from your test
        $signature_method = new OAuthSignatureMethod_HMAC_SHA1();
        $mode = self::get_a_setting('mode');
        //'test' ? $this->sandbox_url : $this->production_url;
        if ($mode == 'test') {

            $statusrequestAPI = 'https://demo.pesapal.com/api/querypaymentstatus';
            //$statusrequestAPI = 'https://demo.pesapal.com/api/querypaymentdetails';
        } else {

            $statusrequestAPI = 'https://www.pesapal.com/api/querypaymentstatus';

        }
        $pesapalNotification = $_GET['pesapal_notification_type'];
        $pesapalTrackingId = $_GET['pesapal_transaction_tracking_id'];
        $pesapal_merchant_reference = $_GET['pesapal_merchant_reference'];
        $signature_method = new OAuthSignatureMethod_HMAC_SHA1();

        if ($pesapalNotification == "CHANGE" && $pesapalTrackingId != '') {
            $token = $params = NULL;
            $consumer = new OAuthConsumer($consumer_key, $consumer_secret);

            //get transaction status
            $request_status = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $statusrequestAPI, $params);
            $request_status->set_parameter("pesapal_merchant_reference", $pesapal_merchant_reference);
            $request_status->set_parameter("pesapal_transaction_tracking_id", $pesapalTrackingId);
            $request_status->sign_request($signature_method, $consumer, $token);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $request_status);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            if (defined('CURL_PROXY_REQUIRED')) if (CURL_PROXY_REQUIRED == 'True') {
                $proxy_tunnel_flag = (defined('CURL_PROXY_TUNNEL_FLAG') && strtoupper(CURL_PROXY_TUNNEL_FLAG) == 'FALSE') ? false : true;
                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, $proxy_tunnel_flag);
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                curl_setopt($ch, CURLOPT_PROXY, CURL_PROXY_SERVER_DETAILS);
            }

            $response = curl_exec($ch);


            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $raw_header = substr($response, 0, $header_size - 4);
            $headerArray = explode("\r\n\r\n", $raw_header);
            $header = $headerArray[count($headerArray) - 1];

            //transaction status
            $elements = preg_split("/=/", substr($response, $header_size));
            //print_r($elements);
            $status = $elements[1];
            //the status PENDING|COMPLETED|FAILED|INVALID
            curl_close($ch);
            switch ($status) {

                case 'PENDING' :
                    $action['id'] = $transaction_id . '_' . $status;
                    $action['type'] = 'add_pending_payment';
                    $action['transaction_id'] = $transaction_id;
                    $action['entry_id'] = $entry['id'];
                    $action['amount'] = $amount;
                    GFPaymentAddOn::add_note($entry['id'],'Payment is still pending');
                    $this->notifyPesapal($pesapalNotification,$pesapalTrackingId,$pesapal_merchant_reference);
                    return $action;
                    break;

                case 'COMPLETED' :
                    $action['id'] = $transaction_id . '_' . $status;
                    $action['type'] = 'complete_payment';
                    $action['transaction_id'] = $transaction_id;
                    $action['amount'] = $amount;
                    $action['entry_id'] = $entry['id'];
                    $action['payment_date'] = gmdate('y-m-d H:i:s');
                    $action['payment_method'] = 'PesaPal';
                    $action['ready_to_fulfill'] = !$entry['is_fulfilled'] ? true : false;
                    //$action['abort_callback'] = true;
                    GFAPI::update_entry_property($entry['id'], 'payment_status', 'Paid');
                    GFPaymentAddOn::insert_transaction($entry['id'], 'payment', $transaction_id, $amount);
                    GFPaymentAddOn::add_note($entry['id'], sprintf(__('Payment amount (%s) received. Entry will marked as Approved. Transaction Id: %s', 'gravityformspesapal'), GFCommon::to_money($amount, $entry['currency']), $transaction_id));
                    $this->notifyPesapal($pesapalNotification,$pesapalTrackingId,$pesapal_merchant_reference);
                    return $action;
                    break;

                case 'FAILED' :
                    $action['id'] = $transaction_id . '_' . $status;
                    $action['type'] = 'fail_payment';
                    $action['transaction_id'] = $transaction_id;
                    $action['entry_id'] = $entry['id'];
                    $action['amount'] = $amount;
                    GFPaymentAddOn::add_note($entry['id'],'Payment FAILED');
                    $this->notifyPesapal($pesapalNotification,$pesapalTrackingId,$pesapal_merchant_reference);
                    return $action;
                    break;

                case 'INVALID' :
                    $action['id'] = $transaction_id . '_' . $status;
                    $action['type'] = 'fail_payment';
                    $action['transaction_id'] = $transaction_id;
                    $action['entry_id'] = $entry['id'];
                    $action['amount'] = $amount;
                    GFPaymentAddOn::add_note($entry['id'],'Payment is invalid');
                    $this->notifyPesapal($pesapalNotification,$pesapalTrackingId,$pesapal_merchant_reference);
                    return $action;
                    break;

            }



        }
    }
    public function notifyPesapal($pesapalNotification,$pesapalTrackingId,$pesapal_merchant_reference){
        $resp = "pesapal_notification_type=$pesapalNotification&pesapal_transaction_tracking_id=$pesapalTrackingId&pesapal_merchant_reference=$pesapal_merchant_reference";
        ob_start();
        echo $resp;
        ob_flush();
        exit;
    }

    public function is_callback_valid()
    {

        if (rgget('page') != 'gf_pesapal_ipn' && rgget('page') != 'gf_pesapal_process') {
            return false;
        }


        return true;
    }

    /**
     * @param $config feed 1
     * @param $entry 2
     * @param $status 3
     * @param $transaction_type 4
     * @param $transaction_id 5
     * @param $parent_transaction_id 6
     * @param $subscriber_id 7
     * @param $amount 8
     * @param $pending_reason 9
     * @param $reason 10
     * @param $recurring_amount 11
     * @return array
     */
    private function process_ipn($config, $entry, $status, $transaction_type, $transaction_id, $parent_transaction_id, $subscriber_id, $amount, $pending_reason, $reason, $recurring_amount)
    {
        $this->log_debug(__METHOD__ . "(): Payment status: {$status} - Transaction Type: {$transaction_type} - Transaction ID: {$transaction_id} - Parent Transaction: {$parent_transaction_id} - Subscriber ID: {$subscriber_id} - Amount: {$amount} - Pending reason: {$pending_reason} - Reason: {$reason}");

        $action = array();
        //pp($status);

        //handles products and donation
        switch ($status) {
            case 'ACCEPT' :
                //creates transaction
                $action['id'] = $transaction_id . '_' . $status;
                $action['type'] = 'complete_payment';
                $action['transaction_id'] = $transaction_id;
                $action['amount'] = $amount;
                $action['entry_id'] = $entry['id'];
                $action['payment_date'] = gmdate('y-m-d H:i:s');
                $action['payment_method'] = 'PesaPal';
                $action['ready_to_fulfill'] = !$entry['is_fulfilled'] ? true : false;

                if (!$this->is_valid_initial_payment_amount($entry['id'], $amount)) {
                    //create note and transaction
                    $this->log_debug(__METHOD__ . '(): Payment amount does not match product price. Entry will not be marked as Approved.');
                    GFPaymentAddOn::add_note($entry['id'], sprintf(__('Payment amount (%s) does not match product price. Entry will not be marked as Approved. Transaction Id: %s', 'gravityformspesapal'), GFCommon::to_money($amount, $entry['currency']), $transaction_id));
                    GFPaymentAddOn::insert_transaction($entry['id'], 'payment', $transaction_id, $amount);

                    $action['abort_callback'] = true;
                } else {
                    GFAPI::update_entry_property($entry['id'], 'payment_status', 'Paid');
                    GFPaymentAddOn::insert_transaction($entry['id'], 'payment', $transaction_id, $amount);
                    GFPaymentAddOn::add_note($entry['id'], sprintf(__('Payment amount (%s) received. Entry will marked as Approved. Transaction Id: %s', 'gravityformspesapal'), GFCommon::to_money($amount, $entry['currency']), $transaction_id));

                }

                return $action;
                break;

            case 'DECLINE' :
                $action['id'] = $transaction_id . '_' . $status;
                $action['type'] = 'fail_payment';
                $action['transaction_id'] = $transaction_id;
                $action['entry_id'] = $entry['id'];
                $action['amount'] = $amount;

                return $action;
                break;
        }


    }


    public function can_process_ipn($feed, $entry)
    {

        $this->log_debug(__METHOD__ . '(): Checking that IPN can be processed.');
        //Only process test messages coming fron SandBox and only process production messages coming from production PesaPal
        if (($feed['meta']['mode'] == 'test' && !rgpost('test_ipn')) || ($feed['meta']['mode'] == 'production' && rgpost('test_ipn'))) {
            $this->log_error(__METHOD__ . "(): Invalid test/production mode. IPN message mode (test/production) does not match mode configured in the PesaPal feed. Configured Mode: {$feed['meta']['mode']}. IPN test mode: " . rgpost('test_ipn'));

            return false;
        }

        //Check business email to make sure it matches
        $business_email = apply_filters('gform_pesapal_business_email', $feed['meta']['pesapalEmail'], $feed, $entry);

        $recipient_email = rgempty('business') ? rgpost('receiver_email') : rgpost('business');
        if (strtolower(trim($recipient_email)) != strtolower(trim($business_email))) {
            $this->log_error(__METHOD__ . '(): PesaPal email does not match. Email entered on PesaPal feed:' . strtolower(trim($business_email)) . ' - Email from IPN message: ' . $recipient_email);

            return false;
        }

        //Pre IPN processing filter. Allows users to cancel IPN processing
        $cancel = apply_filters('gform_pesapal_pre_ipn', false, $_POST, $entry, $feed);

        if ($cancel) {
            $this->log_debug(__METHOD__ . '(): IPN processing cancelled by the gform_pesapal_pre_ipn filter. Aborting.');
            do_action('gform_pesapal_post_ipn', $_POST, $entry, $feed, true);
            if (has_filter('gform_pesapal_post_ipn')) {
                $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_pesapal_post_ipn.');
            }

            return false;
        }

        return true;
    }

    //------- AJAX FUNCTIONS ------------------//

    public function init_ajax()
    {

        parent::init_ajax();

        add_action('wp_ajax_gf_dismiss_pesapal_menu', array($this, 'ajax_dismiss_menu'));

    }

    //------- ADMIN FUNCTIONS/HOOKS -----------//

    public function init_admin()
    {

        parent::init_admin();

        //add actions to allow the payment status to be modified
        add_action('gform_payment_status', array($this, 'admin_edit_payment_status'), 3, 3);

        if (version_compare(GFCommon::$version, '1.8.17.4', '<')) {
            //using legacy hook
            add_action('gform_entry_info', array($this, 'admin_edit_payment_status_details'), 4, 2);
        } else {
            add_action('gform_payment_date', array($this, 'admin_edit_payment_date'), 3, 3);
            add_action('gform_payment_transaction_id', array($this, 'admin_edit_payment_transaction_id'), 3, 3);
            add_action('gform_payment_amount', array($this, 'admin_edit_payment_amount'), 3, 3);
        }

        add_action('gform_after_update_entry', array($this, 'admin_update_payment'), 4, 2);

        add_filter('gform_addon_navigation', array($this, 'maybe_create_menu'));
    }

    public function maybe_create_menu($menus)
    {
        $current_user = wp_get_current_user();
        $dismiss_pesapal_menu = get_metadata('user', $current_user->ID, 'dismiss_pesapal_menu', true);
        if ($dismiss_pesapal_menu != '1') {
            $menus[] = array('name' => $this->_slug, 'label' => $this->get_short_title(), 'callback' => array($this, 'temporary_plugin_page'), 'permission' => $this->_capabilities_form_settings);
        }

        return $menus;
    }

    public function ajax_dismiss_menu()
    {

        $current_user = wp_get_current_user();
        update_metadata('user', $current_user->ID, 'dismiss_pesapal_menu', '1');
    }

    public function temporary_plugin_page()
    {
        $current_user = wp_get_current_user();
        ?>
        <script type="text/javascript">
            function dismissMenu() {
                jQuery('#gf_spinner').show();
                jQuery.post(ajaxurl, {
                        action: "gf_dismiss_pesapal_menu"
                    },
                    function (response) {
                        document.location.href = '?page=gf_edit_forms';
                        jQuery('#gf_spinner').hide();
                    }
                );

            }
        </script>

        <div class="wrap about-wrap">
            <h1><?php _e('PesaPal Add-On v2.0', 'gravityformspesapal') ?></h1>
            <div
                class="about-text"><?php _e('Thank you for updating! The new version of the Gravity Forms PesaPal Standard Add-On makes changes to how you manage your PesaPal integration.', 'gravityformspesapal') ?></div>
            <div class="changelog">
                <hr/>
                <div class="feature-section col two-col">
                    <div class="col-1">
                        <h3><?php _e('Manage PesaPal Contextually', 'gravityformspesapal') ?></h3>
                        <p><?php _e('PesaPal Feeds are now accessed via the PesaPal sub-menu within the Form Settings for the Form you would like to integrate PesaPal with.', 'gravityformspesapal') ?></p>
                    </div>
                    <div class="col-2 last-feature">
                        <img src="http://gravityforms.s3.amazonaws.com/webimages/PayPalNotice/NewPayPal2.png">
                    </div>
                </div>

                <hr/>

                <form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
                    <input type="checkbox" name="dismiss_pesapal_menu" value="1" onclick="dismissMenu();">
                    <label><?php _e('I understand this change, dismiss this message!', 'gravityformspesapal') ?></label>
                    <img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif' ?>"
                         alt="<?php _e('Please wait...', 'gravityformspesapal') ?>" style="display:none;"/>
                </form>

            </div>
        </div>
        <?php
    }

    public function admin_edit_payment_status($payment_status, $form, $lead)
    {
        //allow the payment status to be edited when for pesapal, not set to Approved/Paid, and not a subscription
        if (!$this->is_payment_gateway($lead['id']) || strtolower(rgpost('save')) <> 'edit' || $payment_status == 'Approved' || $payment_status == 'Paid' || rgar($lead, 'transaction_type') == 2) {
            return $payment_status;
        }

        //create drop down for payment status
        $payment_string = gform_tooltip('pesapal_edit_payment_status', '', true);
        $payment_string .= '<select id="payment_status" name="payment_status">';
        $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
        $payment_string .= '<option value="Paid">Paid</option>';
        $payment_string .= '</select>';

        return $payment_string;
    }

    public function admin_edit_payment_date($payment_date, $form, $lead)
    {
        //allow the payment status to be edited when for pesapal, not set to Approved/Paid, and not a subscription
        if (!$this->is_payment_gateway($lead['id']) || strtolower(rgpost('save')) <> 'edit') {
            return $payment_date;
        }

        $payment_date = $lead['payment_date'];
        if (empty($payment_date)) {
            $payment_date = gmdate('y-m-d H:i:s');
        }

        $input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

        return $input;
    }

    public function admin_edit_payment_transaction_id($transaction_id, $form, $lead)
    {
        //allow the payment status to be edited when for pesapal, not set to Approved/Paid, and not a subscription
        if (!$this->is_payment_gateway($lead['id']) || strtolower(rgpost('save')) <> 'edit') {
            return $transaction_id;
        }

        $input = '<input type="text" id="pesapal_transaction_id" name="pesapal_transaction_id" value="' . $transaction_id . '">';

        return $input;
    }

    public function admin_edit_payment_amount($payment_amount, $form, $lead)
    {

        //allow the payment status to be edited when for pesapal, not set to Approved/Paid, and not a subscription
        if (!$this->is_payment_gateway($lead['id']) || strtolower(rgpost('save')) <> 'edit') {
            return $payment_amount;
        }

        if (empty($payment_amount)) {
            $payment_amount = GFCommon::get_order_total($form, $lead);
        }

        $input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

        return $input;
    }


    public function admin_edit_payment_status_details($form_id, $lead)
    {

        $form_action = strtolower(rgpost('save'));
        if (!$this->is_payment_gateway($lead['id']) || $form_action <> 'edit') {
            return;
        }

        //get data from entry to pre-populate fields
        $payment_amount = rgar($lead, 'payment_amount');
        if (empty($payment_amount)) {
            $form = GFFormsModel::get_form_meta($form_id);
            $payment_amount = GFCommon::get_order_total($form, $lead);
        }
        $transaction_id = rgar($lead, 'transaction_id');
        $payment_date = rgar($lead, 'payment_date');
        if (empty($payment_date)) {
            $payment_date = gmdate('y-m-d H:i:s');
        }

        //display edit fields
        ?>
        <div id="edit_payment_status_details" style="display:block">
            <table>
                <tr>
                    <td colspan="2"><strong>Payment Information</strong></td>
                </tr>

                <tr>
                    <td>Date:<?php gform_tooltip('pesapal_edit_payment_date') ?></td>
                    <td>
                        <input type="text" id="payment_date" name="payment_date" value="<?php echo $payment_date ?>">
                    </td>
                </tr>
                <tr>
                    <td>Amount:<?php gform_tooltip('pesapal_edit_payment_amount') ?></td>
                    <td>
                        <input type="text" id="payment_amount" name="payment_amount" class="gform_currency"
                               value="<?php echo $payment_amount ?>">
                    </td>
                </tr>
                <tr>
                    <td nowrap>Transaction ID:<?php gform_tooltip('pesapal_edit_payment_transaction_id') ?></td>
                    <td>
                        <input type="text" id="pesapal_transaction_id" name="pesapal_transaction_id"
                               value="<?php echo $transaction_id ?>">
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function admin_update_payment($form, $lead_id)
    {
        check_admin_referer('gforms_save_entry', 'gforms_save_entry');

        //update payment information in admin, need to use this function so the lead data is updated before displayed in the sidebar info section
        $form_action = strtolower(rgpost('save'));
        if (!$this->is_payment_gateway($lead_id) || $form_action <> 'update') {
            return;
        }
        //get lead
        $lead = GFFormsModel::get_lead($lead_id);

        //check if current payment status is processing
        if ($lead['payment_status'] != 'Processing')
            return;

        //get payment fields to update
        $payment_status = rgpost('payment_status');
        //when updating, payment status may not be editable, if no value in post, set to lead payment status
        if (empty($payment_status)) {
            $payment_status = $lead['payment_status'];
        }

        $payment_amount = GFCommon::to_number(rgpost('payment_amount'));
        $payment_transaction = rgpost('pesapal_transaction_id');
        $payment_date = rgpost('payment_date');
        if (empty($payment_date)) {
            $payment_date = gmdate('y-m-d H:i:s');
        } else {
            //format date entered by user
            $payment_date = date('Y-m-d H:i:s', strtotime($payment_date));
        }

        global $current_user;
        $user_id = 0;
        $user_name = 'System';
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $lead['payment_status'] = $payment_status;
        $lead['payment_amount'] = $payment_amount;
        $lead['payment_date'] = $payment_date;
        $lead['transaction_id'] = $payment_transaction;

        // if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
        if (($payment_status == 'Approved' || $payment_status == 'Paid') && !$lead['is_fulfilled']) {
            $action['id'] = $payment_transaction;
            $action['type'] = 'complete_payment';
            $action['transaction_id'] = $payment_transaction;
            $action['amount'] = $payment_amount;
            $action['entry_id'] = $lead['id'];

            $this->complete_payment($lead, $action);
            $this->fulfill_order($lead, $payment_transaction, $payment_amount);
        }
        //update lead, add a note
        GFAPI::update_entry($lead);
        GFFormsModel::add_note($lead['id'], $user_id, $user_name, sprintf(__('Payment information was manually updated. Status: %s. Amount: %s. Transaction Id: %s. Date: %s', 'gravityformspesapal'), $lead['payment_status'], GFCommon::to_money($lead['payment_amount'], $lead['currency']), $payment_transaction, $lead['payment_date']));
    }

    public function fulfill_order(&$entry, $transaction_id, $amount, $feed = null)
    {
        $this->log_debug(__METHOD__ . '(): function called.');

        if (!$feed) {
            $feed = $this->get_payment_feed($entry);
        }

        $form = GFFormsModel::get_form_meta($entry['form_id']);
        if (rgars($feed, 'meta/delayPost')) {
            $this->log_debug(__METHOD__ . '(): Creating post.');
            $entry['post_id'] = GFFormsModel::create_post($form, $entry);
            $this->log_debug(__METHOD__ . '(): Post created.');
        }

        if (rgars($feed, 'meta/delayNotification')) {
            //sending delayed notifications
            $this->log_debug(__METHOD__ . '(): Delayed notifications need to be sent .');
            $notifications = rgars($feed, 'meta/selectedNotifications');
            GFCommon::send_notifications($notifications, $form, $entry, true, 'form_submission');
        }

        do_action('gform_pesapal_fulfillment', $entry, $feed, $transaction_id, $amount);
        if (has_filter('gform_pesapal_fulfillment')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_pesapal_fulfillment.');
        }

    }

    private function is_valid_initial_payment_amount($entry_id, $amount_paid)
    {

        //get amount initially sent to pesapal
        $amount_sent = gform_get_meta($entry_id, 'payment_amount');
        if (empty($amount_sent)) {
            return true;
        }

        $epsilon = 0.00001;
        $is_equal = abs(floatval($amount_paid) - floatval($amount_sent)) < $epsilon;
        $is_greater = floatval($amount_paid) > floatval($amount_sent);

        //initial payment is valid if it is equal to or greater than product/subscription amount
        if ($is_equal || $is_greater) {
            return true;
        }

        return false;

    }

    public function pesapal_fulfillment($entry, $pesapal_config, $transaction_id, $amount)
    {
        //no need to do anything for pesapal when it runs this function, ignore
        return false;
    }

    //------ FOR BACKWARDS COMPATIBILITY ----------------------//

    //Change data when upgrading from legacy pesapal
    public function upgrade($previous_version)
    {
        if (empty($previous_version)) {
            $previous_version = get_option('gf_pesapal_version');
        }
        $previous_is_pre_addon_framework = !empty($previous_version) && version_compare($previous_version, '2.0.dev1', '<');

        if ($previous_is_pre_addon_framework) {

            //copy plugin settings
            $this->copy_settings();

            //copy existing feeds to new table
            $this->copy_feeds();

            //copy existing pesapal transactions to new table
            $this->copy_transactions();

            //updating payment_gateway entry meta to 'gravityformspesapal' from 'pesapal'
            $this->update_payment_gateway();

            //updating entry status from 'Approved' to 'Paid'
            $this->update_lead();

        }
    }

    public function update_feed_id($old_feed_id, $new_feed_id)
    {
        global $wpdb;
        $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='pesapal_feed_id' AND meta_value=%s", $new_feed_id, $old_feed_id);
        $wpdb->query($sql);
    }

    public function add_legacy_meta($new_meta, $old_feed)
    {

        $known_meta_keys = array(
            'email', 'mode', 'type', 'style', 'continue_text', 'cancel_url', 'disable_note', 'disable_shipping', 'recurring_amount_field', 'recurring_times',
            'recurring_retry', 'billing_cycle_number', 'billing_cycle_type', 'trial_period_enabled', 'trial_amount', 'trial_period_number', 'trial_period_type', 'delay_post',
            'update_post_action', 'delay_notifications', 'selected_notifications', 'pesapal_conditional_enabled', 'pesapal_conditional_field_id',
            'pesapal_conditional_operator', 'pesapal_conditional_value', 'customer_fields',
        );

        foreach ($old_feed['meta'] as $key => $value) {
            if (!in_array($key, $known_meta_keys)) {
                $new_meta[$key] = $value;
            }
        }

        return $new_meta;
    }

    public function update_payment_gateway()
    {
        global $wpdb;
        $sql = $wpdb->prepare("UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='payment_gateway' AND meta_value='pesapal'", $this->_slug);
        $wpdb->query($sql);
    }

    public function update_lead()
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}rg_lead
			 SET payment_status='Paid', payment_method='PesaPal'
		     WHERE payment_status='Approved'
		     		AND ID IN (
					  	SELECT lead_id FROM {$wpdb->prefix}rg_lead_meta WHERE meta_key='payment_gateway' AND meta_value=%s
				   	)",
            $this->_slug);

        $wpdb->query($sql);
    }

    public function copy_settings()
    {
        //copy plugin settings
        $old_settings = get_option('gf_pesapal_configured');
        $new_settings = array('gf_pesapal_configured' => $old_settings);
        $this->update_plugin_settings($new_settings);
    }

    public function copy_feeds()
    {
        //get feeds
        $old_feeds = $this->get_old_feeds();

        if ($old_feeds) {

            $counter = 1;
            foreach ($old_feeds as $old_feed) {
                $feed_name = 'Feed ' . $counter;
                $form_id = $old_feed['form_id'];
                $is_active = $old_feed['is_active'];
                $customer_fields = $old_feed['meta']['customer_fields'];

                $new_meta = array(
                    'feedName' => $feed_name,
                    'pesapalEmail' => rgar($old_feed['meta'], 'email'),
                    'mode' => rgar($old_feed['meta'], 'mode'),
                    'transactionType' => rgar($old_feed['meta'], 'type'),
                    'type' => rgar($old_feed['meta'], 'type'), //For backwards compatibility of the delayed payment feature
                    'pageStyle' => rgar($old_feed['meta'], 'style'),
                    'continueText' => rgar($old_feed['meta'], 'continue_text'),
                    'cancelUrl' => rgar($old_feed['meta'], 'cancel_url'),
                    'disableNote' => rgar($old_feed['meta'], 'disable_note'),
                    'disableShipping' => rgar($old_feed['meta'], 'disable_shipping'),

                    'recurringAmount' => rgar($old_feed['meta'], 'recurring_amount_field') == 'all' ? 'form_total' : rgar($old_feed['meta'], 'recurring_amount_field'),
                    'recurring_amount_field' => rgar($old_feed['meta'], 'recurring_amount_field'), //For backwards compatibility of the delayed payment feature
                    'recurringTimes' => rgar($old_feed['meta'], 'recurring_times'),
                    'recurringRetry' => rgar($old_feed['meta'], 'recurring_retry'),
                    'paymentAmount' => 'form_total',
                    'billingCycle_length' => rgar($old_feed['meta'], 'billing_cycle_number'),
                    'billingCycle_unit' => $this->convert_interval(rgar($old_feed['meta'], 'billing_cycle_type'), 'text'),

                    'trial_enabled' => rgar($old_feed['meta'], 'trial_period_enabled'),
                    'trial_product' => 'enter_amount',
                    'trial_amount' => rgar($old_feed['meta'], 'trial_amount'),
                    'trialPeriod_length' => rgar($old_feed['meta'], 'trial_period_number'),
                    'trialPeriod_unit' => $this->convert_interval(rgar($old_feed['meta'], 'trial_period_type'), 'text'),

                    'delayPost' => rgar($old_feed['meta'], 'delay_post'),
                    'change_post_status' => rgar($old_feed['meta'], 'update_post_action') ? '1' : '0',
                    'update_post_action' => rgar($old_feed['meta'], 'update_post_action'),

                    'delayNotification' => rgar($old_feed['meta'], 'delay_notifications'),
                    'selectedNotifications' => rgar($old_feed['meta'], 'selected_notifications'),

                    'billingInformation_firstName' => rgar($customer_fields, 'first_name'),
                    'billingInformation_lastName' => rgar($customer_fields, 'last_name'),
                    'billingInformation_email' => rgar($customer_fields, 'email'),
                    'billingInformation_address' => rgar($customer_fields, 'address1'),
                    'billingInformation_Company' => rgar($customer_fields, 'company'),
                    'billingInformation_address2' => rgar($customer_fields, 'address2'),
                    'billingInformation_city' => rgar($customer_fields, 'city'),
                    'billingInformation_state' => rgar($customer_fields, 'state'),
                    'billingInformation_zip' => rgar($customer_fields, 'zip'),
                    'billingInformation_country' => rgar($customer_fields, 'country'),

                );

                $new_meta = $this->add_legacy_meta($new_meta, $old_feed);

                //add conditional logic
                $conditional_enabled = rgar($old_feed['meta'], 'pesapal_conditional_enabled');
                if ($conditional_enabled) {
                    $new_meta['feed_condition_conditional_logic'] = 1;
                    $new_meta['feed_condition_conditional_logic_object'] = array(
                        'conditionalLogic' =>
                            array(
                                'actionType' => 'show',
                                'logicType' => 'all',
                                'rules' => array(
                                    array(
                                        'fieldId' => rgar($old_feed['meta'], 'pesapal_conditional_field_id'),
                                        'operator' => rgar($old_feed['meta'], 'pesapal_conditional_operator'),
                                        'value' => rgar($old_feed['meta'], 'pesapal_conditional_value')
                                    ),
                                )
                            )
                    );
                } else {
                    $new_meta['feed_condition_conditional_logic'] = 0;
                }


                $new_feed_id = $this->insert_feed($form_id, $is_active, $new_meta);
                $this->update_feed_id($old_feed['id'], $new_feed_id);

                $counter++;
            }
        }
    }

    public function copy_transactions()
    {
        //copy transactions from the pesapal transaction table to the add payment transaction table
        global $wpdb;
        $old_table_name = $this->get_old_transaction_table_name();
        if (!$this->table_exists($old_table_name)) {
            return false;
        }
        $this->log_debug(__METHOD__ . '(): Copying old PesaPal transactions into new table structure.');

        $new_table_name = $this->get_new_transaction_table_name();

        $sql = "INSERT INTO {$new_table_name} (lead_id, transaction_type, transaction_id, is_recurring, amount, date_created)
					SELECT entry_id, transaction_type, transaction_id, is_renewal, amount, date_created FROM {$old_table_name}";

        $wpdb->query($sql);

        $this->log_debug(__METHOD__ . "(): transactions: {$wpdb->rows_affected} rows were added.");
    }

    public function get_old_transaction_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'rg_pesapal_transaction';
    }

    public function get_new_transaction_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'gf_addon_payment_transaction';
    }

    public function get_old_feeds()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rg_pesapal';

        if (!$this->table_exists($table_name)) {
            return false;
        }

        $form_table_name = GFFormsModel::get_form_table_name();
        $sql = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
					FROM {$table_name} s
					INNER JOIN {$form_table_name} f ON s.form_id = f.id";

        $this->log_debug(__METHOD__ . "(): getting old feeds: {$sql}");

        $results = $wpdb->get_results($sql, ARRAY_A);

        $this->log_debug(__METHOD__ . "(): error?: {$wpdb->last_error}");

        $count = sizeof($results);

        $this->log_debug(__METHOD__ . "(): count: {$count}");

        for ($i = 0; $i < $count; $i++) {
            $results[$i]['meta'] = maybe_unserialize($results[$i]['meta']);
        }

        return $results;
    }

    //This function kept static for backwards compatibility
    public static function get_config_by_entry($entry)
    {

        $pesapal = GFPesaPal::get_instance();

        $feed = $pesapal->get_payment_feed($entry);

        if (empty($feed)) {
            return false;
        }

        return $feed['addon_slug'] == $pesapal->_slug ? $feed : false;
    }

    //This function kept static for backwards compatibility
    //This needs to be here until all add-ons are on the framework, otherwise they look for this function
    public static function get_config($form_id)
    {

        $pesapal = GFPesaPal::get_instance();
        $feed = $pesapal->get_feeds($form_id);

        //Ignore IPN messages from forms that are no longer configured with the PesaPal add-on
        if (!$feed) {
            return false;
        }

        return $feed[0]; //only one feed per form is supported (left for backwards compatibility)
    }

    //------------------------------------------------------
    /* Assets*/
    public function get_country_code($country)
    {
        $countries = array
        (
            'AF' => 'Afghanistan',
            'AX' => 'Aland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua And Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia And Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, Democratic Republic',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Cote D\'Ivoire',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island & Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic Of',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle Of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KR' => 'Korea',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States Of',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts And Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin',
            'PM' => 'Saint Pierre And Miquelon',
            'VC' => 'Saint Vincent And Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome And Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia And Sandwich Isl.',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard And Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad And Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks And Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Viet Nam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.',
            'WF' => 'Wallis And Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        );
        $flipped_array = array_flip($countries);
        return isset($flipped_array[$country]) ? $flipped_array[$country] : '';
    }

}