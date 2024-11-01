<?php
/**
 * Plugin Name:       WoPo Cryptocurrency Widget
 * Plugin URI:        https://wopoweb.com/contact-us/
 * Description:       Price ticker widget for all cryptocurrencies using CoinMarketCap API
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            WoPo Web
 * Author URI:        https://wopoweb.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wopo-cryptocurrency-widget
 * Domain Path:       /languages
 */

class WoPo_Cryptocurrency_Widget extends WP_Widget {
 
    function __construct() {

        parent::__construct(
            'wopo-cryptocurrency-widget',  // Base ID
            'WoPo Cryptocurrency Widget'   // Name
        );

        add_action( 'widgets_init', function() {
            register_widget( 'WoPo_Cryptocurrency_Widget' );
        });

        if ( is_active_widget( false, false, $this->id_base, true ) ) {
            wp_enqueue_script( 'wopo-cryptocurrency-widget','https://files.coinmarketcap.com/static/widget/currency.js' );
        }

        $api_key = get_option('wopoccw_api_key');
        
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
            'start' => '1',
            'limit' => '5000',
        ];

        $upload_dir = wp_upload_dir();
        $upload_data_file = $upload_dir['basedir'].'/wopo-crytocurrency-widget-data.json';

        if (!file_exists($upload_data_file) && !empty($api_key)){
            // get new list currency via API
            $headers = array(
                'Accepts' => 'application/json',
                'X-CMC_PRO_API_KEY' => $api_key
            );
            $qs = http_build_query($parameters); // query string encode the parameters
            $request = "{$url}?{$qs}"; // create the request URL        

            $response = wp_remote_get($request,array('headers' => $headers));
            $response = wp_remote_retrieve_body($response);
            file_put_contents($upload_data_file,$response);
        }else{
            $upload_data_file = __DIR__.'/data.json';
        }            
                
        $response = file_get_contents($upload_data_file);

        if ($response){
            $this->data = json_decode($response)->data;  
        }          
    }

    public $data = '';
    public $args = array(
        'before_title'  => '<h4 class="widgettitle">',
        'after_title'   => '</h4>',
        'before_widget' => '<div class="widget-wrap">',
        'after_widget'  => '</div></div>'
    );

    public function widget( $args, $instance ) {
        $coin_id = ( !empty( $instance['coin_id'] ) ) ? $instance['coin_id'] : 1;
        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }

        echo '<div class="textwidget">';

        echo '<div class="coinmarketcap-currency-widget" data-currencyid="'.$coin_id.'" data-base="USD" data-secondary="" data-ticker="true" data-rank="true" data-marketcap="true" data-volume="true" data-statsticker="true" data-stats="USD"></div>';

        echo '</div>';

        echo $args['after_widget'];

    }

    public function form( $instance ) {
        $api_key = get_option('wopoccw_api_key');
        if(is_array($this->data)){
            $title = ! empty( $instance['title'] ) ? $instance['title'] : '';
            $coin_id = ! empty( $instance['coin_id'] ) ? $instance['coin_id'] : 1;
            ?>
            <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php echo esc_html__( 'Title:', 'wopo-cryptocurrency-widget' ); ?></label>
                <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'coin_id' ) ); ?>"><?php echo esc_html__( 'Cryptocurrency:', 'wopo-cryptocurrency-widget' ); ?></label>
                <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'coin_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'coin_id' ) ); ?>">
                    <?php
                    foreach($this->data as $coin){
                    ?>
                    <option value="<?= $coin->id ?>" <?= $coin->id == $coin_id ? 'selected' : '' ?>><?= $coin->name . ' (' . $coin->symbol . ')' ?></option>
                    <?php } ?>
                </select>         
            </p>
            <?php
        }
        
        if (empty($api_key)){
            ?>
            <p>
                <span style="color:gray">You don't have CoinMarketCap.com API key. Your Cryptocurrency list is old data. Enter your API key <a href="<?= admin_url('options-general.php#wopoccw_api_key_field') ?>">here</a> to update your Cryptocurrency list</span>
            </p>
            <?php
        }
    }

    public function update( $new_instance, $old_instance ) {

        $instance = array();

        $instance['title'] = ( !empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        $instance['coin_id'] = ( !empty( $new_instance['coin_id'] ) ) ? $new_instance['coin_id'] : 1;

        return $instance;
    }

}
$WoPo_Cryptocurrency_Widget = new WoPo_Cryptocurrency_Widget();

function wopoccw_settings_init()
{
    // register a new setting for "reading" page
    register_setting('general', 'wopoccw_api_key');
 
    // register a new section in the "reading" page
    add_settings_section(
        'wopoccw_settings_section',
        'WoPo Cryptocurrency Widget Settings Section',
        'wopoccw_settings_section_cb',
        'general'
    );
 
    // register a new field in the "wporg_settings_section" section, inside the "reading" page
    add_settings_field(
        'wopoccw_settings_field',
        'CoinMarketCap.com API key',
        'wopoccw_settings_field_cb',
        'general',
        'wopoccw_settings_section'
    );
}
 
/**
 * register wporg_settings_init to the admin_init action hook
 */
add_action('admin_init', 'wopoccw_settings_init');
 
/**
 * callback functions
 */
 
// section content cb
function wopoccw_settings_section_cb()
{
    echo '<p>CoinMarketCap.com API. Get one <a target="_blank" href="https://coinmarketcap.com/api/">here</a></p>';
}
 
// field content cb
function wopoccw_settings_field_cb()
{
    // get the value of the setting we've registered with register_setting()
    $api_key = get_option('wopoccw_api_key');
    // output the field
    ?>
    <a id="wopoccw_api_key_field"></a>
    <input type="password" name="wopoccw_api_key" value="<?php echo isset( $api_key ) ? esc_attr( $api_key ) : ''; ?>">
    <?php
}