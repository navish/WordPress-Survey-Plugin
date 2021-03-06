<?php
/**
    This creates an action that runs every time the init action is called. 
    Which should be once for every page before any output. 
    This is important because cookies can't be set after something has been output.
**/
add_action('init', 'set_survey_user_session');
function set_survey_user_session() {
    global $wpdb;
    
    $user_id = FALSE;
    
    if (isset($_GET['logout']) && $_GET['logout'] == 1) {
        survey_logout_user();
        
        //Strip the logout variable from the URL and redirect automatically.
        if (strstr($_SERVER['REQUEST_URI'], "&logout=1")) {
            list($page, $get) = explode('&logout=1', $_SERVER['REQUEST_URI']);
        }
        else {
            list($page, $get) = explode('?', $_SERVER['REQUEST_URI']);
        }
        
        header("Location: $page");
        exit;
    }
    
    if (isset($_POST['survey_submit-l'])) {
        survey_logout_user(); //Force a logout. There have been weird issues.
        $user_id = survey_login_user();
    }
    elseif (isset($_POST['survey_submit-r'])) {
        survey_logout_user(); //Force a logout. There have been weird issues.
        $user_id = survey_register_user();
    }
    
    if ($user_id !== FALSE) {
        $date = date('Y-m-d H:i:s');
        
        //Cookies expire in 1 hour.
        $dc = setcookie('survey_hash', sha1($date), time()+60*60*1, '/');
        $id = setcookie('survey_u', $user_id, time()+60*60*1, '/');
        
        if (!$id || !$dc) {
            error_log("Failed to set cookie!");
            return FALSE;
        }
        else {
            //Set these variables so that they're accessible immediately without reloading.
            $_COOKIE['survey_hash'] = (!isset($_COOKIE['survey_hash'])) ? sha1($date) : $_COOKIE['survey_hash'];
            $_COOKIE['survey_u'] = $user_id;
            
            //Update the logged in time with the same time as the cookie.
            $wpdb->update($wpdb->prefix.'survey_users', array('logged_in'=>$date), 
                          array('id'=>$user_id), array('%s'), array('%d'));
        }
    }
    
    return TRUE;
}

/**
    Checks to see if the user is logged in, and if they are return their user id. Otherwise return false.
**/
function get_survey_user_session() {
    global $wpdb;
    
    if (isset($_COOKIE['survey_u'])) {
        $user_id = intval($_COOKIE['survey_u']);
        $logged_in = $_COOKIE['survey_hash'];
        
        $query = $wpdb->prepare("SELECT logged_in FROM {$wpdb->prefix}survey_users WHERE id=%d", $user_id);
        $db_logged_in = $wpdb->get_var($query);
        
        //If the logged in time is the same as the one in the database, and it hasn't been an hour yet they're good.
        if (sha1($db_logged_in) == $logged_in && strtotime($db_logged_in) < (time()+60*60*1)) {
            return $user_id;
        }
    }
    
    return FALSE;
}

/**
    Attempt to log the user in. Return the user id if successful and false if not.
**/
function survey_login_user() {
    global $wpdb;
    global $survey_salt;
    global $survey_register_output;
    
    $query = "SELECT id FROM {$wpdb->prefix}survey_users WHERE username=%s AND password=%s";
    $prepared = $wpdb->prepare($query,$_POST['survey_username-l'],sha1($_POST['survey_password-l'].$survey_salt, true));
    
    $user_id = $wpdb->get_var($prepared);
    
    if ($user_id !== NULL) {
        return $user_id;
    }
    else {
        $survey_register_output .= "Sorry username/password combination not valid. Please try again.";
    }
    
    return FALSE;
}

/**
    Log out the user by setting the cookies to expire in the past, causing them to be deleted.
**/
function survey_logout_user() {
    setcookie('survey_u', '', time()-3600, '/');
    setcookie('survey_hash', '', time()-3600, '/');
    //The cookies are still accessible until the page reloads, so also clear them manually.
    unset($_COOKIE['survey_u']);
    unset($_COOKIE['survey_hash']);
}

/**
    Attempt to register user. Return the user id if successful and false if not.
**/
function survey_register_user() {
    global $wpdb;
    global $survey_salt;
    global $survey_register_output;
    
    $continue = TRUE;
    
    if (strlen($_POST['survey_username-r']) < 4) {
        $survey_register_output .= "<div>Registration failed. Your username must me at least 4 characters.</div>";
        $continue = FALSE;
    }
    if (strlen($_POST['survey_password-r']) < 4) {
        $survey_register_output .= "<div>Registration failed. Your password must me at least 4 characters.</div>";
        $continue = FALSE;
    }
    if (strlen($_POST['survey_fullname-r']) == 0) {
        $survey_register_output .= "<div>Registration failed. You must enter your full name.</div>";
        $continue = FALSE;
    }
    
    if ($continue) {
        $insert = $wpdb->insert($wpdb->prefix.'survey_users', 
                                array('username'=>$_POST['survey_username-r'], 
                                      'password'=>sha1($_POST['survey_password-r'].$survey_salt, true), 
                                      'fullname'=>$_POST['survey_fullname-r']),
                                array('%s', '%s', '%s'));
        
        if ($insert !== FALSE) {
            $survey_register_output .= "<div id='survey-registration-success'>Survey Registration Success!</div>";
            
            return $wpdb->insert_id;
        }
        else {
            $survey_register_output .= "<div id='survey-registration-failed'>Survey Registration Failed. ".
                                       "Try a different username.</div>";
        }
    }
    
    return FALSE;
}

/**
    Allows a shortcode to be created that will create a registration page. The shortcode is [survey-registration].
    This can also be called as a function on the survey page.
**/
add_shortcode('survey-registration','survey_registration');
function survey_registration($atts, $content=null) {
    global $survey_register_output;
    
    echo <<<HTML
    {$survey_register_output}
    <form id='survey-registration' action='{$_SERVER['REQUEST_URI']}' method='post'>
        <h3>Register</h3> 
        <div id='survey-r'>
            <div id='survey-username'>
                <span>Username:</span> <input type='text' name='survey_username-r' maxlength='30' />
            </div>
            <div id='survey-password'>
                <span>Password:</span> <input type='password' name='survey_password-r' maxlength='20' />
            </div>
            <div id='survey-fullname'>
               <span>Full Name:</span> <input type='text' name='survey_fullname-r' maxlength='50' />
            </div>
            <div id='survey-submit-r'><input type='submit' value='Register' name='survey_submit-r' /></div>
        </div>
    </form>
    <form id='survey-login' action='{$_SERVER['REQUEST_URI']}' method='post'>
        <h3>Login</h3> 
        <div id='survey-l'>
            <div id='survey-username-l'>
                <span>Username:</span> <input type='text' name='survey_username-l' maxlength='30' />
            </div>
            <div id='survey-password-l'>
                <span>Password:</span> <input type='password' name='survey_password-l' maxlength='20' />
            </div>
            <div id='survey-submit'><input type='submit' value='Login' name='survey_submit-l' /></div>
        </div>
    </form>
HTML;
}