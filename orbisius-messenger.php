<?php
/*
Plugin Name: Orbisius Messenger
Plugin URI: http://club.orbisius.com/products/wordpress-plugins/orbisius-messenger/
Description: Makes the automated attempts to wait and then it shows an internal error message.
Version: 1.0.0
Author: Svetoslav Marinov (Slavi)
Author URI: http://orbisius.com
*/

/*  Copyright 2014-2050 Svetoslav Marinov (Slavi) <slavi@orbisius.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$orb_messenger = new orbisius_messenger();

add_action('init', array($orb_messenger, 'init'));
add_action('admin_init', array($orb_messenger, 'register_admin_settings'));
add_action('admin_menu', array($orb_messenger, 'setup_admin_menu'));

add_action('wp_head', array($orb_messenger, 'ajaxurl_fix'));
add_action('wp_ajax_orbisius_messenger_ajax_load_users', array($orb_messenger, 'ajax_load_users'));

class orbisius_messenger {
    /**
     * 
     * Returns JSON
     */
    function ajax_load_users() {
        $term = empty($_REQUEST['term']) ? '' : wp_kses($_REQUEST['term'], array());
       
        $users = get_users( array(
            'search' => $term . '*', // wildcard search
            'orderby' => 'nicename',
            'number' => 5,
            'exclude' => array( get_current_user_id() ), // exclude logged in user
        ) );

        // return array of obj containing id, label, value
        foreach ($users as $user_obj) {
            $ready_rec['id'] = $user_obj->ID;
            $ready_rec['label'] = $user_obj->user_nicename;
            $ready_rec['value'] = $user_obj->user_login;
            $prep_array[] = $ready_rec;
        }

        wp_send_json($prep_array);
    }

    function __construct() {
        
    }

    /**
    * The ajaxurl is only defined in the admin section
    */
    function ajaxurl_fix() {
        $url = admin_url('admin-ajax.php');
        echo <<<EOF_BUFF
    <script type="text/javascript">var ajaxurl = '$url';</script>
EOF_BUFF;
}

    function init() {
        $suffix = '';

        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'jquery-ui' );
        wp_enqueue_script( 'jquery-ui-autocomplete' );
        
		wp_register_script( 'orbisius_messenger', plugins_url("/assets/main{$suffix}.js", __FILE__), array('jquery', ),
				filemtime( plugin_dir_path( __FILE__ ) . "/assets/main{$suffix}.js" ), true);
		wp_enqueue_script( 'orbisius_messenger' );

        add_shortcode('orbisius_messenger_contact_form', array($this, 'render_contact_form'));
    }

    /**
    * We don't want any assholes using the site.
    */
    public function register_admin_settings() {
        //register_setting('orbisius_messenger_settings', 'orbisius_messenger_options', 'orbisius_messenger_validate_settings');
    }

    /**
    * We don't want any assholes using the site.
    */
    public function render_contact_form() {
        if ( ! is_user_logged_in() ) {
            return 'Please, login first in order to send a message to a member.';
        }

        ob_start();

        $default_data = array(
            'recipient' => '',
            'message' => '',
        );

        $data = empty( $_REQUEST['orbisius_messenger_contact_form'] ) ? array() : $_REQUEST['orbisius_messenger_contact_form'];
        $data = array_map('strip_tags', $data);
        $data = array_map('trim', $data);

        if ( !empty( $_POST ) ) {

            try {
                if ( empty( $data['cmd'] ) || $data['cmd'] != 'send_message' ) {
                    throw new Exception( "Invalid command." );
                }

                if ( empty( $data['recipient'] ) ) {
                    throw new Exception( "Please, enter username." );
                }

                if ( empty( $data['message'] ) ) {
                    throw new Exception( "Please, enter message." );
                }

                // http://codex.wordpress.org/Function_Reference/get_user_by
                $recipient_user = get_user_by( 'login', $data['recipient'] );

                if ( empty( $recipient_user ) ) {
                    throw new Exception( sprintf( "Username: %s not found.", esc_attr( $data['recipient'] ) ) );
                }
                
                $current_user = wp_get_current_user();

                if ( $current_user->user_login == $recipient_user->user_login ) {
                    throw new Exception( "You can't send a message to yourself. Nice try though :)." );
                }

                $host = $_SERVER['HTTP_HOST'];
                $host = preg_replace('#www\.#si', '', $host);
                $site_name = get_bloginfo( 'name' );

                $to = $recipient_user->user_email;
                $subject = 'New Message from ' . $current_user->user_login;
                $reply_to = $current_user->user_email;
                $message = $data['message'];

                $headers = '';
                $headers .= "From: $site_name Mailer <mailer@$host>\r\n";
                $headers .= "Reply-To: $reply_to\r\n"; // @todo add the names if present

                $attachments = array();

                $mail_status = wp_mail( $to, $subject, $message, $headers, $attachments );
                
                if ( empty( $mail_status ) ) {
                    throw new Exception( "There was a problem sending the message. Please try again later." );
                }

                echo "<div class='success'>The message has been sent!</div>";
                $data = array(); // no need to preserve the message.
            } catch (Exception $e ) {
                echo "<div class='error'>Error: . " . $e->getMessage() . " </div>";
            }
        }

        $data = array_merge($default_data, $data);
        /*
         <style>
        .orbisius_messenger_contact_form_container .orbisius_messenger_contact_form .ui-autocomplete {
            list-style-type:none;
        }
        </style>
         */
        ?>
        <div id="orbisius_messenger_contact_form_container" class="orbisius_messenger_contact_form_container">
            <form id="orbisius_messenger_contact_form" class="orbisius_messenger_contact_form" method="post">
                <?php wp_nonce_field( 'orbisius_messenger_contact_form', 'orbisius_messenger_contact_form_nonce' ); ?>
                <input type="hidden" name="orbisius_messenger_contact_form[cmd]"
                       id="orbisius_messenger_contact_form_cmd"
                       class="orbisius_messenger_contact_form_cmd" value="send_message" />
                <label for="orbisius_messenger_contact_form_recipient">Recipient:</label>
                
                <input type="text" name="orbisius_messenger_contact_form[recipient]"
                       placeholder="Username"
                       id="orbisius_messenger_contact_form_recipient"
                       class="orbisius_messenger_contact_form_recipient"
                       value="<?php echo esc_attr($data['recipient']); ?>" /><br/>

                <label for="orbisius_messenger_contact_form_message">Message:</label>
                <textarea name="orbisius_messenger_contact_form[message]"
                          placeholder="Enter the message ..."
                          id="orbisius_messenger_contact_form_message"
                          class="orbisius_messenger_contact_form_message"
                          rows="5" cols="40"><?php echo esc_attr($data['message']); ?></textarea><br/>
                
                <input type="submit" id="orbisius_messenger_contact_form_submit" name="orbisius_messenger_contact_form_submit"
                       class="orbisius_messenger_contact_form_submit" value="Send"/>
            </form>
        </div> <!-- /orbisius_messenger_contact_form_container -->

        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    /**
     * Set up administration
     *
     * @package Orbisius Messenger
     * @since 0.1
     */
    public function setup_admin_menu() {
        $hook = add_options_page( 'Orbisius Messenger', 'Orbisius Messenger',
            'manage_options', __FILE__, array($this, 'output_options_page') );

        add_filter( 'plugin_action_links', array($this, 'add_quick_settings_link'), 10, 2 );
    }

    /**
    * Adds the action link to settings. That's from Plugins. It is a nice thing.
    * @param type $links
    * @param type $file
    * @return type
    */
    function add_quick_settings_link($links, $file) {
       if ($file == plugin_basename(__FILE__)) {
           $link = admin_url('options-general.php?page=' . plugin_basename(__FILE__));
           $html_link = "<a href=\"{$link}\">Settings</a>";
           array_unshift($links, $html_link);
       }

       return $links;
    }

    /**
    * Retrieves the plugin options. It inserts some defaults.
    * The saving is handled by the settings page. Basically, we submit to WP and it takes
    * care of the saving.
    *
    * @return array
    */
   function get_options() {
       $defaults = array(
           'status' => 1,
       );

       $opts = get_option('orbisius_messenger_options');

       $opts = (array) $opts;
       $opts = array_merge($defaults, $opts);

       return $opts;
   }


    /**
     * Options page
     *
     * @package Orbisius Messenger
     * @since 1.0
     */
    function output_options_page() {
        $opts = $this->get_options();
        ?>

        <div class="wrap orbisius_messenger_admin_wrapper orbisius_messenger_container">

            <div id="icon-options-general" class="icon32"></div>
            <h2>Orbisius Messenger</h2>

            <div id="poststuff">

                <div id="post-body" class="metabox-holder columns-2">

                    <!-- main content -->
                    <div id="post-body-content">

                        <div class="meta-box-sortables ui-sortable">

                            <div class="postbox">

                                <h3><span>Settings</span></h3>
                                <div class="inside">
                                    <?php if (0) : ?>
                                    <form method="post" action="options.php">
                                        <?php settings_fields('orbisius_messenger_settings'); ?>
                                        <table class="form-table">

                                            <tr valign="top">
                                                <th scope="row">Plugin Status</th>
                                                <td>
                                                    <label for="radio1">
                                                        <input type="radio" id="radio1" name="orbisius_messenger_options[status]"
                                                            value="1" <?php echo empty($opts['status']) ? '' : 'checked="checked"'; ?> /> Enabled
                                                    </label>
                                                    <br/>
                                                    <label for="radio2">
                                                        <input type="radio" id="radio2" name="orbisius_messenger_options[status]"
                                                            value="0" <?php echo !empty($opts['status']) ? '' : 'checked="checked"'; ?> /> Disabled
                                                    </label>
                                                </td>
                                            </tr>

                                            <tr valign="top">
                                                <th scope="row">How to display ID</th>
                                                <td>
                                                    <label for="render_id_col1">
                                                        <input type="radio" id="render_id_col1" name="orbisius_messenger_options[render_id_col]"
                                                            value="product_id" <?php echo checked($opts['render_id_col'], 'product_id'); ?> /> Show Product ID
                                                    </label>
                                                    <br/>

                                                    <label for="render_id_col_show_sku">
                                                        <input type="radio" id="render_id_col_show_sku" name="orbisius_messenger_options[render_id_col]"
                                                            value="show_sku" <?php echo checked($opts['render_id_col'], 'show_sku'); ?> /> Show SKU (performs extra database queries)
                                                    </label>
                                                    <br/>

                                                    <label for="render_id_col_none">
                                                        <input type="radio" id="render_id_col_none" name="orbisius_messenger_options[render_id_col]"
                                                            value="none" <?php echo checked($opts['render_id_col'], 'none'); ?> /> Nothing (do not show it at all)
                                                    </label>
                                                </td>
                                            </tr>
                                        </table>

                                        <p class="submit">
                                            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                                        </p>
                                    </form>
                                    <?php else : ?>
                                        <div>
                                            The plugin doesn't have settings options at the moment.<br/><br/>

                                            <textarea class="widefat" readonly="readonly" rows="20">
Usage:
Create a page and paste this shortcode.
[orbisius_messenger_contact_form]

The code will generate a simple form which will accept username and message and send the message.
</textarea>
                                        </div>

                                    <?php endif; ?>
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->

                            <!-- Demo -->
                            <div class="postbox">
                                <h3><span>Demo</span></h3>
                                <div class="inside">

                                    TODO
                                    <?php if (0) : ?>
                                    <p>
                                        Link: <a href="http://www.youtube.com/watch?v=RsRBmCGuz1w&hd=1" target="_blank" title="[opens in a new and bigger tab/window]">http://www.youtube.com/watch?v=RsRBmCGuz1w&hd=1</a>
                                        <p>
                                            <iframe width="640" height="480" src="http://www.youtube.com/embed/RsRBmCGuz1w?hl=en&fs=1" frameborder="0" allowfullscreen></iframe>
                                        </p>

                                        <?php
                                            $plugin_data = get_plugin_data(__FILE__);
                                            $product_name = trim($plugin_data['Name']);
                                            $product_page = trim($plugin_data['PluginURI']);
                                            $product_descr = trim($plugin_data['Description']);
                                            $product_descr_short = substr($product_descr, 0, 50) . '...';

                                            $product_name .= ' #WordPress #plugin';
                                            $product_descr_short .= ' #WordPress #plugin';

                                            $base_name_slug = basename(__FILE__);
                                            $base_name_slug = str_replace('.php', '', $base_name_slug);
                                            $product_page .= (strpos($product_page, '?') === false) ? '?' : '&';
                                            $product_page .= "utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product";

                                            $product_page_tweet_link = $product_page;
                                            $product_page_tweet_link = str_replace('plugin-settings', 'tweet', $product_page_tweet_link);

                                            $app_link = 'http://www.youtube.com/embed/RsRBmCGuz1w?hl=en&fs=1';
                                            $app_title = esc_attr($product_name);
                                            $app_descr = esc_attr($product_descr_short);
                                        ?>
                                        <p>Share this video:
                                            <!-- AddThis Button BEGIN -->
                                            <div class="addthis_toolbox addthis_default_style addthis_32x32_style">
                                            <a class="addthis_button_facebook" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_twitter" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_google_plusone" g:plusone:count="false" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_linkedin" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_email" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_myspace" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_google" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_digg" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_delicious" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_stumbleupon" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_tumblr" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_favorites" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_compact"></a>
                                            </div>
                                            <!-- The JS code is in the footer -->
                                        </p>

                                        <script type="text/javascript">
                                        var addthis_config = {"data_track_clickback":true};
                                        var addthis_share = {
                                          templates: { twitter: 'Check out {{title}} @ {{lurl}} (from @orbisius)' }
                                        }
                                        </script>
                                        <!-- AddThis Button START part2 -->
                                        <script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=lordspace"></script>
                                        <!-- AddThis Button END part2 -->
                                    </p>

                                    <?php endif; ?>
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->
                            <!-- /Demo -->

                            <?php orbisius_messenger_widget::output_widget('author'); ?>

                        </div> <!-- .meta-box-sortables .ui-sortable -->

                    </div> <!-- post-body-content -->

                    <!-- sidebar -->
                    <div id="postbox-container-1" class="postbox-container">

                        <div class="meta-box-sortables">
                            <!-- Hire Us -->
                            <div class="postbox">
                                <h3><span>Hire Us</span></h3>
                                <div class="inside">
                                    Hire us to create a plugin/web/mobile app
                                    <br/><a href="http://orbisius.com/page/free-quote/?utm_source=<?php echo str_replace('.php', '', basename(__FILE__));?>&utm_medium=plugin-settings&utm_campaign=product"
                                       title="If you want a custom web/mobile app/plugin developed contact us. This opens in a new window/tab"
                                        class="button-primary" target="_blank">Get a Free Quote</a>
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->
                            <!-- /Hire Us -->

                            <!-- Newsletter-->
                            <div class="postbox">
                                <h3><span>Newsletter</span></h3>
                                <div class="inside">
                                    <!-- Begin MailChimp Signup Form -->
                                    <div id="mc_embed_signup">
                                        <?php
                                            $current_user = wp_get_current_user();
                                            $email = empty($current_user->user_email) ? '' : $current_user->user_email;
                                        ?>

                                        <form action="http://WebWeb.us2.list-manage.com/subscribe/post?u=005070a78d0e52a7b567e96df&amp;id=1b83cd2093" method="post"
                                              id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank">
                                            <input type="hidden" value="settings" name="SRC2" />
                                            <input type="hidden" value="<?php echo str_replace('.php', '', basename(__FILE__));?>" name="SRC" />

                                            <span>Get notified about cool plugins we release</span>
                                            <!--<div class="indicates-required"><span class="app_asterisk">*</span> indicates required
                                            </div>-->
                                            <div class="mc-field-group">
                                                <label for="mce-EMAIL">Email</label>
                                                <input type="email" value="<?php echo esc_attr($email); ?>" name="EMAIL" class="required email" id="mce-EMAIL">
                                            </div>
                                            <div id="mce-responses" class="clear">
                                                <div class="response" id="mce-error-response" style="display:none"></div>
                                                <div class="response" id="mce-success-response" style="display:none"></div>
                                            </div>	<div class="clear"><input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button-primary"></div>
                                        </form>
                                    </div>
                                    <!--End mc_embed_signup-->
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->
                            <!-- /Newsletter-->

                            <?php orbisius_messenger_widget::output_widget(); ?>

                            <!-- Support options -->
                            <div class="postbox">
                                <h3><span>Support & Feature Requests</span></h3>
                                <h3>
                                    <?php
                                        $plugin_data = get_plugin_data(__FILE__);
                                        $product_name = trim($plugin_data['Name']);
                                        $product_page = trim($plugin_data['PluginURI']);
                                        $product_descr = trim($plugin_data['Description']);
                                        $product_descr_short = substr($product_descr, 0, 50) . '...';
                                        $product_descr_short .= ' #WordPress #plugin';

                                        $base_name_slug = basename(__FILE__);
                                        $base_name_slug = str_replace('.php', '', $base_name_slug);
                                        $product_page .= (strpos($product_page, '?') === false) ? '?' : '&';
                                        $product_page .= "utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product";

                                        $product_page_tweet_link = $product_page;
                                        $product_page_tweet_link = str_replace('plugin-settings', 'tweet', $product_page_tweet_link);
                                    ?>
                                    <!-- Twitter: code -->
                                    <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="http://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
                                    <!-- /Twitter: code -->

                                    <!-- Twitter: Orbisius_Follow:js -->
                                        <a href="https://twitter.com/orbisius" class="twitter-follow-button"
                                           data-align="right" data-show-count="false">Follow @orbisius</a>
                                    <!-- /Twitter: Orbisius_Follow:js -->

                                    &nbsp;

                                    <!-- Twitter: Tweet:js -->
                                    <a href="https://twitter.com/share" class="twitter-share-button"
                                       data-lang="en" data-text="Checkout <?php echo $product_name;?> #WordPress #plugin.<?php echo $product_descr_short; ?>"
                                       data-count="none" data-via="orbisius" data-related="orbisius"
                                       data-url="<?php echo $product_page_tweet_link;?>">Tweet</a>
                                    <!-- /Twitter: Tweet:js -->

                                    <br/>
                                    <span>
                                        <a href="<?php echo $product_page; ?>" target="_blank" title="[new window]">Product Page</a>
                                        |
                                        <a href="http://club.orbisius.com/forums/forum/community-support-forum/wordpress-plugins/<?php echo $base_name_slug;?>/?utm_source=<?php echo $base_name_slug;?>&utm_medium=plugin-settings&utm_campaign=product"
                                        target="_blank" title="[new window]">Support Forums</a>

                                         <!-- |
                                         <a href="http://docs.google.com/viewer?url=https%3A%2F%2Fdl.dropboxusercontent.com%2Fs%2Fwz83vm9841lz3o9%2FOrbisius_LikeGate_Documentation.pdf" target="_blank">Documentation</a>-->
                                    </span>
                                </h3>
                            </div> <!-- .postbox -->
                            <!-- /Support options -->

                            <div class="postbox">

                                <h3><span>Share</span></h3>
                                <div class="inside">
                                    <?php
                                        $plugin_data = get_plugin_data(__FILE__);

                                        $app_link = urlencode($plugin_data['PluginURI']);
                                        $app_title = urlencode($plugin_data['Name']);
                                        $app_descr = urlencode($plugin_data['Description']);
                                    ?>
                                    <p>
                                        <!-- AddThis Button BEGIN -->
                                        <div class="addthis_toolbox addthis_default_style addthis_32x32_style">
                                            <a class="addthis_button_facebook" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_twitter" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_google_plusone" g:plusone:count="false" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_linkedin" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_email" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <!--<a class="addthis_button_myspace" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_google" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_digg" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_delicious" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_stumbleupon" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_tumblr" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_favorites" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>-->
                                            <a class="addthis_button_compact"></a>
                                        </div>
                                        <!-- The JS code is in the footer -->

                                        <script type="text/javascript">
                                        var addthis_config = {"data_track_clickback":true};
                                        var addthis_share = {
                                          templates: { twitter: 'Check out {{title}} @ {{lurl}} (from @orbisius)' }
                                        }
                                        </script>
                                        <!-- AddThis Button START part2 -->
                                        <script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=lordspace"></script>
                                        <!-- AddThis Button END part2 -->
                                    </p>
                                </div> <!-- .inside -->

                            </div> <!-- .postbox -->

                            <div class="postbox"> <!-- quick-contact -->
                                <?php
                                $current_user = wp_get_current_user();
                                $email = empty($current_user->user_email) ? '' : $current_user->user_email;
                                $quick_form_action = is_ssl()
                                        ? 'https://ssl.orbisius.com/apps/quick-contact/'
                                        : 'http://apps.orbisius.com/quick-contact/';

                                if (!empty($_SERVER['DEV_ENV'])) {
                                    $quick_form_action = 'http://localhost/projects/quick-contact/';
                                }
                                ?>
                                <h3><span>Quick Question or Suggestion</span></h3>
                                <div class="inside">
                                    <div>
                                        <form method="post" action="<?php echo $quick_form_action; ?>" target="_blank">
                                            <?php
                                                global $wp_version;
                                                $plugin_data = get_plugin_data(__FILE__);

                                                $hidden_data = array(
                                                    'site_url' => site_url(),
                                                    'wp_ver' => $wp_version,
                                                    'first_name' => $current_user->first_name,
                                                    'last_name' => $current_user->last_name,
                                                    'product_name' => $plugin_data['Name'],
                                                    'product_ver' => $plugin_data['Version'],
                                                    'woocommerce_ver' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : 'n/a',
                                                );
                                                $hid_data = http_build_query($hidden_data);
                                                echo "<input type='hidden' name='data[sys_info]' value='$hid_data' />\n";
                                            ?>
                                            <textarea class="widefat" id='orbisius_messenger_msg' name='data[msg]' required="required"></textarea>
                                            <br/>Your Email: <input type="text" class=""
                                                   name='data[sender_email]' placeholder="Email" required="required"
                                                   value="<?php echo esc_attr($email); ?>"
                                                   />
                                            <br/><input type="submit" class="button-primary" value="<?php _e('Send') ?>"
                                                        onclick="try { if (jQuery('#orbisius_messenger_msg').val().trim() == '') { alert('Enter your message.'); jQuery('#orbisius_messenger_msg').focus(); return false; } } catch(e) {};" />
                                            <br/>
                                            What data will be sent
                                            <a href='javascript:void(0);'
                                                onclick='jQuery(".orbisius-price-changer-woocommerce-quick-contact-data-to-be-sent").toggle();'>(show/hide)</a>
                                            <div class="hide hide-if-js orbisius-price-changer-woocommerce-quick-contact-data-to-be-sent">
                                                <textarea class="widefat" rows="4" readonly="readonly" disabled="disabled"><?php
                                                foreach ($hidden_data as $key => $val) {
                                                    if (is_array($val)) {
                                                        $val = var_export($val, 1);
                                                    }

                                                    echo "$key: $val\n";
                                                }
                                                ?></textarea>
                                            </div>
                                        </form>
                                    </div>
                                </div> <!-- .inside -->

                            </div> <!-- .postbox --> <!-- /quick-contact -->

                            <!-- Support options -->
                            <div class="postbox">
                                <h3><span>Support & Feature Requests</span></h3>
                                <h3>
                                    <?php
                                        $plugin_data = get_plugin_data(__FILE__);
                                        $product_name = trim($plugin_data['Name']);
                                        $product_page = trim($plugin_data['PluginURI']);
                                        $product_descr = trim($plugin_data['Description']);
                                        $product_descr_short = substr($product_descr, 0, 50) . '...';
                                        $product_descr_short .= ' #WordPress #plugin';

                                        $base_name_slug = basename(__FILE__);
                                        $base_name_slug = str_replace('.php', '', $base_name_slug);
                                        $product_page .= (strpos($product_page, '?') === false) ? '?' : '&';
                                        $product_page .= "utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product";

                                        $product_page_tweet_link = $product_page;
                                        $product_page_tweet_link = str_replace('plugin-settings', 'tweet', $product_page_tweet_link);
                                    ?>
                                    <!-- Twitter: code -->
                                    <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="http://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
                                    <!-- /Twitter: code -->

                                    <!-- Twitter: Orbisius_Follow:js -->
                                        <a href="https://twitter.com/orbisius" class="twitter-follow-button"
                                           data-align="right" data-show-count="false">Follow @orbisius</a>
                                    <!-- /Twitter: Orbisius_Follow:js -->

                                    &nbsp;

                                    <!-- Twitter: Tweet:js -->
                                    <a href="https://twitter.com/share" class="twitter-share-button"
                                       data-lang="en" data-text="Checkout <?php echo $product_name;?> #WordPress #plugin.<?php echo $product_descr_short; ?>"
                                       data-count="none" data-via="orbisius" data-related="orbisius"
                                       data-url="<?php echo $product_page_tweet_link;?>">Tweet</a>
                                    <!-- /Twitter: Tweet:js -->

                                    <br/>
                                    <span>
                                        <a href="<?php echo $product_page; ?>" target="_blank" title="[new window]">Product Page</a>
                                        |
                                        <a href="http://club.orbisius.com/forums/forum/community-support-forum/wordpress-plugins/<?php echo $base_name_slug;?>/?utm_source=<?php echo $base_name_slug;?>&utm_medium=plugin-settings&utm_campaign=product"
                                        target="_blank" title="[new window]">Support Forums</a>

                                         <!-- |
                                         <a href="http://docs.google.com/viewer?url=https%3A%2F%2Fdl.dropboxusercontent.com%2Fs%2Fwz83vm9841lz3o9%2FOrbisius_LikeGate_Documentation.pdf" target="_blank">Documentation</a>-->
                                    </span>
                                </h3>
                            </div> <!-- .postbox -->
                            <!-- /Support options -->

                            <div class="postbox">

                                <h3><span>Share</span></h3>
                                <div class="inside">
                                    <?php
                                        $plugin_data = get_plugin_data(__FILE__);

                                        $app_link = urlencode($plugin_data['PluginURI']);
                                        $app_title = urlencode($plugin_data['Name']);
                                        $app_descr = urlencode($plugin_data['Description']);
                                    ?>
                                    <p>
                                        <!-- AddThis Button BEGIN -->
                                        <div class="addthis_toolbox addthis_default_style addthis_32x32_style">
                                            <a class="addthis_button_facebook" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_twitter" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_google_plusone" g:plusone:count="false" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_linkedin" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_email" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <!--<a class="addthis_button_myspace" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_google" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_digg" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_delicious" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_stumbleupon" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_tumblr" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_favorites" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>-->
                                            <a class="addthis_button_compact"></a>
                                        </div>
                                        <!-- The JS code is in the footer -->

                                        <script type="text/javascript">
                                        var addthis_config = {"data_track_clickback":true};
                                        var addthis_share = {
                                          templates: { twitter: 'Check out {{title}} @ {{lurl}} (from @orbisius)' }
                                        }
                                        </script>
                                        <!-- AddThis Button START part2 -->
                                        <script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=lordspace"></script>
                                        <!-- AddThis Button END part2 -->
                                    </p>
                                </div> <!-- .inside -->

                            </div> <!-- .postbox -->

                        </div> <!-- .meta-box-sortables -->

                    </div> <!-- #postbox-container-1 .postbox-container -->

                </div> <!-- #post-body .metabox-holder .columns-2 -->

                <br class="clear">
            </div> <!-- #poststuff -->

        </div> <!-- .wrap -->
        <?php
    }

    function orbisius_messenger_get_plugin_data() {
        // pull only these vars
        $default_headers = array(
            'Name' => 'Plugin Name',
            'PluginURI' => 'Plugin URI',
            'Version' => 'Version', // not tested
        );

        $plugin_data = get_file_data(__FILE__, $default_headers, 'plugin');

        $url = $plugin_data['PluginURI'];
        $name = $plugin_data['Name'];

        $data['name'] = $name;
        $data['url'] = $url;

        return $data;
    }
   
}

/**
 * Orbisius Widget
 */
class orbisius_messenger_widget {
    /**
     * Loads news from Club Orbsius Site.
     * <?php orbisius_messenger_widget::output_widget(); ?>
     * <?php orbisius_messenger_widget::output_widget('author'); ?>
     */
    public static function output_widget($obj = '', $return = 0) {
        $buff = '';
        ?>
        <!-- Orbisius JS Widget -->
            <?php
                $naked_domain = !empty($_SERVER['DEV_ENV']) ? 'orbclub.com.clients.com' : 'club.orbisius.com';

                if (!empty($_SERVER['DEV_ENV']) && is_ssl()) {
                    $naked_domain = 'ssl.orbisius.com/club';
                }

				// obj could be 'author'
                $obj = empty($obj) ? str_replace('.php', '', basename(__FILE__)) : sanitize_title($obj);
                $obj_id = 'orb_widget_' . sha1($obj);

                $params = '?' . http_build_query(array('p' => $obj, 't' => $obj_id, 'layout' => 'plugin', ));
                $buff .= "<div id='$obj_id' class='$obj_id orbisius_ext_content'></div>\n";
                $buff .= "<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://$naked_domain/wpu/widget/$params';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'orbsius-js-$obj_id');</script>";
            ?>
            <!-- /Orbisius JS Widget -->
        <?php

        if ($return) {
            return $buff;
        } else {
            echo $buff;
        }
    }
}
