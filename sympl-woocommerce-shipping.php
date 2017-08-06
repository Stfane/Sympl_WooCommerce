<?php
/**
 * Plugin Name: Sympl Shipping Method
 * Plugin URI: http://www.sympl.fr/woocommerce/
 * Description: Module de livraison woocommerce pour Sympl.
 * Version: 1.0.0
 * Author: StfaneIrie
 * Author URI: http://www.sympl.fr/
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package WordPress
 * @author StfaneIrie
 * @since 1.0.0
**/

/* Exit if accessed directly */
if (!defined('ABSPATH')){
    exit;
}

/**
* 
*/
class WC_sympl_shipping_wrapper
{
	private $endpoint = 'https://stage.sympl.fr';
	/**
     * Check if WooCommerce is active
	*/
	function WC_sympl_shipping_main()
	{
		include_once(ABSPATH.'wp-admin/includes/plugin.php');
        if (is_plugin_active('woocommerce/woocommerce.php'))
            add_action('plugins_loaded', array($this, 'init'), 8);
	}


	public function __construct()
	{
		# code...
		$this->id                 = SYMPL;
		$this->method_title       = __( 'SYMPL', 'woocommerce-sympl' );
		$this->method_description = __( 'The <strong>SYMPL</strong> extension obtains rates dynamically from the SYMPL API during cart/checkout.', 'woocommerce-sympl' );
		
		// WC: Load SYMPL Settings.
		$sympl_settings 		= get_option( 'woocommerce_'.SYMPL.'_settings', null ); 
		$api_mode      		= isset( $sympl_settings['api_mode'] ) ? $sympl_settings['api_mode'] : 'Test';
		if( "Live" == $api_mode ) {
			$this->endpoint = 'https://live.sympl.fr';
		}
		else {
			$this->endpoint = 'https://stage.sympl.fr';
		}

		return init();
	}

	function init(){
		defined('WC_SYMPL_FILE_PATH', plugin_dir_path(__FILE__));
		defined('WC_SYMPL_ROOT_URL', plugin_url('', __FILE__));

		/*Install sympl offer */
		require_once(WC_SYMPL_FILE_PATH. '/classes/class-wc-sympl-offers.php');
		woocommerce_sympl_offers_init();

		/* Add Tools and Seting Tab */
		add_action('admin_menu', array($this, 'add_orders_tab'));
		add_action('admin_init', array($this, 'export_sympl_orders'));
		add_action('woocommerce_display_admin_footer_text', array($this, 'sympl_shipments'));
		add_filter('woocommerce_settings_tabs_array', array($this, 'settings_tab'));
		add_action('woocommerce_settings_tabs_sympl_shipping', array($this, 'settings_tab'));
		add_action('woocommerce_update_options_sympl_shipping', array($this, 'update_settings'))

		load_plugin_textdomain('woocommerce-sympl', false, dirname(plugin_basename(__FILE__)).'/languages/');
	}

	/*Install plugin function now */
	function install()
	{
		global $wp_version;

		if (!is_plugin_active('woocommerce/woocommerce.php'))
		{
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('You must run Woocommerce 2.x to install SYMPL Shipping plugin', 'woocommerce-sympl'), __('WC not activated', 'woocommerce-sympl'), array('back_link' => true));
			return;
		}

		 if ((float)$wp_version < 3.5)
        {
            deactivate_plugins(plugin_basename(__FILE__)); /* Deactivate plugin */
            wp_die(__('You must run at least WordPress version 3.5 to install SYMPL Shipping plugin', 'woocommerce-sympl'), __('WP not compatible', 'woocommerce-sympl'), array('back_link' => true));
            return;
        }

        define('WC_SYMPL_FILE_PATH', dirname(__FILE__));
        /* Install DB tables */
        include_once('controllers/admin/sympl-shipping-install-table.php');
        install_table();
	}

	/* Add SYMPL tab */
	function add_orders_tab()
	{
        add_submenu_page('woocommerce', __('SYMPL', 'woocommerce-sympl'), __('SYMPL', 'woocommerce-sympl'), 'manage_woocommerce', 'woocommerce-sympl', array($this, 'display_export_page'), 8);
	}

	/* Add settings tab */
	public function add_settings_tab($settings_tabs)
	{
		$settings_tabs['sympl_shipping']= __('SYMPL', 'woocommerce-sympl');
		return $settings_tabs;
	}

	/* Create SYMPL settings tab display */
	public function settings_tab()
	{
		echo "<style media=\"screen\" type=\"text/css\">
			#mainform label{
				display: block;
				font-weight: bold;
				padding: 10px 0 0 0;
			}
			</style>
			<div class=\"update woocommerce-message\">
				 <p><strong>".__('Welcome to SYMPL.', 'woocommerce-sympl')."</strong> ".__('The plugin documentation is available here :', 'woocommerce-sympl')."<a target=\"_blank\" href=\"".WC_SYMPL_ROOT_URL."/assets/docs/readme_sympl_woocommerce.pdf\"><img src=\"".WC_SYMPL_ROOT_URL."/assets/img/admin/pdf.png\"/></a>
                <p><strong>Important : </strong>".__('You must be a SYMPL customer and be in posession of your contract details before configuring this module. If you want to get in touch with our sales team, please visit our website', 'woocommerce-sympl')." <a target=\"_blank\" href=\"http://www.sympl.fr\">www.sympl.fr</a>.</p>
                <p><strong>".__('Please proceed to the configuration of the module', 'woocommerce-sympl')."</strong></p>
            </div>";
        echo "<h3>".__('Your shipper data', 'woocommerce-sympl')."</h3>";
        woocommerce_admin_fields( $this->get_shipperdata_settings() );
        echo "<h3>".__('Orders management', 'woocommerce-sympl')."</h3>";
        woocommerce_admin_fields( $this->get_shipments_settings() );
        echo "<h3>".__('Other settings', 'woocommerce-sympl')."</h3>";
        woocommerce_admin_fields( $this->get_other_settings() );
	}

	/* Save the settings in tab */
	public function update_settings()
	{
		woocommerce_update_options($this->get_shipperdata_settings());
		woocommerce_update_options($this->get_shipments_settings());
		woocommerce_update_options($this->get_other_settings());
	}

	/* Set variables to set */
	public function get_shipperdata_settings()
	{
		$settings= array(

			'shipper_phone'		=> array(
				'name'			=> __('Telephone number', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_email'		=> array(
				'name'			=> __('Email address', 'woocommerce-sympl'),
				'type'			=> 'email',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_firstname'	=> array(
				'name'			=> __('Prenom', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_lastname'	=> array(
				'name'			=> __('Nom de famille', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_password'	=> array(
				'name'			=> __('Password', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_name'	=> array(
				'name'		=> __('Company Name', 'woocommerce-sympl'),
				'type'		=> 'text',
				'css'		=> 'width: 400px;',
				'desc'		=> '',
				'id'		=> '',
			),
			'shipper_siret'	=> array(
				'name'		=> __('SIRET', 'woocommerce-sympl'),
				'type'		=> 'text',
				'css'		=> 'width: 400px;',
				'desc'		=> '',
				'id'		=> '',
			),
			'shipper_tva'	=> array(
				'name'		=> __('TVA Number', 'woocommerce-sympl'),
				'type'		=> 'text',
				'css'		=> 'width: 400px;',
				'desc'		=> '',
				'id'		=> '',
			),
			'shipper_shop'	=> array(
				'name'		=> __('Shop Name', 'woocommerce-sympl'),
				'type'		=> 'text',
				'css'		=> 'width: 400px;',
				'desc'		=> '',
				'id'		=> '',
			),
			'shipper_address1'	=> array(
				'name'			=> __('Recovery address 1', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_address2'	=> array(
				'name'			=> __('Recovery address 2', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_complement'	=> array(
				'name'			=> __('Complement for the recovery address', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_postcode'	=> array(
				'name'			=> __('Postal code', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_city'	=> array(
				'name'			=> __('City', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_addovers'	=> array(
				'name'			=> __('The recovery address is different', 'woocommerce-sympl'),
				'type'			=> 'checkbox',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_optadd1'	=> array(
				'name'			=> __('Optional address 1', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_optadd2'	=> array(
				'name'			=> __('Optional address 2', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_optcomp'	=> array(
				'name'			=> __('Complement for the recovery address', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_postcode2'	=> array(
				'name'			=> __('Optional postal code', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
			'shipper_city2'	=> array(
				'name'			=> __('Optional city', 'woocommerce-sympl'),
				'type'			=> 'text',
				'css'			=> 'width: 400px;',
				'desc'			=> '',
				'id'			=> '',
			),
		);
		return $settings;
	}

	/* Set variables to set */
    public function get_shipments_settings()
    {
        $settings = array(
            'etape_expedition' => array(
                'name'     => __( 'Preparation in progress status', 'woocommerce-sympl' ),
                'type'     => 'select',
                'css'      => 'width: 400px;',
                'options'  => wc_get_order_statuses(),
                'desc'     => __( 'Orders in this state will be selected by default for exporting.', 'woocommerce-sympl' ),
                'id'       => 'wc_settings_tab_sympl_etape_expedition'
            ),
            'etape_expediee' => array(
                'name'     => __( 'Shipped status', 'woocommerce-sympl' ),
                'type'     => 'select',
                'css'      => 'width: 400px;',
                'options'  => wc_get_order_statuses(),
                'desc'     => __( 'Once parcel trackings are generated, orders will be updated to this state.', 'woocommerce-sympl' ),
                'id'       => 'wc_settings_tab_sympl_etape_expediee'
            ),
            'etape_livre' => array(
                'name'     => __( 'Delivered status', 'woocommerce-sympl' ),
                'type'     => 'select',
                'css'      => 'width: 400px;',
                'options'  => wc_get_order_statuses(),
                'desc'     => __( 'Once parcels are delivered, orders will be updated to this state.', 'woocommerce-sympl' ),
                'id'       => 'wc_settings_tab_sympl_etape_livre'
            ),
            'auto_update' => array(
                'name'     => __( 'Automatic update of orders', 'woocommerce-sympl' ),
                'type'     => 'select',
                'css'      => 'width: 400px;',
                'options'  => array(0 => __('Disabled', 'woocommerce-sympl' ), 1 => __('Enabled', 'woocommerce-sympl' )),
                'desc'     => __( 'Order statuses will be automatically updated following parcel delivery status.', 'woocommerce-sympl' ),
                'id'       => 'wc_settings_tab_sympl_auto_update'
            ),
            'advalorem_option' => array(
                'title'     => __( 'Default insurance service', 'woocommerce-sympl' ),
                'desc'     => __( 'Ad Valorem : Please refer to your pricing conditions.', 'woocommerce-sympl' ),
                'type'     => 'select',
                'css'      => 'width: 400px;',
                'options'  => array(0 => __('Integrated parcel insurance service (23 € / kg) - LOTI cdts.', 'woocommerce-sympl' ), 1 => __('Ad Valorem insurance service', 'woocommerce-sympl' )),
                'id'       => 'wc_settings_tab_sympl_advalorem_option'
            ),
            'retour_option' => array(
                'name'     => __( 'Returns option', 'woocommerce-sympl' ),
                'type'     => 'select',
                'css'      => 'width: 400px;',
                'options'  => array(0 => __('No returns', 'woocommerce-sympl' ), 3 => __('On Demand', 'woocommerce-sympl' ), 4 => __('Prepared', 'woocommerce-sympl' )),
                'desc'     => __( 'SYMPL Returns options : Please refer to your pricing conditions.', 'woocommerce-sympl' ),
                'id'       => 'wc_settings_tab_sympl_retour_option'
            ),
        );
        return $settings;
    }
    
    /* Uninstall plugin and DB tables */
    public function deactivate()
    {
        include_once('controllers/admin/sympl-shipping-install-table.php');
        uninstall_table();
    }
    /* Return true if WooCommerce version is 3 and up */
    public function is_wc3()
    {
        if (WC()->version >= '3.0.0') {
            return true;
        } else {
            return false;
        }
    }

    /* Add SYMPL settings tab */
    public function sympl_shipments()
    {
        $cron_url = WC_SYMPL_ROOT_URL.'/ajax/syshipments.php';
        if (get_option('wc_settings_tab_sympl_auto_update') == 1) {
            echo '<script type="text/javascript">jQuery.get("'.$cron_url.'");</script>';
        }
    }

     /* [BO] Orders management page */
    function display_export_page()
    {
        global $wpdb;
        /* Display build */
        /* Loads scripts and page header */
        ?>
        <link rel="stylesheet" type="text/css" href="<?php echo WC_SYMPL_ROOT_URL; ?>/assets/css/admin/AdminSympl.css"/>
        <link rel="stylesheet" type="text/css" href="<?php echo WC_SYMPL_ROOT_URL; ?>/assets/css/bootstrap.css"/>
        <link rel="stylesheet" type="text/css" href="<?php echo WC_SYMPL_ROOT_URL; ?>/assets/js/jquery/plugins/fancybox/jquery.fancybox.css"/>
        <script type="text/javascript" src="<?php echo WC_SYMPL_ROOT_URL; ?>/assets/js/jquery/plugins/marquee/jquery.marquee.min.js"></script>
        <script type="text/javascript" src="<?php echo WC_SYMPL_ROOT_URL; ?>/assets/js/jquery/plugins/fancybox/jquery.fancybox.js"></script>
        <script type="text/javascript">
            var $ = jQuery.noConflict();
            $(document).ready(function(){
                $('.marquee').marquee({
                    duration: 20000,
                    gap: 50,
                    delayBeforeStart: 0,
                    direction: 'left',
                    duplicated: true,
                    pauseOnHover: true,
                    allowCss3Support: false,
                });
                $('a.popup').fancybox({
                    'hideOnContentClick': true,
                    'padding'           : 0,
                    'overlayColor'      :'#D3D3D3',
                    'overlayOpacity'    : 0.7,
                    'width'             : 1024,
                    'height'            : 640,
                    'type'              :'iframe'
                });
                $.expr[':'].contains = function(a, i, m) {
                    return $(a).text().toUpperCase().indexOf(m[3].toUpperCase()) >= 0;
                };
                $("#tableFilter").keyup(function () {
                    //split the current value of tableFilter
                    var data = this.value.split(";");
                    //create a jquery object of the rows
                    var jo = $("#the-list").find("tr");
                    if (this.value == "") {
                        jo.show();
                        return;
                    }
                    //hide all the rows
                    jo.hide();
                    //Recusively filter the jquery object to get results.
                    jo.filter(function (i, v) {
                        var t = $(this);
                        for (var d = 0; d < data.length; ++d) {
                            if (t.is(":contains('" + data[d] + "')")) {
                                return true;
                            }
                        }
                        return false;
                    })
                    //show the rows that match.
                    .show();
                    }).focus(function () {
                        this.value = "";
                        $(this).css({
                            "color": "black"
                        });
                        $(this).unbind('focus');
                    }).css({
                        "color": "#C0C0C0"
                    });
            });
            function checkallboxes(ele) {
                var checkboxes = $("#the-list").find(".checkbox:visible");
                if (ele.checked) {
                    for (var i = 0; i < checkboxes.length; i++) {
                        if (checkboxes[i].type == 'checkbox') {
                            checkboxes[i].checked = true;
                        }
                    }
                } else {
                    for (var i = 0; i < checkboxes.length; i++) {
                        if (checkboxes[i].type == 'checkbox') {
                            checkboxes[i].checked = false;
                        }
                    }
                }
            }
        </script>

        <div class="sympl-wrap">
        <h2><img src="<?php echo WC_SYMPL_ROOT_URL; ?>/assets/img/admin/admin.png"/> Liste des commandes</h2>

        <?php
        
        /* Filter field */
        echo '<input id="tableFilter" placeholder="'.__('Search something, separate values by semicolons ;', 'woocommerce-sympl').'"/><img id="filtericon" src="'.WC_SYMPL_ROOT_URL.'/assets/img/admin/search.png"/><br/><br/>';
        /* POST action : updateShippedOrders */
        if (isset($_POST['updateShippedOrders']))
        {
            if (!isset($_POST['checkbox']))
                echo '<div class="warnmsg">'.__('No order selected', 'woocommerce-sympl').'</div>';
            else
            {
                foreach ($_POST['checkbox'] as $order_id)
                {
                    $order = new WC_Order($order_id);
                    /* Get shipping_method_id */
                    foreach ($order->get_shipping_methods() as $shipping_method)
                        $order_shipping_method_id = $shipping_method['method_id'];
                    /* Retrieve shipper data */
                    $sympl_shipper_data = get_option('woocommerce_sympl_offers_settings');
                    
                    /*if ($order_shipping_method_id == 'sympl_relais'){
                        $depot_code = $symplrelais_shipper_data['depot_code'];
                        $shipper_code = $symplrelais_shipper_data['shipper_code'];
                    }else if ($order_shipping_method_id == 'sympl_predict'){
                        $depot_code = $symplpredict_shipper_data['depot_code'];
                        $shipper_code = $symplpredict_shipper_data['shipper_code'];
                    }else if ($order_shipping_method_id == 'sympl_classic'){
                        $depot_code = $symplclassic_shipper_data['depot_code'];
                        $shipper_code = $symplclassic_shipper_data['shipper_code'];
                    }else if ($order_shipping_method_id == 'sympl_world'){
                        $depot_code = $symplworld_shipper_data['depot_code'];
                        $shipper_code = $symplworld_shipper_data['shipper_code'];
                    }*/

                    /* Prepare customer note */
                    $note = __('Dear customer, you can follow your SYMPL parcel delivery by clicking this link:', 'woocommerce-sympl');
                    $link = 'http://www.sympl.fr/tracex_'.$order_id.'_'.$depot_code.$shipper_code;
                    $href = '<a href="'.$link.'">'.$link.'</a>';
                    /* Add customer note to order */
                    if (is_user_logged_in() && current_user_can('manage_woocommerce'))
                    {
                        $user                 = get_user_by( 'id', get_current_user_id() );
                        $comment_author       = $user->display_name;
                        $comment_author_email = $user->user_email;
                    }
                    else
                    {
                        $comment_author       = __( 'WooCommerce', 'woocommerce-sympl' );
                        $comment_author_email = strtolower( __( 'WooCommerce', 'woocommerce-sympl' ) ) . '@';
                        $comment_author_email .= isset( $_SERVER['HTTP_HOST'] ) ? str_replace( 'www.', '', $_SERVER['HTTP_HOST'] ) : 'noreply.com';
                        $comment_author_email = sanitize_email( $comment_author_email );
                    }
                    $comment_post_ID        = $order_id;
                    $comment_author_url     = '';
                    $comment_content        = $note.' '.$href;
                    $comment_agent          = 'WooCommerce';
                    $comment_type           = 'order_note';
                    $comment_parent         = 0;
                    $comment_approved       = 1;
                    $commentdata            = apply_filters( 'woocommerce_new_order_note_data', compact( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_agent', 'comment_type', 'comment_parent', 'comment_approved' ), array( 'order_id' => $order_id, 'is_customer_note' => 1 ) );
                    $comment_id = wp_insert_comment( $commentdata );
                    add_comment_meta( $comment_id, 'is_customer_note', 1 );
                    do_action( 'woocommerce_new_customer_note', array('order_id' => $order_id, 'customer_note' => $note.' '.$href ));
                    /* Update order status */
                    $order->update_status(get_option('wc_settings_tab_sympl_etape_expediee'));
                }
                /* Display confirmation message */
                echo '<div class="okmsg">'.__('Shipped orders statuses were updated', 'woocommerce-sympl').'</div>';
            }
        }
        /* POST action : updateDeliveredOrders */
        if (isset($_POST['updateDeliveredOrders']))
        {
            if (!isset($_POST['checkbox']))
                echo '<div class="warnmsg">'.__('No order selected', 'woocommerce-sympl').'</div>';
            else
            {
                foreach ($_POST['checkbox'] as $order_id)
                {
                    $order = new WC_Order($order_id);
                    /* Update order status */
                    $order->update_status(get_option('wc_settings_tab_sympl_etape_livre'));
                }
                /* Display confirmation message */
                echo '<div class="okmsg">'.__('Delivered orders statuses were updated', 'woocommerce-sympl').'</div>';
            }
        }
        /* Init vars */
        $array_orders = array();
        $order_data = array();
        /* Retrieve orders ID except delivered and completed statuses */
        $post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts}
                            WHERE post_type = 'shop_order'
                            AND post_status NOT IN ('wc-completed','wc-closed','".get_option('wc_settings_tab_sympl_etape_livre')."')
                            ORDER BY id DESC");
        /* Table header */
        echo'
        <form id="exportform" action="admin.php?page=woocommerce-sympl" method="POST" enctype="multipart/form-data">
        <table class="wp-list-table widefat fixed posts">
            <thead>
                <tr>
                    <th scope="col" id="checkbox" class="manage-column column-cb check-column" style=""><label class="screen-reader-text" for="cb-select-all-1">'.__('', 'woocommerce-sympl').'</label><input onchange="checkallboxes(this)" id="cb-select-all-1" type="checkbox"/></th>
                    <th scope="col" id="order_id" class="manage-column column-order_id" style="">'.__('Order ID', 'woocommerce-sympl').'</th>
                    <th scope="col" id="order_date" class="manage-column column-order_date" style="">'.__('Date', 'woocommerce-sympl').'</th>
                    <th scope="col" id="order_customer" class="manage-column column-order_customer" style="">'.__('Customer', 'woocommerce-sympl').'</th>
                    <th scope="col" id="order_shipping_method" class="manage-column column-order_shipping_method"  style="">'.__('Service', 'woocommerce-sympl').'</th>
                    <th scope="col" id="order_address" class="manage-column column-order_address" style="">'.__('Destination', 'woocommerce-sympl').'</th>
                    <th scope="col" id="order_weight" class="manage-column column-order_weight" style="">'.__('Weight', 'woocommerce-sympl').'</th>
                    <th scope="col" id="order_amount" class="manage-column column-order_amount" colspan="2" style="">'.__('Amount (tick to insure this parcel)', 'woocommerce-sympl').'</th>
                    '.(get_option('wc_settings_tab_sympl_retour_option') == 0 ? '' : '<th scope="col" id="order_retour" class="manage-column column-order_retour" style="">'.__('Allow returns', 'woocommerce-sympl').'</th>').'
                    <th scope="col" id="order_status" class="manage-column column-order_status" style="">'.__('Order Status', 'woocommerce-sympl').'</th>
                    <th scope="col" id="order_tracking" class="manage-column column-order_tracking" style="">'.__('Parcel trace', 'woocommerce-sympl').'</th>
                </tr>
            </thead>
            <tbody id="the-list">';
        /* Collect order data */
        foreach ($post_ids as $post_id)
        {
            /* Retrieve order details from its ID */
            $order = wc_get_order($post_id);
            $order_id = $order->get_order_number();
            /* Get shipping_method_id */
            foreach ($order->get_shipping_methods() as $shipping_method)
                $order_shipping_method_id = $shipping_method['method_id'];
            /* Filter orders not carrier by SYMPL */
            if(stripos($order_shipping_method_id, 'sympl_') !== false)
            {
                /* Calculates order total weight */
                $order_weight = 0;
                foreach ($order->get_items() as $item_id => $item)
                    $order_weight = $order_weight + ($order->get_product_from_item($item)->get_weight() * $item['qty']);
                /* WC 3+ : Retrieves correct date and time from post creation timestamp */
                if (is_wc3()) {
                    $post_timestamp = new DateTime();
                    $post_timestamp->setTimestamp(strtotime($order->get_date_created()));
                    $date_created = $post_timestamp->setTimezone(new DateTimeZone(get_option('timezone_string')))->format('d/m/Y H:i:s');
                }
                /* Assembles order data in array */
                $order_data[$order_id] = array(
                        'order_id'          => $order->get_order_number(),
                        'order_date'        => (is_wc3() ? $date_created : date('d/m/Y H:i:s', strtotime($order->order_date))),
                        'order_status'      => $order->get_status(),
                        'order_amount'      => $order->get_total(),
                        'order_weight'      => $order_weight,
                        'order_shipping_method_id'=> $order_shipping_method_id,
                        'customer_note'     => str_replace(array("\r\n", "\n", "\r", "\t"), ' ', (is_wc3() ? $order->get_customer_note() : $order->customer_note)),
                        'customer_email'    => (is_wc3() ? $order->get_billing_email() : $order->billing_email),
                        'customer_phone'    => (is_wc3() ? $order->get_billing_phone() : $order->billing_phone),
                        'shipping_first_name'=>(is_wc3() ? $order->get_shipping_first_name() : $order->shipping_first_name),
                        'shipping_last_name'=> (is_wc3() ? $order->get_shipping_last_name() : $order->shipping_last_name),
                        'shipping_company'  => (is_wc3() ? $order->get_shipping_company() : $order->shipping_company),
                        'shipping_address_1'=> (is_wc3() ? $order->get_shipping_address_1() : $order->shipping_address_1),
                        'shipping_address_2'=> (is_wc3() ? $order->get_shipping_address_2() : $order->shipping_address_2),
                        'shipping_postcode' => (is_wc3() ? $order->get_shipping_postcode() : $order->shipping_postcode),
                        'shipping_city'     => (is_wc3() ? $order->get_shipping_city() : $order->shipping_city),
                        'shipping_country'  => (is_wc3() ? $order->get_shipping_country() : $order->shipping_country),
                );
                /* Retrieve shipper data */
                $symplrelais_shipper_data = get_option('woocommerce_sympl_relais_settings');
                $symplpredict_shipper_data = get_option('woocommerce_sympl_predict_settings');
                $symplclassic_shipper_data = get_option('woocommerce_sympl_classic_settings');
                $symplworld_shipper_data = get_option('woocommerce_sympl_world_settings');
                $isops = array("DE", "AD", "AT", "BE", "BA", "BG", "HR", "DK", "ES", "EE", "FI", "FR", "GB", "GR", "GG", "HU", "IM", "IE", "IT", "JE", "LV", "LI", "LT", "LU", "MC", "NO", "NL", "PL", "PT", "CZ", "RO", "RS", "SK", "SI", "SE", "CH");
                $isoep = array("D", "AND", "A", "B", "BA", "BG", "CRO", "DK", "E", "EST", "SF", "F", "GB", "GR", "GG", "H", "IM", "IRL", "I", "JE", "LET", "LIE", "LIT", "L", "F", "N", "NL", "PL", "P", "CZ", "RO", "RS", "SK", "SLO", "S", "CH");
                if (in_array($order_data[$order_id]['shipping_country'], $isops)) // Si le code ISO est européen, on le convertit au format Station SYMPL
                    $code_iso = str_replace($isops, $isoep, $order_data[$order_id]['shipping_country']);
                else
                    $code_iso = str_replace($order_data[$order_id]['shipping_country'], "INT", $order_data[$order_id]['shipping_country']); // Si le code ISO n'est pas européen, on le passe en "INT" (intercontinental)
                    
                /* Set icon, depot and shipper codes for delivery services */
                if ($order_data[$order_id]['order_shipping_method_id'] == 'sympl_relais' || preg_match('/\(P\d{5}\)/', $order_data[$order_id]['shipping_company'])) {
                    $icon = 'Relais<img src="'.WC_SYMPL_ROOT_URL.'/assets/img/admin/service_relais.png" alt="Relais" title="Relais"/>';
                    $depot_code = $symplrelais_shipper_data['depot_code'];
                    $shipper_code = $symplrelais_shipper_data['shipper_code'];
                    $address = '<a class="popup" href="http://www.sympl.fr/home/shipping/relais_details.php?idPR='.substr($order_data[$order_id]['shipping_company'], -7, 6).'" target="_blank">'.$order_data[$order_id]['shipping_company'].'<br/>'.$order_data[$order_id]['shipping_postcode'].' '.$order_data[$order_id]['shipping_city'].'</a>';
                } else if (!in_array($order_data[$order_id]['order_shipping_method_id'], array('sympl_world', 'sympl_classic', 'sympl_relais')) && preg_match("/^((\+33|0)[67])(?:[ _.-]?(\d{2})){4}$/", SYMPLStation::formatGSM($order_data[$order_id]['customer_phone'], $code_iso))) {
                    $icon = 'Predict<img src="'.WC_SYMPL_ROOT_URL.'/assets/img/admin/service_predict.png" alt="Predict" title="Predict"/>';
                    $depot_code = $symplpredict_shipper_data['depot_code'];
                    $shipper_code = $symplpredict_shipper_data['shipper_code'];
                    $address = '<a class="popup" href="http://maps.google.com/maps?f=q&hl=fr&geocode=&q='.str_replace(' ', '+', $order_data[$order_id]['shipping_address_1']).','.str_replace(' ', '+', $order_data[$order_id]['shipping_postcode']).'+'.str_replace(' ', '+', $order_data[$order_id]['shipping_city']).'&output=embed" target="_blank">'.($order_data[$order_id]['shipping_company'] ? $order_data[$order_id]['shipping_company'].'<br/>' : '').$order_data[$order_id]['shipping_address_1'].'<br/>'.$order_data[$order_id]['shipping_postcode'].' '.$order_data[$order_id]['shipping_city'].'</a>';
                } else if ($order_data[$order_id]['order_shipping_method_id'] == 'sympl_world') {
                    $icon = 'Classic<img src="'.WC_SYMPL_ROOT_URL.'/assets/img/admin/service_world.png" alt="Intercontinental" title="Intercontinental"/>';
                    $depot_code = $symplworld_shipper_data['depot_code'];
                    $shipper_code = $symplworld_shipper_data['shipper_code'];
                    $address = '<a class="popup" href="http://maps.google.com/maps?f=q&hl=fr&geocode=&q='.str_replace(' ', '+', $order_data[$order_id]['shipping_address_1']).','.str_replace(' ', '+', $order_data[$order_id]['shipping_postcode']).'+'.str_replace(' ', '+', $order_data[$order_id]['shipping_city']).'&output=embed" target="_blank">'.($order_data[$order_id]['shipping_company'] ? $order_data[$order_id]['shipping_company'].'<br/>' : '').$order_data[$order_id]['shipping_address_1'].'<br/>'.$order_data[$order_id]['shipping_postcode'].' '.$order_data[$order_id]['shipping_city'].'</a>';
                } else {
                    $icon = 'Classic<img src="'.WC_SYMPL_ROOT_URL.'/assets/img/admin/service_dom.png" alt="Classic" title="Classic"/>';
                    $depot_code = $symplclassic_shipper_data['depot_code'];
                    $shipper_code = $symplclassic_shipper_data['shipper_code'];
                    $address = '<a class="popup" href="http://maps.google.com/maps?f=q&hl=fr&geocode=&q='.str_replace(' ', '+', $order_data[$order_id]['shipping_address_1']).','.str_replace(' ', '+', $order_data[$order_id]['shipping_postcode']).'+'.str_replace(' ', '+', $order_data[$order_id]['shipping_city']).'&output=embed" target="_blank">'.($order_data[$order_id]['shipping_company'] ? $order_data[$order_id]['shipping_company'].'<br/>' : '').$order_data[$order_id]['shipping_address_1'].'<br/>'.$order_data[$order_id]['shipping_postcode'].' '.$order_data[$order_id]['shipping_city'].'</a>';
                }
                $tracking = ($order_data[$order_id]['order_status'] == 'processing' ? '<a target="_blank" href="http://www.sympl.fr/tracex_'.$order_data[$order_id]['order_id'].'_'.$depot_code.$shipper_code.'"><img src="'.WC_SYMPL_ROOT_URL.'/assets/img/admin/tracking.png" alt="Trace"/></a>' : '');
                /* Add table row */
                echo    '<tr>
                            <td><input class="checkbox" type="checkbox" name="checkbox[]" value="'.$order_data[$order_id]['order_id'].'" '. (strpos(get_option('wc_settings_tab_sympl_etape_expedition'), $order_data[$order_id]['order_status']) !== false ? 'checked=checked' : '').'></td>
                            <td class="id">'.$order_data[$order_id]['order_id'].'</td>
                            <td class="date">'.$order_data[$order_id]['order_date'].'</td>
                            <td class="nom">'.$order_data[$order_id]['shipping_first_name'].' '.$order_data[$order_id]['shipping_last_name'].'</td>
                            <td class="type">'.$icon.'</td>
                            <td class="pr">'.$address.'</td>
                            <td class="poids"><input name="poids['.$order_data[$order_id]['order_id'].']" class="poids" value="'.number_format($order_data[$order_id]['order_weight'], (get_option('woocommerce_weight_unit') == 'g' ? 0 : 2), '.', '').'"></input> '.get_option('woocommerce_weight_unit').'</td>
                            <td class="prix" align="right">'.number_format($order_data[$order_id]['order_amount'], 2, '.', '').' €</td>
                            <td class="advalorem"><input class="advalorem" type="checkbox" name="advalorem['.$order_data[$order_id]['order_id'].']" value="'.$order_data[$order_id]['order_amount'].'" '.(get_option('wc_settings_tab_sympl_advalorem_option') == 1 ? 'checked=checked' : '').'></td>
                            '.(get_option('wc_settings_tab_sympl_retour_option') == 0 ? '' : '<td class="retour"><input class="retour" type="checkbox" name="retour['.$order_data[$order_id]['order_id'].']" value="'.$order_data[$order_id]['order_id'].'" '.(get_option('wc_settings_tab_sympl_retour_option') == 0 ? '' : 'checked=checked').'></td>').'
                            <td class="statutcommande" align="center">'.wc_get_order_status_name($order_data[$order_id]['order_status']).'</td>
                            
                        </tr>
                ';
            }
        }
        /* End foreach - push data to array_orders */
        array_push($array_orders, $order_data);
        /* If there are no SYMPL orders, quit */
        if (empty($order_data))
        {
            wp_die('<div class="warnmsg">'.__('There are no SYMPL orders', 'woocommerce-sympl').'</div>');
            exit;
        }
        
        /* Display end of table and footer */
        echo '
            </tbody></table>
            <p>
                <input type="submit" class="button" name="exportOrders" value="'.__('Export selected orders', 'woocommerce-sympl').'" />
                <input type="submit" class="button" name="updateShippedOrders" value="'.__('Update shipped orders', 'woocommerce-sympl').'" />
                <input type="submit" class="button" name="updateDeliveredOrders" value="'.__('Update delivered orders', 'woocommerce-sympl').'" />
            </p>
            </form>
        ';
    }
    function export_sympl_orders()
    {
        global $wpdb;
        if (isset($_POST['exportOrders']))
        {
            /* Init vars */
            $array_orders = array();
            /* Retrieve orders ID except delivered and completed statuses */
            $post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts}
                                WHERE post_type = 'shop_order'
                                AND post_status NOT IN ('wc-completed','wc-closed','".get_option('wc_settings_tab_sympl_etape_livre')."')
                                ORDER BY id DESC");
            /* If there are no SYMPL orders, quit */
            if (empty($post_ids))
            {
                wp_die('<div class="warnmsg">'.__('There are no SYMPL orders', 'woocommerce-sympl').'</div>');
                exit;
            }
            /* Collect order data */
            foreach ($post_ids as $post_id)
            {
                /* Retrieve order details from its ID */
                $order = wc_get_order($post_id);
                $order_id = $order->get_order_number();
                /* Get shipping_method_id */
                foreach ($order->get_shipping_methods() as $shipping_method)
                    $order_shipping_method_id = $shipping_method['method_id'];
                /* Filter orders not carrier by SYMPL */
                if(stripos($order_shipping_method_id, 'sympl_') !== false)
                {
                    /* Calculates order total weight */
                    $order_weight = 0;
                    foreach ($order->get_items() as $item_id => $item)
                        $order_weight = $order_weight + ($order->get_product_from_item($item)->get_weight() * $item['qty']);
                    /* Assembles order data in array */
                    $order_data[$order_id] = array(
                        'order_id'          => $order->get_order_number(),
                        'order_date'        => (is_wc3() ? date_i18n('d/m/Y H:i:s', strtotime($order->get_date_created())) : date('d/m/Y H:i:s', strtotime($order->order_date))),
                        'order_status'      => $order->get_status(),
                        'order_amount'      => $order->get_total(),
                        'order_weight'      => $order_weight,
                        'order_shipping_method_id'=> $order_shipping_method_id,
                        'customer_note'     => str_replace(array("\r\n", "\n", "\r", "\t"), ' ', (is_wc3() ? $order->get_customer_note() : $order->customer_note)),
                        'customer_email'    => (is_wc3() ? $order->get_billing_email() : $order->billing_email),
                        'customer_phone'    => (is_wc3() ? $order->get_billing_phone() : $order->billing_phone),
                        'shipping_first_name'=>(is_wc3() ? $order->get_shipping_first_name() : $order->shipping_first_name),
                        'shipping_last_name'=> (is_wc3() ? $order->get_shipping_last_name() : $order->shipping_last_name),
                        'shipping_company'  => (is_wc3() ? $order->get_shipping_company() : $order->shipping_company),
                        'shipping_address_1'=> (is_wc3() ? $order->get_shipping_address_1() : $order->shipping_address_1),
                        'shipping_address_2'=> (is_wc3() ? $order->get_shipping_address_2() : $order->shipping_address_2),
                        'shipping_postcode' => (is_wc3() ? $order->get_shipping_postcode() : $order->shipping_postcode),
                        'shipping_city'     => (is_wc3() ? $order->get_shipping_city() : $order->shipping_city),
                        'shipping_country'  => (is_wc3() ? $order->get_shipping_country() : $order->shipping_country),
                    );
                    /* Retrieve shipper data */
                    $symplrelais_shipper_data = get_option('woocommerce_sympl_relais_settings');
                    $symplpredict_shipper_data = get_option('woocommerce_sympl_predict_settings');
                    $symplclassic_shipper_data = get_option('woocommerce_sympl_classic_settings');
                    $symplworld_shipper_data = get_option('woocommerce_sympl_world_settings');
                    $isops = array("DE", "AD", "AT", "BE", "BA", "BG", "HR", "DK", "ES", "EE", "FI", "FR", "GB", "GR", "GG", "HU", "IM", "IE", "IT", "JE", "LV", "LI", "LT", "LU", "MC", "NO", "NL", "PL", "PT", "CZ", "RO", "RS", "SK", "SI", "SE", "CH");
                    $isoep = array("D", "AND", "A", "B", "BA", "BG", "CRO", "DK", "E", "EST", "SF", "F", "GB", "GR", "GG", "H", "IM", "IRL", "I", "JE", "LET", "LIE", "LIT", "L", "F", "N", "NL", "PL", "P", "CZ", "RO", "RS", "SK", "SLO", "S", "CH");
                    if (in_array($order_data[$order_id]['shipping_country'], $isops)) // Si le code ISO est européen, on le convertit au format Station SYMPL
                        $code_iso = str_replace($isops, $isoep, $order_data[$order_id]['shipping_country']);
                    else
                        $code_iso = str_replace($order_data[$order_id]['shipping_country'], "INT", $order_data[$order_id]['shipping_country']); // Si le code ISO n'est pas européen, on le passe en "INT" (intercontinental)
                    /* Set depot and shipper codes for delivery services */
                    if ($order_data[$order_id]['order_shipping_method_id'] == 'sympl_relais' || preg_match('/\(P\d{5}\)/', $order_data[$order_id]['shipping_company'])) {
                        $depot_code = $symplrelais_shipper_data['depot_code'];
                        $shipper_code = $symplrelais_shipper_data['shipper_code'];
                    } else if (!in_array($order_data[$order_id]['order_shipping_method_id'], array('sympl_world', 'sympl_classic', 'sympl_relais')) && preg_match("/^((\+33|0)[67])(?:[ _.-]?(\d{2})){4}$/", SYMPLStation::formatGSM($order_data[$order_id]['customer_phone'], $code_iso))) {
                        $depot_code = $symplpredict_shipper_data['depot_code'];
                        $shipper_code = $symplpredict_shipper_data['shipper_code'];
                    } else if ($order_data[$order_id]['order_shipping_method_id'] == 'sympl_world') {
                        $depot_code = $symplworld_shipper_data['depot_code'];
                        $shipper_code = $symplworld_shipper_data['shipper_code'];
                    } else {
                        $depot_code = $symplclassic_shipper_data['depot_code'];
                        $shipper_code = $symplclassic_shipper_data['shipper_code'];
                    }
                }
            }
            /* End foreach - push data to array_orders */
            array_push($array_orders, $order_data);
            if (!isset($_POST['checkbox']))
                echo '<div class="warnmsg">'.__('No order selected', 'woocommerce-sympl').'</div>';
            else
            {
                /* Init SYMPLStation class for interface file creation */
                $record = new SYMPLStation();
                foreach ($_POST['checkbox'] as $order_id)
                {
                    if (array_key_exists($order_id, $array_orders[0]))
                    {
                        /* Retrieve shipper data */
                        $symplrelais_shipper_data = get_option('woocommerce_sympl_relais_settings');
                        $symplpredict_shipper_data = get_option('woocommerce_sympl_predict_settings');
                        $symplclassic_shipper_data = get_option('woocommerce_sympl_classic_settings');
                        $symplworld_shipper_data = get_option('woocommerce_sympl_world_settings');
                        $isops = array("DE", "AD", "AT", "BE", "BA", "BG", "HR", "DK", "ES", "EE", "FI", "FR", "GB", "GR", "GG", "HU", "IM", "IE", "IT", "JE", "LV", "LI", "LT", "LU", "MC", "NO", "NL", "PL", "PT", "CZ", "RO", "RS", "SK", "SI", "SE", "CH");
                        $isoep = array("D", "AND", "A", "B", "BA", "BG", "CRO", "DK", "E", "EST", "SF", "F", "GB", "GR", "GG", "H", "IM", "IRL", "I", "JE", "LET", "LIE", "LIT", "L", "F", "N", "NL", "PL", "P", "CZ", "RO", "RS", "SK", "SLO", "S", "CH");
                        if (in_array($order_data[$order_id]['shipping_country'], $isops)) // Si le code ISO est européen, on le convertit au format Station SYMPL
                            $code_iso = str_replace($isops, $isoep, $order_data[$order_id]['shipping_country']);
                        else
                            $code_iso = str_replace($order_data[$order_id]['shipping_country'], "INT", $order_data[$order_id]['shipping_country']); // Si le code ISO n'est pas européen, on le passe en "INT" (intercontinental)
                        $retour_option=(int)get_option('wc_settings_tab_sympl_retour_option'); /* 2: Inverse, 3: Sur demande, 4: Préparée */
                        /* Set depot and shipper codes for delivery services */
                        if ($order_data[$order_id]['order_shipping_method_id'] == 'sympl_relais' || preg_match('/\(P\d{5}\)/', $order_data[$order_id]['shipping_company'])) {
                            $depot_code = $symplrelais_shipper_data['depot_code'];
                            $shipper_code = $symplrelais_shipper_data['shipper_code'];
                        } else if (!in_array($order_data[$order_id]['order_shipping_method_id'], array('sympl_world', 'sympl_classic', 'sympl_relais')) && preg_match("/^((\+33|0)[67])(?:[ _.-]?(\d{2})){4}$/", SYMPLStation::formatGSM($order_data[$order_id]['customer_phone'], $code_iso))) {
                            $depot_code = $symplpredict_shipper_data['depot_code'];
                            $shipper_code = $symplpredict_shipper_data['shipper_code'];
                        } else if ($order_data[$order_id]['order_shipping_method_id'] == 'sympl_world') {
                            $depot_code = $symplworld_shipper_data['depot_code'];
                            $shipper_code = $symplworld_shipper_data['shipper_code'];
                        } else {
                            $depot_code = $symplclassic_shipper_data['depot_code'];
                            $shipper_code = $symplclassic_shipper_data['shipper_code'];
                        }
                        // Structure du fichier d'interface SYMPL France unifié
                        $record->add($order_id, 0, 35);                                                                                 //  Référence client N°1 - Référence Commande
                        switch (get_option('woocommerce_weight_unit')) {
                            case 'kg' :
                                $record->add(str_pad(intval($_POST['poids'][$order_id]*100), 8, '0', STR_PAD_LEFT), 37, 8);             //  Poids du colis sur 8 caractères
                                break;
                            case 'g' :
                                $record->add(str_pad(intval($_POST['poids'][$order_id]/10), 8, '0', STR_PAD_LEFT), 37, 8);              //  Poids du colis sur 8 caractères
                                break;
                            case 'lbs' :
                                $record->add(str_pad(intval($_POST['poids'][$order_id]*45.3592), 8, '0', STR_PAD_LEFT), 37, 8);         //  Poids du colis sur 8 caractères
                                break;
                            case 'oz' :
                                $record->add(str_pad(intval($_POST['poids'][$order_id]*2.83495), 8, '0', STR_PAD_LEFT), 37, 8);         //  Poids du colis sur 8 caractères
                                break;
                        }
                        if ($order_data[$order_id]['order_shipping_method_id'] !== 'sympl_relais') {
                            $record->add(($order_data[$order_id]['shipping_company'] ? $order_data[$order_id]['shipping_company'] : $order_data[$order_id]['shipping_last_name'].' '.$order_data[$order_id]['shipping_first_name']), 60, 35);   // Société sinon Nom et Prénom destinataire
                            $record->add(($order_data[$order_id]['shipping_company'] ? $order_data[$order_id]['shipping_last_name'].' '.$order_data[$order_id]['shipping_first_name'] : ''), 95, 35);                                           // Si Société : Nom et Prénom destinataire, sinon rien
                        } else {
                            $record->add($order_data[$order_id]['shipping_last_name'], 60, 35);                                         //  Nom de famille du destinataire
                            $record->add($order_data[$order_id]['shipping_first_name'], 95, 35);                                        //  Prénom du destinataire
                        }
                        $record->add($order_data[$order_id]['shipping_address_1'], 130, 35);                                            //  Complément d’adresse 2
                        $record->add($order_data[$order_id]['shipping_address_2'], 165, 35);                                            //  Complément d’adresse 3
                        $record->add($order_data[$order_id]['shipping_postcode'], 270, 10);                                             //  Code postal
                        $record->add($order_data[$order_id]['shipping_city'], 280, 35);                                                 //  Ville
                        $record->add($order_data[$order_id]['shipping_address_1'], 325, 35);                                            //  Rue
                        $record->add($code_iso, 370, 3);                                                                                //  Code Pays destinataire
                        $record->add($order_data[$order_id]['customer_phone'], 373, 30);                                                //  Téléphone Destinataire
                        $record->add(get_option('wc_settings_tab_sympl_shipper_name'), 418, 35);                                    //  Nom expéditeur
                        $record->add(get_option('wc_settings_tab_sympl_shipper_address2'), 453, 35);                                //  Complément d’adresse 1
                        $record->add(get_option('wc_settings_tab_sympl_shipper_postcode'), 628, 10);                                //  Code postal
                        $record->add(get_option('wc_settings_tab_sympl_shipper_city'), 638, 35);                                    //  Ville
                        $record->add(get_option('wc_settings_tab_sympl_shipper_address1'), 683, 35);                                //  Rue
                        $record->add('F', 728, 3);                                                                                      //  Code Pays
                        $record->add(get_option('wc_settings_tab_sympl_shipper_phone'), 731, 30);                                   //  Tél. Expéditeur
                        $record->add($order_data[$order_id]['customer_note'], 761, 140);                                                //  Instructions de livraison
                        $record->add(date("d/m/Y"), 901, 10);                                                                           //  Date d'expédition théorique
                        $record->add(str_pad($shipper_code, 8, '0', STR_PAD_LEFT), 911, 8);                                             //  N° de compte chargeur SYMPL
                        $record->add($order_id, 919, 35);                                                                               //  Code à barres
                        $record->add($order_id, 954, 35);                                                                               //  N° de commande - Id Order
                        if (isset($_POST['advalorem']) && array_key_exists($order_id, $_POST['advalorem']))
                            $record->add(str_pad(number_format($order_data[$order_id]['order_amount'], 2, '.', ''), 9, '0', STR_PAD_LEFT), 1018, 9); // Montant valeur colis
                        $record->add($order_id, 1035, 35);                                                                              //  Référence client N°2
                        $record->add(get_option('wc_settings_tab_sympl_shipper_email'), 1116, 80);                                  //  E-mail expéditeur
                        $record->add(get_option('wc_settings_tab_sympl_shipper_mobile'), 1196, 35);                                 //  GSM expéditeur
                        $record->add($order_data[$order_id]['customer_email'], 1231, 80);                                               //  E-mail destinataire
                        $record->add(SYMPLStation::formatGSM($order_data[$order_id]['customer_phone'], $code_iso), 1311, 35);             //  GSM destinataire
                        if ($order_data[$order_id]['order_shipping_method_id'] == 'sympl_relais'){
                            preg_match('/\(P\d{5}\)/', $order_data[$order_id]['shipping_company'], $relay_id);
                            $record->add(substr($relay_id[0], 1, -1), 1442, 8);}                                                        //  ID Relais
                        if (!in_array($order_data[$order_id]['order_shipping_method_id'], array('sympl_world', 'sympl_classic', 'sympl_relais')) && preg_match("/^((\+33|0)[67])(?:[ _.-]?(\d{2})){4}$/", SYMPLStation::formatGSM($order_data[$order_id]['customer_phone'], $code_iso)))
                            $record->add("+", 1568, 1);                                                                                 //  Flag Predict
                        $record->add($order_data[$order_id]['shipping_last_name'], 1569, 35);                                           //  Nom de famille du destinataire
                        if (isset($_POST['retour']) && array_key_exists($order_id, $_POST['retour']) && $retour_option != 0) {
                            $record->add($retour_option, 1834, 1);                                                                      //  Flag Retour
                        }
                        $record->add_line();
                    }
                }
                $record->download();
            }
        }

        public function download()
    {
        while (@ob_end_clean());
        header('Content-type: application/dat');
        header('Content-Disposition: attachment; filename="SYMPL_'.date('dmY-His').'.dat"');
        echo '$VERSION=110'."\r\n";
        echo $this->contenu_fichier."\r\n";
        exit;
    }
    public function stripAccents($str)
    {
        $str = preg_replace('/[\x{00C0}\x{00C1}\x{00C2}\x{00C3}\x{00C4}\x{00C5}]/u', 'A', $str);
        $str = preg_replace('/[\x{0105}\x{0104}\x{00E0}\x{00E1}\x{00E2}\x{00E3}\x{00E4}\x{00E5}]/u', 'a', $str);
        $str = preg_replace('/[\x{00C7}\x{0106}\x{0108}\x{010A}\x{010C}]/u', 'C', $str);
        $str = preg_replace('/[\x{00E7}\x{0107}\x{0109}\x{010B}\x{010D}}]/u', 'c', $str);
        $str = preg_replace('/[\x{010E}\x{0110}]/u', 'D', $str);
        $str = preg_replace('/[\x{010F}\x{0111}]/u', 'd', $str);
        $str = preg_replace('/[\x{00C8}\x{00C9}\x{00CA}\x{00CB}\x{0112}\x{0114}\x{0116}\x{0118}\x{011A}]/u', 'E', $str);
        $str = preg_replace('/[\x{00E8}\x{00E9}\x{00EA}\x{00EB}\x{0113}\x{0115}\x{0117}\x{0119}\x{011B}]/u', 'e', $str);
        $str = preg_replace('/[\x{00CC}\x{00CD}\x{00CE}\x{00CF}\x{0128}\x{012A}\x{012C}\x{012E}\x{0130}]/u', 'I', $str);
        $str = preg_replace('/[\x{00EC}\x{00ED}\x{00EE}\x{00EF}\x{0129}\x{012B}\x{012D}\x{012F}\x{0131}]/u', 'i', $str);
        $str = preg_replace('/[\x{0142}\x{0141}\x{013E}\x{013A}]/u', 'l', $str);
        $str = preg_replace('/[\x{00F1}\x{0148}]/u', 'n', $str);
        $str = preg_replace('/[\x{00D2}\x{00D3}\x{00D4}\x{00D5}\x{00D6}\x{00D8}]/u', 'O', $str);
        $str = preg_replace('/[\x{00F2}\x{00F3}\x{00F4}\x{00F5}\x{00F6}\x{00F8}]/u', 'o', $str);
        $str = preg_replace('/[\x{0159}\x{0155}]/u', 'r', $str);
        $str = preg_replace('/[\x{015B}\x{015A}\x{0161}]/u', 's', $str);
        $str = preg_replace('/[\x{00DF}]/u', 'ss', $str);
        $str = preg_replace('/[\x{0165}]/u', 't', $str);
        $str = preg_replace('/[\x{00D9}\x{00DA}\x{00DB}\x{00DC}\x{016E}\x{0170}\x{0172}]/u', 'U', $str);
        $str = preg_replace('/[\x{00F9}\x{00FA}\x{00FB}\x{00FC}\x{016F}\x{0171}\x{0173}]/u', 'u', $str);
        $str = preg_replace('/[\x{00FD}\x{00FF}]/u', 'y', $str);
        $str = preg_replace('/[\x{017C}\x{017A}\x{017B}\x{0179}\x{017E}]/u', 'z', $str);
        $str = preg_replace('/[\x{00C6}]/u', 'AE', $str);
        $str = preg_replace('/[\x{00E6}]/u', 'ae', $str);
        $str = preg_replace('/[\x{0152}]/u', 'OE', $str);
        $str = preg_replace('/[\x{0153}]/u', 'oe', $str);
        $str = preg_replace('/[\x{0022}\x{0025}\x{0026}\x{0027}\x{00A1}\x{00A2}\x{00A3}\x{00A4}\x{00A5}\x{00A6}\x{00A7}\x{00A8}\x{00AA}\x{00AB}\x{00AC}\x{00AD}\x{00AE}\x{00AF}\x{00B0}\x{00B1}\x{00B2}\x{00B3}\x{00B4}\x{00B5}\x{00B6}\x{00B7}\x{00B8}\x{00BA}\x{00BB}\x{00BC}\x{00BD}\x{00BE}\x{00BF}]/u', ' ', $str);
        return $str;
    }
    public static function formatGSM($gsm_dest, $code_iso)
    {
        if ($code_iso=='F') {
            $gsm_dest=str_replace(array(' ', '.', '-', ',', ';', '/', '\\', '(', ')'), '', $gsm_dest);
            $gsm_dest=str_replace('+33', '0', $gsm_dest);
            if (substr($gsm_dest, 0, 2)==33) {
                // Chrome autofill fix
                $gsm_dest=substr_replace($gsm_dest, '0', 0, 2);
            }
            if ((substr($gsm_dest, 0, 2)==06||substr($gsm_dest, 0, 2)==07)&&strlen($gsm_dest)==10) {
                return $gsm_dest;
            } else {
                return false;
            }
        } else {
            return $gsm_dest;
        }
    }
}

$module = new WC_sympl_shipping_wrapper();
/* Register plugin status hooks */
register_activation_hook(__FILE__, array($module, 'install'));
register_deactivation_hook(__FILE__, array($module, 'deactivate'));
/* Exec */
$module->WC_sympl_shipping_main();

 
