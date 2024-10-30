<?php

namespace Catenis\WP;

use stdClass;
use Exception;
use Catenis\WP\Catenis\ApiClient as CatenisApiClient;
use Catenis\WP\Notification\NotificationCtrl;
use Catenis\WP\Notification\CommPipe;
use Catenis\WP\Notification\CommCommand;

class ApiClient
{
    private static $initNtfyProcWaitRespTimeout = 1;    // 1 sec. (in seconds)
    private static $pollServerTimeout = 30;    // 30 sec. (in seconds)
    private static $minCompressThreshold = 32;   // Minimum compression threshold in bytes

    private $pluginPath;
    private $pluginBaseName;

    private static function trimArray(&$arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $idx => $value) {
                if (is_array($value)) {
                    self::trimArray($value);

                    if (!count($value)) {
                        unset($arr[$idx]);
                    }
                } else {
                    $arr[$idx] = trim($value);

                    if ($arr[$idx] == '') {
                        unset($arr[$idx]);
                    }
                }
            }

            if (!count($arr)) {
                $arr = null;
            }
        }
    }

    private static function getCtnClientData($postID)
    {
        if (empty($postID) || empty($postMetadata = get_post_meta($postID, '_ctn_api_client', true))) {
            return false;
        }

        // Prepare to instantiate Catenis API client
        $globalCtnClientCredentials = get_option('ctn_client_credentials');
        $ctnClientData = new stdClass();
        $ctnClientData->ctnClientCredentials = new stdClass();
        $ctnClientData->ctnClientCredentials->deviceId = !empty($postMetadata['ctn_device_id'])
            ? $postMetadata['ctn_device_id'] : (!empty($globalCtnClientCredentials['ctn_device_id'])
            ? $globalCtnClientCredentials['ctn_device_id'] : null);
        $ctnClientData->ctnClientCredentials->apiAccessSecret = !empty($postMetadata['ctn_api_access_secret'])
            ? $postMetadata['ctn_api_access_secret'] : (!empty($globalCtnClientCredentials['ctn_api_access_secret'])
            ? $globalCtnClientCredentials['ctn_api_access_secret'] : null);

        $globalCtnClientOptions = get_option('ctn_client_options');
        $options = [];

        if (!empty($postMetadata['ctn_host'])) {
            $options['host'] = $postMetadata['ctn_host'];
        } elseif (!empty($globalCtnClientOptions['ctn_host'])) {
            $options['host'] = $globalCtnClientOptions['ctn_host'];
        }

        if (!empty($postMetadata['ctn_environment'])) {
            $options['environment'] = $postMetadata['ctn_environment'];
        } elseif (!empty($globalCtnClientOptions['ctn_environment'])) {
            $options['environment'] = $globalCtnClientOptions['ctn_environment'];
        }

        if (!empty($postMetadata['ctn_secure'])) {
            $options['secure'] = $postMetadata['ctn_secure'] === 'on';
        } elseif (!empty($globalCtnClientOptions['ctn_secure'])) {
            $options['secure'] = $globalCtnClientOptions['ctn_secure'] === 'on';
        }

        if (!empty($postMetadata['ctn_compress_threshold']) && ($val = (int)$postMetadata['ctn_compress_threshold'])
                >= self::$minCompressThreshold) {
            $options['compressThreshold'] = $val;
        }

        $ctnClientData->ctnClientOptions = !empty($options) ? $options : null;

        return $ctnClientData;
    }

    private static function deletePluginData()
    {
        // Delete all plugin's meta options
        delete_post_meta_by_key('_ctn_api_client');

        // Remove plugin's options
        unregister_setting('ctn_api_client_opts', 'ctn_client_credentials');
        unregister_setting('ctn_api_client_opts', 'ctn_client_options');

        delete_option('ctn_client_credentials');
        delete_option('ctn_client_options');
    }

    public static function sanitizeOptions($opts)
    {
        self::trimArray($opts);

        return $opts;
    }

    public static function uninstall()
    {
        global $wpdb;

        if (is_multisite()) {
            // get ids of all sites
            $blogIds = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");

            foreach ($blogIds as $blogId) {
                switch_to_blog($blogId);

                // Delete plugin's data for this specific site
                self::deletePluginData();

                restore_current_blog();
            }
        } else {
            self::deletePluginData();
        }
    }

    public function __construct($pluginPath)
    {
        $this->pluginPath = $pluginPath;
        $this->pluginBaseName = plugin_basename($pluginPath);

        register_activation_hook($pluginPath, [$this, 'activate']);
        register_uninstall_hook($pluginPath, ['\Catenis\WP\ApiClient', 'uninstall']);

        // Wire up action handlers
        add_action('admin_init', [$this, 'adminInitHandler']);
        add_action('admin_menu', [$this, 'adminMenuHandler']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScriptsHandler']);
        add_action("in_plugin_update_message-$this->pluginBaseName", [$this, 'showUpgradeNotice'], 10, 2);

        // Setup AJAX methods
        add_action('wp_ajax_ctn_call_api_method', [$this, 'callApiMethod']);
        add_action('wp_ajax_ctn_open_notify_channel', [$this, 'openNotifyChannel']);
        add_action('wp_ajax_ctn_close_notify_channel', [$this, 'closeNotifyChannel']);
        add_action('wp_ajax_ctn_poll_server', [$this, 'processServerPolling']);
        // Note: the following is required so non-logged-in users can execute the Ajax call
        add_action('wp_ajax_nopriv_ctn_call_api_method', [$this, 'callApiMethod']);
        add_action('wp_ajax_nopriv_ctn_open_notify_channel', [$this, 'openNotifyChannel']);
        add_action('wp_ajax_nopriv_ctn_close_notify_channel', [$this, 'closeNotifyChannel']);
        add_action('wp_ajax_nopriv_ctn_poll_server', [$this, 'processServerPolling']);
    }

    public function activate()
    {
        // Make sure that directory used to hold fifos for communication with
        //  notification process exists
        if (!file_exists(__DIR__ . '/../io')) {
            mkdir(__DIR__ . '/../io', 0700);
        }
    }

    public function showUpgradeNotice($plugin_data, $response)
    {
        if ($plugin_data['update'] && isset($plugin_data['upgrade_notice'])) {
            echo '<br><b>' . strip_tags($plugin_data['upgrade_notice']) . '</b>';
        }
    }

    public function enqueueScriptsHandler()
    {
        global $post;

        if ($post->post_type === 'page') {
            // Make sure that plugin's JavaScript files are only added to pages that request it
            $postMetadata = get_post_meta($post->ID, '_ctn_api_client', true);

            if (!empty($postMetadata['ctn_load_client']) && $postMetadata['ctn_load_client'] === 'on') {
                // Register JavaScript modules it depends on
                wp_register_script(
                    'heir',
                    plugins_url('/js/lib/heir.js', $this->pluginPath),
                    [],
                    '2.0.0'
                );
                wp_register_script(
                    'event_emitter',
                    plugins_url('/js/lib/EventEmitter.min.js', $this->pluginPath),
                    [],
                    '4.2.11'
                );
                wp_register_script(
                    'setImmediate',
                    plugins_url('/js/lib/setImmediate.min.js', $this->pluginPath),
                    [],
                    '1.0.5'
                );

                wp_enqueue_script(
                    'ctn_api_proxy',
                    plugins_url('/js/CatenisApiProxy.js', $this->pluginPath),
                    ['jquery', 'heir', 'event_emitter', 'setImmediate']
                );

                try {
                    $clientUID = random_int(1, 9999999999);
                } catch (Exception $ex) {
                    $clientUID = rand(1, 9999999999);
                }

                wp_localize_script(
                    'ctn_api_proxy',
                    'ctn_api_proxy_obj',
                    [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce(__FILE__),
                        'post_id' => $post->ID,
                        'client_uid' => $clientUID
                    ]
                );
            }
        }
    }

    public function adminInitHandler()
    {
        // Set up Catenis API client options
        register_setting(
            'ctn_api_client_opts',
            'ctn_client_credentials',
            ['sanitize_callback' => ['\Catenis\WP\ApiClient', 'sanitizeOptions']]
        );
        register_setting(
            'ctn_api_client_opts',
            'ctn_client_options',
            ['sanitize_callback' => ['\Catenis\WP\ApiClient', 'sanitizeOptions']]
        );

        add_settings_section(
            'ctn_client_credentials',
            'Client Credentials',
            [$this, 'displayClientCredentialsSectionInfo'],
            'ctn_api_client_opts'
        );
        add_settings_section(
            'ctn_client_options',
            'Client Options',
            [$this, 'displayClientOptionsSectionInfo'],
            'ctn_api_client_opts'
        );

        // Client credentials fields
        add_settings_field(
            'ctn_device_id',
            'Device ID',
            [$this, 'displayDeviceIdFieldContents'],
            'ctn_api_client_opts',
            'ctn_client_credentials',
            ['label_for' => 'ctn_device_id']
        );
        add_settings_field(
            'ctn_api_access_secret',
            'API Access Secret',
            [$this, 'displayApiAccessSecretFieldContents'],
            'ctn_api_client_opts',
            'ctn_client_credentials',
            ['label_for' => 'ctn_api_access_secret']
        );

        // Client options fields
        add_settings_field(
            'ctn_host',
            'Host',
            [$this, 'displayHostFieldContents'],
            'ctn_api_client_opts',
            'ctn_client_options',
            ['label_for' => 'ctn_host']
        );
        add_settings_field(
            'ctn_environment',
            'Environment',
            [$this, 'displayEnvironmentFieldContents'],
            'ctn_api_client_opts',
            'ctn_client_options',
            ['label_for' => 'ctn_environment']
        );
        add_settings_field(
            'ctn_secure',
            'Secure Connection',
            [$this, 'displaySecureConnectionFieldContents'],
            'ctn_api_client_opts',
            'ctn_client_options',
            ['label_for' => 'ctn_secure']
        );
        add_settings_field(
            'ctn_compress_threshold',
            'Compression Threshold',
            [$this, 'displayCompressThresholdFieldContents'],
            'ctn_api_client_opts',
            'ctn_client_options',
            ['label_for' => 'ctn_compress_threshold']
        );

        // Add Catenis API client config panel to pages (post type = 'page')
        add_meta_box(
            'ctn_api_client_meta',
            'Catenis API Client',
            [$this, 'displayPostMetadataFormContents'],
            'page',
            'normal',
            'high'
        );

        // Wire up action handler to save post metadata
        add_action('save_post', [$this, 'savePostMetadata']);
    }

    public function adminMenuHandler()
    {
        add_options_page(
            'Catenis API Client',
            'Catenis API Client',
            'manage_options',
            'ctn_api_client_opts',
            [$this, 'displayOptionsPage']
        );
    }

    public function displayOptionsPage()
    {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php
        // output security fields for the registered setting "ctn_api_client_opts"
        settings_fields('ctn_api_client_opts');
        // output setting sections and their fields
        do_settings_sections('ctn_api_client_opts');
        // output save settings button
        submit_button('Save Settings');
        ?>
    </form>
</div>
        <?php
    }

    public function displayClientCredentialsSectionInfo()
    {
        echo '<p>Enter credentials of Catenis device to use with the Catenis API client</p>';
    }

    public function displayClientOptionsSectionInfo()
    {
        echo '<p>Enter the options for instantiating the Catenis API client<br><span style="color:gray">(leave blank'
            . ' for default settings)</span></p>';
    }

    public function displayDeviceIdFieldContents($args)
    {
        $ctnClientCredentials = get_option('ctn_client_credentials');
        ?>
<input type="text" id="<?php echo $args['label_for'] ?>" name="ctn_client_credentials[<?php echo $args['label_for'] ?>]"
    class="regular-text" maxlength="20" autocomplete="off" <?php echo !empty($ctnClientCredentials[$args['label_for']])
        ? 'value="' . esc_attr($ctnClientCredentials[$args['label_for']]) . '"' : '' ?>>
        <?php
    }

    public function displayApiAccessSecretFieldContents($args)
    {
        $ctnClientCredentials = get_option('ctn_client_credentials');
        ?>
<input type="password" id="<?php echo $args['label_for'] ?>" name="ctn_client_credentials[<?php echo $args['label_for'] ?>]"
    class="regular-text" maxlength="128" autocomplete="off" <?php echo !empty($ctnClientCredentials[$args['label_for']])
        ? 'value="' . esc_attr($ctnClientCredentials[$args['label_for']]) . '"' : '' ?>>
        <?php
    }

    public function displayHostFieldContents($args)
    {
        $ctnClientOptions = get_option('ctn_client_options');
        ?>
<input type="text" id="<?php echo $args['label_for'] ?>" name="ctn_client_options[<?php echo $args['label_for'] ?>]"
    class="regular-text" maxlength="80" autocomplete="off" <?php echo !empty($ctnClientOptions[$args['label_for']])
        ? 'value="' . esc_attr($ctnClientOptions[$args['label_for']]) . '"' : '' ?>>
        <?php
    }

    public function displayEnvironmentFieldContents($args)
    {
        $ctnClientOptions = get_option('ctn_client_options');
        ?>
<select id="<?php echo $args['label_for'] ?>" name="ctn_client_options[<?php echo $args['label_for'] ?>]">
    <option value=""></option>
    <option value="prod" <?php echo !empty($ctnClientOptions[$args['label_for']])
        ? selected($ctnClientOptions[$args['label_for']], 'prod', false) : ''; ?>>Production</option>
    <option value="sandbox" <?php echo !empty($ctnClientOptions[$args['label_for']])
        ? selected($ctnClientOptions[$args['label_for']], 'sandbox', false) : ''; ?>>Sandbox</option>
</select>
        <?php
    }

    public function displaySecureConnectionFieldContents($args)
    {
        $ctnClientOptions = get_option('ctn_client_options');
        ?>
<select id="<?php echo $args['label_for'] ?>" name="ctn_client_options[<?php echo $args['label_for'] ?>]">
    <option value=""></option>
    <option value="on" <?php echo !empty($ctnClientOptions[$args['label_for']])
        ? selected($ctnClientOptions[$args['label_for']], 'on', false) : ''; ?>>On</option>
    <option value="off" <?php echo !empty($ctnClientOptions[$args['label_for']])
        ? selected($ctnClientOptions[$args['label_for']], 'off', false) : ''; ?>>Off</option>
</select>
        <?php
    }

    public function displayCompressThresholdFieldContents($args)
    {
        $ctnClientOptions = get_option('ctn_client_options');
        ?>
<input type="number" id="<?php echo $args['label_for'] ?>" name="ctn_client_options[<?php echo $args['label_for'] ?>]"
    class="regular-text" min="<?php echo self::$minCompressThreshold ?>" step="32" <?php echo
    !empty($ctnClientOptions[$args['label_for']]) ? 'value="' . esc_attr($ctnClientOptions[$args['label_for']]) . '"'
    : '' ?>>
        <?php
    }

    public function displayPostMetadataFormContents()
    {
        global $post;

        $postMetadata = get_post_meta($post->ID, '_ctn_api_client', true);

        // Include post metadata form contents
        include_once __DIR__ . '/../inc/PostMetadataFormContents.php';

        // Create a custom nonce for submit verification later
        echo '<input type="hidden" name="catenis_api_client_nonce" value="' . wp_create_nonce(__FILE__) . '" />';
    }

    public function savePostMetadata($post_id)
    {
        // Make sure data came from our post metadata form
        if (! isset($_POST['catenis_api_client_nonce'])
                || !wp_verify_nonce($_POST['catenis_api_client_nonce'], __FILE__)) {
            return $post_id;
        }
        
        // Make sure that user has required permission
        if ($_POST['post_type'] === 'page') {
            if (!current_user_can('edit_page', $post_id)) {
                return $post_id;
            }
        } elseif (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        // Save post metadata
        $currPostMetadata = get_post_meta($post_id, '_ctn_api_client', true);
        $newPostMetadata = $_POST['_ctn_api_client'];
        self::trimArray($newPostMetadata);

        if ($currPostMetadata) {
            if (is_null($newPostMetadata)) {
                delete_post_meta($post_id, '_ctn_api_client');
            } else {
                update_post_meta($post_id, '_ctn_api_client', $newPostMetadata);
            }
        } elseif (!is_null($newPostMetadata)) {
            add_post_meta($post_id, '_ctn_api_client', $newPostMetadata, true);
        }

        return $post_id;
    }

    public function callApiMethod()
    {
        check_ajax_referer(__FILE__);

        $postID = $_POST['post_id'];
        $ctnClientData = self::getCtnClientData($postID);

        if (!$ctnClientData) {
            wp_send_json_error('Invalid or undefined post ID', 500);
        }

        try {
            // Instantiate Catenis API client
            $ctnApiClient = new CatenisApiClient(
                $ctnClientData->ctnClientCredentials->deviceId,
                $ctnClientData->ctnClientCredentials->apiAccessSecret,
                $ctnClientData->ctnClientOptions
            );
        } catch (Exception $ex) {
            wp_send_json_error('Error instantiating Catenis API client: ' . $ex->getMessage(), 500);
            return;
        }

        try {
            // Get passed in parameter
            $methodName = $_POST['method_name'];
            $methodParams = json_decode(stripslashes($_POST['method_params']), true);

            // Call Catenis API method
            $result = call_user_func_array([$ctnApiClient, $methodName], $methodParams);

            wp_send_json_success($result);
        } catch (Exception $ex) {
            wp_send_json_error($ex->getMessage(), 500);
        }
    }

    public function openNotifyChannel()
    {
        check_ajax_referer(__FILE__);

        $clientUID = $_POST['client_uid'];

        if (!$clientUID) {
            wp_send_json_error('Missing client UID', 500);
        }

        try {
            $commPipe = new CommPipe(
                $clientUID,
                true,
                CommPipe::SEND_COMM_MODE | CommPipe::SEND_COMM_CTRL_MODE | CommPipe::RECEIVE_COMM_CTRL_MODE,
                true
            );
        } catch (Exception $ex) {
            wp_send_json_error('Error opening communication pipe: ' . $ex->getMessage(), 500);
        }

        $commCommand = new CommCommand($commPipe);

        if (!$commPipe->werePipesAlreadyCreated()) {
            // Run (child) process to handle Catenis notifications
            NotificationCtrl::execProcess($clientUID);

            // Send initialization data (so child process can instantiate Catenis API client)
            $postID = $_POST['post_id'];
            $ctnClientData = self::getCtnClientData($postID);

            if (!$ctnClientData) {
                $commPipe->delete();
                wp_send_json_error('Invalid or undefined post ID', 500);
            }

            try {
                $commCommand->sendInitCommand($ctnClientData);
            } catch (Exception $ex) {
                $commPipe->delete();
                wp_send_json_error('Error sending init command: ' . $ex->getMessage(), 500);
            }

            // Wait for response
            $errorMsg = '';

            try {
                // Check for received control data
                if ($commCommand->receiveControl(self::$initNtfyProcWaitRespTimeout)) {
                    $command = $commCommand->getNextControlCommand();

                    if (($commandType = CommCommand::commandType($command)) !== CommCommand::INIT_RESPONSE_CMD) {
                        $errorMsg = 'Unexpected response from notification process: ' . $commandType;
                    } elseif (!$command->data->success) {
                        $errorMsg = $command->data->error;
                    }
                } else {
                    $errorMsg = 'No response from notification process';
                }
            } catch (Exception $ex) {
                $errorMsg = 'Error while retrieving response from notification process: ' . $ex->getMessage();
            }

            if (!empty($errorMsg)) {
                // Error initializing notification process. Make sure that communication pipes are deleted
                $commPipe->delete();
                wp_send_json_error('Error while initializing notification process: ' . $errorMsg, 500);
            }
        }

        // Send command to open notification channel
        $channelId = $_POST['channel_id'];
        $eventName = $_POST['event_name'];

        try {
            $commCommand->sendOpenNotifyChannelCommand($channelId, $eventName);
        } catch (Exception $ex) {
            wp_send_json_error('Error sending open notification channel command: ' . $ex->getMessage(), 500);
        }

        $commPipe->close();
        wp_send_json_success();
    }

    public function closeNotifyChannel()
    {
        check_ajax_referer(__FILE__);

        $clientUID = $_POST['client_uid'];

        if (!$clientUID) {
            wp_send_json_error('Missing client UID', 500);
        }

        try {
            $commPipe = new CommPipe($clientUID, true, CommPipe::SEND_COMM_MODE);
        } catch (Exception $ex) {
            wp_send_json_error('Error opening communication pipe: ' . $ex->getMessage(), 500);
        }

        // Make sure that communication pipes exist
        if ($commPipe->pipesExist()) {
            $commCommand = new CommCommand($commPipe);

            // Send command to close notification channel
            $channelId = $_POST['channel_id'];

            try {
                $commCommand->sendCloseNotifyChannelCommand($channelId);
            } catch (Exception $ex) {
                wp_send_json_error('Error sending close notification channel command: ' . $ex->getMessage(), 500);
            }

            $commPipe->close();
        }

        wp_send_json_success();
    }

    public function processServerPolling()
    {
        check_ajax_referer(__FILE__);

        $clientUID = $_POST['client_uid'];

        if (!$clientUID) {
            wp_send_json_error('Missing client UID', 500);
        }

        $response = [
            'notifyCommands' => null
        ];

        try {
            $commPipe = new CommPipe($clientUID, true, CommPipe::RECEIVE_COMM_MODE | CommPipe::SEND_COMM_CTRL_MODE);
        } catch (Exception $ex) {
            // Return error
            wp_send_json_error('Error opening communication pipe: ' . $ex->getMessage(), 500);
        }

        // Make sure that communication pipes exist
        if ($commPipe->pipesExist()) {
            $commCommand = new CommCommand($commPipe);

            // Send ping command
            try {
                $commCommand->sendPingCommand();
            } catch (Exception $ex) {
                // Return error
                wp_send_json_error('Error sending ping command: ' . $ex->getMessage(), 500);
            }

            // Retrieve received commands
            $notifyProcCommands = [];

            try {
                if ($commCommand->receive(self::$pollServerTimeout)) {
                    do {
                        $notifyProcCommands[] = $commCommand->getNextCommand();
                    } while ($commCommand->hasReceivedCommand());
                }
            } catch (Exception $ex) {
                // Return error
                wp_send_json_error('Error while retrieving response from notification process: '
                    . $ex->getMessage(), 500);
            }

            if (!empty($notifyProcCommands)) {
                // Add notification process commands to response
                $response['notifyCommands'] = $notifyProcCommands;
            }

            $commPipe->close();
        } else {
            // Communication pipes not available. Return error indicating that
            //  notification process is not running
            wp_send_json_error('Notification process is not running', 503);
        }

        wp_send_json_success($response);
    }
}