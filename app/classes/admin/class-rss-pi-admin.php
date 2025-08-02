<?php

/**
 * The class that handles the admin screen
 *
 * @author mobilova UG (haftungsbeschränkt) <rsspostimporter@feedsapi.com>
 */
class rssPIAdmin {

    /**
     * Whether the API key is valid
     *
     * @var bool
     */
    public bool $is_key_valid;

    /**
     * The options
     *
     * @var array
     */
    public array $options;

    /**
     * Aprompt for invalid/absent API keys
     * @var string
     */
    public string $key_prompt;

    /**
     * Logging instance
     * @var rssPILog
     */
    public rssPILog $log;

    /**
     * Form processor instance
     * @var rssPIAdminProcessor
     */
    public rssPIAdminProcessor $processor;

    /**
     * OPML handler instance
     * @var Rss_pi_opml|null
     */
    public ?Rss_pi_opml $opml = null;

    /**
     *  Start
     *
     * @global object $rss_post_importer
     */
    public function __construct() {

        $this->load_options();

        add_action('init', [$this, 'init_properties']);

        // initialise logging
        $this->log = new rssPILog();
        $this->log->init();

        // load the form processor
        $this->processor = new rssPIAdminProcessor();
    }

    public function init_properties() {
        $this->key_prompt = __('%1$sYou need a <a href="%2$s" target="_blank">Full Text RSS Key</a> to activate this section, please <a href="%2$s" target="_blank">get one and try it free</a> for the next 14 days to see how it goes.', 'rss-post-importer');
    }

    private function load_options(): void {
        global $rss_post_importer;

        // add options
        $this->options = $rss_post_importer->options;

        // check for valid key when we don't have it cached
        // actually this populates the settings with our defaults on the first plugin activation
        if ( !isset($this->options['settings']['is_key_valid']) ) {
            // check if key is valid
            $this->is_key_valid = $rss_post_importer->is_valid_key($this->options['settings']['feeds_api_key']);
            $this->options['settings']['is_key_valid'] = $this->is_key_valid;
            // if the key is not fine
            if (!empty($this->options['settings']['feeds_api_key']) && !$this->is_key_valid) {
                // unset from settings
                unset($this->options['settings']['feeds_api_key']);
            }
            // update options
            $new_options = array(
                'feeds' => $this->options['feeds'],
                'settings' => $this->options['settings'],
                'latest_import' => $this->options['latest_import'] ?? null,
                'imports' => $this->options['imports'] ?? null,
                'upgraded' => $this->options['upgraded'] ?? null
            );
            // update in db
            update_option('rss_pi_feeds', $new_options);
        } else {
            $this->is_key_valid = $this->options['settings']['is_key_valid'];
        }
    }

    /**
     * Initialise and hook all actions
     */
    public function init(): void {

        // add to admin menu
        add_action('admin_menu', [$this, 'admin_menu']);

        // process and save options prior to screen ui display
        add_action('load-settings_page_rss_pi', [$this, 'save_options']);

        // load scripts and styles we need
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        // manage meta data on post deletion and restoring
        add_action('wp_trash_post', [$this, 'delete_post']); // trashing a post
//		add_action('before_delete_post', [$this, 'delete_post']); // deleting a post permanently
        add_action('untrash_post', [$this, 'restore_post']); // restoring a post from trash

        // the ajax for adding new feeds (table rows)
        add_action('wp_ajax_rss_pi_add_row', [$this, 'add_row']);

        // the ajax for editing a feed row
        add_action('wp_ajax_rss_pi_edit_row', [$this, 'edit_row']);

        // the ajax for stats chart
        add_action('wp_ajax_rss_pi_stats', [$this, 'ajax_stats']);

        // the ajax for importing feeds via admin
        add_action('wp_ajax_rss_pi_import', [$this, 'ajax_import']);

        // disable the feed author dropdown for invalid/absent API keys
        add_filter('wp_dropdown_users', [$this, 'disable_user_dropdown']);

        // Add 10 minutes in frequency.
        add_filter('cron_schedules', [$this, 'rss_pi_cron_add']);

        add_filter('cron_schedules', [$this, 'rss_pi_cron_add_custom']);

        // trigger on Export
        if ( isset($_POST['export_opml']) ) {
            $this->opml = new Rss_pi_opml();
            $this->opml->export();
        }

    }

    /**
     * Add to admin menu
     */
    public function admin_menu(): void {
        add_options_page('Rss Post Importer', 'Rss Post Importer', 'manage_options','rss_pi', [$this, 'screen']);
    }

    /**
     * Enqueue our admin css and js
     *
     * @param string $hook The current screens hook
     * @return void
     */
    public function enqueue(string $hook): void {

        // don't load if it isn't our screen
        if ($hook != 'settings_page_rss_pi') {
            return;
        }

        // register scripts & styles
        wp_enqueue_style('rss-pi', RSS_PI_URL . 'app/assets/css/style.css', [], RSS_PI_VERSION);

        wp_enqueue_style('rss-pi-jquery-ui-css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.21/themes/redmond/jquery-ui.css', [], RSS_PI_VERSION);

        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-progressbar');

        wp_enqueue_script('modernizr', RSS_PI_URL . 'app/assets/js/modernizr.custom.32882.js', [], RSS_PI_VERSION, true);
        wp_enqueue_script('phpjs-uniqid', RSS_PI_URL . 'app/assets/js/uniqid.js', [], RSS_PI_VERSION, true);
        wp_enqueue_script('rss-pi', RSS_PI_URL . 'app/assets/js/main.js', ['jquery'], RSS_PI_VERSION, true);

        // localise ajaxuel for use
        $localise_args = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'pluginurl' => RSS_PI_URL,
            'l18n' => [
                'unsaved' => __( 'You have unsaved changes on this page. Do you want to leave this page and discard your changes or stay on this page?', 'rss-post-importer' )
            ]
        ];
        wp_localize_script('rss-pi', 'rss_pi', $localise_args);
    }

    // add post URL to rss_pi_deleted_posts when trashing
    public function delete_post(int $post_id): void {
        $rss_pi_deleted_posts = get_option( 'rss_pi_deleted_posts', [] );
        $source_md5 = get_post_meta($post_id, 'rss_pi_source_md5', true);
        if ( $source_md5 && ! in_array( $source_md5, $rss_pi_deleted_posts ) ) {
            // add this source URL hash to the "deleted" metadata
            $rss_pi_deleted_posts[] = $source_md5;
            update_option('rss_pi_deleted_posts', $rss_pi_deleted_posts);
        }
    }

    // remove post URL from rss_pi_deleted_posts when restoring from trash
    public function restore_post(int $post_id): void {
        $rss_pi_deleted_posts = get_option( 'rss_pi_deleted_posts', [] );
        $source_md5 = get_post_meta($post_id, 'rss_pi_source_md5', true);
        if ( $source_md5 && in_array( $source_md5, $rss_pi_deleted_posts ) ) {
            // remove this source URL hash from the "deleted" metadata
            $rss_pi_deleted_posts = array_diff( $rss_pi_deleted_posts, [ $source_md5 ] );
            update_option('rss_pi_deleted_posts', $rss_pi_deleted_posts);
        }
    }

    public function rss_pi_cron_add(array $schedules): array {

        $schedules['minutes_10'] = [
            'interval' => 600,
            'display' => '10 minutes'
        ];

        return $schedules;
    }

    // this will fetch custom frequency
    public function rss_pi_cron_add_custom(array $schedules): array {

        // min in sec
        $rss_min = 60;
        $custom_cron_options = get_option('rss_custom_cron_frequency', []);
        if(! empty($custom_cron_options)) {
            $rss_custom_cron    = @unserialize($custom_cron_options);
            if (is_array($rss_custom_cron) && isset($rss_custom_cron['frequency'], $rss_custom_cron['time'])) {
                $rss_frequency_cron = $rss_custom_cron['frequency'];
                $rss_frequency_time = $rss_custom_cron['time'];
                // Adding custom cron
                $schedules[$rss_frequency_cron] = [
                    'interval' => $rss_min * $rss_frequency_time,
                    'display' => $rss_frequency_time . ' minutes'
                ];
            }
        }

        return $schedules;
    }

    /**
     * save any options submitted before the screen/ui get displayed
     */
    public function save_options(): void {

        // load the form processor
        $this->processor->process();

        if ( $this->is_key_valid ) {
            // purge "deleted posts" cache when requested
            $this->processor->purge_deleted_posts_cache();
        }
    }

    /**
     * Display the screen/ui
     */
    public function screen(): void {

        // it'll process any submitted form data
        // reload the options just in case
        $this->load_options();

        // display a success message
        if ( isset($_GET['deleted_cache_purged']) || isset($_GET['settings-updated']) || isset($_GET['invalid_api_key']) || (isset($_GET['import']) && @$_GET['settings-updated']) ) {
?>
        <div id="message" class="updated">
<?php
            if( isset($_GET['deleted_cache_purged']) && $_GET['deleted_cache_purged'] == 'true' ) {
?>
            <p><strong><?php _e('Cache for Deleted posts was purged.') ?></strong></p>
<?php
            }
            if( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) {
?>
            <p><strong><?php _e('Settings saved.') ?></strong></p>
<?php
            }
?>
        </div>
<?php
            // import feeds via AJAX but only when Save is done
            if( isset($_GET['import']) && isset($_GET['settings-updated']) && $_GET['settings-updated'] ) {
?>
<script type="text/javascript">
// determine the feed ids within wordpress
<?php
    $feed_ids = [];
    if (is_array($this->options['feeds']) ) {
        foreach ($this->options['feeds'] as $f) {
            $feed_ids[] = $f['id'];
        }
    }
?>
// and set them as a global JS variable
    if ( typeof feeds !== 'undefined' ) {
        feeds.set(<?php echo json_encode($feed_ids); ?>);
    }  else {
        var feeds = <?php echo json_encode($feed_ids); ?>;
    }
    console.log('what is this');
</script>
<?php
            }
        }

        // display an error message
        if( isset($_GET['message']) && $_GET['message'] > 1 ) {
?>
        <div id="message" class="error">
<?php
            switch ( $_GET['message'] ) {
                case 2:
                {
?>
            <p><strong><?php _e('Invalid API key!', 'rss_api'); ?></strong></p>
<?php
                }
            }
?>
        </div>
<?php
        }

        global $rss_post_importer;

        // include the template for the ui
        include( RSS_PI_PATH . 'app/templates/admin-ui.php');
    }

    /**
     * Display errors
     *
     * @param string $error The error message
     * @param bool $inline Whether the error is inline or shown like regular wp errors
     */
    public function key_error(string $error, bool $inline = false): void {

        $class = ($inline) ? 'rss-pi-error' : 'error';

        echo '<div class="' . $class . '"><p>' . $error . '</p></div>';
    }

    /**
     * Add a new row for a new feed
     */
    public function add_row(): void {
        if (! isset($_POST['feed_id'])) {
            die();
        }

        $ajax_add = true;
        $ajax_feed_id = $_POST['feed_id'];
        include( RSS_PI_PATH . 'app/templates/feed-table-row.php');
        die();
    }

    public function edit_row(): void {
        if (! isset($_POST['feed_id'])) {
            die();
        }

        foreach ($this->options['feeds'] as $f) {
            if ($f['id'] == $_POST['feed_id']) {
                $ajax_edit = true;
                include( RSS_PI_PATH . 'app/templates/feed-table-row.php');
                die();
            }
        }
    }

    /**
     * Generate stats data and return
     */
    public function ajax_stats(): void {
        include( RSS_PI_PATH . 'app/templates/stats.php');
        die();
    }

    /**
     * Import any feeds
     */
    public function ajax_import(): void {
        global $rss_post_importer;

        $this->load_options();

        // if there's nothing for processing or invalid data, bail
        if ( ! isset($_POST['feed']) ) {
            wp_send_json_error(['message'=>'no feed provided']);
        }

        $_found = false;
        foreach ( $this->options['feeds'] as $id => $f ) {
            if ( $f['id'] == $_POST['feed'] ) {
                $_found = $id;
                break;
            }
        }
        if ( $_found === false ) {
            wp_send_json_error(['message'=>'wrong feed id provided']);
        }

        // TODO: make this better
        if ( $_found == 0 ) {
            // check for valid key only for the first feed
            $this->is_key_valid = $rss_post_importer->is_valid_key($this->options['settings']['feeds_api_key']);
            $this->options['settings']['is_key_valid'] = $this->is_key_valid;
            // if the key is not fine
            if (!empty($this->options['settings']['feeds_api_key']) && !$this->is_key_valid) {
                // unset from settings
                unset($this->options['settings']['feeds_api_key']);
            }
            // update options
            $new_options = [
                'feeds' => $this->options['feeds'],
                'settings' => $this->options['settings'],
                'latest_import' => $this->options['latest_import'] ?? null,
                'imports' => $this->options['imports'] ?? null,
                'upgraded' => $this->options['upgraded'] ?? null
            ];
            // update in db
            update_option('rss_pi_feeds', $new_options);
        }

        $post_count = 0;

        $f = $this->options['feeds'][$_found];

        $engine = new rssPIEngine();

        // filter cache lifetime
        add_filter('wp_feed_cache_transient_lifetime', [$engine, 'frequency']);

        // prepare, import feed and count imported posts
        $items = $engine->do_import($f);
        if ( $items ) {
            $post_count += count($items);
        }

        remove_filter('wp_feed_cache_transient_lifetime', [$engine, 'frequency']);

        if ( $items === false ) {
            // there were an wp_error doing fetch_feed
            wp_send_json_error(['url'=>$f['url']]);
        }

        // reformulate import count
        $imports = intval($this->options['imports'] ?? 0) + $post_count;

        // update options
        $new_options = [
            'feeds' => $this->options['feeds'],
            'settings' => $this->options['settings'],
            'latest_import' => date("Y-m-d H:i:s"),
            'imports' => $imports,
            'upgraded' => $this->options['upgraded'] ?? null
        ];
        // update in db
        update_option('rss_pi_feeds', $new_options);

        global $rss_post_importer;
        // reload options
        $rss_post_importer->load_options();

        // log this
        $this->log->log($post_count);
//        rssPILog::log($post_count);

        wp_send_json_success(['count'=>$post_count, 'url'=>$f['url']]);

    }

    /**
     * Disable the user dropdwon for each feed
     *
     * @param string $output The html of the select dropdown
     * @return string
     */
    public function disable_user_dropdown(string $output): string {

        // if we have a valid key we don't need to disable anything
        if ($this->is_key_valid) {
            return $output;
        }

        // check if this is the feed dropdown (and not any other)
        preg_match('/rss-pi-specific-feed-author/i', $output, $matched);

        // this is not our dropdown, no need to disable
        if (empty($matched)) {
            return $output;
        }

        // otherwise just disable the dropdown
        return str_replace('<select ', '<select disabled="disabled" ', $output);
    }

    /**
     * Walker class function for category multiple checkbox
     */
    public function wp_category_checklist_rss_pi(
        int $post_id = 0,
        int $descendants_and_self = 0,
        array|false $selected_cats = false,
        array|false $popular_cats = false,
        ?Walker $walker = null,
        bool $checked_ontop = true
    ): string {

        $cat = "";
        if (empty($walker) || !is_a($walker, 'Walker'))
            $walker = new Walker_Category_Checklist;
        $descendants_and_self = (int) $descendants_and_self;
        $args = [];
        if (is_array($selected_cats))
            $args['selected_cats'] = $selected_cats;
        elseif ($post_id)
            $args['selected_cats'] = wp_get_post_categories($post_id);
        else
            $args['selected_cats'] = [];

        if ($descendants_and_self) {
            $categories = get_categories("child_of=$descendants_and_self&hierarchical=0&hide_empty=0");
            $self = get_category($descendants_and_self);
            array_unshift($categories, $self);
        } else {
            $categories = get_categories('get=all');
        }
        if ($checked_ontop) {
            // Post process $categories rather than adding an exclude to the get_terms() query to keep the query the same across all posts (for any query cache)
            $checked_categories = [];
            $keys = array_keys($categories);
            foreach ($keys as $k) {
                if (in_array($categories[$k]->term_id, $args['selected_cats'])) {
                    $checked_categories[] = $categories[$k];
                    unset($categories[$k]);
                }
            }
            // Put checked cats on top
            $cat .= call_user_func_array([$walker, 'walk'], [
                $checked_categories,
                0,
                $args
            ]);
        }
        // Then the rest of them
        $cat .= call_user_func_array([$walker, 'walk'], [
            $categories,
            0,
            $args
        ]);
        return $cat;
    }

    public function rss_pi_tags_dropdown(string $fid, array $seleced_tags): void {

        if ($tags = get_tags(['orderby' => 'name', 'hide_empty' => false])) {

            echo '<select name="' . $fid . '-tags_id[]" id="tag" class="postform">';

            foreach ($tags as $tag) {
                $strsel = "";
                if (!empty($seleced_tags)) {

                    if ($seleced_tags[0] == $tag->term_id) {
                        $strsel = "selected='selected'";
                    }
                }
                echo '<option value="' . $tag->term_id . '" ' . $strsel . '>' . $tag->name . '</option>';
            }
            echo '</select> ';
        }
    }

    public function rss_pi_tags_checkboxes(string $fid, array $seleced_tags): void {

        $tags = get_tags(['hide_empty' => false]);
        if ($tags) {
            $checkboxes = "<ul>";

            foreach ($tags as $tag) :
                $strsel = "";
                if (in_array($tag->term_id, $seleced_tags))
                    $strsel = "checked='checked'";

                $checkboxes .=
                        '<li><label for="tag-' . $tag->term_id . '">
                                <input type="checkbox" name="' . $fid . '-tags_id[]" value="' . $tag->term_id . '" id="tag-' . $tag->term_id . '" ' . $strsel . ' />' . $tag->name . '
                            </label></li>';
            endforeach;
            $checkboxes .= "</ul>";
            print $checkboxes;
        }
    }

}
