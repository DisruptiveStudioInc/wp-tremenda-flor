<?php
/**
 * The admin advanced settings page functionality of the plugin.
 *
 * @link       https://themehigh.com
 * @since      2.3.0
 *
 * @package    woocommerce-extra-product-options-pro
 * @subpackage woocommerce-extra-product-options-pro/admin
 */
if(!defined('WPINC')){	die; }

if(!class_exists('THWEPOF_Admin_Settings_Advanced')):

class THWEPOF_Admin_Settings_Advanced extends THWEPOF_Admin_Settings{
	protected static $_instance = null;
	
	private $settings_fields = NULL;
	private $cell_props = array();
	private $cell_props_CB = array();
	private $cell_props_TA = array();
	
	public function __construct() {
		parent::__construct('advanced_settings');
		$this->init_constants();
	}
	
	public static function instance() {
		if(is_null(self::$_instance)){
			self::$_instance = new self();
		}
		return self::$_instance;
	} 
	
	public function init_constants(){
		$this->cell_props = array( 
			'label_cell_props' => 'class="label"', 
			'input_cell_props' => 'class="field"',
			'input_width' => '260px',
			'label_cell_th' => true
		);

		$this->cell_props_TA = array( 
			'label_cell_props' => 'class="label"', 
			'input_cell_props' => 'class="field"',
			'rows' => 10,
			'cols' => 100,
		);

		$this->cell_props_CB = array( 
			'label_props' => 'style="margin-right: 40px;"', 
		);
		
		$this->settings_fields = $this->get_advanced_settings_fields();
	}
	
	public function get_advanced_settings_fields(){
		return array(
			'add-to_cart_text_settings' => array('title'=>__('Add to cart text', 'woo-extra-product-options'), 'type'=>'separator', 'colspan'=>'3'),
			'add_to_cart_text_addon' => array(
				'name'=>'add_to_cart_text_addon', 'label'=>__('Products having Extra Options', 'woo-extra-product-options'), 'type'=>'text', 'value'=>'Select options', 'placeholder'=>__('ex: Select options', 'woo-extra-product-options')
			),
			'add_to_cart_text_simple' => array(
				'name'=>'add_to_cart_text_simple', 'label'=>__('Simple Products', 'woo-extra-product-options'), 'type'=>'text', 'value'=>'', 'placeholder'=>__('ex: Add to cart', 'woo-extra-product-options')
			),
			'add_to_cart_text_variable' => array(
				'name'=>'add_to_cart_text_variable', 'label'=>__('Variable Products', 'woo-extra-product-options'), 'type'=>'text', 'value'=>'', 'placeholder'=>__('ex: Select options', 'woo-extra-product-options')
			),
			'section_other_settings' => array('title'=>__('Other Settings', 'woo-extra-product-options'), 'type'=>'separator', 'colspan'=>'3'),
			'hide_in_cart' => array(
				'name'=>'hide_in_cart', 'label'=>__('Hide custom fields in Cart Page', 'woo-extra-product-options'), 'type'=>'checkbox', 'value'=>'yes', 'checked'=>0
			),
			'hide_in_checkout' => array(
				'name'=>'hide_in_checkout', 'label'=>__('Hide custom fields in Checkout page', 'woo-extra-product-options'), 'type'=>'checkbox', 'value'=>'yes', 'checked'=>0
			),
			'hide_in_order' => array(
				'name'=>'hide_in_order', 'label'=>__('Hide custom fields in Order Details page', 'woo-extra-product-options'), 'type'=>'checkbox', 'value'=>'yes', 'checked'=>0
			),
			'allow_get_method' => array(
				'name'=>'allow_get_method', 'label'=>__('Allow posting extra options as url parameters', 'woo-extra-product-options'), 'type'=>'checkbox', 'value'=>'yes', 'checked'=>0
			),

			/*'disable_select2_for_select_fields' => array(
				'name'=>'disable_select2_for_select_fields', 'label'=>'Disable "Enhanced Select(Select2)" for select fields.', 'type'=>'checkbox', 'value'=>'yes', 'checked'=>0
			)*/
		);
	}
	
	public function render_page(){
		$this->render_tabs();
		$this->render_content();
	}
		
	public function save_advanced_settings($settings){
		$result = update_option(THWEPOF_Utils::OPTION_KEY_ADVANCED_SETTINGS, $settings, 'no');
		return $result;
	}
	
	private function reset_settings(){
		check_admin_referer( 'update_advanced_settings', 'update_advanced_nonce' );

		$capability = THWEPOF_Utils::wepo_capability();
		if(!current_user_can($capability)){
			wp_die();
		}

		delete_option(THWEPOF_Utils::OPTION_KEY_ADVANCED_SETTINGS);
		$this->print_notices(__('Settings successfully reset.', 'woo-extra-product-options'), 'updated', false);
	}
	
	private function save_settings(){

		check_admin_referer( 'update_advanced_settings', 'update_advanced_nonce' );

		$capability = THWEPOF_Utils::wepo_capability();
		if(!current_user_can($capability)){
			wp_die();
		}

		$settings = array();
		
		foreach ( $this->settings_fields as $name => $field ) {
			$i_name = 'i_' . $name;
			
			// Check existence and get unslashed value
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in subsequent lines
			$raw_value = isset( $_POST[ $i_name ] ) ? wp_unslash( $_POST[ $i_name ] ) : '';

			if ( $field['type'] === 'multiselect_grouped' ) {
				// Sanitize (wc_clean handles arrays recursively)
				$cleaned_value = wc_clean( $raw_value );
				// Convert array to CSV string
				$value = is_array( $cleaned_value ) ? implode( ',', $cleaned_value ) : $cleaned_value;
			} else {
				// Standard sanitization
				$value = wc_clean( $raw_value );
			}
			
			$settings[ $name ] = $value;
		}
				
		$result = $this->save_advanced_settings($settings);
		if ($result == true) {
			$this->print_notices(__('Your changes were saved.', 'woo-extra-product-options'), 'updated', false);
		} else {
			$this->print_notices(__('Your changes were not saved due to an error (or you made none!).', 'woo-extra-product-options'), 'error', false);
		}	
	}
	
	private function render_content(){
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in reset_settings
		if(isset($_POST['reset_settings']))
			$this->reset_settings();	
			
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_settings
		if(isset($_POST['save_settings']))
			$this->save_settings();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_plugin_settings
		if(isset($_POST['save_plugin_settings'])) 
			$result = $this->save_plugin_settings();
			
    	$this->render_plugin_settings();
    	$this->render_import_export_settings();
	}

	private function render_plugin_settings(){
		$settings = THWEPOF_Utils::get_advanced_settings();
		?>            
        <div class="wrap" style="padding-left: 13px;">
		    <form id="advanced_settings_form" method="post" action="">
                <table class="thwepof-settings-table thpladmin-form-table">
                    <tbody>
                    <?php
                    $this->render_add_to_cart_btn_settings($settings);
                    $this->render_other_settings($settings);
					?>
                    </tbody>
                </table> 
                <p class="submit">
					<input type="submit" name="save_settings" class="btn btn-small btn-primary" value="<?php esc_attr_e('Save changes', 'woo-extra-product-options'); ?>">
                    <input type="submit" name="reset_settings" class="btn btn-small" value="<?php esc_attr_e('Reset to default','woo-extra-product-options'); ?>" 
					onclick="return confirm('Are you sure you want to reset to default settings? all your changes will be deleted.');">
					<?php wp_nonce_field( 'update_advanced_settings', 'update_advanced_nonce' ); ?>
            	</p>
            </form>
    	</div>       
    	<?php
	}

	private function render_add_to_cart_btn_settings($settings){
		$this->render_form_elm_row_title(__('Modify Add to cart button text', 'woo-extra-product-options'));
		$this->render_form_elm_row($this->settings_fields['add_to_cart_text_addon'], $settings);
		$this->render_form_elm_row($this->settings_fields['add_to_cart_text_simple'], $settings);
		$this->render_form_elm_row($this->settings_fields['add_to_cart_text_variable'], $settings);		
	}

	private function render_other_settings($settings){
		$this->render_form_elm_row_title(__('Other Settings', 'woo-extra-product-options'));
		$this->render_form_elm_row_cb($this->settings_fields['hide_in_cart'], $settings, true);
		$this->render_form_elm_row_cb($this->settings_fields['hide_in_checkout'], $settings, true);
		$this->render_form_elm_row_cb($this->settings_fields['hide_in_order'], $settings, true);
		$this->render_form_elm_row_cb($this->settings_fields['allow_get_method'], $settings, true);
	}
	
    /************************************************
	 *-------- IMPORT & EXPORT SETTINGS - START -----
	 ************************************************/
	public function prepare_plugin_settings(){
		$settings_sections = get_option(THWEPOF_Utils::OPTION_KEY_CUSTOM_SECTIONS);
		$settings_hook_map = get_option(THWEPOF_Utils::OPTION_KEY_SECTION_HOOK_MAP);
		$settings_name_title_map = get_option(THWEPOF_Utils::OPTION_KEY_NAME_TITLE_MAP);
		$settings_advanced = get_option(THWEPOF_Utils::OPTION_KEY_ADVANCED_SETTINGS);

		$plugin_settings = array(
			'OPTION_KEY_CUSTOM_SECTIONS' => $settings_sections,
			'OPTION_KEY_SECTION_HOOK_MAP' => $settings_hook_map,
			'OPTION_KEY_NAME_TITLE_MAP' => $settings_name_title_map,
			'OPTION_KEY_ADVANCED_SETTINGS' => $settings_advanced,
		);

		return base64_encode(serialize($plugin_settings));
		// return base64_encode(json_encode($plugin_settings));
	}
	
	public function render_import_export_settings(){
		/*
		if(isset($_POST['save_plugin_settings'])) 
			$result = $this->save_plugin_settings(); 
		*/

		if(isset($_POST['import_settings'])){			   
		} 
		
		$plugin_settings = $this->prepare_plugin_settings();
		if(isset($_POST['export_settings'])) {
			check_admin_referer( 'import_wepo_settings', 'import_wepo_nonce' );
			echo wp_kses_post($this->export_settings($plugin_settings));   
		}
		
		$imp_exp_fields = array(
			'section_import_export' => array('title'=>__('Backup and Import Settings', 'woo-extra-product-options'), 'type'=>'separator', 'colspan'=>'3'),
			'settings_data' => array(
				'name'=>'settings_data', 'label'=>__('Plugin Settings Data', 'woo-extra-product-options'), 'type'=>'textarea', 'value' => $plugin_settings,
				'sub_label'=>__('You can transfer the saved settings data between different installs by copying the text inside the text box. To import data from another install, replace the data in the text box with the one from another install and click "Import Settings".', 'woo-extra-product-options'),
				//'sub_label'=>'You can insert the settings data to the textarea field to import the settings from one site to another website.'
			),
		);
		?>
		<div style="padding-left: 30px;">               
		    <form id="import_export_settings_form" method="post" action="" class="clear">
                <table class="thwepof-settings-table">
                    <tbody>
                    <?php
                    $this->render_form_elm_row_title(__('Backup and Import Settings', 'woo-extra-product-options'));
					$this->render_form_elm_row_ta($imp_exp_fields['settings_data']);
					?>
                    </tbody>
					<tfoot>
						<tr valign="top">
							<td colspan="2">&nbsp;</td>
							<td class="submit">
								<input type="submit" name="save_plugin_settings" class="btn btn-small btn-primary" value="<?php esc_attr_e('Import Settings', 'woo-extra-product-options'); ?>">
								<?php wp_nonce_field( 'import_wepo_settings', 'import_wepo_nonce' ); ?>
								<!--<input type="submit" name="import_settings" class="button" value="Import Settings(CSV)">-->
								<!--<input type="submit" name="export_settings" class="button" value="Export Settings(CSV)">-->
							</td>
						</tr>
					</tfoot>
                </table> 
            </form>
    	</div> 
		<?php
	}
		
	private function get_import_allowed_classes() {
		return array(
			'THWEPOF_Section',
			'WEPOF_Product_Field',
			'WEPOF_Product_Field_InputText',
			'WEPOF_Product_Field_Hidden',
			'WEPOF_Product_Field_Email',
			'WEPOF_Product_Field_Radio',
			'WEPOF_Product_Field_Textarea',
			'WEPOF_Product_Field_Checkbox',
			'WEPOF_Product_Field_Tel',
			'WEPOF_Product_Field_CheckboxGroup',
			'WEPOF_Product_Field_Password',
			'WEPOF_Product_Field_Number',
			'WEPOF_Product_Field_Heading',
			'WEPOF_Product_Field_Select',
			'WEPOF_Product_Field_ColorPicker',
			'WEPOF_Product_Field_DatePicker',
			'WEPOF_Product_Field_Separator',
			'WEPOF_Product_Field_Range',
			'WEPOF_Product_Field_Url',
			'WEPOF_Product_Field_TimePicker',
			'WEPOF_Product_Field_Paragraph',
			'WEPOF_Product_Field_Switch',
			'WEPOF_Condition',
			'WEPOF_Condition_Rule',
			'WEPOF_Condition_Set',
			'WEPOF_Condition_Rule_Set',
		);
	}

	private function is_valid_import_value($value, $allowed_classes, $depth = 0) {
		if ($depth > 64) {
			return false;
		}
		if (is_object($value)) {
			if (!in_array(get_class($value), $allowed_classes, true)) {
				return false;
			}
			$value = (array) $value;
		}
		if (is_array($value)) {
			foreach ($value as $child) {
				if (!$this->is_valid_import_value($child, $allowed_classes, $depth + 1)) {
					return false;
				}
			}
			return true;
		}
		return is_scalar($value) || is_null($value);
	}

	/**
	 * Legacy backups may contain object values in advanced settings. These are not
	 * valid plugin settings and must never be written back to the options table.
	 */
	private function remove_imported_advanced_objects(&$settings, &$removed, $depth = 0) {
		if ($depth > 64) {
			return false;
		}
		foreach (array_keys($settings) as $key) {
			if (is_object($settings[$key])) {
				unset($settings[$key]);
				$removed = true;
			} elseif (is_array($settings[$key])) {
				if (!$this->remove_imported_advanced_objects($settings[$key], $removed, $depth + 1)) {
					return false;
				}
			}
		}
		return true;
	}

	public function save_plugin_settings(){

		check_admin_referer( 'import_wepo_settings', 'import_wepo_nonce' );

		$capability = THWEPOF_Utils::wepo_capability();
		if(!current_user_can($capability)){
			wp_die();
		}

		if(isset($_POST['i_settings_data']) && !empty($_POST['i_settings_data'])) {
			if (!is_string($_POST['i_settings_data'])) {
				$this->print_notices(__('Invalid import settings data.', 'woo-extra-product-options'), 'error', false);
				return false;
			}

			$settings_data_encoded = sanitize_textarea_field(wp_unslash($_POST['i_settings_data']));
			$base64_decoded = base64_decode($settings_data_encoded, true);
			if ($base64_decoded === false || !is_serialized($base64_decoded)) {
				$this->print_notices(__('Invalid import settings data.', 'woo-extra-product-options'), 'error', false);
				return false;
			}

			$allowed_classes = $this->get_import_allowed_classes();
			try {
				$settings = @unserialize($base64_decoded, array('allowed_classes' => $allowed_classes));
			} catch (\Throwable $exception) {
				$settings = false;
			}

			$removed_advanced_objects = false;
			if (
			    is_array($settings) &&
			    isset($settings['OPTION_KEY_ADVANCED_SETTINGS']) &&
			    is_array($settings['OPTION_KEY_ADVANCED_SETTINGS'])
			) {
			    $depth_ok = $this->remove_imported_advanced_objects(
			        $settings['OPTION_KEY_ADVANCED_SETTINGS'],
			        $removed_advanced_objects
			    );

			    if (!$depth_ok || $removed_advanced_objects) {
			        $this->print_notices(__('Invalid or unsafe import settings data.', 'woo-extra-product-options'), 'error', false);
			        return false;
			    }
			}

			if (!is_array($settings) || !$this->is_valid_import_value($settings, $allowed_classes)) {
				$this->print_notices(__('Invalid or unsafe import settings data.', 'woo-extra-product-options'), 'error', false);
				return false;
			}

			$allowed_keys = array(
				'OPTION_KEY_CUSTOM_SECTIONS',
				'OPTION_KEY_SECTION_HOOK_MAP',
				'OPTION_KEY_NAME_TITLE_MAP',
				'OPTION_KEY_ADVANCED_SETTINGS',
			);
			foreach ($settings as $key => $value) {
				if (!in_array($key, $allowed_keys, true) || ($value !== false && !is_array($value))) {
					$this->print_notices(__('Invalid import settings format.', 'woo-extra-product-options'), 'error', false);
					return false;
				}
			}
			if (!empty($settings['OPTION_KEY_CUSTOM_SECTIONS'])) {
				foreach ($settings['OPTION_KEY_CUSTOM_SECTIONS'] as $section) {
					if (!($section instanceof THWEPOF_Section)) {
						$this->print_notices(__('Invalid imported section.', 'woo-extra-product-options'), 'error', false);
						return false;
					}
				}
			}

			$result = $result1 = $result2 = $result3 = false;

			if($settings){
				foreach($settings as $key => $value){	
					if($key === 'OPTION_KEY_CUSTOM_SECTIONS'){
						$result = update_option(THWEPOF_Utils::OPTION_KEY_CUSTOM_SECTIONS, $value, 'no');
					}
					if($key === 'OPTION_KEY_SECTION_HOOK_MAP'){ 
						$result1 = update_option(THWEPOF_Utils::OPTION_KEY_SECTION_HOOK_MAP, $value, 'no');
					}
					if($key === 'OPTION_KEY_NAME_TITLE_MAP'){
						$result2 = update_option(THWEPOF_Utils::OPTION_KEY_NAME_TITLE_MAP, $value, 'no');
					}
					if($key === 'OPTION_KEY_ADVANCED_SETTINGS'){ 
						$result3 = $this->save_advanced_settings($value);  
					}
				}
			}
									
			if($result || $result1 || $result2 || $result3){
				$this->print_notices(__('Your Settings Updated.', 'woo-extra-product-options'), 'updated', false);
				return true; 
			}else{
				$this->print_notices(__('Your changes were not saved due to an error (or you made none!).', 'woo-extra-product-options'), 'error', false);
				return false;
			}
		}
	}

	public function export_settings($settings){
		ob_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=\"wcfe-checkout-field-editor-settings.csv\";" );
		echo esc_html($settings);	
        ob_flush();     
     	exit; 		
	}
	
	public function import_settings(){
	
	}

	// public function is_json($settings,$return_data = false) {
	// 	$data = json_decode($settings);
	// 	return (json_last_error() == JSON_ERROR_NONE) ? ($return_data ? $data : TRUE) : FALSE;
	// }
	
    /**********************************************
	 *-------- IMPORT & EXPORT SETTINGS - END -----
	 **********************************************/


    /*----- Form Element Row -----*/
	public function render_form_elm_row_title($title=''){
		?>
		<tr>
			<td colspan="3" class="section-title" ><?php echo esc_html($title); ?></td>
		</tr>
		<?php
	}

	private function render_form_elm_row($field, $settings=false){
		$name = $field['name'];
		if(is_array($settings) && isset($settings[$name])){
			$field['value'] = $settings[$name];
		}

		?>
		<tr>
			<?php $this->render_form_field_element($field, $this->cell_props); ?>
		</tr>
		<?php
	}

	private function render_form_elm_row_ta($field, $settings=false){
		$name = $field['name'];
		if(is_array($settings) && isset($settings[$name])){
			$field['value'] = $settings[$name];
		}
		
		?>
		<tr valign="top">
			<?php $this->render_form_field_element($field, $this->cell_props_TA); ?>
		</tr>
		<?php
	}

	private function render_form_elm_row_cb($field, $settings=false, $merge_cells=false){
		$name = $field['name'];
		if(is_array($settings) && isset($settings[$name])){
			if($field['value'] === $settings[$name]){
				$field['checked'] = 1;
			}
		}

		if($merge_cells){
			?>
			<tr>
				<td colspan="3">
		    		<?php $this->render_form_field_element($field, $this->cell_props_CB, false); ?>
		    	</td>
		    </tr>
			<?php
		}else{
			?>
			<tr>
				<td colspan="2"></td>
				<td class="field">
		    		<?php $this->render_form_field_element($field, $this->cell_props_CB, false); ?>
		    	</td>
		    </tr>
			<?php
		}
	}
}

endif;