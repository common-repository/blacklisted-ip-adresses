<?php
/**
 * @file
 * use BoldizArt\BlacklistedIPAddresses;
 */
namespace BoldizArt;

/*
Copyright 2020 BoldizArt

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
class BlacklistedIPAddresses
{
    /** @param string $basename */
    public $basename;

    /**
     * Constructor
     */
    public function __construct($basename)
    {
        $this->basename = $basename;

        // Add styles
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles']);

        // Add scripts
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts']);

        // Add admin menu items
        add_action('admin_menu', [$this, 'blacklistedIpAddressesLink']);

        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidget']);
        add_filter('plugin_action_links', [$this, 'my_plugin_settings_link'], 10, 2);
    }

    // Add a "Settings" link to the plugin list
    function my_plugin_settings_link( $links, $file ) {
        if ( $this->basename == $file ) {
            $settings_link = '<a href="' . admin_url('tools.php?page=blacklistedIpAddresses') . '">' . esc_html(__('Settings')) . '</a>';
            array_unshift( $links, $settings_link );
        }
        return $links;
    }

    /**
     * Add the blacklisted IP addresses to the .htaccess file
     * @param array $ips
     */
    public function addBlacklistedIpAddresses(array $ips)
    {
        // Define the response array
        $response = [];

        // Define the .htaccess file path
        $htaccessUrl = ABSPATH . '.htaccess';

        // Fetch the file content
        $content = file_get_contents($htaccessUrl);

        // Format the ips
        $formatedIps = "order allow,deny\n";
        foreach ($ips as $ip) {
            $ip = str_replace(' ', '', $ip);
            $ip = trim($ip);
            if (preg_match('/^((\d{1,2}|1\d{2}|2[0-4]\d|25[0-5])\.){3}(\d{1,2}|1\d{2}|2[0-4]\d|25[0-5])$/', $ip)) {
                $response[] = $ip;
                $formatedIps .= "deny from {$ip}\n";
            }
        }
        $formatedIps .= "allow from all\n";

        // Create regEx IP pattern
        $pattern = "/order allow,deny\n(.*\n)*allow from all\n/";

        try {
            // If there is not an IP block part, just add it
            if (strpos($content, "order allow,deny") === false || strpos($content, "allow from all") === false) {
                $newContent = "# Blacklist IP addresses START\n\n{$formatedIps}\n# Blacklist IP addresses END\n";

                // Add the new content to the end of the file
                file_put_contents($htaccessUrl, $newContent, FILE_APPEND);
            } else {
                // Othewise change the ip block part
                $newContent = preg_replace($pattern, $formatedIps, $content);
                file_put_contents($htaccessUrl, $newContent);
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p><strong>'.esc_html(sprintf(__('Error: %s', 'blacklistedipaddresses')), $e->getMessage()).'</strong></p></div>';
        }

        return $response;
    }

    /**
     * Admin page functions
     */
    public function blacklistedIpAddressesPage()
    {
        // Check for a specific post request
        if (isset($_POST['blacklisted_ip_addresses'], $_POST['blacklisted_ip_addresses_nonce'])) {

            // Validate the nonce
            if (wp_verify_nonce($_POST['blacklisted_ip_addresses_nonce'], 'blacklisted_ip_addresses_nonce')) {

                // Get the textarea field value and sanitize it
                $ipAddresses = sanitize_textarea_field($_POST['blacklisted_ip_addresses']);

                // Fetch the blacklisted IP addresses
                $ips = explode("\n", $ipAddresses);
                $ips = array_map('trim', $ips);
                $ips = array_filter($ips, 'strlen');

                // Add them to the .htaccess file
                $validated = $this->addBlacklistedIpAddresses($ips);
                $validatedIps = implode("\n", $validated);

                // Save the data as "blacklisted_ip_addresses"
                update_option('blacklisted_ip_addresses', $validatedIps);

                // Show a "success" message
                echo '<div class="notice notice-success"><p><strong>' . esc_html(__('Successfully updated', 'blacklistedipaddresses')) . '</strong></p></div>';
            }  else {
                // Nonce is invalid, handle the error appropriately
                echo '<div class="notice notice-error"><p>' . esc_html(__('Invalid or missing security token', 'blacklistedipaddresses')) . '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Blacklisted IP addresses', 'blacklistedipaddresses'); ?></h1>
            <form method="post" action="">
            <?php wp_nonce_field('blacklisted_ip_addresses_nonce', 'blacklisted_ip_addresses_nonce'); ?>
                <p><?php esc_html_e('Add the IP addresses you want to block, one address per line, in the format yyy.yy.yy.yy(/yy)', 'blacklistedipaddresses'); ?></p>
                <textarea name="blacklisted_ip_addresses" rows="10" cols="50"><?php echo esc_html($this->getIpAddresses()); ?></textarea><br>
                <p>
                    <?php
                    $count = $this->getIpAddresses('count');
                    if ($count == 0) {
                        esc_html_e('There are no IP addresses yet', 'text-domain');
                    } else {
                        printf(_n('There is %s IP address', 'There are %s IP addresses', $count, 'text-domain'), $count);
                    }
                    ?>
                </p>
                <p>
                    <input type="submit" class="button action" name="submit" value="<?php echo esc_attr__('Save', 'blacklistedipaddresses'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Get/create fetch token
     * @param string $type
     * @return mixed
     */
    public function getIpAddresses($type = '')
    {
        // Get the "blacklisted_ip_addresses" value from the options
        $ipAddresses = get_option('blacklisted_ip_addresses');

        switch ($type) {
            case 'array':
                $ips = explode("\n", $ipAddresses);
                return array_filter($ips, 'strlen');
                break;
            case 'count':
                $ips = explode("\n", $ipAddresses);
                $ips = array_filter($ips, 'strlen');
                return count($ips);
                break;
            
            default:
                return $ipAddresses;
                break;
        }
    }

    // Add link to the side menu under the tools menu item
    public function blacklistedIpAddressesLink()
    {
        add_submenu_page(
            'tools.php',
            'Blacklisted IP addresses',
            'Blacklisted IP addresses',
            'manage_options',
            'blacklistedIpAddresses',
            [$this, 'blacklistedIpAddressesPage']
        );
    }

    /**
     * Add dashboard widgets
     */
    public function addDashboardWidget()
    {
        $wid = 'ba_blacklisted_ip_addresses';
        $wname = 'Looking for help with your website?';        
        wp_add_dashboard_widget($wid, $wname, [$this, 'promotionWidget']);
    }

    /**
     * Add a promotion widget to the admin dashboard
     */
    public function promotionWidget()
    {
        ?>
        <div class="boldizart-block">
            <p>Look no further! Whether you're dealing with slow load times, frustrating bugs, or just want some custom functions, our team is here to help.</p>
            <ul class="list">
                <li>&#10003; <a href="https://boldizart.com/" target="_blank">Website development</a></li>
                <li>&#10003; <a href="https://boldizart.com/" target="_blank">Custom WordPress plugins</a></li>
                <li>&#10003; <a href="https://boldizart.com/" target="_blank">Technical SEO</a></li>
                <li>&#10003; <a href="https://boldizart.com/" target="_blank">Website audit</a></li>
                <li>&#10003; <a href="https://boldizart.com/" target="_blank">Automatisation</a></li>
            </ul>
            <a href="https://boldizart.com/" target="_blank" class="cta">Click here, and read more</a>
            <div style="clear: both;"></div>
        </div>
        <style>
            .boldizart-block {
                padding-bottom: 1rem;
            }
            .boldizart-block a {
                text-decoration: none;
                color: #6d6d6d;
            }
            .boldizart-block a:focus,
            .boldizart-block a:hover {
                color: #222;
                box-shadow: none;
            }
            .list {
                padding: 12px 6px;
            }
            .boldizart-block .cta {
                padding: .6rem 1.2rem;
                background: #ff6331;
                color: #fff;
                border-radius: 4px;
                transition: background-color ease-in-out 280ms;
            }
            .boldizart-block .cta:focus,
            .boldizart-block .cta:hover {
                background: #ff460b;
                box-shadow: none;
                color: #fff;
            }
        </style>
        <?php
    }

    /**
     * Activation
     */
    function activate()
    {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Deactivation
     */
    function deactivate()
    {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Uninstall
     */
    function uninstall()
    {
        // Security checks
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            exit;
        }
    }

    /**
     * Add admin scripts to the website
     */
    function addAdminScripts()
    {

    }

    /**
     * Add admin stylesheets to the website
     */
    function addAdminStyles()
    {

    }
}
