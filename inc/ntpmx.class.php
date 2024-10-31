<?php
class ntpmx_api{

    private $ntpmx_version;
    public $ntpmx_private_domain;
    private $ntpmx_api_key;
    private $ntpmx_show_rows;
    private $ntpmx_clean_rows_in_days;
    private $ntpmx_date_activation;
    private $count_new_contents;
    private $ntpmx_status;
    private $ntpmx_msg;
    private $ntpmx_path_img;
    public $ntpmx_public_domain;
    const ntpmx_max_new_content=99;
    private $ntpmx_rich_text;
    private $ntpmx_links;
    private $ntpmx_source;
    public $ntpmx_sku;
    public $ntpmx_customer_id;

    function __construct(){
        add_action( 'admin_menu', array($this, 'ntpmx_plugin_settings_page') );
        add_action( 'admin_menu', array($this, 'ntpmx_import'));
        add_action( 'admin_menu', array($this, 'ntpmx_import_force'));
        $this->ntpmx_private_domain = 'https://admin.notipress.mx';
        $this->ntpmx_public_domain = 'https://notipress.mx';
        $this->ntpmx_api_key = get_option("ntpmx_api");
        $this->ntpmx_rich_text = get_option("ntpmx_rich_text");
        $this->ntpmx_links = get_option("ntpmx_links");
        $this->ntpmx_source = get_option("ntpmx_source");
        $this->ntpmx_show_rows = get_option("ntpmx_show_rows");
        $this->ntpmx_clean_rows_in_days = get_option('ntpmx_clean_rows_in_days');
        $this->ntpmx_date_activation = get_option('ntpmx_date_activation');
        if (!isset($this->ntpmx_show_rows) && empty($this->ntpmx_show_rows)) $this->ntpmx_show_rows=20;
        if (!isset($this->ntpmx_clean_rows_in_days) && empty($this->ntpmx_clean_rows_in_days)) $this->ntpmx_clean_rows_in_days=96;
        $this->count_new_contents=0;
        $this->ntpmx_path_img = '/img/notifree/';
        $this->ntpmx_version = get_option('ntpmx_version');
        $this->ntpmx_sku=get_option('ntpmx_sku');
        self::ntpmx_get_customer_id();
        self::ntpmx_update_db_check();
    }
    public function ntpmx_activation(){
        add_option('ntpmx_product','NotiPress Noticias',0);
        update_option('ntpmx_version',NTPMX_VERSION,0);
        add_option('ntpmx_date_install',current_datetime()->format('Y-m-d H:i'));
        update_option('ntpmx_date_update',current_datetime()->format('Y-m-d H:i'));
        add_option('ntpmx_date_activation');
        add_option('ntpmx_minutes',3600);
        add_option('ntpmx_show_rows',20);
        add_option('ntpmx_clean_rows_in_days',96);
        add_option('ntpmx_qty',20);
        add_option('ntpmx_new_content',0);
        add_option('ntpmx_rich_text',1);
        add_option('ntpmx_links',1);
        add_option('ntpmx_source',0);
        add_option('ntpmx_sku',null);
        add_option('ntpmx_customer_id',-1);
        add_option('ntpmx_sku_name',"NotiFree");
        self::ntpmx_db_structure();
        if (!wp_next_scheduled( 'ntpmx_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'ntpmx_cron' );
        }
        add_action( 'ntpmx_cron', 'ntpmx_get_contents_from_notipress' );
        if (!get_option('ntpmx_last_check')) add_option('ntpmx_last_check');
        $this->ntpmx_status=0;
        $this->ntpmx_msg="";
        self::ntpmx_get_sku();
    }
    public function init(){
        add_action('admin_menu', [$this, 'ntpmx_menu' ]);
        add_action('admin_menu', [$this, 'ntpmx_plugin_setting']);
        add_action('admin_init', [$this, 'ntpmx_setup_sections']);
        add_action('admin_init', [$this, 'ntpmx_setup_api_fields']);
        add_action('admin_init', [$this, 'ntpmx_setup_minutes_fields']);
        add_action('admin_init', [$this, 'ntpmx_setup_qty_fields']);
        add_action('admin_init', [$this, 'ntpmx_css_js']);
        add_filter('query_vars', [$this, 'ntpmx_get_remote_id']);
        add_filter('query_vars', [$this, 'ntpmx_get_force']);
        self::ntpmx_has_new_content() > 0 ? $this->count_new_contents = self::ntpmx_has_new_content() : $this->count_new_contents = 0;
        $ntpmx_qty = esc_attr(get_option('ntpmx_qty'));
        if (!isset($ntpmx_qty) && empty($ntpmx_qty)) update_option('ntpmx_qty',20);
        if ($ntpmx_qty <= 0) update_option('ntpmx_qty',20);
        if ($ntpmx_qty >= 50) update_option('ntpmx_qty',50);
        if (!get_option('ntpmx_version')) add_option('ntpmx_version',NTPMX_VERSION);
        if (!get_option('ntpmx_last_check')) add_option('ntpmx_last_check');
        if (!get_option('ntpmx_new_content')) add_option('ntpmx_new_content',0);
        if (!get_option('ntpmx_rich_text')) add_option('ntpmx_rich_text',1);
        if (!get_option('ntpmx_links')) add_option('ntpmx_links',1);
        if (!get_option('ntpmx_source')) add_option('ntpmx_source',0);
        if (!get_option('ntpmx_version')) {
            add_option('ntpmx_version', NTPMX_VERSION);
        }
        if (!get_option('ntpmx_customer_id')){
            add_option('ntpmx_customer_id',-1);
        }
        self::ntpmx_get_sku_name_force();
        self::ntpmx_update_db_check();
    }
    public function ntpmx_date_activation(){
        if (isset($this->ntpmx_date_activation) && !empty($this->ntpmx_date_activation)) {
            return self::ntpmx_get_date($this->ntpmx_date_activation);
        }
        else{
            if (empty($this->ntpmx_date_activation)) {
                update_option('ntpmx_date_activation', current_datetime()->format('Y-m-d H:i'));
            }
            return self::ntpmx_get_date($this->ntpmx_date_activation);
        }
    }
    public function ntpmx_update_db_check(){
        if (version_compare($this->ntpmx_version, '1.4.1', '<=' ) ) {
            self::ntpmx_db_update();
            update_option('ntpmx_version', NTPMX_VERSION);
        }
    }
    private static function ntpmx_db_structure(){
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;
        $table_name = $wpdb->prefix."ntpmx_contents";
        $sql = "CREATE TABLE IF NOT EXISTS " . $table_name . "(              
              id int(9) NOT NULL AUTO_INCREMENT,
              date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              date_published datetime NULL,
              remote_id int(9) NOT NULL,
              title VARCHAR(100) NOT NULL,
              intro VARCHAR(200) NOT NULL,
              category VARCHAR(32) NOT NULL,
              author VARCHAR(32) NOT NULL,
              status tinyint(1) DEFAULT 0,
              thumbnail VARCHAR(32) NULL,
              notipack tinyint(1) DEFAULT 0,
              photo_id int(9) NULL,
              photo_code varchar(16) NULL,
              content_type tinyint(1) DEFAULT 1,
              UNIQUE KEY id (id)
            );";
        dbDelta( $sql );
    }
    public static function ntpmx_refresh_page($sec = 1, $url = '') {
        echo("<meta http-equiv='refresh' content='".$sec.";url=".$url."'>");
    }
    private static function ntpmx_db_update(){
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;
        $table_ntpmx_contents = $wpdb->prefix."ntpmx_contents";
        $sql="IF NOT EXISTS( SELECT NULL
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = '" . $table_ntpmx_contents . "'
                AND table_schema = '" . $table_ntpmx_contents . "'
                AND column_name = 'thumbnail')  THEN
                ALTER TABLE " . $table_ntpmx_contents . " ADD thumbnail varchar(32) NULL;
                END IF;";
        dbDelta($sql);
        $sql="IF NOT EXISTS( SELECT NULL
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = '" . $table_ntpmx_contents . "'
                AND table_schema = '" . $table_ntpmx_contents . "'
                AND column_name = 'notipack')  THEN
                ALTER TABLE " . $table_ntpmx_contents . " ADD notipack tinyint(1) NOT NULL DEFAULT 0;
                END IF;";
        dbDelta($sql);
        $sql="IF NOT EXISTS( SELECT NULL
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = '" . $table_ntpmx_contents . "'
                AND table_schema = '" . $table_ntpmx_contents . "'
                AND column_name = 'photo_id')  THEN
                ALTER TABLE " . $table_ntpmx_contents . " ADD photo_id int(9) NULL;
                END IF;";
        dbDelta($sql);
        $sql="IF NOT EXISTS( SELECT NULL
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = '" . $table_ntpmx_contents . "'
                AND table_schema = '" . $table_ntpmx_contents . "'
                AND column_name = 'photo_code')  THEN
                ALTER TABLE " . $table_ntpmx_contents . " ADD photo_code varchar(16) NULL;
                END IF;";
        dbDelta($sql);
        $sql="IF NOT EXISTS( SELECT NULL
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = '" . $table_ntpmx_contents . "'
                AND table_schema = '" . $table_ntpmx_contents . "'
                AND column_name = 'content_type')  THEN
                ALTER TABLE " . $table_ntpmx_contents . " ADD content_type tinyint(1) NOT NULL DEFAULT 1;
                END IF;";
        dbDelta($sql);
    }
    private function ntpmx_recurrence_cron(){
        $interval = get_option('ntpmx_minutes');
        switch ($interval){
            case 3600:
                return "hourly";
                break;
            case 43200:
                return "twicedaily";
                break;
            case 86400:
                return "daily";
                break;
            default:
                return "hourly";
                break;
        }
    }
    public function ntpmx_plugin_setting() {
        add_options_page( 'Configuración', 'Configuración', 'manage_options', 'ntpmx_settings', [$this,'ntpmx_api_options_page']);
        register_setting( 'ntpmx_setting', 'ntpmx_api',[$this,'ntpmx_field_api_validation']);
        register_setting( 'ntpmx_setting', 'ntpmx_minutes','');
        register_setting( 'ntpmx_setting', 'ntpmx_qty','');
        register_setting( 'ntpmx_setting', 'ntpmx_show_rows','');
        register_setting( 'ntpmx_setting', 'ntpmx_rich_text','');
        register_setting( 'ntpmx_setting', 'ntpmx_links','');
        register_setting( 'ntpmx_setting', 'ntpmx_source','');
    }
    public function ntpmx_setup_sections() {
        add_settings_section( 'ntpmx_plugin_api', 'Licencia de uso', [$this, 'ntpmx_section_callback'], 'ntpmx_settings' );
        add_settings_section( 'ntpmx_plugin_minutes', 'Conexión a plataforma de NotiPress', [$this, 'ntpmx_section_callback'], 'ntpmx_settings' );
        add_settings_section( 'ntpmx_plugin_qty', 'Visualización', [$this, 'ntpmx_section_callback'], 'ntpmx_settings' );
    }
    public function ntpmx_setup_api_fields() {
        add_settings_field( 'ntpmx_plugin_api', 'Clave de licencia:', [$this, 'ntpmx_field_api_callback'], 'ntpmx_settings', 'ntpmx_plugin_api' );

    }
    public function ntpmx_setup_minutes_fields() {
        add_settings_field( 'ntpmx_plugin_minutes', 'Buscar contenidos:', array( $this, 'ntpmx_field_minutes_callback' ), 'ntpmx_settings', 'ntpmx_plugin_minutes' );
        add_settings_field( 'ntpmx_plugin_rich_text', 'Traer texto enriquecido:', array( $this, 'ntpmx_field_rich_text_callback' ), 'ntpmx_settings', 'ntpmx_plugin_minutes' );
        add_settings_field( 'ntpmx_plugin_links', 'Traer texto con links:', array( $this, 'ntpmx_field_links_callback' ), 'ntpmx_settings', 'ntpmx_plugin_minutes' );
        add_settings_field( 'ntpmx_plugin_source', 'Agregar agencia/autor:', array( $this, 'ntpmx_field_source_callback' ), 'ntpmx_settings', 'ntpmx_plugin_minutes' );
    }
    public function ntpmx_setup_qty_fields() {
        add_settings_field( 'ntpmx_plugin_qty', 'Mostrar:', array( $this, 'ntpmx_field_qty_callback' ), 'ntpmx_settings', 'ntpmx_plugin_qty' );
    }
    public function ntpmx_section_callback($arguments){
        switch( $arguments['id'] ){
            case 'ntpmx_api_lbl':
                echo 'API1';
                break;
        }
    }
    public function ntpmx_field_api_validation($value){
        if (empty($value)){
        }
        else if (!preg_match( '/^[a-z0-9]{16}$/i', $value)){
        }
        else {
            return sanitize_text_field($value);
        }
    }
    public function ntpmx_admin_notices_fail_key(){
        $obj = get_current_screen();
        $base = $obj->base;
        $page = $obj->parent_base;
        if ($page=="ntpmx-contents" || $base=="settings_page_ntpmx_settings"){
            echo "<div class='notice notice-error my-dismiss-notice is-dismissible'><p>La licencia del plugin <strong>NotiPress Noticias</strong> no tiene el formato correcto. Favor de verificar y/o contactar al fabricante.</p></div>";
        }
    }
    public function ntpmx_field_api_callback() {
        $key = $this->ntpmx_api_key;
        echo '<p><input name="ntpmx_api" id="ntpmx_api" type="text" autocomplete="off" value="' . $key . '" /></p>';
    }
    public function ntpmx_field_minutes_callback($arguments) {
        echo $this->ntpmx_dropdown_minutes(esc_attr(get_option( 'ntpmx_minutes' )));
    }
    public function ntpmx_field_qty_callback() {
        echo $this->ntpmx_dropdown_qty(esc_attr(get_option('ntpmx_show_rows')));
    }
    public function ntpmx_field_rich_text_callback($value){
        $value = esc_attr(get_option( 'ntpmx_rich_text' ));
        ?>
        <select name="ntpmx_rich_text" id="ntpmx_rich_text">
            <option value="1" <?php if($value == '1') echo 'selected'; ?>>Si</option>
            <option value="0" <?php if($value == '0') echo 'selected'; ?>>No</option>
        </select>
        <?php
    }
    public function ntpmx_field_links_callback($value){
        $value = esc_attr(get_option( 'ntpmx_links' ));
        ?>
        <select name="ntpmx_links" id="ntpmx_links">
            <option value="1" <?php if($value == '1') echo 'selected'; ?>>Si</option>
            <option value="0" <?php if($value == '0') echo 'selected'; ?>>No</option>
        </select>
        <?php
    }
    public function ntpmx_field_source_callback($value){
        $value = esc_attr(get_option( 'ntpmx_source' ));
        ?>
        <select name="ntpmx_source" id="ntpmx_source">
            <option value="1" <?php if($value == '1') echo 'selected'; ?>>Si</option>
            <option value="0" <?php if($value == '0') echo 'selected'; ?>>No</option>
        </select>
        <?php
    }
    public function ntpmx_get_remote_id($aVars) {
        $aVars[] = "remote_id";
        return $aVars;
    }
    public function ntpmx_get_force($aVars) {
        $aVars[] = "force";
        return $aVars;
    }
    public function ntpmx_plugin_settings_page() {
        // Add the menu item and page
        $page_title = 'NotiPress Plugin';
        $menu_title = 'NotiPress Plugin';
        $capability = 'manage_options';
        $slug = 'ntpmx_settings';
        $callback = array( $this, 'ntpmx_api_options_page' );
        $position = 100;
        add_submenu_page( null,$page_title, $menu_title, $capability, $slug, $callback, $position );
    }
    public function ntpmx_import() {
        // Add the menu item and page
        $page_title = '';
        $menu_title = '';
        $capability = 'manage_options';
        $slug = 'ntpmx-import';
        $callback = array( $this, 'ntpmx_import_page' );
        $position = 100;
        add_submenu_page( null,$page_title, $menu_title, $capability, $slug, $callback, $position );
    }
    public function ntpmx_import_force() {
        $page_title = '';
        $menu_title = '';
        $capability = 'manage_options';
        $slug = 'ntpmx-import-force';
        $callback = array( $this, 'ntpmx_import_page_force' );
        $position = 100;
        add_submenu_page( null,$page_title, $menu_title, $capability, $slug, $callback, $position );
    }
    public function ntpmx_menu(){
        $count=0;
        define( 'NTPMX_ICON',plugins_url('notipress-noticias/img/notipress-menu.png'));
        $this->count_new_contents=get_option('ntpmx_new_content');
        if (isset($this->count_new_contents) && !empty($this->count_new_contents) && $this->count_new_contents > 0){
            $count = $this->count_new_contents;
            if ($this->count_new_contents>=self::ntpmx_max_new_content){
                add_menu_page('NotiPress', 'NotiPress ' . sprintf('<span class="awaiting-mod">100+</span>', $count), 'manage_options', 'ntpmx-contents', [$this, 'ntpmx_menu_contents'], NTPMX_ICON);
            }
            else {
                add_menu_page('NotiPress', 'NotiPress ' . sprintf('<span class="awaiting-mod">%d</span>', $count), 'manage_options', 'ntpmx-contents', [$this, 'ntpmx_menu_contents'], NTPMX_ICON);
            }
        }
        else{
            add_menu_page('NotiPress','NotiPress','manage_options','ntpmx-contents',[$this,'ntpmx_menu_contents'],NTPMX_ICON);
        }

    }
    public function ntpmx_menu_contents() {
        include NTPMX_PLUGIN_DIR . 'inc/ntpmx-contents.php';
    }
    public function ntpmx_import_page() {
        if (!empty(filter_input(INPUT_GET,"remote_id",FILTER_SANITIZE_STRING))){
            $query_args = ['page'=>'ntpmx-contents'];
            $url = add_query_arg( $query_args, admin_url( 'admin.php'));
            //$this->ntpmx_get_content(filter_input(INPUT_GET,"remote_id",FILTER_SANITIZE_STRING));
            if ($this->ntpmx_get_content(filter_input(INPUT_GET,"remote_id",FILTER_SANITIZE_STRING)) == -1){
                ?>
                <div class="">
                    <p>&nbsp;</p>
                </div>
                <div class="message-box">
                    <h2 class="message-title">Licencia</h2>
                    <p class="message-text">Para obtener acceso al contenido premium o reciente debe contar con una licencia de pago.</p>
                    <a class="message-button" href="<?=$url?>">Ir al dashboard</a>
                </div>
                <?php
                die;
            }
        }
        echo("<script>location.href = '".$url."'</script>");
    }
    public function ntpmx_import_page_force() {
        if (empty($this->ntpmx_api_key)) {
            $query_args = ['page' => 'ntpmx-contents'];
            $url = add_query_arg($query_args, admin_url('admin.php'));
            $this->ntpmx_refresh_page(1,$url);
        }
        else{
            $this->ntpmx_clean_database();
            self::ntpmx_get_sku();
            self::ntpmx_get_sku_name_force();
            if (!empty(filter_input(INPUT_GET, "force", FILTER_SANITIZE_STRING))) {
                if (filter_input(INPUT_GET, "force", FILTER_SANITIZE_STRING) == 1) {
                    $this->ntpmx_get_contents_from_notipress();
                    if (empty($this->ntpmx_date_activation)) {
                        update_option('ntpmx_date_activation', current_datetime()->format('Y-m-d H:i'));
                    }
                }
            }
            $query_args = ['page' => 'ntpmx-contents'];
            $url = add_query_arg($query_args, admin_url('admin.php'));
            echo("<script>location.href = '" . $url . "'</script>");
        }
    }
    public function ntpmx_api_options_page() {
        if (!current_user_can('manage_options'))  {
            wp_die( __( 'No tiene permisos para acceder a la configuración del plugin de NotiPress. Debe ser un admin.' ) );
        }
        ?>
        <form action='options.php' method='post'>
            <input type="hidden" name="updated" value="true" />
            <h2>NotiPress Plugin</h2>
            <?php
            wp_nonce_field( 'ntpmx_update', 'ntpmx_form' );
            settings_fields( 'ntpmx_setting' );
            do_settings_sections( 'ntpmx_settings' );
            submit_button();
            ?>
        </form>
        <?php
        self::ntpmx_update_cron();
    }
    private function ntpmx_update_cron(){
        if (isset($this->ntpmx_api_key) && !empty($this->ntpmx_api_key)) {
            if (wp_next_scheduled('ntpmx_cron')) {
                wp_clear_scheduled_hook('ntpmx_cron');
                $recurrence = self::ntpmx_recurrence_cron();
                wp_schedule_event(time(), $recurrence, 'ntpmx_cron');
            }
        }
    }
    public function ntpmx_dropdown_minutes($dropdown_value) {
        ?>
        <select name="ntpmx_minutes" id="ntpmx_minutes">
            <option value="-1" <?php if($dropdown_value == '-1') echo 'selected'; ?>>Desactivado</option>
            <option value="3600" <?php if($dropdown_value == '3600') echo 'selected'; ?>>Cada hora</option>
            <option value="43200" <?php if($dropdown_value == '43200') echo 'selected'; ?>>Dos veces al día</option>
            <option value="86400" <?php if($dropdown_value == '86400') echo 'selected'; ?>>Cada 24 horas</option>
        </select>
        <?php
    }
    public function ntpmx_dropdown_qty($dropdown_value) {
        ?>
        <select name="ntpmx_show_rows" id="ntpmx_show_rows">
            <option value="10" <?php if($dropdown_value == '20') echo 'selected'; ?>>10 contenidos</option>
            <option value="20" <?php if($dropdown_value == '20') echo 'selected'; ?>>20 contenidos</option>
            <option value="30" <?php if($dropdown_value == '30') echo 'selected'; ?>>30 contenidos</option>
            <option value="40" <?php if($dropdown_value == '40') echo 'selected'; ?>>40 contenidos</option>
        </select>
        <?php
    }
    private function ntpmx_headers($timeout=4){
            return [
                    'headers' => [
                    'timeout' => $timeout,
                    'Authorization' => 'Bearer ' . $this->ntpmx_api_key,
                    ],
                ];
    }
    public function ntpmx_get_content($remote_id)
    {
        global $wpdb;
        $code_status = 0;
        $table_name = $wpdb->prefix . "ntpmx_contents";
        $url = $this->ntpmx_private_domain . "/apiv3/get-content-advanced";
        $args = [
            'headers' => [
                'timeout' => 4,
                'Authorization' => 'Bearer ' . $this->ntpmx_api_key,
            ],
            'body' => [
                'remote_id' => $remote_id,
                'rich_text' => $this->ntpmx_rich_text,
                'links' => $this->ntpmx_links,
                'ntpmx_version' => $this->ntpmx_version,
            ],
        ];
        $output = wp_remote_get($url, $args);
        $output_error = json_decode($output["body"],true);
        if (isset($output_error["error_code"])) {
            if ($output_error["error_code"] == "-1") {
                self::ntpmx_set_status($output_error["error_code"], "Su licencia no le permite acceso a este contenido de NotiPress");
                return -1;
            }
        }
        if (is_array($output) && !is_wp_error($output)) {
            if (isset($output["response"]["code"]) && !empty($output["response"]["code"])) {
                if (is_numeric($output["response"]["code"])) {
                    $code_status = (int)$output["response"]["code"];
                }
            }
            if ($code_status == 401) {
                self::ntpmx_set_status($output["response"]["code"], "Error 401 de acceso al servidor de NotiPress");
                exit;
            } else if ($code_status !== 200) {
                self::ntpmx_set_status($output["response"]["code"], "Error " . $output["response"]["code"] . "  cuando intentaba acceder al servidor de NotiPress");
                exit;
            }
            $arr_data = json_decode($output["body"], true);
            if (is_array($arr_data) && $code_status == 200) {
                $exist = $wpdb->get_var("SELECT count(*) FROM " . $table_name . " where status=1 and remote_id=" . $remote_id);
                if ($exist == 0) {
                    $content_type=1;
                    if ($this->ntpmx_source==1){
                        $text = $arr_data["text"] . "<p>NotiPress/" . $arr_data["author"] . "</p>";
                    }
                    else{
                        $text = $arr_data["text"];
                    }
                    if (isset($arr_data["content_type"])){
                        $content_type = $arr_data["content_type"];
                    }
                    $data = [
                        'post_title' => $arr_data["title"],
                        'post_content' => $text,
                        'post_status' => 'draft',
                        'post_type' => 'post',
                        'content_type'=>$content_type
                    ];
                    $this->ntpmx_insert_content($data);
                    $sql = "UPDATE " . $table_name . " set status=1 where remote_id=" . $remote_id;
                    $wpdb->query($sql);
                }
            }
        }
    }
    private function ntpmx_insert_content($arr){
        wp_insert_post($arr);
    }
    public function ntpmx_get_contents($qty=20){
        $code_status=0;
        $url = $this->ntpmx_private_domain . "/apiv3/content-list-qty/" . $qty;
        $output = wp_remote_get($url, array(
            'headers' => array(
                'timeout' => 5,
                'Authorization' => 'Bearer ' . $this->ntpmx_api_key
            ),
        ));
        if (is_array($output) && !is_wp_error($output)) {
            if (isset($output["response"]["code"]) && !empty($output["response"]["code"])) {
                if (is_numeric($output["response"]["code"])) {
                    $code_status = (int)$output["response"]["code"];
                }
            }
            if ($output["response"]["code"] == 401) {
                self::ntpmx_set_status($output["response"]["code"], "Error 401 de acceso al servidor de NotiPress");
                exit;
            } else if ($output["response"]["code"] !== 200) {
                self::ntpmx_set_status($output["response"]["code"], "Error " . $output["response"]["code"] . "  cuando intentaba acceder al servidor de NotiPress");
                exit;
            }
            $arr_data = json_decode($output["body"], true);
            if (is_array($arr_data) && $code_status == 200) {
                global $wpdb;
                $table_name = $wpdb->prefix . "ntpmx_contents";
                foreach ($arr_data as $item) {
                    $exist = $wpdb->get_var("SELECT count(*) FROM " . $table_name . " where remote_id=" . $item["id"]);
                    if ($exist == 0) {
                        $sql = "INSERT INTO " . $table_name . " (remote_id,title,intro,date_published,category,author,thumbnail,notipack,photo_id,photo_code,content_type) VALUES(" . $item["id"] . "," . self::ntpmx_standarize($item["title"]) . "," . self::ntpmx_standarize($item["intro"]) . "," . self::ntpmx_standarize($item["date_published"]) . "," . self::ntpmx_standarize($item["category"]) . "," . self::ntpmx_standarize($item["author"]) . "," . self::ntpmx_standarize($item["thumbnail"]) . "," . self::ntpmx_standarize($item["notipack"]) . "," . self::ntpmx_standarize($item["photo_id"]) . "," . self::ntpmx_standarize($item["photo_code"]) . "," . self::ntpmx_standarize($item["content_type"]) . ")";
                        $wpdb->query($sql);
                    }
                }
                update_option('ntpmx_new_content',0);
            }
        }
    }
    private function ntpmx_get_last_article_published(){
        global $wpdb;$res=-1;
        $table_name = $wpdb->prefix."ntpmx_contents";
        $row = $wpdb->get_row("SELECT remote_id FROM " . $table_name . " ORDER BY date_published DESC LIMIT 1");
        if (is_object($row)){
            $res = $row->remote_id;
        }
        return $res;
    }
    private function ntpmx_get_datetime_last_check(){
        $last_check=get_option('ntpmx_last_check');
        if (!$last_check) {
            $datetime_last_check = self::ntpmx_get_sub_date(self::ntpmx_sys_get_now(), 301, true);
            return self::ntpmx_diff_seconds($datetime_last_check, self::ntpmx_sys_get_now());
        }
        else{
            return self::ntpmx_diff_seconds($last_check, self::ntpmx_sys_get_now());
        }
    }
    public static function ntpmx_diff_seconds($date1,$date2,$seconds=300){
        if (!isset($date1) & empty($date1)){
            $date1 = self::ntpmx_get_sub_date(self::ntpmx_sys_get_now(),$seconds);
        }
        $new_mins = (strtotime($date1)-strtotime($date2));
        $new_mins = abs($new_mins); $new_mins = floor($new_mins);
        return $new_mins;
    }
    public function ntpmx_sys_get_now(){
        return date("Y-m-d H:i:s",strtotime(date_i18n('Y-m-d H:i:s', current_time('timestamp'))));
    }
    public function ntpmx_get_sub_date($date,$seconds,$with_time = false){
        $now = date("Y-m-d H:i:s",strtotime(date_i18n('Y-m-d H:i:s', current_time('timestamp'))));
        $sys_now = new DateTime($now);
        $sys_now->sub(new \DateInterval('PT' . $seconds . 'S'));
        $new_date = $with_time ? $sys_now->format('Y-m-d H:i:s') : $sys_now->format('Y-m-d');
        return $new_date;
    }
    public function ntpmx_has_new_content(){
        $res=0;
        $code_status=0;
        if (isset($this->ntpmx_api_key) && !empty($this->ntpmx_api_key)) {
            $this->count_new_contents = get_option('ntpmx_new_content');
            if ($this->count_new_contents<=self::ntpmx_max_new_content) {
                $diff = self::ntpmx_get_datetime_last_check();
                if (isset($diff) && strlen($diff)==0){
                    $diff=301;
                }
                if ($diff >= 300) {
                    //echo "_______&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;diff: " . $diff;
                    $last_article_id = self::ntpmx_get_last_article_published();
                    $url = $this->ntpmx_private_domain . "/apiv3/has-new-content?last_article_id=" . $last_article_id;
                    $output = wp_remote_get($url, array(
                        'headers' => array(
                            'timeout' => 2,
                            'Authorization' => 'Bearer ' . $this->ntpmx_api_key
                        ),
                    ));
                    if (is_array($output) && !is_wp_error($output["body"])) {
                        if (isset($output["response"]["code"]) && !empty($output["response"]["code"])) {
                            if (is_numeric($output["response"]["code"])) {
                                $code_status = (int)$output["response"]["code"];
                            }
                        }
                        if ($output["response"]["code"] == 401) {
                            self::ntpmx_set_status($output["response"]["code"], "Error 401 de acceso al servidor de NotiPress");
                        } else if ($output["response"]["code"] !== 200) {
                            self::ntpmx_set_status($output["response"]["code"], "Error " . $output["response"]["code"] . "  cuando intentaba acceder al servidor de NotiPress");
                        }
                        $arr_data = json_decode($output["body"], true);
                        if ($code_status == 200) {
                            if (is_array($arr_data)) {
                                if ($arr_data[0]["hasNewContent"] == true) {
                                    $res = $arr_data[0]["count"];
                                    update_option('ntpmx_new_content', $arr_data[0]["count"]);
                                    $this->count_new_contents = $arr_data[0]["count"];
                                }
                            }
                        }
                        update_option('ntpmx_last_check', self::ntpmx_sys_get_now());;
                    }
                }
                else {
                    $this->count_new_contents = get_option('ntpmx_new_content');
                }
            }
            else{
                $res=$this->count_new_contents;
            }
        }
        return $res;
    }
    private function ntpmx_set_status($code_status,$msg){
        $this->ntpmx_status = $code_status;
        $this->ntpmx_msg = $msg;
    }
    private function ntpmx_standarize($str){
        $str = str_replace(["'"], "\'", htmlspecialchars($str));
        $str = str_replace(["\""], "\"", htmlspecialchars($str));
        return "'" . $str . "'";
    }
    public function ntpmx_show_content_from_database(){
        global $wpdb;$i=0;
        $path_img = $this->ntpmx_public_domain . $this->ntpmx_path_img;
        $str="";
        $table_name = $wpdb->prefix."ntpmx_contents";
        $rows = $wpdb->get_results( "SELECT * FROM " . $table_name . " order by date_published desc limit " . $this->ntpmx_show_rows);
        foreach ($rows as $item){
            $premium=false;
            $i++;
            $query_args = ['page' => 'ntpmx-import', 'remote_id' => $item->remote_id];
            if (isset($item->content_type) && $item->content_type==11) {
                $premium = true;
            }
            if ($item->notipack==1) {
                $sku = strtolower($this->ntpmx_sku);
                if (($premium) && ($sku=='nplan')) {
                    $notipack = "&nbsp;&nbsp;<a class='ntpmx-btn-disabled' target='_blank' href='#')'>Descargar imagen</a>";
                }
                else{
                    $notipack = "&nbsp;&nbsp;<a class='ntpmx-btn' target='_blank' href='" . $this->ntpmx_private_domain . "/photo/download?id=" . esc_attr($item->photo_id) . "&hash=" . esc_attr($item->photo_code) . "&notipack=1'>Descargar imagen</a>";
                }

            }
            else{
                $notipack = "";
            }
            if ($premium) {
                $icon = '&nbsp;<span class="ntpmx-sku-notipro">Contenido premium</span>';
                $status = $item->status == 0 ? '<a class="ntpmx-btn" href="' . add_query_arg($query_args, admin_url('admin.php')) . '">Agregar</a>' . $notipack . ' ' . $icon : '<a class="ntpmx-btn-disabled" href="#">Agregado</a>' . $notipack . ' '  . $icon;
            }
            else{
                $icon="";
                $status = $item->status == 0 ? '<a class="ntpmx-btn" href="' . add_query_arg($query_args, admin_url('admin.php')) . '">Agregar</a>' . $notipack . ' ' . $icon: '<a class="ntpmx-btn-disabled" href="#">Agregado</a>' . $notipack . ' ' . $icon;
            }
            $str .= '<div class="ntpmx-notifications">';

            $str .= '    <div class="ntpmx-container ntpmx-container_content">';
            $str .= '<picture>';
            if (empty($item->thumbnail)) {
                $img_thumbnail = plugins_url('notipress-noticias/img/noimage.jpg');
                $str .= '   <source srcset="' . plugins_url('notipress-noticias/img/noimage.webp') . '" type="image/webp">
                        <source srcset="' . $img_thumbnail . '" type="image/jpeg"> 
                        <img class="img-preview" src="' . $img_thumbnail . '" alt="">
                        ' . self::ntpmx_tag($item->category) . '
                    </picture>';

            }
            else{
                $img_thumbnail = $path_img . $item->thumbnail;
            $str .= '   <source srcset="' . $img_thumbnail . '.webp' . '" type="image/webp">
                        <source srcset="' . $img_thumbnail . '.jpg' . '" type="image/jpeg"> 
                        <img class="img-preview" src="' . $img_thumbnail . '.jpg' . '" alt="">
                        ' . self::ntpmx_tag($item->category) . '
                    </picture>';
            }
            $str .=                 '<div class="ntpmx-notifications_intro">' .
                '<strong>' . wp_specialchars_decode($item->title) . '</strong>
                                    <p>' . wp_specialchars_decode($item->intro) . ' |  ' . self::ntpmx_date_friendly($item->date_published) . ' | Autor: ' . $item->author . '</p>' .
                $status . '
                             </div>                        
                        </div>
                     </div>';
        }
        return $str;
    }
    private function ntpmx_tag($category){
        switch ($category) {
            case "Actualidad":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-present w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Economía":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 5px 3px'><div class='w3-tag w3-round w3-economy w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Internacional":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-international w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Ciencia y tecnología":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-technology w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Negocios":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-npbusiness w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Estilo de vida":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-style w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Movilidad":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-green w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Empresas":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-enterprise w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Política":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-politics w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            case "Tiempo libre":
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-freetime w3-border w3-border-white'>" . $category . "</div></div>";
                break;
            default:
                $type = "<div class='w3-tag w3-round w3-blue' style='padding:3px 3px 3px 3px'><div class='w3-tag w3-round w3-present w3-border w3-border-white'>" . $category . "</div></div>";
                break;
        }
        return $type;
    }
    private function ntpmx_get_date($datetime) {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        return date("{$date_format} {$time_format}", strtotime($datetime));
    }
    public function ntpmx_css_js() {
        wp_register_style('ntpmx_general', plugins_url('notipress-noticias/css/ntpmx-general.css?v24'));
        wp_enqueue_style('ntpmx_general');
    }
    public function ntpmx_get_date_friendly($date){
        return self::ntpmx_date_friendly($date);
    }
    private function ntpmx_date_friendly($date){
        if(empty($date)){
            return "fecha no especificada";
        }
        $periods    = array("segundo", "minuto", "hora", "d&iacute;a", "semana", "mese", "a&ntilde;o", "d&eacute;cada");
        $lengths    = array("60","60","24","7","4.35","12","10");
        //$now = strtotime(date_i18n('Y-m-d H:i:s', current_time('timestamp',false)));
        $now = current_time('timestamp',false);

        if(is_int($date)){
            $unix_date   = $date;
        }else{
            $unix_date   = strtotime($date);
        }
        if(empty($unix_date)) {
            return "fecha incorrecta";
        }
        if($now > $unix_date) {
            $difference  = $now - $unix_date;
            $tense       = "hace ";
        }else{
            $difference  = $unix_date - $now;
            $tense       = "en ";
        }
        for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
            $difference /= $lengths[$j];
        }
        $difference = round($difference);
        if($difference != 1) {
            $periods[$j].= "s";
        }
        return "{$tense} $difference $periods[$j] ";
    }
    public function ntpmx_clean_database(){
        global $wpdb;
        $table_name = $wpdb->prefix."ntpmx_contents";
        $sql = 'delete from ' . $table_name . ' WHERE date_published < DATE_SUB(NOW(),INTERVAL ' . $this->ntpmx_clean_rows_in_days . ' HOUR)';
        $wpdb->query($sql);
    }
    public function ntpmx_get_contents_from_notipress(){
        self::ntpmx_get_contents();
    }
    public function ntpmx_status(){
        return $this->ntpmx_status;
    }
    public function ntpmx_msg(){
        return $this->ntpmx_msg;
    }
    public function ntpmx_get_sku(){
        $res=0;
        $code_status=0;
        if (isset($this->ntpmx_api_key) && !empty($this->ntpmx_api_key)) {
            $url = $this->ntpmx_private_domain . "/apiv3/get-sku?api=" . $this->ntpmx_api_key . "&ntpmx_version=".$this->ntpmx_version;
            $output = wp_remote_get($url, array(
                'headers' => array(
                    'timeout' => 2,
                    'Authorization' => 'Bearer ' . $this->ntpmx_api_key
                ),
            ));
            if (is_array($output) && !is_wp_error($output)) {
                if (isset($output["response"]["code"]) && !empty($output["response"]["code"])) {
                    if (is_numeric($output["response"]["code"])) {
                        $code_status = (int)$output["response"]["code"];
                    }
                }
                if ($output["response"]["code"] == 401) {
                    self::ntpmx_set_status($output["response"]["code"], "Error 401 de acceso al servidor de NotiPress");
                } else if ($output["response"]["code"] !== 200) {
                    self::ntpmx_set_status($output["response"]["code"], "Error " . $output["response"]["code"] . "  cuando intentaba acceder al servidor de NotiPress");
                }
                $arr_data = json_decode($output["body"], true);
                if ($code_status == 200) {
                    if (is_array($arr_data)) {
                        $res = strtolower($arr_data["sku"]);
                        update_option('ntpmx_sku', $res);
                    }
                }
            }
            $res = get_option('ntpmx_sku');
            $this->ntpmx_sku=$res;
        }
        return $res;
    }
    public function ntpmx_get_sku_name(){
        return get_option('ntpmx_sku_name');
    }
    public function ntpmx_get_sku_name_force(){
        $res=0;
        $code_status=0;
        if (isset($this->ntpmx_api_key) && !empty($this->ntpmx_api_key)) {
            $url = $this->ntpmx_private_domain . "/apiv3/get-sku-name?api=" . $this->ntpmx_api_key . "&ntpmx_version=".$this->ntpmx_version;
            $output = wp_remote_get($url, array(
                'headers' => array(
                    'timeout' => 2,
                    'Authorization' => 'Bearer ' . $this->ntpmx_api_key
                ),
            ));
            if (is_array($output) && !is_wp_error($output)) {
                if (isset($output["response"]["code"]) && !empty($output["response"]["code"])) {
                    if (is_numeric($output["response"]["code"])) {
                        $code_status = (int)$output["response"]["code"];
                    }
                }
                if ($output["response"]["code"] == 401) {
                    self::ntpmx_set_status($output["response"]["code"], "Error 401 de acceso al servidor de NotiPress");
                } else if ($output["response"]["code"] !== 200) {
                    self::ntpmx_set_status($output["response"]["code"], "Error " . $output["response"]["code"] . "  cuando intentaba acceder al servidor de NotiPress");
                }
                $arr_data = json_decode($output["body"], true);
                if ($code_status == 200) {
                    if (is_array($arr_data)) {
                        $res = $arr_data["sku_name"];
                        if (!get_option('ntpmx_sku_name')) add_option('ntpmx_sku_name',$res);
                        update_option('ntpmx_sku_name',$res);
                    }
                }
            }
        }
        return $res;
    }
    public function ntpmx_get_customer_id()
    {
        $res = 0;
        $code_status = 0;
        if (isset($this->ntpmx_customer_id) && $this->ntpmx_customer_id !== -1) {
            return $this->ntpmx_customer_id;
        } else {
            if (isset($this->ntpmx_api_key) && !empty($this->ntpmx_api_key)) {
                $url = $this->ntpmx_private_domain . "/apiv3/get-customer-id?api=" . $this->ntpmx_api_key . "&ntpmx_version=" . $this->ntpmx_version;
                $output = wp_remote_get($url, array(
                    'headers' => array(
                        'timeout' => 2,
                        'Authorization' => 'Bearer ' . $this->ntpmx_api_key
                    ),
                ));
                if (is_array($output) && !is_wp_error($output)) {
                    if (isset($output["response"]["code"]) && !empty($output["response"]["code"])) {
                        if (is_numeric($output["response"]["code"])) {
                            $code_status = (int)$output["response"]["code"];
                        }
                    }
                    if ($output["response"]["code"] == 401) {
                        self::ntpmx_set_status($output["response"]["code"], "Error 401 de acceso al servidor de NotiPress");
                    } else if ($output["response"]["code"] !== 200) {
                        self::ntpmx_set_status($output["response"]["code"], "Error " . $output["response"]["code"] . "  cuando intentaba acceder al servidor de NotiPress");
                    }
                    $arr_data = json_decode($output["body"], true);
                    if ($code_status == 200) {
                        if (is_array($arr_data)) {
                            $res = strtolower($arr_data["customer_id"]);
                            update_option('ntpmx_customer_id', $res);
                        }
                    }
                }
                else{
                    print_r($output);
                }
                $this->ntpmx_customer_id = get_option('ntpmx_customer_id');
            }
        }
        return $this->ntpmx_customer_id;
    }
    public function sku(){
        return strtolower($this->ntpmx_sku);
    }
    public function get_message($customer_id):array {
        global $res;
        $res="";
        try {
            if (isset($this->ntpmx_api_key) && !empty($this->ntpmx_api_key)) {
                $url = $this->ntpmx_private_domain . "/apiv3/get-messages?customer_id=" . $customer_id;
                $output = wp_remote_get($url, array(
                    'headers' => array(
                        'timeout' => 2,
                        'Authorization' => 'Bearer ' . $this->ntpmx_api_key
                    ),
                ));
                if (is_array($output) && !is_wp_error($output)) {
                    if (isset($output["response"]["code"]) && !empty($output["response"]["code"])) {
                        if (is_numeric($output["response"]["code"])) {
                            $code_status = (int)$output["response"]["code"];
                        }
                    }
                    if ($output["response"]["code"] == 401) {
                        self::ntpmx_set_status($output["response"]["code"], "Error 401 de acceso al servidor de NotiPress");
                    } else if ($output["response"]["code"] !== 200) {
                        self::ntpmx_set_status($output["response"]["code"], "Error " . $output["response"]["code"] . "  cuando intentaba acceder al servidor de NotiPress");
                    }
                    $arr_data = json_decode($output["body"], true);
                    if ($code_status == 200) {
                        if (is_array($arr_data)) {
                            $res = [
                                'message_id' => $arr_data["message_id"],
                                'intro' => $arr_data["intro"]
                            ];
                        }
                    }
                }
                else {
                    $res = $output;
                }
            } else {
                $res = "Licencia inválida, contacte con soporte@notipress.mx";
            }
        }
        catch (\Exception $eh) {

        }
        finally{
                return $res;
            }
    }
    public function get_consumption($customer_id):array {
        global $res;
        $res=[];
        if (isset($this->ntpmx_api_key) && !empty($this->ntpmx_api_key)) {
            $url = $this->ntpmx_private_domain . "/apiv3/get-consumption?customer_id=" . $customer_id;
            $output = wp_remote_get($url, array(
                'headers' => array(
                    'timeout' => 2,
                    'Authorization' => 'Bearer ' . $this->ntpmx_api_key
                ),
            ));
            if (is_array($output) && !is_wp_error($output)) {
                if (isset($output["response"]["code"]) && !empty($output["response"]["code"])) {
                    if (is_numeric($output["response"]["code"])) {
                        $code_status = (int)$output["response"]["code"];
                    }
                }
                if ($output["response"]["code"] == 401) {
                    self::ntpmx_set_status($output["response"]["code"], "Error 401 de acceso al servidor de NotiPress");
                } else if ($output["response"]["code"] !== 200) {
                    self::ntpmx_set_status($output["response"]["code"], "Error " . $output["response"]["code"] . "  cuando intentaba acceder al servidor de NotiPress");
                }
                $arr_data = json_decode($output["body"], true);
                if ($code_status == 200) {
                    if (is_array($arr_data)) {
                        $res = [
                            'total_contents'=> $arr_data["total_contents"],
                            'total_unlocked'=> $arr_data["total_unlocked"],
                            'total_photos'=>$arr_data["total_photos"],
                            'total_photos_unlocked'=>$arr_data["total_photos_unlocked"],
                        ];
                    }
                    return $res;
                }
            }
            else{
                $res = [
                        $output,
                        'total_contents'=>-1,
                        'total_unlocked'=>-1,
                        'total_photos'=>-1,
                        'total_photos_unlocked'=>-1,
                ];
            }
        }
        else{
            $res = [
                    'msg'=>"Licencia inválida, contacte con soporte@notipress.mx",
                    'total_contents'=>-1,
                    'total_unlocked'=>-1,
                    'total_photos'=>-1,
                    'total_photos_unlocked'=>-1,
            ];
        }
        return $res;
    }
    public function get_api():string {
        return $this->ntpmx_api_key;
    }

}