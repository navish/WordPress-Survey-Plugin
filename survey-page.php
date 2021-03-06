<?php
/**
    Allows a shortcode to be created that will add the survey to the page. The shortcode is [survey-page id=123] 
**/
add_shortcode('survey-page','survey_page');
function survey_page($atts, $content=null) {
    global $wpdb;
    
    $user_id = get_survey_user_session();
    
    if ($user_id !== FALSE) {
        //Grab the users name so we can display it later.
        $prepared = $wpdb->prepare("SELECT fullname FROM {$wpdb->prefix}survey_users WHERE id=%d", $user_id);
        $fullname = $wpdb->get_var($prepared);
        
        //Create the logout string depending on the URL type.
        $logout = (strstr($_SERVER['REQUEST_URI'], '?') === FALSE) ? "?logout=1" : "&logout=1";
    }
    
    if ($user_id !== FALSE) {
        $survey = new survey($atts['id']);
        
        echo "<h3>$survey->name</h3>\n";
        echo "<div id='survey-logout'>
                You are currently logged in as $fullname, 
                <a href='{$_SERVER['REQUEST_URI']}{$logout}'>click here to logout</a>
              </div>";
            
        for ($i = 1; $i <= $survey->pages; $i++) {
            echo $survey->output_survey($i);
        }
    }
    else {
        survey_registration(NULL);
    }
}

/**
    Allows a shortcode to be created that will create a test survey with sample data. Debug use only!
**/
add_shortcode('survey-test','survey_test');
function survey_test($atts, $content=null) {
    global $wpdb;
    global $survey_salt;
    
    //Empty the database and restart it.
    if (isset($_GET['restart'])) { 
        //Truncate the created tables for this plugin
        $wpdb->query("TRUNCATE TABLE ".$wpdb->prefix."survey");
        $wpdb->query("TRUNCATE TABLE ".$wpdb->prefix."survey_questions");
        $wpdb->query("TRUNCATE TABLE ".$wpdb->prefix."survey_answers");
        $wpdb->query("TRUNCATE TABLE ".$wpdb->prefix."survey_users");
        $wpdb->query("TRUNCATE TABLE ".$wpdb->prefix."survey_user_answers");
    }
    
    $survey1 = new survey(FALSE, "Survey 1");
    
    $question1 = $survey1->add_question(question::truefalse, "True/False: Order 1", -1, -1, 1);
    $question1->add_answer("TF Answer 1 DONT SHOW THIS!", 1);
    
    $question2 = $survey1->add_question(question::multichoice, "Multiple Choice: Order 2", -1, -1, 2);
    $question2->add_answer("MC Answer 1", 1);
    $question2->add_answer("MC Answer 2");
    $question2->add_answer("MC Answer 3", 3);
    
    $question3 = $survey1->add_question(question::dropdown, "Dropdown: Order 3", -1, -1, 3);
    $question3->add_answer("DD Answer 1");
    $question3->add_answer("DD Answer 2", 2);
    
    $question4 = $survey1->add_question(question::multiselect, "Multiple Select: Order 4", -1, -1, 1);
    $question4->add_answer("MS Answer 2", 2);
    $question4->add_answer("MS Answer 1", 1);
    $question4->add_answer("MS Answer 3", 3);
    
    $question5 = $survey1->add_question(question::shortanswer, "Short Answer: Order 6", -1, -1, 6);
    $question5->add_answer("SA Answer 1 DONT SHOW THIS!", 1);
    
    $question6 = $survey1->add_question(question::longanswer, "Long Answer: Order 5", -1, -1, 5);
    $question6->add_answer("LA Answer 1 DONT SHOW THIS!", 1);
    
    $question7 = $survey1->add_question(question::multichoiceother, "Multiple Choice Other: Order 7", -1, -1, 7);
    $question7->add_answer("MCO Answer 1");
    $question7->add_answer("MCO Answer 2");
    
    $question8 = new question(FALSE, question::multiselectother, "Multiple Select Other: Order 8", -1, -1, 8);
    $question8->add_answer("MSO Answer 1");
    $question8->add_answer("MSO Answer 2");
    $survey1->add_qobject($question8);
    
    //$survey1->output_survey();
    debug($survey1);
    
    //Empty the database and restart it.
    if (isset($_GET['restart'])) {
        $insert = $wpdb->insert($wpdb->prefix.'survey_users', 
                                array('username'=>'tester', 'password'=>sha1('tester'.$survey_salt, true), 
                                      'fullname'=>'Test User'), 
                                array('%s', '%s', '%s'));
                                
        $id = $insert ? $wpdb->insert_id : FALSE;
        
        if ($id !== FALSE) {
            var_dump(bin2hex(sha1('tester'.$survey_salt, true)));
            var_dump(bin2hex($wpdb->get_var("SELECT password FROM {$wpdb->prefix}survey_users WHERE id=$id")));
        }
        else {
            echo "Failed to insert!";
            var_dump(bin2hex(sha1('test'.$survey_salt, true)));
        }
        
        $post = array(
            'menu_order'     => 0, //If new post is a page, it sets the order in which it should appear in the tabs.
            'comment_status' => 'closed', // 'closed' means no comments.
            'ping_status'    => 'closed', // 'closed' means pingbacks or trackbacks turned off
            'post_author'    => get_current_user_id(), //The user ID number of the author.
            'post_content'   => "[survey-page id={$survey1->id}]", //The full text of the post.
            'post_name'      => 'test-survey-page', // The name (slug) for your post
            'post_status'    => 'publish', //Set the status of the new post.
            'post_title'     => 'Test Survey Page', //The title of your post.
            'post_type'      => 'page' //You may want to insert a regular post, page, link, a menu item or some custom post type
        );
        
        wp_insert_post($post, true);
    }
}