<?php

define('MM_WS_MYSQL_DATE_TIME', 'Y-m-d H:i:s');
define('MM_WS_STUDENT_LOG_SCHEMA_FILE', 'student-log.sql');

class MailManager_WebService
{
  private $email_lookup_connection = null;
  private $audit_log_connection = null;
  private $student_log_connection = null;
  
  private $student_username;
  private $student_password;
  private $student_host;
  private $student_dbname;
  
  private $student_email_address;
  
  public function __construct($db_config)
  {
    $this->authenticate();
	$this->validate();
    $this->open_connections();
	$this->set_student_email_address();
  }
  
  private function authenticate()
  {
    $this->student_username = isset($_POST['username']) ? trim($_POST['username']) : null;
	$this->student_password = isset($_POST['password']) ? trim($_POST['password']) : null;
	$this->student_host = isset($_POST['host']) ? trim($_POST['host']) : null;
	$this->student_dbname = isset($_POST['dbname']) ? trim($_POST['dbname']) : null;
	
	// Don't even try to authenticate if we are missing a username/password combination
	if (empty($this->student_username) || empty($this->student_password))
	{
	  throw new Exception('Could not authenticate student');
	}
	
	$this->student_log_connection = new mysqli($this->student_host, $this->student_username, $this->student_password, $this->student_dbname);
	
	if ($this->student_log_connection->connect_error)
	{
	  throw new Exception('Could not authenticate student');
	}
  }
  
  /**
   * Create the student copy of the log table if it does not already
   * exist. This is similar to the audit log, but can be accessed by the
   * student and so cannot be used for auditing.
   */
  private function create_student_log_table()
  {
    // First check if table exists
    $sql = 'SELECT id FROM mail_message_log';
    $result = $this->student_log_connection->query($sql);

    // Table does not exist, so create it
    if ($result === FALSE)
    {
      $schema = file_get_contents(MM_WS_STUDENT_LOG_SCHEMA_FILE);

      if (!empty($schema))
      {
        $result = $this->student_log_connection->query($schema);
      }
    }
  }
  
  private function validate()
  {
    
  }
  
  private function open_connections()
  {
    $this->email_lookup_connection = new mysqli($db_config['email_lookup']['host'], $db_config['email_lookup']['username'], $db_config['email_lookup']['password'], $db_config['email_lookup']['dbname']);
	
	if ($this->email_lookup_connection->connect_error)
	{
	  throw new Exception('Could not establish email lookup connection');
	}
	
	$this->audit_log_connection = new mysqli($db_config['audit_log']['host'], $db_config['audit_log']['username'], $db_config['audit_log']['password'], $db_config['audit_log']['dbname']);
	
	if ($this->audit_log_connection)
	{
	  throw new Exception('Could not establish audit log connection');
	}
  }
  
  private function get_current_date_time()
  {
    return date(MM_WS_MYSQL_DATE_TIME);
  }
  
  /**
   * Set student email address based on their username. This involves a simple
   * database lookup, although we may be able to replace this with LDAP at a later
   * date.
   */
  private function set_student_email_address()
  {
    $sql = 'SELECT email FROM users WHERE username = ? LIMIT 1';
	$statement = $this->email_lookup_connection->prepare($sql);
	$statement->bind_param('s', $this->student_username);
	$statement->execute();
	
	$result = $statement->get_result();
	
	if ($result !== FALSE)
	{
	  if ($result->num_rows === 1)
	  {
	    $data = $result->fetch_assoc();
		
		if (is_array($data) && isset($data['email']) && !empty($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL))
		{
		  $this->student_email_address = $data['email'];
		}
		else
		{
		  throw new Exception('Could not find student email address');
		}
	  }
	  else
	  {
	    throw new Exception('Could not find student email address');
	  }
	}
	else
	{
	  throw new Exception('Could not find student email address');
	}
  }
}