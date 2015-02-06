<?php

/**
 * WebLoginPE
 * A progressively enhanced (PE) user management and login snippet for MODx
 * v1.4.0
 *
 * @package WebLoginPE
 * @author Scotty Delicious scottydelicious@gmail.com
 * @access public
 * @copyright ©2007-2008 Scotty Delicious http://scottydelicious.com
 */

if (!class_exists('newChunkie')) {
    include WLPE_BASE_PATH . 'classes/newchunkie.class.php';
}

class WebLoginPE
{
    /**
     * An array of language specific phrases.
     *
     * @var array
     * @access public
     * @see __construct()
     */
    var $Language;

    /**
     * Holds a message if one was generated.
     *
     * @var string
     * @access public
     * @see FormatMessage()
     */
    var $Report;

    /**
     * A comma separated list of MODx document IDs to attempt to redirect the user to after login.
     *
     * @var string
     * @access public
     * @see login()
     * @see LoginHomePage()
     */
    var $liHomeId;

    /**
     * The MODx document ID to redirect the user to after logout.
     *
     * @var string
     * @access public
     * @see logout()
     * @see LogoutHomePage()
     */
    var $loHomeId;

    /**
     * the type of WebLoginPE (simple, register, profile, or taconite).
     *
     * @var string
     * @access protected
     */
    var $Type;

    /**
     * Value of $_POST['username'].
     *
     * @var string
     * @access protected
     */
    var $Username;

    /**
     * Value of $_POST['password'].
     *
     * @var string
     * @access protected
     */
    var $Password;

    /**
     * The user object assembled from data queried from web_users and web_user_attributes tables.
     *
     * @var array
     * @access protected
     * @see QueryDbForUser()
     */
    var $User;

    /**
     * Template for messages returned by WebLoginPE.
     *
     * @var string;
     * @access public
     * @see FormatMessage;
     */
    var $MessageTemplate;

    /**
     * Number of failed logins.
     *
     * @var string
     * @access protected
     * @see Authenticate
     */
    var $LoginErrorCount;

    /**
     * Full table name of the custom extended user attributes table.
     *
     * @var string
     * @access protected
     * @see CustomTable
     */
    var $CustomTable;

    /**
     * An array of column names for the extended user attributes table.
     *
     * @var array
     * @access protected
     * @see CustomTable
     */
    var $CustomFields;

    /**
     * Internal placeholder.
     *
     * @var array
     * @access protected
     * @see CustomTable
     */
    var $Placeholder;

    /**
     * The MODX document parser object.
     *
     * @var object
     * @access protected
     */
    var $modx;

    /**
     * Class Options.
     *
     * @var array
     * @access protected
     */
    var $Options;

    /**
     * WebLoginPE Class Constructor
     *
     * @param array $LanguageArray An array of language specific strings.
     * @return void
     * @author Scotty Delicious
     */
    function __construct($modx, $options)
    {
        $this->modx = & $modx;
        if (!class_exists('PHPMailer')) {
            include(MODX_BASE_PATH . 'manager/includes/controls/class.phpmailer.php');
        }
        $this->Language = $options['language'];
        $this->Type = isset($options['type']) ? $options['type'] : 'simple';
        $this->Placeholder = array('lang' => $this->Language);
        $this->Options['userImageSettings'] = isset($options['userImageSettings']) ? $options['userImageSettings'] : '105000,100,100'; // Dimensions for the user image
        $this->Options['dateFormat'] = isset($options['dateFormat']) ? $options['dateFormat'] : '%A %B %d, %Y at %I:%M %p'; // PHP strftime() format for dates in placeholders
        $this->Options['paging'] = isset($options['paging']) ? intval($options['paging']) : 3000; // Number of items listed on one page
        $this->Options['templateFolder'] = isset($options['templateFolder']) ? $options['templateFolder'] : 'default'; // Name of the template folder
        $this->Options['currentLocale'] = setlocale(LC_TIME, 0);
        $this->Options['locale'] = isset($this->Language['locale']) ? $this->Language['locale'] : 'en_US.UTF-8';
        $this->Options['dobFormat'] = isset($options['dobFormat']) ? $options['dobFormat'] : '%m-%d-%Y';
    }

    /**
     * Reference to the construct method (for PHP4 compatibility)
     *
     * @see __construct
     */
    function WebLoginPE($modx, $options)
    {
        $this->__construct($modx, $options);
    }

    function setPlaceholder($key, $value)
    {
        $this->Placeholder[$key] = $value;
    }

    function replacePlaceholder($template)
    {
        $chunkie = new newChunkie($this->modx, array('basepath' => WLPE_PATH));
        $chunkie->setPlaceholder('', $this->Placeholder);
        $chunkie->setTpl($template);
        $chunkie->prepareTemplate('', array());
        return $chunkie->process();
    }

    /**
     * FormatMessage
     * Sets a value for $this->Report which is returned to the page if there is an error.
     * This function is public and can be used to format a message for the calling script.
     *
     * @param string $message
     * @return void
     * @author Scotty Delicious
     */
    function FormatMessage($message = 'There was an error', $messageclass = 'error')
    {
        unset($this->Report);
        $messageTemplate = str_replace(array('[+wlpe.message.text+]', '[+wlpe.message.class+]'), array($message, $messageclass), $this->MessageTemplate);
        $this->Report = $messageTemplate;
        $this->setPlaceholder('wlpe.message', $messageTemplate);
        unset($messageTemplate);
        return;
    }

    /**
     * login
     * Perform all the necessary functions to establish a secure user session with permissions
     *
     * @param string $type If type = 'taconite' do not call $this->LoginHomePage().
     * @param string $liHomeId Comma separated list of MODx document ID's to attempt to redirect to after login.
     * @return void
     * @author Scotty Delicious
     */
    function Login($type, $liHomeId = '')
    {
        $this->Type = $type;
        $this->liHomeId = $liHomeId;

        $this->Username = $this->modx->db->escape(strip_tags($_POST['username']));
        $this->Password = $this->modx->db->escape(strip_tags($_POST['password']));
        if ($this->Username == '' || $this->Password == '') {
            $this->FormatMessage($this->Language['error_login_fields_blank'], 'error');
            return;
        }
        $_SESSION['groups'] = array('Registered Users', 'Fans');
        $this->OnBeforeWebLogin();
        $this->User = $this->QueryDbForUser($this->Username);

        if ($this->User == false) {
            $this->FormatMessage($this->Language['error_invalid_username'], 'error');
            return;
        }

        $this->UserIsBlocked();
        $this->Authenticate();
        $this->SessionHandler('start');
        $this->OnWebLogin();
        $this->ActiveUsers();
        $this->UserDocumentGroups();
        if ($type !== 'taconite') {
            $this->LoginHomePage();
        }
    }

    /**
     * AutoLogin checks for a user cookie and logs the user in automatically
     *
     * @return void
     * @author Scotty Delicious
     */
    function AutoLogin()
    {
        $cookieName = 'WebLoginPE';

        $cookie = explode('|', $_COOKIE[$cookieName]);
        $this->Username = $cookie[0];
        $this->Password = $cookie[1];

        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');

        $query = "SELECT * FROM " . $web_users . ", " . $web_user_attributes . " WHERE MD5(" . $web_users . ".`username`) = '" . $this->Username . "' AND " . $web_user_attributes . ".`internalKey` = " . $web_users . ".`id`";
        $dataSource = $this->modx->db->query($query);
        $limit = $this->modx->db->getRecordCount($dataSource);
        if ($limit == 0 || $limit > 1) {
            $this->User = null;
            return false;
        } else {
            $this->User = $this->modx->db->getRow($dataSource);
            $this->Username = $this->User['username'];
        }

        if ($this->User['password'] !== $this->Password) {
            return false;
        }

        $this->UserIsBlocked();
        $this->Authenticate();
        $this->SessionHandler('start');
        $this->UserDocumentGroups();
        $this->LoginHomePage();
    }

    /**
     * logout
     * Destroy the user session and redirect or refresh.
     *
     * @param string $type If type = 'taconite' do not call $this->LogoutHomePage().
     * @param int $loHomeId MODx document ID to redirect to after logout.
     * @return void
     * @author Scotty Delicious
     */
    function Logout($type, $loHomeId = '')
    {
        $logoutparameters = array(
            'username' => $_SESSION['webShortname'],
            'internalKey' => $_SESSION['webInternalKey'],
            'userid' => $_SESSION['webInternalKey']
        ); // SMF connector fix http://forums.modx.com/thread/48913/smf-connector-webloginpe-was-broken-now-fixed c/o tazzydemon

        $this->Type = $type;
        $this->loHomeId = $loHomeId;

        $this->OnBeforeWebLogout();
        $this->StatusToOffline();
        $this->SessionHandler('destroy');
        $this->OnWebLogout($logoutparameters); // SMF connector fix http://forums.modx.com/thread/48913/smf-connector-webloginpe-was-broken-now-fixed c/o tazzydemon and binary_trust
        if ($type !== 'taconite') {
            $this->LogoutHomePage();
        }
    }

    /**
     * Custom table checks for the specified extended user attributes table and creates it if it does not exist.
     * It also checks for custom column names and inserts them into the extended user attributes table if they do not exist.
     *
     * @param string $table The name of the custom table (Default is "web_user_attributes_extended")
     * @param string $fields A comma separated list of column names for the custom table.
     * @return void
     * @author Scotty Delicious
     */
    function CustomTable($table, $fields, $prefixTable = 1, $tableCheck = 1)
    {
        $allTables = array();

        if ($prefixTable == 0) {
            $tableFull = '`' . $table . '`';
            $table = $table;
        } else {
            $tableFull = $this->modx->getFullTableName($table);
            $table = explode('.', $tableFull);
            $table = str_replace('`', '', $table[1]);
        }

        if ($fields !== '') {
            $fields = array_map('trim', explode(',', $fields));
        }

        $this->CustomTable = $tableFull;
        $this->CustomFields = array();
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (strpos($field, '|')) {
                    list($name, $type) = explode('|', $field);
                    switch (strtolower($type)) {
                        case 'text':
                            $type = 'TEXT';
                            break;
                        case 'boolean':
                            $type = 'TINYINT (1)';
                            break;
                        case 'int':
                            $type = 'INT (10)';
                            break;
                        case 'date':
                            $type = 'DATETIME';
                            break;
                        case 'unixtime':
                            $type = 'TIMESTAMP';
                            break;
                        case 'varchar':
                        default:
                            $type = 'VARCHAR(255)';
                            break;
                    }
                } else {
                    $name = $field;
                    $type = 'VARCHAR(255)';
                }
                $customField = new stdClass();
                $customField->name = trim($name);
                $customField->type = $type;
                $this->CustomFields[] = $customField;
            }
        }

        if ($tableCheck == 1) {
            // Check if custom table exists. If it does not, create it with default values.
            $tableNames = $this->modx->db->query("SHOW TABLES");
            while ($eachTable = $this->modx->db->getRow($tableNames, 'num')) {
                $allTables[] = $eachTable[0];
            }
            if (!in_array($table, $allTables)) {
                $createTable = $this->modx->db->query("CREATE TABLE IF NOT EXISTS " . $this->CustomTable . " (id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, internalKey INT(10) NOT NULL)");
                if (!$createTable) {
                    return $this->FormatMessage($this->modx->db->getLastError, 'error');
                }
                $addIndex = $this->modx->db->query("ALTER TABLE " . $this->CustomTable . " ADD INDEX `userid` ( `internalKey` )");
            }

            // Create the additional MODx events if they do not exist
            $system_eventnames = $this->modx->getFullTableName('system_eventnames');
            $newEvents = array('OnBeforeWebSaveUser', 'OnBeforeAddToGroup', 'OnViewUserProfile');
            foreach ($newEvents as $aNewEvent) {
                $findEvent = $this->modx->db->query("SELECT * FROM " . $system_eventnames . " WHERE `name` = '" . $aNewEvent . "'");
                $limit = $this->modx->db->getRecordCount($findEvent);
                if ($limit == 0) {
                    $addEvent = $this->modx->db->query("INSERT INTO " . $system_eventnames . " (`name`,`service`) VALUES ('" . $aNewEvent . "', 3)");
                }
            }
        }

        // Check if custom fields exist in custom table. If they do not, create them.
        if (!empty($this->CustomFields)) {
            $columns = $this->modx->db->query("SELECT * FROM " . $this->CustomTable);
            $columnNames = $this->modx->db->getColumnNames($columns);
            foreach ($this->CustomFields as $field) {
                if (!in_array($field->name, $columnNames)) {
                    $addColumn = $this->modx->db->query("ALTER TABLE " . $this->CustomTable . " ADD (`" . $field->name . "` " . $field->type . " NOT NULL)");
                }
            }
        }
    }

    /**
     * register
     * Inserts a new user into web_users and web_user_attributes.
     *
     * @param string $regType 'instant' or 'verify'
     * @param string $groups which webgroup('s) should the new user be added to.
     * @param string $regRequired Comma separated list of required fields.
     * @param string $notify Comma separated list of emails to notify of new registrations.
     * @param string $notifyTpl Template for email notification message.
     * @param string $notifySubject Subject line for email notification.
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function Register($regType, $groups, $regRequired, $notify, $notifyTpl, $notifySubject, $approvedDomains = '', $pendingGroups = '')
    {
        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');
        $web_groups = $this->modx->getFullTableName('web_groups');
        $webgroup_names = $this->modx->getFullTableName('webgroup_names');

        $username = $_POST['username'];
        $this->Username = $username;
        $password = $this->modx->db->escape($this->modx->stripTags($_POST['password']));
        $passwordConfirm = $this->modx->db->escape($this->modx->stripTags($_POST['password_confirm']));
        $fullname = $this->modx->db->escape($this->modx->stripTags($_POST['fullname']));
        $email = $this->modx->db->escape($this->modx->stripTags($_POST['email']));
        $phone = $this->modx->db->escape($this->modx->stripTags($_POST['phone']));
        $mobilephone = $this->modx->db->escape($this->modx->stripTags($_POST['mobilephone']));
        $fax = $this->modx->db->escape($this->modx->stripTags($_POST['fax']));
        $dob = $this->modx->db->escape($this->modx->stripTags($_POST['dob']));
        $gender = $this->modx->db->escape($this->modx->stripTags($_POST['gender']));
        $country = $this->modx->db->escape($this->modx->stripTags($_POST['country']));
        $state = $this->modx->db->escape($this->modx->stripTags($_POST['state']));
        $zip = $this->modx->db->escape($this->modx->stripTags($_POST['zip']));
        $comment = $this->modx->db->escape($this->modx->stripTags($_POST['comment']));
        $cachepwd = time();

        // Check for required fields.
        if ($_POST['username'] == '' || empty($_POST['username']) || trim($_POST['username']) == '') { // pixelchutes
            return $this->FormatMessage($this->Language['error_required_fields_blank'], 'error');
        }
        if (strlen($_POST['email']) > 0) { // pixelchutes
            // Validate the email address.
            $this->ValidateEmail($_POST['email']);
            if (!empty($this->Report)) {
                return $this->report;
            }
        }
        if ($regRequired !== '') {
            $requiredFields = explode(',', str_replace(' ,', ',', $regRequired));
            foreach ($requiredFields as $field) {
                if ($field == 'formcode') {
                    $formcode = $_POST['formcode'];
                    if ($_SESSION['veriword'] !== $formcode) {
                        return $this->FormatMessage($this->Language['error_invalid_captcha_code'], 'error');
                    }
                }

                if ($field == 'email') {
                    // Validate the email address.
                    $this->ValidateEmail($_POST['email']);
                    if (!empty($this->Report)) {
                        return $this->report;
                    }
                }

                if ($_POST[$field] == '' || empty($_POST[$field])) {
                    if ($field == 'tos') {
                        return $this->FormatMessage($this->Language['error_agree_tos'], 'error');
                    }

                    return $this->FormatMessage($this->Language['error_required_fields_blank'], 'error');
                }
            }
        }

        // Check username for invalid characters
        if (!ctype_alnum($username)) {
            return $this->FormatMessage($this->Language['error_username_invalid_charakters'], 'error');
        }

        // Check username length
        if (strlen($username) > 100) {
            return $this->FormatMessage($this->Language['error_username_length'], 'error');
        }

        // Check for arrays and that "confirm" fields match.
        $fieldMessage = '';
        foreach ($_POST as $field => $value) {
            if (is_array($_POST[$field])) {
                $_POST[$field] = implode('||', $_POST[$field]);
            }

            $confirm = $field . '_confirm';
            if (isset($_POST[$confirm])) {
                if ($_POST[$field] !== $_POST[$confirm]) {
                    $error = $this->Language['error_fields_not_match'] . ' <br />';
                    $fieldMessage .= str_replace('[+000+]', '"' . $field . '"', $error);
                }
            }
        }

        // If confirm fields were mismatched, throw this error:
        if (!empty($fieldMessage)) {
            $err = $fieldMessage;
            unset($fieldMessage);
            return $this->FormatMessage($err, 'error');
        }

        // Check Password locally
        if ($regType == 'instant') {
            if (strlen($password) < 6) {
                return $this->FormatMessage($this->Language['error_password_too_short'], 'error');
            }

            if (empty($password) || $password == '') {
                return $this->FormatMessage($this->Language['error_password_too_short'], 'error');
            }

            if (md5($password) !== md5($_POST['password'])) {
                return $this->FormatMessage($this->Language['error_password_illegal_characters'], 'error');
            }
        }

        $checkUsername = $this->modx->db->query("SELECT `id` FROM " . $web_users . " WHERE `username`='" . $username . "'");
        $limit = $this->modx->recordCount($checkUsername);

        if ($limit > 0) {
            return $this->FormatMessage($this->Language['error_username_in_use'], 'error');
        }

        $lowercase = strtolower(str_replace(' ', '_', $username));
        if ($lowercase == 'default_user') {
            return $this->FormatMessage($this->Language['error_username_in_use'], 'error');
        }

        $checkEmail = $this->modx->db->query("SELECT * FROM " . $web_user_attributes . " WHERE `email`='" . $email . "'");
        $limit = $this->modx->recordCount($checkEmail);

        if ($limit > 0) {
            return $this->FormatMessage($this->Language['error_email_in_use'], 'error');
        }

        // If you want to verify your users email address before letting them log in, this generates a random password.
        if ($regType == 'verify' || $regType == 'pending') {
            $password = $this->GeneratePassword(10);
        }

        // Create the user image if necessary.
        if (!empty($_FILES['photo']['name'])) {
            $photo = $this->CreateUserImage();
            if (!empty($this->Report)) {
                return;
            }
        } else {
            $photo = $this->modx->config['site_url'] . 'assets/snippets/webloginpe/userimages/default_user.jpg';
        }

        // EVENT: OnBeforeWebSaveUser
        foreach ($_POST as $name => $value) {
            $NewUser[$name] = $value;
        }
        $this->OnBeforeWebSaveUser($NewUser, array());

        // If all that crap checks out, now we can create the account.
        $newUser = "INSERT INTO " . $web_users . " (`username`, `password`, `cachepwd`) VALUES ('" . $username . "', '" . md5($password) . "', '" . $cachepwd . "')";
        $createNewUser = $this->modx->db->query($newUser);

        if (!$createNewUser) {
            return $this->FormatMessage($this->Language['error_register_account'], 'error');
        }

        $key = $this->modx->db->getInsertId();
        $NewUser['internalKey'] = $key; // pixelchutes

        $newUserAttr = "INSERT INTO " . $web_user_attributes .
            " (internalKey, fullname, email, phone, mobilephone, dob, gender, country, state, zip, fax, photo, comment) VALUES" .
            " ('" . $key . "', '" . $fullname . "', '" . $email . "', '" . $phone . "', '" . $mobilephone . "', '" . $dob . "', '" . $gender . "', '" . $country . "', '" . $state . "', '" . $zip . "', '" . $fax . "', '" . $photo . "', '" . $comment . "')";
        $insertUserAttr = $this->modx->db->query($newUserAttr);

        if (!$insertUserAttr) {
            return $this->FormatMessage($this->Language['error_save_account'], 'error');
        }

        if (!empty($this->CustomFields)) {
            $extendedFields = array();
            $extendedFieldValues = array();
            foreach ($this->CustomFields as $field) {
                $extendedFields[$field->name] = $field->name;
                $extendedFieldValues[$field->name] = $this->modx->db->escape($_POST[$field->name]);
            }
            $extendedFieldValues = implode("', '", $extendedFieldValues);
            $extendedFields = implode('`, `', $extendedFields);
            $extendedUserAttr = "INSERT INTO " . $this->CustomTable . " (`internalKey`, `" . $extendedFields . "`) VALUES ('" . $key . "', '" . $extendedFieldValues . "')";
            $insertExtendedAttr = $this->modx->db->query($extendedUserAttr);

            if (!$insertExtendedAttr) {
                return $this->FormatMessage($this->Language['error_save_account'], 'error');
            }
        }

        // Set group to pending
        if ($regType == 'pending') {
            $groups = $pendingGroups;
        }

        // Set group for auto approved domains
        if (!empty($approvedDomains)) {
            $domainSets = explode("\|\|", $approvedDomains);
            $userEmail = explode("@", $email);
            foreach ($domainSets as $set) {
                $set = explode(":", $set);
                $domains = explode(",", $set[0]);
                $group = $set[1];
                if (in_array($userEmail[1], $domains)) {
                    $groups = $group;
                    $regType = 'verify';
                }
            }
        }

        $groups = str_replace(', ', ',', $groups);
        $GLOBALS['groupsArray'] = explode(',', $groups);

        // EVENT: OnBeforeAddToGroup
        $this->OnBeforeAddToGroup($GLOBALS['groupsArray']);
        if (count($GLOBALS['groupsArray'] > 0)) {
            $groupsList = "'" . implode("','", $GLOBALS['groupsArray']) . "'";
            $groupNames = $this->modx->db->query("SELECT `id` FROM " . $webgroup_names . " WHERE `name` IN (" . $groupsList . ")");
            if (!$groupNames) {
                return $this->FormatMessage($this->Language['error_update_webgroups'], 'error');
            } else {
                while ($row = $this->modx->db->getRow($groupNames)) {
                    $webGroupId = $row['id'];
                    $this->modx->db->query("REPLACE INTO " . $web_groups . " (`webgroup`, `webuser`) VALUES ('" . $webGroupId . "', '" . $key . "')");
                }
            }
        }

        // EVENT: OnWebSaveUser
        $this->OnWebSaveUser('new', $NewUser);
        if ($regType != 'pending') {
            // Replace some placeholders in the Config websignupemail message.
            $messageTpl = $this->modx->config['websignupemail_message'];
            $myEmail = $this->modx->config['emailsender'];
            $emailSubject = $this->modx->config['emailsubject'];
            $siteName = $this->modx->config['site_name'];
            $siteURL = $this->modx->config['site_url'];
            $now = new DateTime();
            $showToday = $now->format('d.m.Y');

            $message = str_replace('[+uid+]', $username, $messageTpl);
            $message = str_replace('[+pwd+]', $password, $message);
            $message = str_replace('[+ufn+]', $fullname, $message);
            $message = str_replace('[+sname+]', $siteName, $message);
            $message = str_replace('[+semail+]', $myEmail, $message);
            $message = str_replace('[+surl+]', $siteURL, $message);
            // dg@visions.ch 2014-05-20: Heutiges Datum als Platzhalter mitgeben

            //$message = str_replace('[+today+]', $showToday, $message)
            foreach ($_POST as $name => $value) {
                $toReplace = '[+post.' . $name . '+]';
                $message = str_replace($toReplace, $value, $message);
            }

            // Bring in php mailer!
            $Register = new PHPMailer();
            
            // enable smtp via modx configuration
            if ($modx->config['email_method'] == 'smtp')
            {
              $Register->IsSMTP(); // telling the class to use SMTP             
              $Register->SMTPAuth = true;                  
              $Register->Host = $modx->config['smtp_host']; //host from modx configuration
              $Register->Port = $modx->config['smtp_port']; //port from modx configuration
              $Register->Username = $modx->config['smtp_username'];  //user from modx configuration
              $passsmtp = $modx->config['smtppw']; //encoded password from modx configuration
              $passsmtp = base64_decode(substr($passsmtp, 0, strpos($passsmtp, '%')) . '=');
              $Register->Password = $passsmtp;    
            }
            
            $Register->CharSet = $this->modx->config['modx_charset'];
            $Register->From = $myEmail;
            $Register->FromName = $siteName;
            $Register->Subject = $emailSubject;
            $Register->Body = $message;
            // dg@visions.ch 2014-05-20:
            $Register->ContentType = 'text/html';
            $Register->IsHTML(true);
            $Register->MsgHTML($message);
            $Register->AddAddress($email, $fullname);

            if (!$Register->Send()) {
                return $this->FormatMessage($this->Language['error_sending_email'], 'error');
            }
        }

        // Add the list of administrators to be notified on new registration to a Blind Carbon Copy.
        if (isset($notify) && $notify !== '') {
            $notify = ($notify == 'default') ? $this->modx->config['emailsender'] : $notify;
            $emailList = str_replace(', ', ',', $notify);
            $emailArray = explode(',', $emailList);

            $notification = str_replace('[+uid+]', $username, $notifyTpl);
            $notification = str_replace('[+ufn+]', $fullname, $notification);
            $notification = str_replace('[+sname+]', $siteName, $notification);
            $notification = str_replace('[+semail+]', $myEmail, $notification);
            $notification = str_replace('[+surl+]', $siteURL, $notification);
            $notification = str_replace('[+uem+]', $email, $notification);
            foreach ($_POST as $name => $value) {
                $toReplace = '[+post.' . $name . '+]';
                $notification = str_replace($toReplace, $value, $notification);
            }
            // Cleanup any unused placeholders
            $notification = preg_replace('#\[\+post\.+[a-zA-Z]+\+\]#', '', $notification);

            $Notify = new PHPMailer();
            $Notify->CharSet = $this->modx->config['modx_charset'];
            
            // enable smtp via modx configuration
            if ($modx->config['email_method'] == 'smtp')
            {
              $Notify->IsSMTP(); // telling the class to use SMTP             
              $Notify->SMTPAuth   = true;                  
              $Notify->Host = $modx->config['smtp_host']; //host from modx configuration
              $Notify->Port = $modx->config['smtp_port']; //port from modx configuration
              $Notify->Username = $modx->config['smtp_username'];  //user from modx configuration
              $passsmtp = $modx->config['smtppw']; //encoded password from modx configuration
              $passsmtp = base64_decode(substr($passsmtp, 0, strpos($passsmtp, '%')) . '=');
              $Notify->Password   = $passsmtp; 
            }		

            foreach ($emailArray as $address) {
                $Notify->From = $email;
                $Notify->FromName = $fullname;
                $Notify->Subject = $notifySubject;
                // dg@visions.ch 2014-05-20:
                $Notify->ContentType = 'text/html';
                $Notify->IsHTML(true);
                $Notify->MsgHTML($notification);

                //				$Notify->Body = $notification;
                $Notify->AddAddress($address);
                if (!$Notify->Send()) {
                    return $this->FormatMessage($Notify->ErrorInfo, 'error');
                }
                $Notify->ClearAddresses();
            }
        }
        $this->SessionHandler('destroy');
        $this->FormatMessage($this->Language['message_account_created'] . ' ' . $this->modx->config['site_name'], 'success');
        return 'success';
    }

    /**
     * PruneUsers will remove non-activated user accounts older than the number of days specified in $pruneDays.
     *
     * @param int $pruneDays The number of days to wait before removing non-activated users.
     * @return void
     * @author Scotty Delicious
     */
    function PruneUsers($pruneDays)
    {
        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');
        $web_groups = $this->modx->getFullTableName('web_groups');

        $findDeadAccounts = $this->modx->db->query("SELECT * FROM " . $web_users . " WHERE `cachepwd` != '' AND CHAR_LENGTH( `cachepwd` ) < 20");
        $deadAccounts = $this->FetchAll($findDeadAccounts);

        foreach ($deadAccounts as $user) {
            if ($user['cachepwd'] <= (time() - (60 * 60 * 24 * $pruneDays)) && $user['cachepwd'] != 0) {
                $deleteUser = $this->modx->db->query("DELETE FROM " . $web_users . " WHERE `id`='" . $user['id'] . "'");
                $deleteAttributes = $this->modx->db->query("DELETE FROM " . $web_user_attributes . " WHERE `internalKey`='" . $user['id'] . "'");
                $deleteFromGroups = $this->modx->db->query("DELETE FROM " . $web_groups . " WHERE `webuser`='" . $user['id'] . "'");

                // Email the Web Master regarding pruned accounts.
                $prunedMessage = str_replace('[+000+]', $user['username'], $this->Language['message_user_deleted_text']);
                setlocale(LC_TIME, $this->Options['locale']);
                $prunedMessage = str_replace('[+111+]', strftime('%A %B %d, %Y', $user['cachepwd']), $prunedMessage);
                setlocale(LC_TIME, $this->Options['currentLocale']);
                $emailsender = $this->modx->config['emailsender'];

                $Pruned = new PHPMailer();
                
                // enable smtp via modx configuration
                if ($modx->config['email_method'] == 'smtp')
                {
                  $Pruned->IsSMTP(); // telling the class to use SMTP             
                  $Pruned->SMTPAuth   = true;                  
                  $Pruned->Host = $modx->config['smtp_host']; //host from modx configuration
                  $Pruned->Port = $modx->config['smtp_port']; //port from modx configuration
                  $Pruned->Username = $modx->config['smtp_username'];  //user from modx configuration
                  $passsmtp = $modx->config['smtppw']; //encoded password from modx configuration
                  $passsmtp = base64_decode(substr($passsmtp, 0, strpos($passsmtp, '%')) . '=');
                  $Pruned->Password   = $passsmtp; 
                }	
    
                $Pruned->CharSet = $this->modx->config['modx_charset'];
                $Pruned->From = $this->modx->config['emailsender'];
                $Pruned->FromName = 'WebLoginPE Pruning Agent';
                $Pruned->Subject = $this->Language['message_user_deleted'];
                $Pruned->Body = $prunedMessage;
                $Pruned->AddAddress($emailsender);
                if (!$Pruned->Send()) {
                    return $this->FormatMessage($Pruned->ErrorInfo, 'error');
                }
                $Pruned->ClearAddresses();
            }
        }
    }

    /**
     * Template takes a template parameter and checks to see if it is a chunk.
     * If it is a chunk, returns the contents of the chunk, if it is not a chunk,
     * tries to find a file of that name (or path) and gets its contents. If it
     * is not a chunk or a file, returns the value passed as the parameter $chunk.
     *
     * @param string $chunk
     * @return string HTML block.
     * @author Scotty Delicious
     */
    function Template($chunk)
    {
        $template = '';
        if ($this->modx->getChunk($chunk) != '') {
            $template = $this->modx->getChunk($chunk);
        } else if (is_file($chunk)) {
            $template = file_get_contents($chunk);
        } else {
            $template = $chunk;
        }
        return $template;
    }

    /**
     * SaveUserProfile
     * Updates the web_user_attributes table for a given internalKey.
     *
     * @return void
     * @author Scotty Delicious
     */
    function SaveUserProfile($internalKey = '', $groups = '', $activate = false, $activateId = '', $activateConfig = '', $activatePost = '')
    {
        if ($internalKey == '' || empty($internalKey)) {
            $currentWebUser = $this->modx->getWebUserInfo($this->modx->getLoginUserID());
            $internalKey = $currentWebUser['internalKey'];
            $refreshSession = true;
        } else {
            $currentWebUser = $this->modx->getWebUserInfo($internalKey);
            $refreshSession = false;
        }

        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');
        $web_groups = $this->modx->getFullTableName('web_groups');
        $webgroup_names = $this->modx->getFullTableName('webgroup_names');

        // EVENT: OnBeforeWebSaveUser
        $this->OnBeforeWebSaveUser(array(), array()); // pixelchutes
        if (!empty($this->Report)) {
            return; // pixelchutes
        }
        if (!empty($_POST['password']) && isset($_POST['password']) && isset($_POST['password_confirm'])) { // pixelchutes
            if ($_POST['password'] === $_POST['password_confirm']) { // pixelchutes
                if (md5($_POST['password']) === md5($this->modx->db->escape(strip_tags($_POST['password'])))) {
                    if (strlen($_POST['password']) > 5) {
                        $passwordElement = "UPDATE " . $web_users . " SET `password`='" . md5($this->modx->db->escape($_POST['password'])) . "' WHERE `id`='" . $internalKey . "'";
                        $saveMyPassword = $this->modx->db->query($passwordElement);
                    } else {
                        $this->FormatMessage($this->Language['error_password_too_short'], 'error');
                        return;
                    }
                } else {
                    $this->FormatMessage($this->Language['error_password_illegal_characters'], 'error');
                    return;
                }
            } else {
                $this->FormatMessage($this->Language['error_fields_not_match'], 'error');
                return;
            }
        }

        // Check for arrays and that "confirm" fields match.
        $fieldMessage = '';
        foreach ($_POST as $field => $value) {
            if (is_array($_POST[$field])) {
                $_POST[$field] = implode('||', $_POST[$field]);
            }

            $confirm = $field . '_confirm';
            if (isset($_POST[$confirm])) {
                if ($_POST[$field] !== $_POST[$confirm]) {
                    $error = $this->Language['error_fields_not_match'] . ' <br />';
                    $fieldMessage .= str_replace('[+000+]', '"' . $field . '"', $error);
                }
            }
        }

        // If confirm fields were mismatched, throw this error:
        if (!empty($fieldMessage)) {
            $err = $fieldMessage;
            unset($fieldMessage);
            $this->FormatMessage($err, 'error');
            return;
        }

        $generalElementsArray = array('fullname', 'email', 'phone', 'mobilephone', 'dob', 'gender', 'country', 'state', 'zip', 'fax', 'photo', 'comment');
        $generalElementsUpdate = array();

        // CREDIT: Guillaume to delete data and for code optimisation
        foreach ($generalElementsArray as $field) {
            if ($field == 'photo') {
                if ($_FILES['photo']['name'] !== '' && !empty($_FILES['photo']['name'])) {
                    $_POST['photo'] = $this->CreateUserImage();
                    if (!empty($this->Report)) {
                        return;
                    }
                }
            }
            if ($field == 'dob' && trim($_POST['dob']) != '') { // for not format an empty date else date is 0 (01-01-1970)
                $_POST['dob'] = $this->MakeDateForDb($_POST['dob']);
            }
            if ($field != 'photo' || ($_FILES['photo']['name'] !== '' && !empty($_FILES['photo']['name']))) { // for update db with value and blank value (except if the field is 'photo')
                $generalElementsUpdate[] = " `" . $field . "` = '" . $this->modx->db->escape(htmlspecialchars(trim($_POST[$field]), ENT_NOQUOTES, $this->modx->config['modx_charset'])) . "'";
            }
        }

        if (!empty($this->CustomFields)) {
            $checkForExtended = "SELECT * FROM " . $this->CustomTable . " WHERE `internalKey` = '" . $internalKey . "'";
            $isExtended = $this->modx->db->query($checkForExtended);
            $extendedRows = $this->modx->db->getRow($isExtended);

            if (!empty($extendedRows)) {
                $extendedFieldValues = array();
                foreach ($this->CustomFields as $field) {
                    $extendedFieldValues[] = " `" . $field->name . "` = '" . $this->modx->db->escape(htmlspecialchars(trim($_POST[$field->name]), ENT_NOQUOTES, $this->modx->config['modx_charset'])) . "'";
                }
                $this->OnBeforeWebSaveUser($generalElementsUpdate, $extendedFieldValues);

                $extendedUserAttr = "UPDATE " . $this->CustomTable . " SET" . implode(', ', $extendedFieldValues) . " WHERE `internalkey` = '" . $internalKey . "'";
            } else {
                $extendedFields = array();
                $extendedFieldValues = array();
                foreach ($this->CustomFields as $field) {
                    $extendedFields[$field->name] = $field->name;
                    $extendedFieldValues[$field->name] = $this->modx->db->escape(htmlspecialchars(trim($_POST[$field->name]), ENT_NOQUOTES, $this->modx->config['modx_charset']));
                }
                $this->OnBeforeWebSaveUser($generalElementsUpdate, $extendedFieldValues);

                $extendedFieldValues = implode("', '", $extendedFieldValues);
                $extendedFields = implode('`, `', $extendedFields);
                $extendedUserAttr = "INSERT INTO " . $this->CustomTable . " (`internalKey`, `" . $extendedFields . "`) VALUES ('" . $internalKey . "', '" . $extendedFieldValues . "')";
            }
        }

        // Prepare the query for General Elements
        $generalElementsSQL = "UPDATE " . $web_user_attributes . " SET " . implode(', ', $generalElementsUpdate) . " WHERE `internalkey` = '" . $internalKey . "'";

        // Set custom configuration of activation
        if ($activate && !empty($activateConfig) && !empty($activatePost)) {
            // FORMAT: activationType:groups:template:emailSubject|activationType:groups:template:emailSubject
            $activateGroups = explode("\|", $activateConfig);
            foreach ($activateGroups as $activateGroup) {
                $typeConfig = explode(":", $activateGroup);
                if ($_POST[$activatePost] == $typeConfig[0]) {
                    $groups = $typeConfig[1];
                    $messageTpl = $this->Template($typeConfig[2]);
                    $emailSubject = (isset($typeConfig[3]) ? $typeConfig[3] : "");
                    break;
                }
            }
        }

        // Update webuser groups
        if (!empty($groups)) {
            // Flush existing group settings
            $deleteFromGroups = $this->modx->db->query("DELETE FROM " . $web_groups . " WHERE `webuser`='" . $internalKey . "'");

            $groups = str_replace(', ', ',', $groups);
            $GLOBALS['groupsArray'] = explode(',', $groups);

            // EVENT: OnBeforeAddToGroup
            $this->OnBeforeAddToGroup($GLOBALS['groupsArray']);
            if (count($GLOBALS['groupsArray'] > 0)) {
                $groupsList = "'" . implode("','", $GLOBALS['groupsArray']) . "'";
                $groupNames = $this->modx->db->query("SELECT `id` FROM " . $webgroup_names . " WHERE `name` IN (" . $groupsList . ")");
                if (!$groupNames) {
                    $this->FormatMessage($this->Language['error_update_webgroups'], 'error');
                    return;
                } else {
                    while ($row = $this->modx->db->getRow($groupNames)) {
                        $webGroupId = $row['id'];
                        $this->modx->db->query("REPLACE INTO " . $web_groups . " (`webgroup`, `webuser`) VALUES ('" . $webGroupId . "', '" . $internalKey . "')");
                    }
                }
            }
        }

        // Send activation e-mail to user if approved
        if ($activate) {
            $findUser = "SELECT * FROM " . $web_user_attributes . ", " . $web_users . " WHERE " . $web_users . ".`id`='" . $internalKey . "' AND " . $web_user_attributes . ".`internalKey`=" . $web_users . ".`id`";
            $userInfo = $this->modx->db->query($findUser);
            $limit = $this->modx->recordCount($userInfo);
            if ($limit == 1) {
                // Generate new password
                $newPassword = $this->GeneratePassword(10);
                $newPasswordKey = $this->GeneratePassword(10);
                $this->User = $this->modx->db->getRow($userInfo);
                $insertNewPassword = "UPDATE " . $web_users . " SET cachepwd='" . $newPassword . "|" . $newPasswordKey . "' WHERE id='" . $this->User['internalKey'] . "'";
                $setCachePassword = $this->modx->db->query($insertNewPassword);

                // build activation url
                $activateId = (!empty($activateId) ? $activateId : $this->modx->documentIdentifier);
                if ($_SERVER['SERVER_PORT'] != '80') {
                    $url = $this->modx->config['server_protocol'] . '://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $this->modx->makeURL($activateId, '', "?service=activate&userid=" . $this->User['id'] . "&activationkey=" . $newPasswordKey);
                } else {
                    $url = $this->modx->config['server_protocol'] . '://' . $_SERVER['SERVER_NAME'] . $this->modx->makeURL($activateId, '', "?service=activate&userid=" . $this->User['id'] . "&activationkey=" . $newPasswordKey);
                    //$url = $_SERVER['HTTP_REFERER']."&service=activate&userid=".$this->User['id']."&activationkey=".$newPasswordKey;
                }

                // Replace some placeholders in the Config websignupemail message.
                if (empty($messageTpl)) {
                    $messageTpl = $this->modx->config['webpwdreminder_message'];
                }
                if (empty($emailSubject)) {
                    $emailSubject = $this->modx->config['emailsubject'];
                }
                $myEmail = $this->modx->config['emailsender'];
                $siteName = $this->modx->config['site_name'];
                $siteURL = $this->modx->config['site_url'];

                $message = str_replace("[+uid+]", $this->User['username'], $messageTpl);
                $message = str_replace("[+pwd+]", $newPassword, $message);
                $message = str_replace("[+ufn+]", $this->User['fullname'], $message);
                $message = str_replace("[+sname+]", $siteName, $message);
                $message = str_replace("[+semail+]", $myEmail, $message);
                $message = str_replace("[+surl+]", $url, $message);

                foreach ($_POST as $name => $value) {
                    $toReplace = '[+post.' . $name . '+]';
                    $message = str_replace($toReplace, $value, $message);
                }


                // Bring in php mailer!
                $Register = new PHPMailer();
                
                // enable smtp via modx configuration
                if ($modx->config['email_method'] == 'smtp')
                {
                  $Register->IsSMTP(); // telling the class to use SMTP             
                  $Register->SMTPAuth   = true;                  
                  $Register->Host = $modx->config['smtp_host']; //host from modx configuration
                  $Register->Port = $modx->config['smtp_port']; //port from modx configuration
                  $Register->Username = $modx->config['smtp_username'];  //user from modx configuration
                  $passsmtp = $modx->config['smtppw']; //encoded password from modx configuration
                  $passsmtp = base64_decode(substr($passsmtp, 0, strpos($passsmtp, '%')) . '=');
                  $Register->Password   = $passsmtp;    
                }	
    
                $Register->CharSet = $this->modx->config['modx_charset'];
                $Register->From = $myEmail;
                $Register->FromName = $siteName;
                $Register->Subject = $emailSubject;
                // dg@visions.ch 2014-05-20:
                $Register->ContentType = 'text/html';
                $Register->IsHTML(true);
                $Register->Body = $message;
                $Register->AddAddress($this->User['email'], $this->User['fullname']);

                if (!$Register->Send()) {
                    $this->FormatMessage($this->Language['error_sending_email'], 'error');
                    return;
                }
            }
        }


        // Execute the database queries.
        if (count($generalElementsUpdate) > 0)
            $saveMyProfile = $this->modx->db->query($generalElementsSQL);
        if (!empty($this->CustomFields)) {
            $insertExtendedAttr = $this->modx->db->query($extendedUserAttr);
        }

        $this->User = $this->QueryDbForUser($currentWebUser['username']);
        $this->OnWebSaveUser('upd', $this->User);

        if ($refreshSession === true) {
            $this->SessionHandler('start');
        }

        $this->FormatMessage($this->Language['message_account_updated'], 'success');
    }

    function RemoveProfile($internalKey)
    {
        $deletedUser = $this->modx->getWebUserInfo($internalKey);
        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');
        $web_groups = $this->modx->getFullTableName('web_groups');
        $active_users = $this->modx->getFullTableName('active_users');

        $deleteUser = $this->modx->db->query("DELETE FROM " . $web_users . " WHERE `id`='" . $internalKey . "'");
        $deleteAttributes = $this->modx->db->query("DELETE FROM " . $web_user_attributes . " WHERE `internalKey`='" . $internalKey . "'");
        $deleteFromGroups = $this->modx->db->query("DELETE FROM " . $web_groups . " WHERE `webuser`='" . $internalKey . "'");
        $deleteFromActiveUsers = $this->modx->db->query("DELETE FROM " . $active_users . " WHERE `internalKey`='-" . $internalKey . "'");

        if (!$deleteUser || !$deleteAttributes || !$deleteFromGroups || !$deleteFromActiveUsers) {
            return $this->FormatMessage($this->Language['error_remove_account'], 'error');
        }
        $this->OnWebDeleteUser($internalKey, $deleteUser['username']);
        return;
    }

    /**
     * RemoveUserProfile
     * Deletes the table entries in web_users, web_user_attributes, and web_groups for a given internalKey.
     *
     * @return void
     * @author Scotty Delicious
     */
    function RemoveUserProfile()
    {
        $currentWebUser = $this->modx->getWebUserInfo($this->modx->getLoginUserID());
        $internalKey = $currentWebUser['internalKey'];
        $this->RemoveProfile($internalKey);
        $this->SessionHandler('destroy');
        $this->FormatMessage($this->Language['message_account_deleted'], 'success');
        return;
    }

    function RemoveUserProfileManager($internalKey)
    {
        $this->RemoveProfile($internalKey);
        $this->FormatMessage($this->Language['message_account_deleted'], 'success');
        return;
    }

    /**
     * View all users stored in the web_users table.
     *
     * @param string $userTemplate HTML template to display each web user.
     * @return string HTML block containing all the users
     * @author Scotty Delicious
     */
    function ViewAllUsers($userTemplate, $outerTemplate, $listUsers)
    {
        $positionInList = trim($_REQUEST['pag']);
        if (!is_numeric($positionInList)) {
            $positionInList = 0;
        }
        $web_users = $this->modx->getFullTableName('web_users');
        $allRows = $this->modx->db->query("SELECT id FROM " . $web_users);
        $alumni = mysql_num_rows($allRows);
        $num_rows = ceil($alumni / $this->Options['paging']);

        $output = '';
        if ($num_rows > 1) {
            for ($i = 1; $i <= $num_rows; $i++) {
                $startPos = ($i * $this->Options['paging']) - $this->Options['paging'];
                if ($startPos != $positionInList) {
                    $output .= " <a href=\"index.php?id=" . $this->modx->documentIdentifier . "&pag=" . $startPos . "\">" . $i . "</a>";
                } else {
                    $output .= " " . $i;
                }
            }
        }
        echo $output;
        $fetchUsers = $this->modx->db->query("SELECT `username` FROM " . $web_users . "ORDER BY `username` LIMIT " . $positionInList . "," . $this->Options['paging']);


        $allUsers = $this->FetchAll($fetchUsers);

        if ($listUsers == '') {
            $listUsers = '[(site_name)] Members:default:default:username:ASC:';
        }

        if ($listUsers !== '') {
            $eachList = explode('||', $listUsers);
            $FinalDisplay = '';
            foreach ($eachList as $eachListFormat) {
                $format = explode(':', $eachListFormat);
                $listName = $format[0];

                $listOuterTemplate = $format[1];
                if ($listOuterTemplate == 'default') {
                    $listOuterTemplate = $outerTemplate;
                } else {
                    $listOuterTemplate = $this->Template($listOuterTemplate);
                }
                //return $listOuterTemplate;

                $listTemplate = $format[2];
                if ($listTemplate == 'default') {
                    $listTemplate = $userTemplate;
                } else {
                    $listTemplate = $this->Template($listTemplate);
                }

                $listSortBy = $format[3];
                $listSortOrder = $format[4];
                if ($listSortOrder == 'DESC') {
                    $listSortOrder = SORT_DESC;
                } else {
                    $listSortOrder = SORT_ASC;
                }

                // Filters.
                if ($format[5] == '') {
                    $format[5] = 'username()';
                }

                $CompleteUserList = array();
                foreach ($allUsers as $user) {
                    $username = $user['username'];
                    $CompleteUserList[$username] = $this->QueryDbForUser($username);
                }

                $allFilters = explode(',', str_replace(', ', ',', $format[5]));
                foreach ($allFilters as $theFilter) {
                    $filters = explode('(', $theFilter);
                    $filterBy = $filters[0];

                    $sortNumerics = array('dob', 'lastlogin', 'thislogin', 'internalKey', 'logincount', 'blocked', 'blockeduntil', 'blockedafter', 'failedlogincount', 'gender');
                    if (in_array($filterBy, $sortNumerics)) {
                        $typeFlag = SORT_NUMERIC;
                    } else {
                        $typeFlag = SORT_STRING;
                    }

                    $filterValue = str_replace(')', '', $filters[1]);
                    if ($filterValue == '') {
                        unset($filterValue);
                    }

                    foreach ($CompleteUserList as $theUser) {
                        switch ($filterBy) {
                            case 'webgroup':
                                $web_groups = $this->modx->getFullTableName('web_groups');
                                $webgroup_names = $this->modx->getFullTableName('webgroup_names');
                                $findWebGroup = $this->modx->db->query("SELECT `id` FROM " . $webgroup_names . " WHERE `name` = '" . $filterValue . "'");
                                $limit = $this->modx->db->getRecordCount($findWebGroup);
                                if ($limit == 0) {
                                    print 'There is no webgroup by the name "' . $filterValue . '"';
                                }
                                $webGroupIdSearch = $this->modx->db->getRow($findWebGroup);
                                $webGroupId = $webGroupIdSearch['id'];

                                $groupQuery = "SELECT * FROM " . $web_groups . " WHERE `webgroup` = '" . $webGroupId . "' AND `webuser` = '" . $theUser['internalKey'] . "'";
                                $isMember = $this->modx->db->query($groupQuery);
                                $limit = $this->modx->db->getRecordCount($isMember);
                                if ($limit == 0) {
                                    $username = $theUser['username'];
                                    unset($CompleteUserList[$username]);
                                }
                                break;
                            case 'online':
                                $active_users = $this->modx->getFullTableName('active_users');
                                $activityCheck = "SELECT * FROM " . $active_users . " WHERE `internalKey` = '-" . $theUser['internalKey'] . "'";
                                $lastActive = $this->modx->db->query($activityCheck);
                                $limit = $this->modx->db->getRecordCount($lastActive);
                                if ($limit !== 0) {
                                    $userStatus = $this->modx->db->getRow($lastActive);
                                    if ($userStatus['lasthit'] >= time() - (60 * 15)) {
                                        // Good, User is online and active
                                    } else {
                                        $username = $theUser['username'];
                                        unset($CompleteUserList[$username]);
                                    }
                                } else {
                                    $username = $theUser['username'];
                                    unset($CompleteUserList[$username]);
                                }
                                break;
                            default:
                                if (empty($theUser[$filterBy]) || $theUser[$filterBy] == '' && $filterBy !== 'webgroup') {
                                    $username = $theUser['username'];
                                    unset($CompleteUserList[$username]);
                                }
                                if (isset($filterValue) && $filterBy !== 'webgroup') {
                                    if ($theUser[$filterBy] !== '' && !empty($theUser[$filterBy])) {
                                        $isValue = strpos(strtolower($filterValue), strtolower($theUser[$filterBy]));
                                        $isValueAlt = strpos(strtolower($theUser[$filterBy]), strtolower($filterValue));
                                        if ($isValue === false && $isValueAlt === false) {
                                            $username = $theUser['username'];
                                            unset($CompleteUserList[$username]);
                                        }
                                    }
                                }
                                break;
                        }
                    }
                }

                // SORT ARRAY
                $sortArray = array();
                foreach ($CompleteUserList as $username => $attributes) {
                    foreach ($attributes as $field => $value) {
                        $sortArray[$field][$username] = $value;
                    }
                }
                //List here
                if (is_array($sortArray[$listSortBy])) {
                    $arrayMap = array_map('strtolower', $sortArray[$listSortBy]);
                    array_multisort($arrayMap, $listSortOrder, $typeFlag, $CompleteUserList);

                    $displayUserTemplate = '';
                    foreach ($CompleteUserList as $theUser) {
                        $user = $this->QueryDbForUser($theUser['username']);

                        $active_users = $this->modx->getFullTableName('active_users');
                        $activityCheck = "SELECT * FROM " . $active_users . " WHERE `internalKey` = '-" . $theUser['internalKey'] . "'";
                        $lastActive = $this->modx->db->query($activityCheck);
                        $limit = $this->modx->db->getRecordCount($lastActive);
                        if ($limit !== 0) {
                            $userStatus = $this->modx->db->getRow($lastActive);
                            if ($userStatus['lasthit'] >= time() - (60 * 30)) {
                                $user['status'] = $this->Language['online'];
                            } else {
                                $user['status'] = $this->Language['offline'];
                            }
                        } else {
                            $user['status'] = $this->Language['offline'];
                        }

                        $eachProfile = $listTemplate;
                        foreach ($user as $field => $value) {
                            // $value = html_entity_decode($value);
                            $needToSplit = strpos($value, '||');
                            if ($needToSplit > 0) {
                                $user[$field] = str_replace('||', ', ', $value);
                            }

                            $placeholder = '[+view.' . $field . '+]';

                            if ($field == 'dob') {
                                if ($value == 0) {
                                    $value = $this->Language['unknown'];
                                    $eachProfile = str_replace('[+view.age+]', $value, $eachProfile);
                                } else {
                                    $ageDecimal = ((time() - $value) / (60 * 60 * 24 * 365));
                                    $age = substr($ageDecimal, 0, strpos($ageDecimal, "."));
                                    $value = strftime('%m-%d-%Y', $value);
                                    $eachProfile = str_replace('[+view.age+]', $age, $eachProfile);
                                }
                            } else if ($field == 'lastlogin' || $field == 'thislogin') {
                                if ($value == 0) {
                                    $value = $this->Language['unknown'];
                                } else {
                                    setlocale(LC_TIME, $this->Options['locale']);
                                    $value = strftime($this->Options['dateFormat'], $value);
                                    setlocale(LC_TIME, $this->Options['currentLocale']);
                                }
                            }


                            $eachProfile = str_replace($placeholder, $value, $eachProfile);
                        }
                        $displayUserTemplate .= $eachProfile;
                    }
                    $CombinedList = str_replace('[+view.title+]', $listName, $listOuterTemplate);
                    $CombinedList = str_replace('[+view.list+]', $displayUserTemplate, $CombinedList);
                    unset($displayUserTemplate);
                }
                $FinalDisplay .= $CombinedList;
            }
        }
        $FinalDisplay = (empty($FinalDisplay) ? '<p>No results.</p>' : $FinalDisplay);
        return $FinalDisplay;
    }

    /**
     * ViewUserProfile displays sets the placeholders for the attributes of another site user
     *
     * @param string $username The username of the other user's profile to view
     * @return void
     * @author Scotty Delicious
     */
    function ViewUserProfile($username, $inputHandler = array())
    {
        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');

        $findID = $this->modx->db->query("SELECT * FROM " . $web_users . " WHERE `username` = '" . $username . "'");
        $userID = $this->modx->db->getRow($findID);
        $userID = $userID['id'];
        $viewUser = $this->modx->getWebUserInfo($userID);
        $this->setPlaceholder('view.username', $viewUser['username']);
        $allFields = $this->modx->db->query("SELECT * FROM " . $web_user_attributes . ", " . $this->CustomTable . " WHERE " . $web_user_attributes . ".`internalKey` = '" . $userID . "' AND " . $this->CustomTable . ".`internalKey` = '" . $userID . "'");
        $limit = $this->modx->db->getRecordCount($allFields);

        if ($limit == 0) {
            $allFields = $this->modx->db->query("SELECT * FROM " . $web_user_attributes . " WHERE " . $web_user_attributes . ".`internalKey` = '" . $userID . "'");
        }

        $viewUser = $this->modx->db->getRow($allFields);

        $active_users = $this->modx->getFullTableName('active_users');
        $activityCheck = "SELECT * FROM " . $active_users . " WHERE `internalKey` = '-" . $viewUser['internalKey'] . "'";
        $lastActive = $this->modx->db->query($activityCheck);
        $limit = $this->modx->db->getRecordCount($lastActive);
        if ($limit !== 0) {
            $userStatus = $this->modx->db->getRow($lastActive);
            if ($userStatus['lasthit'] >= time() - (60 * 30)) {
                $viewUser['status'] = $this->Language['online'];
            } else {
                $viewUser['status'] = $this->Language['offline'];
            }
        } else {
            $viewUser['status'] = $this->Language['offline'];
        }

        foreach ($viewUser as $column => $setting) {
            // $setting = html_entity_decode($setting);
            $needToSplit = strpos($setting, '||');
            if ($needToSplit > 0) {
                $viewUser[$column] = str_replace('||', ', ', $setting);
            }

            $this->setPlaceholder('view.' . $column, $viewUser[$column]);

            if ($column == 'dob') {
                if ($setting == 0) {
                    if ($this->Type !== 'manager') {
                        $this->setPlaceholder('view.dob', $this->Language['unknown']);
                    } else {
                        $this->setPlaceholder('view.dob', '');
                    }
                    $this->setPlaceholder('view.age', $this->Language['unknown']);
                } else {
                    $ageDecimal = ((time() - $setting) / (60 * 60 * 24 * 365));
                    $age = substr($ageDecimal, 0, strpos($ageDecimal, "."));
                    setlocale(LC_TIME, $this->Options['locale']);
                    $this->setPlaceholder('view.dob', strftime($this->Options['dobFormat'], $viewUser['dob'])); // dobFormat by Bruno
                    $this->setPlaceholder('view.age', $age);
                    setlocale(LC_TIME, $this->Options['currentLocale']);
                }
            }
            if ($column == 'lastlogin') {
                setlocale(LC_TIME, $this->Options['locale']);
                $this->setPlaceholder('view.lastlogin', strftime($this->Options['dateFormat'], $viewUser['lastlogin']));
                setlocale(LC_TIME, $this->Options['currentLocale']);
            }
            if ($column == 'thislogin') {
                setlocale(LC_TIME, $this->Options['locale']);
                $this->setPlaceholder('view.thislogin', strftime($this->Options['dateFormat'], $viewUser['thislogin']));
                setlocale(LC_TIME, $this->Options['currentLocale']);
            }

            if ($this->Type !== 'manager') {
                $private = strpos($column, 'private');
                if ($private > 0) {
                    if ($setting == 'on') {
                        $fieldToReplace = str_replace('private', '', $column);
                        $viewUser[$fieldToReplace] = $this->Language['private'];
                        $this->setPlaceholder('view.' . $fieldToReplace, $this->Language['private']);
                    }
                }
                // end private
            }
        }
        $this->setPlaceholder('view.gender', $this->StringForGenderInt($viewUser['gender']));
        $this->setPlaceholder('view.country', $this->StringForCountryInt($viewUser['country']));

        // Handle Special input placeholders.
        $_country_lang = array();
        $countries = array();
        include MODX_MANAGER_PATH . 'includes/lang/country/' . $this->Language['language'] . '_country.inc.php';
        asort($_country_lang);
        foreach ($_country_lang as $key => $value) {
            $countries[] = $value . '(' . strval($key) . ')';
        }
        $inputHandler[] = '[+lang.country+]:UserProfileCountry:country:select:(0),' . implode(',', $countries);
        $inputHandler[] = '[+lang.gender+]:UserProfileGender:gender:select:(0),[+lang.male+](1),[+lang.female+](2)';

        foreach ($inputHandler as $value) {
            $this->ParseInputHandler($value, $viewUser);
        }

        unset($viewUser);
    }

    /**
     * Parse Input handler.
     *
     * @param array $inputHandler The input handler array
     * @param array $user The user array
     * @return void.
     * @author Thomas Jakobi
     */
    function ParseInputHandler($inputHandler, $user)
    {
        $dataType = explode(':', $inputHandler);
        $label = $dataType[0];
        $DOMid = $dataType[1];
        $name = $dataType[2];
        $type = $dataType[3];
        $values = $dataType[4];

        switch ($type) {
            case 'select multiple':
            case 'select':
                $outerTpl = file_get_contents(WLPE_BASE_PATH . 'templates/' . $this->Options['templateFolder'] . '/FieldSelectOuterTpl.html');
                $rowTpl = file_get_contents(WLPE_BASE_PATH . 'templates/' . $this->Options['templateFolder'] . '/FieldSelectRowTpl.html');

                $options = explode(',', $values);
                $selectOptions = array();
                foreach ($options as $eachOption) {
                    $option = explode('(', $eachOption);
                    $option = str_replace(')', '', $option);

                    $placeholder = array(
                        'value' => $option[1],
                        'text' => $option[0],
                        'selected' => (
                                (isset($_POST[$name]) && $option[1] === $_POST[$name]) ||
                                (isset($user[$name]) && $option[1] === $user[$name]) ||
                                (is_array($user[$name]) && in_array($option[1], $user[$name]))
                            ) ? ' selected="selected"' : ''
                    );
                    $selectOptions[] = $this->modx->parseText($rowTpl, $placeholder);
                }
                $placeholder = array(
                    'wrapper' => implode("\n", $selectOptions),
                    'dom_id' => $DOMid,
                    'label' => $label,
                    'name' => ($type == 'select multiple') ? $name . '[]' : $name,
                    'multiple' => ($type == 'select multiple') ? ' multiple' : ''
                );

                // Set the Placeholder
                $this->setPlaceholder('form.' . $name, $this->modx->parseText($outerTpl, $placeholder));
                break;


            case 'radio':
                $outerTpl = file_get_contents(WLPE_BASE_PATH . 'templates/' . $this->Options['templateFolder'] . '/FieldRadioOuterTpl.html');
                $rowTpl = file_get_contents(WLPE_BASE_PATH . 'templates/' . $this->Options['templateFolder'] . '/FieldRadioRowTpl.html');

                $options = explode(',', $values);
                $radioOptions = array();
                foreach ($options as $eachOption) {
                    $option = explode('(', $eachOption);
                    $option = str_replace(')', '', $option);

                    $placeholder = array(
                        'value' => $option[1],
                        'text' => $option[0],
                        'dom_id' => $DOMid,
                        'checked' => (
                                isset($user[$name]) && $option[1] === $user[$name]
                            ) ? ' checked="checked"' : ''
                    );
                    $radioOptions[] = $this->modx->parseText($rowTpl, $placeholder);
                }
                $placeholder = array(
                    'wrapper' => implode("\n", $radioOptions),
                    'dom_id' => $DOMid,
                    'label' => $label,
                    'name' => $name
                );

                // Set the Placeholder
                $this->setPlaceholder('form.' . $name, $this->modx->parseText($outerTpl, $placeholder));
                break;

            case 'checkbox':
                $outerTpl = file_get_contents(WLPE_BASE_PATH . 'templates/' . $this->Options['templateFolder'] . '/FieldCheckboxOuterTpl.html');
                $rowTpl = file_get_contents(WLPE_BASE_PATH . 'templates/' . $this->Options['templateFolder'] . '/FieldCheckboxRowTpl.html');

                $options = explode(',', $values);
                $radioOptions = array();
                foreach ($options as $eachOption) {
                    $option = explode('(', $eachOption);
                    $option = str_replace(')', '', $option);

                    $placeholder = array(
                        'value' => $option[1],
                        'text' => $option[0],
                        'checked' => (
                                isset($user[$name]) && $user[$name] === 'on'
                            ) ? ' checked="checked"' : ''
                    );
                    $radioOptions[] = $this->modx->parseText($rowTpl, $placeholder);
                }
                $placeholder = array(
                    'wrapper' => implode("\n", $radioOptions),
                    'dom_id' => $DOMid,
                    'label' => $label,
                    'name' => $name
                );

                // Set the Placeholder
                $this->setPlaceholder('form.' . $name, $this->modx->parseText($outerTpl, $placeholder));
                break;
        }
    }

    /**
     * SendMessageToUser allows site users to send email messages to each other.
     *
     * @return void.
     * @author Scotty Delicious
     */
    function SendMessageToUser()
    {
        $me = $this->modx->getWebUserInfo($this->modx->db->escape($_POST['me']));
        $you = $this->modx->getWebUserInfo($this->modx->db->escape($_POST['you']));
        $subject = $this->modx->db->escape($_POST['subject']);
        $message = strip_tags($_POST['message']) . "\n\n" . $this->modx->config['site_name'];

        if (empty($subject) || $subject == '' || empty($message) || $message == '') {
            $this->FormatMessage($this->Language['error_required_fields_blank'], 'error');
            $this->ViewUserProfile($you['username']);
            return;
        }

        $EmailMessage = new PHPMailer();
        
        // enable smtp via modx configuration
        if ($modx->config['email_method'] == 'smtp')
        {
          $EmailMessage->IsSMTP(); // telling the class to use SMTP             
          $EmailMessage->SMTPAuth   = true;                  
          $EmailMessage->Host = $modx->config['smtp_host']; //host from modx configuration
          $EmailMessage->Port = $modx->config['smtp_port']; //port from modx configuration
          $EmailMessage->Username = $modx->config['smtp_username'];  //user from modx configuration
          $passsmtp = $modx->config['smtppw']; //encoded password from modx configuration
          $passsmtp = base64_decode(substr($passsmtp, 0, strpos($passsmtp, '%')) . '=');
          $EmailMessage->Password   = $passsmtp; 
        }	
    
        $EmailMessage->CharSet = $this->modx->config['modx_charset'];
        $EmailMessage->From = $me['email'];
        $EmailMessage->FromName = $me['fullname'] . " (" . $me['username'] . ")";
        $EmailMessage->Subject = $subject;
        $EmailMessage->Body = $message;
        $EmailMessage->AddAddress($you['email'], $you['fullname']);

        if (!$EmailMessage->Send()) {
            $this->FormatMessage($EmailMessage->ErrorInfo, 'error');
            $this->ViewUserProfile($you['username']);
            return;
        }
        $this->FormatMessage($this->Language['message_sent_to'] . ' "' . $you['username'] . '"', 'success');
        $this->ViewUserProfile($you['username']);
        return;
    }

    /**
     * ResetPassword
     * Sets a random password | random key in the web_users.cachepwd field,
     * then sends an email to the user with instructions and a URL to activate.
     *
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function ResetPassword()
    {
        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');

        $email = $this->modx->db->escape(trim($_POST['email'])); // pixelchutes
        if (empty($email)) {
            return $this->FormatMessage($this->Language['error_required_fields_blank'], 'error'); // pixelchutes
        }
        $webpwdreminder_message = $this->modx->config['webpwdreminder_message'];
        $emailsubject = $this->modx->config['emailsubject'];
        $site_name = $this->modx->config['site_name'];
        $emailsender = $this->modx->config['emailsender'];

        $findUser = "SELECT * FROM " . $web_user_attributes . ", " . $web_users . " WHERE `email`='" . $email . "' AND `internalKey`=" . $web_users . ".`id`";
        $userInfo = $this->modx->db->query($findUser);
        $limit = $this->modx->recordCount($userInfo);

        if ($limit == 1) {
            // Reset the password and fire off an email to the user
            $newPassword = $this->GeneratePassword(10);
            $newPasswordKey = $this->GeneratePassword(10);
            $this->User = $this->modx->db->getRow($userInfo);
            $insertNewPassword = "UPDATE " . $web_users . " SET cachepwd='" . $newPassword . "|" . $newPasswordKey . "' WHERE id='" . $this->User['internalKey'] . "'";
            $setCachePassword = $this->modx->db->query($insertNewPassword);

            // build activation url
            if ($_SERVER['SERVER_PORT'] != '80') {
                $url = $this->modx->config['server_protocol'] . '://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $this->modx->makeURL($this->modx->documentIdentifier, '', "?service=activate&userid=" . $this->User['id'] . "&activationkey=" . $newPasswordKey);
            } else {
                //$url = $this->modx->config['server_protocol'].'://'.$_SERVER['SERVER_NAME'].$this->modx->makeURL($this->modx->documentIdentifier,'',"&service=activate&userid=".$this->User['id']."&activationkey=".$newPasswordKey);
                $url = $_SERVER['HTTP_REFERER'] . "?service=activate&userid=" . $this->User['id'] . "&activationkey=" . $newPasswordKey;
            }

            $message = str_replace("[+uid+]", $this->User['username'], $webpwdreminder_message);
            $message = str_replace("[+pwd+]", $newPassword, $message);
            $message = str_replace("[+ufn+]", $this->User['fullname'], $message);
            $message = str_replace("[+sname+]", $site_name, $message);
            $message = str_replace("[+semail+]", $emailsender, $message);
            $message = str_replace("[+surl+]", $url, $message);

            $Reset = new PHPMailer();
            
            // enable smtp via modx configuration
            if ($modx->config['email_method'] == 'smtp')
            {
              $Reset->IsSMTP(); // telling the class to use SMTP             
              $Reset->SMTPAuth   = true;                  
              $Reset->Host = $modx->config['smtp_host']; //host from modx configuration
              $Reset->Port = $modx->config['smtp_port']; //port from modx configuration
              $Reset->Username = $modx->config['smtp_username'];  //user from modx configuration
              $passsmtp = $modx->config['smtppw']; //encoded password from modx configuration
              $passsmtp = base64_decode(substr($passsmtp, 0, strpos($passsmtp, '%')) . '=');
              $Reset->Password   = $passsmtp; 
            }	
            
            $Reset->CharSet = $this->modx->config['modx_charset'];
            $Reset->From = $emailsender;
            $Reset->FromName = $site_name;
            $Reset->Subject = $emailsubject;
            $Reset->Body = $message;
            $Reset->AddAddress($email, $this->User['fullname']);

            if (!$Reset->Send()) {
                return $this->FormatMessage($this->Language['error_sending_email'], 'error');
            }
        } else {
            return $this->FormatMessage($this->Language['error_email_not_active'], 'error');
        }
        $this->FormatMessage($this->Language['message_account_password_activate'], 'error');
        return;
    }

    /**
     * ActivateUser
     * Activates the user after they have requested to have their password reset.
     *
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function ActivateUser()
    {
        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');

        $userid = $this->modx->db->escape($_REQUEST['userid']);
        $activationKey = $this->modx->db->escape($_REQUEST['activationkey']);
        $passwordKey = $this->modx->db->escape($_POST['activationpassword']);
        $newPassword = $this->modx->db->escape($_POST['newpassword']);
        $newPasswordConfirm = $this->modx->db->escape($_POST['newpassword_confirm']); // pixelchutes 1:55 AM 9/19/2007

        $findUser = "SELECT * FROM " . $web_users . " WHERE id='" . $userid . "'";
        $userInfo = $this->modx->db->query($findUser);
        $limit = $this->modx->recordCount($userInfo);

        if ($limit !== 1) {
            return $this->FormatMessage($this->Language['error_load_account'], 'error');
        }

        $this->User = $this->modx->db->getRow($userInfo);
        list($cachePassword, $cacheKey) = explode("|", $this->User['cachepwd']);

        if (($passwordKey !== $cachePassword) || ($activationKey !== $cacheKey)) {
            return $this->FormatMessage($this->Language['error_invalid_activation_key'], 'error');
        }

        if (!empty($newPassword) && isset($newPassword) && isset($newPasswordConfirm)) {
            if ($newPassword === $newPasswordConfirm) {
                if (md5($newPassword) === md5($this->modx->db->escape($newPassword))) {
                    if (strlen($newPassword) > 5) {
                        $passwordElement = "UPDATE " . $web_users . " SET `password`='" . md5($this->modx->db->escape($newPassword)) . "', cachepwd='' WHERE `id`='" . $this->User['id'] . "'";
                        $saveMyPassword = $this->modx->db->query($passwordElement);

                        $blocks = "UPDATE " . $web_user_attributes . " SET `blocked`='0', `blockeduntil`='0' WHERE `internalKey`='" . $this->User['id'] . "'";
                        $unblockUser = $this->modx->db->query($blocks);

                        // EVENT: OnWebChangePassword
                        $this->OnWebChangePassword($this->User['id'], $this->User['username'], $newPassword);
                    } else {
                        return $this->FormatMessage($this->Language['error_password_too_short'], 'error');
                    }
                } else {
                    return $this->FormatMessage($this->Language['error_password_illegal_characters'], 'error');
                }
            } else {
                return $this->FormatMessage($this->Language['error_fields_not_match'], 'error');
            }
        }
        if (!$saveMyPassword || !$unblockUser) {
            return $this->FormatMessage($this->Language['error_activating_password'], 'error');
        }
        $this->FormatMessage($this->Language['message_password_activated'], 'success');
        return;
    }

    /**
     * PlaceHolders
     * Sets place holders using the MODx method setPlaceholder() for fields in web_user_attributes.
     *
     * @param array $inputHandler An array of inputs to... uhh... handle?
     * @param string $MessageTemplate The template for $this->Report.
     * @return void
     * @author Scotty Delicious
     */
    function PlaceHolders($inputHandler, $MessageTemplate = '[+wlpe.message.text+]')
    {
        $this->MessageTemplate = $MessageTemplate;
        $CurrentUser = $this->modx->getWebUserInfo($this->modx->getLoginUserID());
        $this->setPlaceholder('user.username', $CurrentUser['username']);

        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');

        $extraFields = $this->modx->db->query("SELECT * FROM " . $this->CustomTable . ", " . $web_user_attributes . " WHERE " . $web_user_attributes . ".`internalKey` = '" . $this->modx->getLoginUserID() . "' AND " . $this->CustomTable . ".`internalKey` = '" . $this->modx->getLoginUserID() . "'");
        $limit = $this->modx->db->getRecordCount($extraFields);

        if ($limit == 0) {
            $extraFields = $this->modx->db->query("SELECT * FROM " . $web_user_attributes . " WHERE " . $web_user_attributes . ".`internalKey` = '" . $this->modx->getLoginUserID() . "'");
        }

        if ($this->modx->getLoginUserID() && $extraFields) {
            $CurrentUser = $this->modx->db->getRow($extraFields);
            if (!empty($CurrentUser)) {
                foreach ($CurrentUser as $key => $value) {
                    // $value = html_entity_decode($value);
                    switch ($key) {
                        case 'id';
                            // Do Nothing, we don't need that shit in the placeholders.
                            break;
                        case 'dob':
                            // CREDIT : Guillaume for not format an empty date
                            setlocale(LC_TIME, $this->Options['locale']);
                            if ($value != 0) {
                                $this->setPlaceholder('user.' . $key, strftime($this->Options['dobFormat'], $value)); // dobFormat by Bruno
                            }
                            $this->setPlaceholder('user.age', strftime('%Y', time() - $value));
                            setlocale(LC_TIME, $this->Options['currentLocale']);
                            break;
                        case 'thislogin':
                        case 'lastlogin':
                            if ($value == 0) {
                                $this->setPlaceholder('user.' . $key, $this->Language['unknown']);
                            } else {
                                $this->setPlaceholder('user.' . $key, strftime($this->Options['dateFormat'], $value));
                            }
                            break;
                        case'country':
                            $this->setPlaceholder('user.country.integer', $value);
                            $this->setPlaceholder('user.country', $this->StringForCountryInt($value));
                            break;
                        case 'gender':
                            $this->setPlaceholder('user.gender.integer', $value);
                            $this->setPlaceholder('user.gender', $this->StringForGenderInt($value));
                            break;
                        default:
                            $this->setPlaceholder('user.' . $key, $value);
                            break;
                    }

                    if (strpos($value, '||') > 0) {
                        $CurrentUser[$key] = explode('||', $value);
                    }
                }
            }
        }
        $this->setPlaceholder('user.defaultphoto', 'assets/snippets/webloginpe/userimages/default_user.jpg');
        $this->setPlaceholder('request.userid', $_REQUEST['userid']);
        $this->setPlaceholder('request.activationkey', $_REQUEST['activationkey']);
        $this->setPlaceholder('form.captcha', 'manager/includes/veriword.php');

        // Handle Special input placeholders.
        $_country_lang = array();
        $countries = array();
        include MODX_MANAGER_PATH . 'includes/lang/country/' . $this->Language['language'] . '_country.inc.php';
        asort($_country_lang);
        foreach ($_country_lang as $key => $value) {
            $countries[] = $value . '(' . strval($key) . ')';
        }
        $inputHandler[] = '[+lang.country+]:UserProfileCountry:country:select:(0),' . implode(',', $countries);
        $inputHandler[] = '[+lang.gender+]:UserProfileGender:gender:select:(0),[+lang.male+](1),[+lang.female+](2)';

        foreach ($inputHandler as $value) {
            $this->ParseInputHandler($value, $CurrentUser);
        }

        if (!empty($_POST)) {
            foreach ($_POST as $key => $value) {
                $this->setPlaceholder('post.' . $key, $value);
            }
        }
    }

    /**
     * RegisterScripts
     * Uses the MODx regClientStartupScript() method to load the jQuery scripts for taconite.
     * Optionally, it can load a custom js file (passed as a parameter.) if needed.
     *
     * @param string $customJs URL to a custom javascript file to be loaded.
     * @return void
     * @author Scotty Delicious
     */
    function RegisterScripts($customJs = '')
    {
        $this->modx->regClientStartupScript($this->modx->config['site_url'] . 'assets/snippets/webloginpe/js/jquery.packed.js');
        $this->modx->regClientStartupScript($this->modx->config['site_url'] . 'assets/snippets/webloginpe/js/jquery.form.js');
        $this->modx->regClientStartupScript($this->modx->config['site_url'] . 'assets/snippets/webloginpe/js/jquery.taconite.js');

        if (isset($customJs)) {
            $this->modx->regClientStartupScript($customJs);
        }
    }

    /**
     * Authenticate
     * Authenticates the user or sets failure counts on error.
     *
     * @return void
     * @author Scotty Delicious
     */
    function Authenticate()
    {
        if (!empty($this->Report)) {
            return; //There was an error in the last step
        }

        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');

        $authenticate = $this->OnWebAuthentication();
        // check if there is a plugin to authenticate user and that said plugin authenticated the user
        // else use a simple authentication scheme comparing MD5 of password to database password.
        if (!$authenticate || (is_array($authenticate) && !in_array(true, $authenticate))) {
            // check user password - local authentication
            if ($this->User['password'] != md5($this->Password)) {
                // in the case of a persistent login the password will already be a MD5 checksum.
                if ($this->User['password'] != $this->Password) {
                    $this->LoginErrorCount = 1;
                }
            }
        }

        if ($this->LoginErrorCount == 1) {
            $this->User['failedlogincount'] += $this->LoginErrorCount;

            if ($this->User['failedlogincount'] >= $this->modx->config['failed_login_attempts']) { //increment the failed login counter, and block!
                $sql = "UPDATE " . $web_user_attributes . " SET `failedlogincount`='0', `blockeduntil`='" . (time() + ($this->modx->config['blocked_minutes'] * 60)) . "' WHERE `internalKey`='" . $this->User['internalKey'] . "'";
                $failLoginAndBlockUser = $this->modx->db->query($sql);
                $anError = str_replace('[+000+]', $this->modx->config['blocked_minutes'], $this->Language['error_too_many_failed_logins']);
                $this->FormatMessage($anError, 'error');
                return;
            } else { //increment the failed login counter
                $sql = "UPDATE " . $web_user_attributes . " SET failedlogincount='" . $this->User['failedlogincount'] . "' WHERE internalKey='" . $this->User['internalKey'] . "'";
                $updateFailedLoginCount = $this->modx->db->query($sql);

                // Get a fresh copy of the user attributes.
                $this->User = $this->QueryDbForUser($this->User['username']);

                $failedLoginCount = $this->User['failedlogincount'];

                $anError = $this->Language['error_password_incorrect_message'];
                $anError = str_replace('[+000+]', $failedLoginCount, $anError);
                $anError = str_replace('[+111+]', $this->modx->config['blocked_minutes'], $anError);
                $anError = str_replace('[+222+]', $this->modx->config['failed_login_attempts'], $anError);

                $this->LoginErrorCount = 0;
                return $this->FormatMessage($anError, 'error');
            }
            $this->SessionHandler('destroy');
            return;
        }

        $CurrentSessionID = session_id();

        if (!isset($_SESSION['webValidated'])) {
            $isNowWebValidated = $this->modx->db->query("UPDATE " . $web_user_attributes . " SET `failedlogincount` = 0, `logincount` = `logincount` + 1, `lastlogin` = `thislogin`, `thislogin` = " . time() . ", `sessionid` = '" . $CurrentSessionID . "' where internalKey='" . $this->User['internalKey'] . "'");
        }
        // Flag the account as "Activated" by deleting the timestamp in `cachepwd`
        $cacheTimestamp = $this->modx->db->query("UPDATE " . $web_users . " SET `cachepwd`='' WHERE `id`='" . $this->User['internalKey'] . "'");
    }

    /**
     * UserDocumentGroups
     * Find the document groups that this user is a member of.
     *
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function UserDocumentGroups()
    {
        if (!empty($this->Report)) {
            return; //There was an error in the last step
        }

        $web_groups = $this->modx->getFullTableName('web_groups');
        $webgroup_access = $this->modx->getFullTableName('webgroup_access');

        $documentGroups = '';
        $i = 0;
        $sql = "SELECT uga.documentgroup FROM " . $web_groups . " ug INNER JOIN " . $webgroup_access . " uga ON uga.webgroup=ug.webgroup WHERE ug.webuser =" . $this->User['internalKey'];
        $currentUsersGroups = $this->modx->db->query($sql);

        $_SESSION['webDocgroups'] = array();
        while ($row = $this->modx->db->getRow($currentUsersGroups, 'num')) {
            $_SESSION['webDocgroups'][$i++] = $row[0];
        }
    }

    /**
     * UserWebGroups
     * Find the web groups that this user is a member of (NB: contrary to previous documentation/comments the above function UserDocumentGroups() does gets the document groups)
     *
     * @return void
     * @author Tim Spencer
     */
    function UserWebGroups()
    {
        $web_groups = $this->modx->getFullTableName('web_groups');
        $webgroup_names = $this->modx->getFullTableName('webgroup_names');

        $currentUsersWebGroups = $this->modx->db->query("SELECT {$webgroup_names}.* FROM {$web_groups}, {$webgroup_names} WHERE {$webgroup_names}.id = {$web_groups}.webgroup AND webuser = " . $this->User['internalKey']);

        $_SESSION['webUserGroupNames'] = array();
        while ($row = $this->modx->db->getRow($currentUsersWebGroups)) {
            $_SESSION['webUserGroupNames'][$row['id']] = $row['name'];
        }
    }


    /**
     * LoginHomePage
     * Redirect user to specified login page ($this->liHomeId).
     * $this->liHomeId is an array, each document ID is queried.
     * The user is redirected to the first page that they have permission to view.
     *
     * If $this->liHomeId is empty, refresh the current page.
     *
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function LoginHomePage()
    {
        if (!empty($this->Report)) {
            return; //There was an error in the last step
        }

        if ($this->Type == 'taconite') {
            return;
        }

        if (!empty($this->liHomeId)) {
            if (is_array($this->liHomeId)) {
                foreach ($this->liHomeId as $id) {
                    $id = trim($id);
                    if ($this->modx->getPageInfo($id)) {
                        $url = $this->modx->makeURL($id);
                        $this->modx->sendRedirect($url, 0, 'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
                        return;
                    }
                }
            } else {
                $url = $this->modx->makeURL($this->loHomeId);
                $this->modx->sendRedirect($url, 0, 'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
                return;
            }
        } else {
            $url = $this->modx->makeURL($this->modx->documentIdentifier);
            $this->modx->sendRedirect($url, 0, 'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
        }
        return;
    }

    /**
     * LogoutHomePage
     * Redirect user to specified logout page ($this->loHomeId).
     * If $this->loHomeId is empty, refresh the current page.
     *
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function LogoutHomePage()
    {
        if (!empty($this->Report)) {
            return; //There was an error in the last step
        }

        if ($this->Type == 'taconite') {
            return;
        }

        if (!empty($this->loHomeId)) {
            $url = $this->modx->makeURL($this->loHomeId);
            $this->modx->sendRedirect($url, 0, 'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
            return;
        } else {
            $url = $this->modx->makeURL($this->modx->documentIdentifier);
            $this->modx->sendRedirect($url, 0, 'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
        }
        return;
    }

    /**
     * SessionHandler
     * Starts the user session on login success. Destroys session on error or logout.
     *
     * @param string $directive ('start' or 'destroy')
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function SessionHandler($directive)
    {
        if (!empty($this->Report)) {
            return; //There was an error in the last step
        }

        if ($directive == 'start') {
            $_SESSION['webShortname'] = $this->Username;
            $_SESSION['webFullname'] = $this->User['fullname'];
            $_SESSION['webEmail'] = $this->User['email'];
            $_SESSION['webValidated'] = 1;
            $_SESSION['webInternalKey'] = $this->User['internalKey'];
            $_SESSION['webValid'] = base64_encode($this->Password);
            $_SESSION['webUser'] = base64_encode($this->Username);
            $_SESSION['webFailedlogins'] = $this->User['failedlogincount'];
            $_SESSION['webLastlogin'] = $this->User['lastlogin'];
            $_SESSION['webnrlogins'] = $this->User['logincount'];
            $this->UserWebGroups();

            if ($_POST['rememberme'] == 'on') {
                $cookieName = 'WebLoginPE';
                $cookieValue = md5($this->User['username']) . '|' . $this->User['password'];
                $cookieExpires = time() + (60 * 60 * 24 * 365 * 5); //5 years

                setcookie($cookieName, $cookieValue, $cookieExpires, '/', $_SERVER['SERVER_NAME'], 0);
            }

            if (isset($_POST['stayloggedin']) && $_POST['stayloggedin'] !== '') {
                $cookieName = 'WebLoginPE';
                $cookieValue = md5($this->User['username']) . '|' . $this->User['password'];
                $cookieExpires = time() + $_POST['stayloggedin'];

                setcookie($cookieName, $cookieValue, $cookieExpires, '/', $_SERVER['SERVER_NAME'], 0);
            }
        }

        if ($directive == 'destroy') {
            // if we were launched from the manager do NOT destroy session !!!
            if (isset($_SESSION['mgrValidated'])) {
                unset($_SESSION['webShortname']);
                unset($_SESSION['webFullname']);
                unset($_SESSION['webEmail']);
                unset($_SESSION['webValidated']);
                unset($_SESSION['webInternalKey']);
                unset($_SESSION['webValid']);
                unset($_SESSION['webUser']);
                unset($_SESSION['webFailedlogins']);
                unset($_SESSION['webLastlogin']);
                unset($_SESSION['webnrlogins']);
                unset($_SESSION['webUsrConfigSet']);
                unset($_SESSION['webUserGroupNames']);
                unset($_SESSION['webDocgroups']);

                $cookieName = 'WebLoginPE';
                setcookie($cookieName, '', time() - 60, '/', $_SERVER['SERVER_NAME'], 0);
            } else {
                if (isset($_COOKIE[session_name()])) {
                    setcookie(session_name(), '', 0, $this->modx->config['base_url']);
                }

                $cookieName = 'WebLoginPE';
                setcookie($cookieName, '', time() - 60, '/', $_SERVER['SERVER_NAME'], 0);
                session_destroy();
            }
        }
    }

    /**
     * Set timestamp in `active_users`.`lasthit` to current time.
     *
     * @return void
     * @access public
     * @author Scotty Delicious
     */
    function ActiveUsers()
    {
        if (!$this->modx->getLoginUserID() || !empty($this->Report)) {
            return;
        }
        $CurrentUser = $this->modx->getWebUserInfo($this->modx->getLoginUserID());

        if ($_SERVER['HTTP_X_FORWARD_FOR']) {
            $ip = $_SERVER['HTTP_X_FORWARD_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $active_users = $this->modx->getFullTableName('active_users');
        $activityCheck = "SELECT * FROM " . $active_users . " WHERE `internalKey` = '-" . $CurrentUser['internalKey'] . "'";
        $IamActive = $this->modx->db->query($activityCheck);
        $limit = $this->modx->db->getRecordCount($IamActive);
        if ($limit == 0) {
            $makeMeActive = $this->modx->db->query("INSERT INTO " . $active_users . " (`internalKey`,`username`,`lasthit`,`id`,`action`,`ip`) VALUES ('-" . $CurrentUser['internalKey'] . "','" . $CurrentUser['username'] . "','" . time() . "','0','998','" . $ip . "')");
        } else {
            $updateActivity = $this->modx->db->query("UPDATE " . $active_users . " SET `lasthit` = '" . time() . "', `ip` = '" . $ip . "' WHERE `internalKey` = '-" . $CurrentUser['internalKey'] . "'");
        }
    }

    /**
     * Set timestamp in `active_users` table to 0.
     *
     * @return void
     * @access protected
     * @author Scotty Delicious
     */
    function StatusToOffline()
    {
        $CurrentUser = $this->modx->getWebUserInfo($this->modx->getLoginUserID());
        $active_users = $this->modx->getFullTableName('active_users');
        $IamOffline = $this->modx->db->query("UPDATE " . $active_users . " SET `lasthit` = '0' WHERE `internalKey` = '-" . $CurrentUser['internalKey'] . "'");
    }

    /**
     * QueryDbForUser
     * Queries the web_users table for $_POST['username'].
     *
     * @param string $Username The username of the user to query for.
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function QueryDbForUser($Username)
    {
        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');

        $query = "SELECT * FROM " . $web_users . ", " . $web_user_attributes . ", " . $this->CustomTable . " WHERE BINARY LOWER(" . $web_users . ".username) = '" . strtolower($Username) . "' AND " . $web_user_attributes . ".`internalKey` = " . $web_users . ".`id` AND " . $this->CustomTable . ".`internalKey` = " . $web_users . ".`id`";
        $query2 = "SELECT * FROM " . $web_users . ", " . $web_user_attributes . ", " . $this->CustomTable . " WHERE(" . $web_users . ".username) = '" . $Username . "' AND " . $web_user_attributes . ".`internalKey` = " . $web_users . ".`id` AND " . $this->CustomTable . ".`internalKey` = " . $web_users . ".`id`";
        if (!$limit = $this->modx->db->getRecordCount($dataSource = $this->modx->db->query($query)))
            $limit = $this->modx->db->getRecordCount($dataSource = $this->modx->db->query($query2));

        if ($limit == 0) {
            $query = "SELECT * FROM " . $web_users . ", " . $web_user_attributes . " WHERE BINARY LOWER(" . $web_users . ".username) = '" . strtolower($Username) . "' AND " . $web_user_attributes . ".`internalKey` = " . $web_users . ".`id`";
            $query2 = "SELECT * FROM " . $web_users . ", " . $web_user_attributes . " WHERE(" . $web_users . ".username) = '" . $Username . "' AND " . $web_user_attributes . ".`internalKey` = " . $web_users . ".`id`";
            if (!$limit = $this->modx->db->getRecordCount($dataSource = $this->modx->db->query($query)))
                $limit = $this->modx->db->getRecordCount($dataSource = $this->modx->db->query($query2));
        }

        if ($limit == 0 || $limit > 1) {
            $this->User = false;
            return false;
        } else {
            return $this->modx->db->getRow($dataSource);
        }
    }

    /**
     * UserIsBlocked
     * Queries the web_user_attributes table to see if this user should
     * be blocked. If the user IS blocked, prevent them from logging in.
     *
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function UserIsBlocked()
    {
        if (!empty($this->Report)) {
            return; //There was an error in the last step
        }

        $web_users = $this->modx->getFullTableName('web_users');
        $web_user_attributes = $this->modx->getFullTableName('web_user_attributes');

        if ($this->User['failedlogincount'] >= $this->modx->config['failed_login_attempts'] && $this->User['blockeduntil'] > time()) {
            $this->SessionHandler('destroy');
            return $this->FormatMessage($this->Language['error_blocked_message'], 'error');
        }

        if ($this->User['failedlogincount'] >= $this->modx->config['failed_login_attempts'] && $this->User['blockeduntil'] < time()) { // blocked due to number of login errors, but get to try again
            $sql = "UPDATE " . $web_user_attributes . " SET failedlogincount='0', blockeduntil='" . (time() - 1) . "' where internalKey=" . $this->User['internalKey'];
            $updateFailedLoginCount = $this->modx->db->query($sql);
            return;
        }

        if ($this->User['blocked'] == "1") { // this user has been blocked by an admin, so no way he's loggin in!
            $this->SessionHandler('destroy');
            return $this->FormatMessage($this->Language['error_blocked'], 'error');
        }

        if ($this->User['blockeduntil'] >= time()) { // this user has a block until date
            $blockedUntilTime = $this->User['blockeduntil'] - time();
            $UserIsBlockedUntil = $blockedUntilTime / 60;
            $blockedMinutes = substr($UserIsBlockedUntil, 0, strpos($UserIsBlockedUntil, "."));

            $this->SessionHandler('destroy');
            $anError = str_replace('[+000+]', $blockedMinutes, $this->Language['error_blocked_time_message']);
            return $this->FormatMessage($anError, 'error');
        }

        if ($this->User['blockedafter'] > 0 && $this->User['blockedafter'] < time()) { // this user has a block after date
            $this->SessionHandler('destroy');
            return $this->FormatMessage($this->Language['error_blocked'], 'error');
        }

        if (isset($this->modx->config['allowed_ip'])) {
            if (strpos($this->modx->config['allowed_ip'], $_SERVER['REMOTE_ADDR']) === false) {
                return $this->FormatMessage($this->Language['error_blocked_ip'], 'error');
            }
        }

        if (isset($this->modx->config['allowed_days'])) {
            $date = getdate();
            $day = $date['wday'] + 1;
            if (strpos($this->modx->config['allowed_days'], $day) === false) {
                return $this->FormatMessage($this->Language['error_blocked_timerange'], 'error');
            }
        }
    }

    /**
     * MakeDateForDb
     * Returns a UNIX timestamp for the string provided.
     *
     * @param string $date A date in the format MM-DD-YYY
     * @return int Returns a UNIX timestamp for the date provided.
     * @author Scotty Delicious
     */
    function MakeDateForDb($date)
    { // modified by Bruno for $dobFormat
        $formatArray = preg_split('/[\.-]+/', $this->Options['dobFormat']);
        $dateArray = preg_split('/[\.-]+/', $date);
        // $date is a string like 01-22-1975.
        if (count($dateArray) !== 3) {
            return $this->FormatMessage($this->Language['error_format_birthdate'], 'error');
        }
        $daypos = array_search('%d', $formatArray);
        $monthpos = array_search('%m', $formatArray);
        $yearpos = array_search('%Y', $formatArray);

        // $dateArray is somethink like [0]=01, [1]=22, [2]=1975
        // make a unix timestamp out of the original date string.
        $timestamp = mktime(0, 0, 0, $dateArray[$monthpos], $dateArray[$daypos], $dateArray[$yearpos]);
        return $timestamp;
    }

    /**
     * CreateUserImage
     * Creates a 100px by 100px image for the user profile from a user uploaded image.
     * This image is renamed to the username and moved to the webloginpe/userimages/ folder.
     * The URL to this image is returned to be stored in the web_user_attributes table.
     *
     * @return string A URL to the user image created.
     * @author Scotty Delicious
     */
    function CreateUserImage()
    {
        $imageAttributes = str_replace(', ', ',', $this->Options['UserImageSettings']);
        $imageAttributes = explode(',', $imageAttributes);

        if ($_FILES['photo']['size'] >= $imageAttributes[0]) {
            $sizeInKb = round($imageAttributes[0] / 1024);
            $sizeError = str_replace('[+000+]', $sizeInKb, $this->Language['error_image_too_large']);
            return $this->FormatMessage($sizeError, 'error');
        }

        $userImage = $this->modx->config['base_path'] . strtolower(str_replace(' ', '-', basename($_FILES['photo']['name'])));
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $userImage)) {
            return $this->FormatMessage($this->Language['error_image_upload'], 'error');
        }

        // License and registration ma'am. I need to se an ID!
        if ($this->modx->getLoginUserID()) {
            $currentWebUser = $this->modx->getWebUserInfo($this->modx->getLoginUserID());
            if ($this->Type == 'manager') {
                $currentWebUser['username'] = $_POST['username'];
            }
        } else {
            $currentWebUser['username'] = $this->Username;
            if ($this->Username == '' || empty($this->Username)) {
                $currentWebUser['username'] = $_POST['username'];
            }
        }

        // Get dimensions and set new ones.
        list($width, $height) = getimagesize($userImage);
        $new_width = $imageAttributes[1];
        $new_height = $imageAttributes[2];

        $wm = $width / $new_width;
        $hm = $height / $new_height;
        if ($wm > 1 || $hm > 1) { // (don't magnify a smaller image)
            if ($wm > $hm)
                $new_height = $height / $wm;
            else
                $new_width = $width / $hm;
        } else {
            $new_width = $width;
            $new_height = $height;
        } // (must set the original size)
        // Resample
        $image_p = imagecreatetruecolor($new_width, $new_height);

        switch ($_FILES['photo']['type']) {
            case 'image/jpeg':
            case 'image/jpg': // added support for .jpg to the "default" support for .jpeg, so WLPE doesn't give a filetype error
            case 'image/pjpeg': // fix for IE6, which handles the .jpg filetype incorrectly
                $image = imagecreatefromjpeg($userImage);
                $ext = '.jpg';
                break;

            case 'image/gif':
                $image = imagecreatefromgif($userImage);
                imageSaveAlpha($image, true);
                imagesavealpha($image_p, true);
                $trans = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
                imagefill($image_p, 0, 0, $trans);
                $ext = '.gif';
                break;

            case 'image/png':
            case 'image/x-png':
                $image = imagecreatefrompng($userImage);
                imageSaveAlpha($image, true);
                imagesavealpha($image_p, true);
                $trans = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
                imagefill($image_p, 0, 0, $trans);
                $ext = '.png';
                break;

            default :
                return $this->FormatMessage($this->Language['error_image_type'], 'error');
                break;
        }
        imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        // Output
        $userImageFilePath = $this->modx->config['base_path'] . 'assets/snippets/webloginpe/userimages/' . str_replace(' ', '_', strtolower($currentWebUser['username'])) . $ext;
        $userImageFileURL = 'assets/snippets/webloginpe/userimages/' . str_replace(' ', '_', strtolower($currentWebUser['username'])) . $ext;

        switch ($_FILES['photo']['type']) {
            case 'image/jpeg':
                imagejpeg($image_p, $userImageFilePath, 100);
                break;

            case 'image/gif':
                imagegif($image_p, $userImageFilePath);
                break;

            case 'image/png':
            case 'image/x-png':
                imagepng($image_p, $userImageFilePath, 0);
                break;

            default :
                imagejpeg($image_p, $userImageFilePath, 100);
        }

        unlink($userImage);

        return $userImageFileURL;
    }

    /**
     * StringForGenderInt
     * Returns a string ('Male', 'Female', or 'Unknown') for the integer $genderInt (integer stored in web_user_attributes).
     *
     * @param int $genderInt (0, 1, or 2)
     * @return string (0 = 'Unknown', 1 = 'Male', 2 = 'Female')
     * @author Scotty Delicious
     */
    function StringForGenderInt($genderInt)
    {
        // use language file by Jako
        if ($genderInt == 1) {
            return $this->Language['male'];
        } else if ($genderInt == 2) {
            return $this->Language['female'];
        } else {
            return $this->Language['unknown'];
        }
    }

    /**
     * StringForCountryInt
     * Returns a string (the name of the country) for the integer $countryInt (integer stored in web_user_attributes).
     *
     * @param int $countryInt
     * @return string The name of the country
     * @author Scotty Delicious
     * @author Jako
     */
    function StringForCountryInt($countryInt)
    {
        $countryInt = (string)$countryInt;

        // use manager country.inc by Jako
        $_country_lang = array();
        $langFile = isset($this->Language['language']) ? $this->Language['language'] : 'english';
        if (file_exists(MODX_MANAGER_PATH . 'includes/lang/country/' . $langFile . '_country.inc.php')) {
            include MODX_MANAGER_PATH . 'includes/lang/country/' . $langFile . '_country.inc.php';
        } else {
            include MODX_MANAGER_PATH . 'includes/lang/country/english_country.inc.php';
        }
        return $_country_lang[$countryInt];
    }

    /**
     * Validate an email address by regex and MX reccord
     *
     * @param string $Email An email address.
     * @return void
     * @author Scotty Delicious
     */
    function ValidateEmail($Email)
    {
        // Original: ^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$
        if (!preg_match('/^[^@]+@[a-zA-Z0-9._-]+\.[a-zA-Z]+$/', $Email)) { // vhollo
            return $this->FormatMessage($this->Language['error_invalid_email'], 'error');
        }
    }

    /**
     * GeneratePassword
     * Generate a random password of (int $length). [a-z][A-Z][2-9].
     *
     * @param int $length
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     */
    function GeneratePassword($length = 10)
    {
        $allowable_characters = "abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $ps_len = strlen($allowable_characters);
        mt_srand((double)microtime() * 1000000);
        $pass = "";
        for ($i = 0; $i < $length; $i++) {
            $pass .= $allowable_characters[mt_rand(0, $ps_len - 1)];
        }
        return $pass;
    }

    /**
     * Fetch all rows in a data source recursively
     *
     * @param string $ds A data source.
     * @return array $all An array of the data source
     * @author Scotty Delicious
     */
    function FetchAll($ds)
    {
        $all = array();
        while ($all[] = $this->modx->db->getRow($ds)) {
        }
        foreach ($all as $key => $value) {
            if (empty($all[$key])) {
                unset($all[$key]);
            }
        }
        return $all;
    }

    /**
     * System Events
     */

    function OnBeforeWebLogin()
    {
        $parameters = array(
            'username' => $this->Username,
            'password' => $this->Password,
            'rememberme' => $_POST['rememberme'],
            'stayloggedin' => $_POST['stayloggedin']
        );
        $this->modx->invokeEvent("OnBeforeWebLogin", $parameters);
    }

    function OnWebLogin()
    {
        $parameters = array( // SMF connector fix http://forums.modx.com/thread/48913/smf-connector-webloginpe-was-broken-now-fixed c/o tazzydemon
            'user' => $this->User,
            'username' => $this->User['username']
        );
        $this->modx->invokeEvent('OnWebLogin', $parameters);
    }

    function OnWebAuthentication()
    {
        $parameters = array(
            'internalKey' => $this->User['internalKey'],
            'username' => $this->Username,
            'form_password' => $this->Password,
            'db_password' => $this->User['password'],
            'rememberme' => $_POST['rememberme'],
            'stayloggedin' => $_POST['stayloggedin']
        );
        $this->modx->invokeEvent('OnWebAuthentication', $parameters);
    }

    function OnBeforeWebSaveUser($Attributes = array(), $ExtendedFields = array())
    {
        $parameters = array(
            'Attributes' => $Attributes,
            'ExtendedFields' => $ExtendedFields
        );
        $this->modx->invokeEvent('OnBeforeWebSaveUser', $parameters);
    }

    function OnWebSaveUser($mode = 'new', $user = array())
    {
        $parameters = array(
            'mode' => $mode,

            'user' => $user, // Use of this parameter is discouraged. It is non-standard.

            'username' => $user['username'], // 1) SMF connector fix http://modxcms.com/forums/index.php?topic=26565.0
            'userpassword' => $user['password'], // 2) Further items to bring this into line with save_web_user.processor.php
            'useremail' => $user['email'],
            'userfullname' => $user['fullname'],
            'userid' => $user['internalKey']
        );
        $this->modx->invokeEvent('OnWebSaveUser', $parameters);
    }

    function OnBeforeAddToGroup($groups = array())
    {
        $parameters = array('groups' => $groups);
        $this->modx->invokeEvent('OnBeforeAddToGroup', $parameters);
    }

    function OnWebChangePassword($internalKey, $username, $newPassword)
    {
        $parameters = array(
            'internalKey' => $internalKey,
            'username' => $username,
            'password' => $newPassword
        );
        $this->modx->invokeEvent('OnWebChangePassword', $parameters);
    }

    function OnViewUserProfile($internalKey, $username, $viewerKey, $viewerName)
    {
        $parameters = array(
            'internalKey' => $internalKey,
            'username' => $username,
            'viewerKey' => $viewerKey,
            'viewername' => $viewerName
        );
        $this->modx->invokeEvent('OnViewProfile', $parameters);
    }

    function OnWebDeleteUser($internalKey, $username)
    {
        $parameters = array(
            'internalKey' => $internalKey,
            'username' => $username,
            'timestamp' => time()
        );
        $this->modx->invokeEvent('OnWebDeleteUser', $parameters);
    }

    function OnBeforeWebLogout()
    {
        $parameters = array(
            'userid' => $_SESSION['webInternalKey'],
            'internalKey' => $_SESSION['webInternalKey'],
            'username' => $_SESSION['webShortname']
        );
        $this->modx->invokeEvent('OnBeforeWebLogout', $parameters);
    }

    function OnWebLogout($logoutparameters) // SMF connector fix http://forums.modx.com/thread/48913/smf-connector-webloginpe-was-broken-now-fixed c/o tazzydemon
    {
        $this->modx->invokeEvent('OnWebLogout', $logoutparameters);
    }

}

// end WebLoginPE Class
