<?php
/*
Plugin Name: Thumbs Up Rating
Plugin URI: http://www.adamjctaylor.com/apps/thumbs-up/
Description: Provides a simple thumbs up/thumbs down rating of posts.
Version: 1.0
Author: Adam Taylor
Author URI: http://www.adamjctaylor.com
*/

/*  Copyright 2009  Adam Taylor  (email : adamjctaylor @ gmail dot com)

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

    /**
     * ... Description
     *
     * @package ThumbsUpRating
     * @since 1.0
     */
    class ThumbsUp {
        
        public $table_name; 
        public $table_prefix;
        
        function ThumbsUp() {
            global $wpdb;
            $this->table_name = $wpdb->prefix . "thumbs_up";
            $this->table_prefix = $wpdb->prefix;
        }
        
        function installThumbsUp() {
            $this->createDatabaseTables();
            
            $thumbsDBVersion = '1.0';
            add_option("thumbs_up_db_version", $thumbsDBVersion);
            
        }
        
        /**
         * Creates the database tables required by the plugin.
         *
         * @package ThumbsUpRating
         * @since 1.0
         */
        function createDatabaseTables() {
            global $wpdb;
            
            if($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name) {
                $sql = "CREATE TABLE " . $this->table_name . " (
                	  id bigint(20) NOT NULL AUTO_INCREMENT,
                	  post_id bigint(20) NOT NULL,
                	  thumb tinyint(1) NOT NULL,
                	  ip varchar(15) NOT NULL,
                	  user varchar(60),
                	  UNIQUE KEY id (id)
                	);";

                /** 
                 * This file allows us to call a built in DB function to create our table.
                 */
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }
        }
        
        /**
         * Displays the ratings on a page or post.
         *
         * @package ThumbsUpRating
         * @since 1.0
         */
        function displayThumbs() {
            global $post;
            $post_id = $post->ID;
            $ip = $_SERVER['REMOTE_ADDR'];
            global $current_user; 
            get_currentuserinfo(); 
            $user = $current_user->user_login;
            
            $content  = '<div id="thumbs"><img src="http://dev.wordpress/wp-content/plugins/wp-postratings/images/thumbs/title-doyoulike.gif">';
                                    $content .= '<br /><a onclick="thumbs_up_cast_vote( '. $post_id .',1,\''. $ip .'\',\''. $user .'\' )"><img src="/wp-content/plugins/thumbs-up/img/thumb_up.png" alt="thumbs up" title="Vote this post up" width="16px" height="16px" /> <strong>Yes</strong> </a>  <a onclick="thumbs_up_cast_vote( '. $post_id .',-1,\''. $ip .'\',\''. $user .'\' )"><img src="/wp-content/plugins/thumbs-up/img/thumb_down.png" alt="thumbs down" title="Vote this post down" width="16px" height="16px" /> <strong>No</strong> </a> ';
            
            // $content .= '<form>
            //          Your vote: <input type="text" name="uservote" />
            //          <input type="button" value="Vote!" onclick="thumbs_up_cast_vote('. $post_id .',1);" />
            //          <div id="thumbs_results">';
            
            if ( $this->getUpVotes( $post_id ) > 0 || $this->getDownVotes($post_id) > 0 ) {
                $content .= '<div id="thumbs_results"> <strong>'. $this->getUpVotes( $post_id )  .'</strong> thumbs up | <strong>'. $this->getDownVotes( $post_id ) .'</strong> thumbs down.</div>';
            } else {
                $content .= '<div id="thumbs_results"><strong>0</strong> thumbs up | <strong>0</strong> thumbs down.</div>';
            }
            
            $content .= '</div>';
            
            echo $content;
        }
        
        /**
         * Processes a new rating.
         *
         * @package ThumbsUpRating
         * @since 1.0
         *
         * @param    int        $post_id    The ID of the post which is being rated.
         * @param    int        $vote       The rating of the post.    
         * @param    string     $ip         The IP of the user voting.
         * @param    string     $user       The username of the user voting if they are logged in.
         */
        function processVote($post_id, $vote, $ip, $user = '') {
            //require_once('../../../wp-load.php');
            global $wpdb;
            
            $table_name;
            if ( $this->table_name == '') {
                $table_name = $this->tablename;
            } else {
                $table_name = $wpdb->prefix.'thumbs_up';
            }

            // Compose JavaScript for return
            if ( $this->hasVoted( $post_id, $ip, $user ) ) {
                $output = 'Sorry you have already rated this post.<br/ >';
            } else {
                // Record vote in DB
               $wpdb->insert( $table_name, array( 'post_id' => $wpdb->escape($post_id), 'thumb' => $wpdb->escape($vote), 'ip' => $wpdb->escape($ip), 'user' => $wpdb->escape($user) ) );
            }
            
            if ( $this->getUpVotes( $post_id ) > 0 || $this->getDownVotes($post_id) > 0 ) {
                $output .= ' <strong> '. $this->getUpVotes( $post_id ) .'</strong> thumbs up | <strong> '. $this->getDownVotes( $post_id ) .'</strong> thumbs down.';
            } else {
                $output .= '<strong>0</strong> thumbs up | <strong>0</strong> thumbs down.';
            }
            
            // Send the output back via AJAX
            die( "document.getElementById('thumbs_results').innerHTML = '$output'" ); 
        }
        
        // Check if user has already voted, return boolean.
        function hasVoted( $post_id, $ip, $user = '' ) {           
            
            if ( $user != '' ) {
                if ( $this->hasUserVoted( $post_id, $user ) ) {
                    return true;
                }
            }
            
            if ( $this->hasIPVoted( $post_id, $ip ) ) 
                return true;
            
            return false;
        }
        
        function hasUserVoted( $post_id, $user ) {
            global $wpdb;
            
            $user = '"' . $user . '"';
            
            $result = $wpdb->get_var("SELECT count(id) FROM $this->table_name WHERE post_id = $wpdb->escape($post_id) AND user = $wpdb->escape($user)");

            if ($result > 0)
                return true;
                
            return false;
        }
        
        function hasIPVoted( $post_id, $ip ) {
            global $wpdb;
            
            $ip = '"' . $ip . '"';
            
            $result = $wpdb->get_var("SELECT count(id) FROM $this->table_name WHERE post_id = $wpdb->escape($post_id) AND ip = $wpdb->escape($ip)");

            if ($result > 0)
                return true;
                
            return false; 
        }
        
        /**
         * Return a count of the thumbs up for a post.
         *
         * @package ThumbsUpRating
         * @since 1.0
         *
         * @param    int    $post_id    The ID of the post we are counting votes for.
         * @return   int                The count of the thumbs up for the post.
         */
        function getUpVotes($post_id) {
            global $wpdb;
            
            $result = $wpdb->get_var("SELECT count(id) FROM $this->table_name WHERE post_id = $wpdb->escape($post_id) AND thumb = '1'");
            
            return $result;
        }
        
        /**
         * Return a count of the thumbs down for a post.
         *
         * @package ThumbsUpRating
         * @since 1.0
         *
         * @param    int    $post_id    The ID of the post we are counting votes for.
         * @return   int                The count of the thumbs down for the post.
         */
        function getDownVotes($post_id) {
            global $wpdb;
                       
            $result = $wpdb->get_var("SELECT count(id) FROM $this->table_name WHERE post_id = $wpdb->escape($post_id) AND thumb = '-1'");
            
            return $result;
        }
        
        function addJSHeader() {
            // use JavaScript SACK library for Ajax
            wp_print_scripts( array( 'sack' ));

            // Define custom JavaScript function
            ?>
            <script type="text/javascript">
            //<![CDATA[
            function thumbs_up_cast_vote( post_id, vote, ip, user ) {
                var mysack = new sack( 
                    "<?php bloginfo( 'wpurl' ); ?>/wp-content/plugins/thumbs-up/thumbs-up.php" );    
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "post_id", post_id );
                mysack.setVar( "vote", vote );
                mysack.setVar( "ip", ip );
                mysack.setVar( "user", user );
                mysack.onError = function() { alert('Ajax error in voting' )};
                mysack.runAJAX();
                
                return true;
            } 
            //]]>
            </script>
            <?php
        } 
        
        
    }
    
    
    // Make sure we have add_action()
    if (!function_exists('add_action')) {
    	$wp_root = '../../..';
    	if (file_exists($wp_root.'/wp-load.php')) {
    		require_once($wp_root.'/wp-load.php');
    	} else {
    		require_once($wp_root.'/wp-config.php');
    	}
    }
    
    // Our global object variable for use in the rest of the functions.
    $thumbs = new ThumbsUp();
    
    $post_id = intval($_POST['post_id']);
    $vote = intval($_POST['vote']);
    $ip = $_POST['ip'];
    $user = $_POST['user'];
    
    if ($post_id && $vote && $ip) {
        $thumbs->processVote( $post_id,$vote,$ip,$user );
    }
    
    global $thumbs;
    function thumbs_install() {
        //global $thumbs;
        $thumbs;
        $thumbs->installThumbsUp();
    }
    
    function thumbs_init() {
        global $thumbs;
        $thumbs->displayThumbs();
    }

    // When plugin is activated -> install.
    register_activation_hook(__FILE__,'thumbs_install');
    // Add the JS to the <head> for AJAXiness
    add_action( 'wp_head', array( 'ThumbsUp', 'addJSHeader' ) );
    
?>