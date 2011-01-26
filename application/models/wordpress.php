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
	var $_config = null;
	
	/**
	 * Constructor
	 */
	function Wordpress()
	{
		$this->CI =& get_instance();
		
		// Import the config settings
		$this->CI->config->load('wordpress', true);
		$this->_config = $this->CI->config->item('wordpress');
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
	 * @return  mixed
	 */
	function get_option($option)
	{
		$this->CI->db->select('option_value');
		$this->CI->db->from($this->_table_prefix.'options');
		$this->CI->db->where('option_name', $option);
		
		$query = $this->CI->db->get();
		
		if ($query->num_rows())
		{
			$row = $query->row_array();
			return $row['option_value'];
		}
		return null;
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
	 * Figures out the appropriate salt value for a hash
	 *
	 * @access  public
	 * @param   string    the scheme
	 * @return  string
	 */
	function _salt($scheme)
	{
		// Get the default key
		$secret_key = 
		
		if ($scheme == 'logged_in')
		{
			
		}
		else
		{
			
		}
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
	}
	
	
	
	
	
	
	
}

/* End of file wordpress.php */
/* Location ./application/models/wordpress.php */
