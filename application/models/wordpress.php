<?php

/*
|---------------------------------------------------------------
| WordPress-CI
|---------------------------------------------------------------
|
| A model designed for interfacing with a WordPress database.
| Built for WordPress version 3.0.4
|
| @author     James Brumond
| @version    0.1.1-dev
| @copyright  Copyright 2011 James Brumond
| @license    Dual licensed under MIT and GPL
| @requires   CodeIgniter
|
*/


if (! function_exists('hash_hmac')) :
/**
 * In case hash_hmac doesn't exist, define a replacement
 *
 * @link    http://us2.php.net/manual/en/function.hash-hmac.php#93440
 *
 * @access  global
 * @param   string    the algorithm
 * @param   string    hash data
 * @param   stirng    secret key
 * @param   bool      raw output?
 * @return  string
 */
function hash_hmac($algo, $data, $key, $raw_output = false)
{
    $algo = strtolower($algo);
    $pack = 'H'.strlen($algo('test'));
    $size = 64;
    $opad = str_repeat(chr(0x5C), $size);
    $ipad = str_repeat(chr(0x36), $size);

    if (strlen($key) > $size)
    {
        $key = str_pad(pack($pack, $algo($key)), $size, chr(0x00));
    }
    else
    {
        $key = str_pad($key, $size, chr(0x00));
    }

    for ($i = 0; $i < strlen($key) - 1; $i++)
    {
        $opad[$i] = $opad[$i] ^ $key[$i];
        $ipad[$i] = $ipad[$i] ^ $key[$i];
    }

    $output = $algo($opad.pack($pack, $algo($ipad.$data)));

    return ($raw_output) ? pack($pack, $output) : $output;
}
endif;


/**
 * The WordPress class
 */
class Wordpress extends Model {
	
	/**
	 * The master CI object
	 */
	var $CI = null;
	
	/**
	 * The table prefix
	 *
	 * @access  public
	 * @type    string
	 */
	var $_table_prefix = 'wp_';
	
	/**
	 * Logged in cookie
	 *
	 * @access  public
	 * @type    string
	 */
	var $_logged_in_cookie = false;
	
	/**
	 * The default secret key
	 *
	 * @access  public
	 * @type    string
	 */
	var $_default_secret_key = 'put your unique phrase here';
	
	/**
	 * Allow a grace period for POST requests?
	 *
	 * @access  public
	 * @type    bool
	 */
	var $_allow_grace = true;
	
	/**
	 * Config data from the wordpress.php config file
	 *
	 * @access  public
	 * @type    array
	 */
	var $_config = array(
		'logged_in_key'  => 'put your unique phrase here',
		'logged_in_salt' => 'put your unique phrase here',
		'secret_key'     => 'put your unique phrase here',
		'table_prefix'   => 'wp_',
		'blog_id'        => 0
	);
	
	/**
	 * Constructor
	 */
	function Wordpress()
	{
		$this->CI =& get_instance();
		
		// Import the config settings
		$this->CI->config->load('wordpress', true);
		$this->_config = array_merge($this->_config, $this->CI->config->item('wordpress'));
	}

// ----------------------------------------------------------------------------
	
	/**
	 * Change the table prefix
	 *
	 * @access  public
	 * @param   stirng    the new prefix
	 * @return  void
	 */
	function set_table_prefix($prefix = 'wp_')
	{
		if (is_string($prefix))
		{
			$this->_table_prefix = $prefix;
		}
	}
	
	/**
	 * Read an option
	 *
	 * @access  public
	 * @param   string    the option
	 * @param   bool      test only
	 * @return  mixed
	 */
	function get_option($option, $test = false)
	{
		$this->CI->db->select('option_value');
		$this->CI->db->from($this->_table_prefix.'options');
		$this->CI->db->where(array(
			'blog_id' => $this->_config['blog_id'],
			'option_name' => $option
		));
		
		$query = $this->CI->db->get();
		
		if ($test)
		{
			return (!! $query->num_rows());
		}
		
		if ($query->num_rows())
		{
			$row = $query->row_array();
			return $row['option_value'];
		}
		return null;
	}
	
	/**
	 * Set an option
	 *
	 * @access  public
	 * @param   string    the option
	 * @param   mixed     the new value
	 * @return  void
	 */
	function set_option($option, $value)
	{
		// It already exists, update it
		if ($this->get_option($option, $this->_config['blog_id'], true))
		{
			$this->CI->db->where(array(
				'blog_id' => $this->_config['blog_id'],
				'option_name' => $option
			));
			$this->CI->db->update($this->_table_prefix.'options', array(
				'option_value' => $value
			));
		}
		// It doesn't exist, create it
		else
		{
			$this->CI->db->insert($this->_table_prefix.'options', array(
				'blog_id' => $this->_config['blog_id'],
				'option_name' => $name,
				'option_value' => $value,
				'auto_load' => 'yes'
			));
		}
	}
	
	/**
	 * Read a post
	 *
	 * @access  public
	 * @param   int       the post id
	 * @return  object
	 */
	function get_post($id)
	{
		$this->CI->db->select('*');
		$this->CI->db->from($this->_table_prefix.'posts');
		$this->CI->db->where('ID', $id);
		
		$query = $this->CI->db->get();
		
		if ($query->num_rows())
		{
			return $query->row();
		}
	}
	
	/**
	 * Read posts, optionally by type
	 *
	 * @access  public
	 * @param   string    the types
	 * @return  array
	 */
	function get_posts($types = null)
	{
		$this->CI->db->select('*');
		$this->CI->db->from($this->_table_prefix.'posts');
		
		if (is_string($types))
		{
			$types = explode('|', $types);
			$this->CI->db->where_in('post_type', $types);
		}
		
		$query = $this->CI->db->get();
		
		$result = array();
		if ($query->num_rows())
		{
			$result = $query->result();
		}
		return $result;
	}
	
	/**
	 * Reads a user's meta data
	 *
	 * @access  public
	 * @param   int       the user id
	 * @return  array
	 */
	function get_user_meta($id)
	{
		$this->CI->db->select('meta_key, meta_value');
		$this->CI->db->from($this->_table_prefix.'usermeta');
		$this->CI->db->where('ID', $id);
		
		$query = $this->CI->db->get();
		
		if ($query->num_rows())
		{
			$result = array();
			foreach ($query->result() as $row)
			{
				$result[$row->meta_key] = $row->meta_value;
			}
			return $result;
		}
		
		return false;
	}
	
	/**
	 * Reads a user
	 *
	 * @access  public
	 * @param   int       the user id
	 * @return  object
	 */
	function get_user($id)
	{
		$meta = $this->get_user_meta($id);
		
		$this->CI->db->select('*');
		$this->CI->db->from($this->_table_prefix.'users');
		$this->CI->db->where('user_id', $id);
		
		$query = $this->CI->db->get();
		
		if ($query->num_rows())
		{
			$row = $query->row_array();
			$row['meta'] = $meta;
			return ((object) $row);
		}
		
		return false;
	}
	
	/**
	 * Reads all users into an array
	 *
	 * @access  public
	 * @return  array
	 */
	function get_all_users()
	{
		$this->CI->db->select('ID');
		$this->CI->db->from($this->_table_prefix.'users');
		
		$query = $this->CI->db->get();
		
		$result = array();
		if ($query->num_rows())
		{
			foreach ($query->result() as $row)
			{
				$result[] = $this->get_user($row->id);
			}
		}
		return $result;
	}
	
	/**
	 * Reads data about the current user
	 *
	 * @access  public
	 * @return  object
	 */
	function get_current_user()
	{
		return $this->_validate_auth_cookie();
	}
	
	/**
	 * Sets the current user by ID
	 *
	 * @access  public
	 * @param   int       the id
	 * @return  bool
	 */
	function set_current_user_by_id($id)
	{
		// ...
	}
	
	/**
	 * Sets the current user by username
	 *
	 * @access  public
	 * @param   string    the username
	 * @return  bool
	 */
	function set_current_user_by_username($username)
	{
		// ...
	}

// ----------------------------------------------------------------------------
	
	/**
	 * A fake apply_filters clone
	 *
	 * Some way of doing this may be come up with at some point, but
	 * for the time being, any filters in the WP install will break
	 * functionality.
	 *
	 * @access  public
	 * @param   string    the filter
	 * @param   string    the value to filter
	 * @return  string
	 */
	function _apply_filters($filter, $str)
	{
		return $str;
	}
	
	/**
	 * A random number generator
	 *
	 * WP uses a big, ugly, complex generator, but seeing as "random"
	 * values do not have to be reproducable, I am simply going to
	 * use mt_rand.
	 *
	 * @access  public
	 * @param   int       the start
	 * @param   int       the end
	 * @return  int
	 */
	function _rand($min, $max)
	{
		return mt_rand($min, $max);
	}
	
	/**
	 * Generates a password string
	 *
	 * @access  public
	 * @param   int       the length
	 * @param   bool      use special chars?
	 * @param   bool      use extra special chars?
	 * @return  string
	 */
	function _generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
	{
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		
		if ($special_chars)
		{
			$chars .= '!@#$%^&*()';
		}
		
		if ($extra_special_chars)
		{
			$chars .= '-_ []{}<>~`+=,.;:/?|';
		}
		
		$password = '';
		$char_count = count($chars);
		for ($i = 0; $i < $length; $i++)
		{
			$password .= $chars[$this->_rand(0, $char_count - 1)];
		}
		
		return $this->_apply_filters('random_password', $password);
	}
	
	/**
	 * Figures out the appropriate salt value for a hash
	 *
	 * @access  public
	 * @param   string    the scheme
	 * @return  string
	 */
	function _salt($scheme)
	{
		$C = $this->_config;
		
		// Get the default hashing key
		$secret_key = '';
		if ($C['secret_key'] != '' && $C['secret_key'] != $this->_default_secret_key)
		{
			$secret_key = $C['secret_key'];
		}
		
		if ($scheme == 'logged_in')
		{
			// Override the default key
			if ($C['logged_in_key'] != '' && $C['logged_in_key'] != $this->_default_secret_key)
			{
				$secret_key = $logged_in_key;
			}
			
			// Get the salt value
			if ($C['logged_in_salt'] != '' && $C['logged_in_salt'] != $this->_default_secret_key)
			{
				$salt = $C['logged_in_salt'];
			}
			else
			{
				$salt = $this->get_option('logged_in_salt');
				if (empty($salt))
				{
					$salt = $this->_generate_password(64, true, true);
					$this->set_option('logged_in_salt', $salt);
				}
			}
		}
		else
		{
			$salt = hash_hmac('md5', $scheme, $secret_key);
		}
		
		return $this->_apply_filters('salt', $secret_key.$salt, $scheme);
	}
	
	/**
	 * Hash a string with hash_hmac
	 *
	 * @access  public
	 * @param   string    the string to hash
	 * @param   string    the salt scheme
	 * @return  string
	 */
	function _hash($str, $scheme = 'logged_in')
	{
		$salt = $this->_salt($scheme);
		return hash_hmac('md5', $str, $salt);
	}
	
	/**
	 * Defines the logged in cookie
	 *
	 * @access  public
	 * @return  void
	 */
	function _define_logged_in_cookie()
	{
		$site_url = $this->get_option('site_url');
		$cookie_hash = $site_url ? md5($site_url) : '';
		$this->_logged_in_cookie = 'wordpress_logged_in_'.$cookie_hash;
	}
	
	/**
	 * Parse a WordPress auth cookie
	 *
	 * @access  public
	 * @return  string
	 */
	function _parse_auth_cookie()
	{
		if (! $this->_logged_in_cookie)
		{
			$this->_define_logged_in_cookie();
		}
		
		// Read the cookie value
		$cookie = $this->CI->input->cookie($this->_logged_in_cookie, true);
		if (! $cookie)
		{
			return false;
		}
		
		// Seperate the cookie into segments
		$segments = explode('|', $cookie);
		if (count($segments) != 3)
		{
			return false;
		}
		
		return array_combine(array('username', 'expiration', 'hmac'), $segments);
	}
	
	/**
	 * Tests for a valid auth cookie
	 *
	 * @access  public
	 * @return  int
	 */
	function _validate_auth_cookie()
	{
		// Get the cookie segments
		if (! $segments = $this->_parse_auth_cookie())
		{
			return false;
		}
		extract($segments, EXTR_OVERWRITE);
		
		// Allow POST requests a grace period
		$expired = $expiration;
		if ($this->_allow_grace && $this->CI->input->server('REQUEST_METHOD'))
		{
			$expired += 3600;
		}
		
		// Check for cookie expiration
		if ($expired < time())
		{
			return false;
		}
		
		// Read the user data
		if (! $user = $this->get_user($username))
		{
			return false;
		}
		
		// Build authentication data
		$pass_frag = substr($user->user_pass, 8, 4);
		$key = $this->_hash($username.$pass_frag.'|'.$expiration, 'logged_in');
		$hash = hash_hmac('md5', $username.'|'.$expiration, $key);
		
		// Test for user authenticity
		if ($hash != $hmac)
		{
			return false;
		}
		
		return $user;
	}
	
	/**
	 * Generate a new auth cookie for a user
	 *
	 * @access  public
	 * @param   mixed     the user/user id
	 * @return  string
	 */
	function _generate_auth_cookie($user)
	{
		// Make sure we have a user object
		if (! is_object($user))
		{
			if (! $user = $this->get_user($user))
			{
				return false;
			}
		}
		
		// Generate authentication data
		$pass_frag = substr($user->user_pass, 8, 4);
		$key = $this->_hash($user->user_login.$pass_frag.'|'.$expiration, $scheme);
		$hash = hash_hmac('md5', $user->user_login.'|'.$expiration, $key);
		
		// Build the cookie string
		$cookie = $user->user_login.'|'.$expiration.'|'.$hash;
		return $this->_apply_filters('auth_cookie', $cookie, $user->ID, $expiration, $scheme);
	}
	
	
	
	
	
	
	
}

/* End of file wordpress.php */
/* Location ./application/models/wordpress.php */
