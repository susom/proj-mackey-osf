<?php
namespace Stanford\OSF;
/** @var \Stanford\OSF\OSF $module */

use \REDCap as REDCap;

/**
 * This is the INTAKE survey
 */


// IF POST, PROCESS "SUBMISSION"
if (!empty($_POST['submit_new_user'])) {
    $module::log("NEW USER INCOMING");
    $email 		= trim($_POST["username"]);
    $emailtoo 	= trim($_POST["usernametoo"]);

    $hash = trim($_GET["h"]);

    //todo: do we need to check that email = emailtoo?

    $fname  		= (!empty($_POST["firstname"])      ? $_POST["firstname"] : null ) ;
    $lname 	    	= (!empty($_POST["lastname"]) 	    ? $_POST["lastname"] : null) ;
    $phone_str 	    = (!empty($_POST["phone"]) 		    ? $_POST["phone"] :null ) ;
    $osf_ok_contact = (isset($_POST["osf_ok_contact"]) 	? $_POST["osf_ok_contact"]: null) ;

    //REDCap only accepts 10 digit phone number; strip +()-
    if(  preg_match( '/\+\d\s\((\d{3})\)\s(\d{3})-(\d{4})/', $phone_str,  $matches ) ) {
        $phone = $matches[1] . $matches[2] .  $matches[3];
    }
    // Print the entire match result
    $module::log($phone);

    $data = array(
        "osf_first_name"     =>$fname,
        "osf_last_name"      =>$lname,
        "osf_email_address"  =>$email,
        "osf_phone"          =>$phone,
        "osf_ok_contact___1" =>$osf_ok_contact,
        "hash"               => $hash
    );
    //saveData into new record
    $next_id = $module->setNewUser($data);

    if ($next_id) {
        //get survey link for the next instrument
        $instrument = 'demographics_pain_experience_history';  //todo: hardcoded?
        $survey_link = REDCap::getSurveyLink($next_id, $instrument);

        $module::log("SURVEY_LINK",$next_id, $survey_link);
        //redirect to filtering survey
        redirect($survey_link);
    } else {
        //the record was not saved, report error to REDCap logging?

    }

}




?><!DOCTYPE html>
<!--[if IE 7]> <html lang="en" class="ie7"> <![endif]-->
<!--[if IE 8]> <html lang="en" class="ie8"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->
<head>
    <title><?php echo $module->config['title']; ?></title>
    <!-- Meta -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Site / page description" />
    <meta name="author" content="Stanford | Medicine" />
    <!-- These meta tags are used when someone shares a link to this page on Facebook,
         Twitter or other social media sites. All tags are optional, but including them
         and customizing the content for specific sites can help the visibility of your
         content.
    <meta property="og:type" content="website" />
    <meta property="og:title" content="<?php echo $module->config['title']; ?>" />
    <meta property="og:description" content="<?php echo $module->config['title']; ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@TwitterHandle" />
    <meta name="twitter:title" content="Title for Twitter" />
    <meta name="twitter:description" content="Snippet when tweet is expanded." />
    <meta name="twitter:image" content="http://stanford.edu/about/images/intro_about.jpg" />
    <link rel="publisher" href="https://plus.google.com/id# of Google+ entity associated with your department or group" />
    -->

    <!-- Apple Icons - look into http://cubiq.org/add-to-home-screen -->
    <link rel="apple-touch-icon" sizes="57x57" href="<?php echo $module->getUrl('img/apple-icon-57x57.png',true, true) ?>" />
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo $module->getUrl('img/apple-icon-72x72.png',true, true) ?>" />
    <link rel="apple-touch-icon" sizes="114x114" href="<?php echo $module->getUrl('img/apple-icon-114x114.png',true, true) ?>" />
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo $module->getUrl('img/apple-icon-144x144.png',true, true) ?>" />
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $module->getUrl('img/favicon-32x32.png',true, true) ?>" />
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $module->getUrl('img/favicon-16x16.png',true, true) ?>" />
    <link rel="shortcut icon" href="<?php echo $module->getUrl('/img/favicon.ico',true, true) ?>" />

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_PATH_WEBROOT . DS . "Resources/css/fontawesome/css/fontawesome-all.min.css" ?>" type="text/css" />
    <link rel="stylesheet" href="<?php echo $module->getUrl('css/bootstrap.min.css',true, true) ?>" type="text/css" />
    <link rel="stylesheet" href="<?php echo $module->getUrl('css/bootstrap-formhelpers.min.css',true, true) ?>" type="text/css" />
<!--    <link rel="stylesheet" href="--><?php //echo $module->getUrl('/css/base.min.css',true, true) ?><!--" type="text/css" />-->
    <link rel="stylesheet" href="<?php echo $module->getUrl('css/custom.css',true, true) ?>" type="text/css"/>



    <style>
        .well {
            background: #fff url("<?php echo $module->getUrl('img/logo.png', true,true);?>") 50% 50px no-repeat;
            background-size:12%;

        }
        body {
            background:url("<?php echo $module->getUrl('img/bg.jpg', true,true);?>") no-repeat;
        }
    </style>

    <!--[if lt IE 9]>
    <!--<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>-->
    <!--[endif]-->
    <!--[if IE 8]>
    <!--<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl('/css/ie/ie8.css',true, true) ?>" />-->
    <!--[endif]-->
    <!--[if IE 7]>
    <!--<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl('/css/ie/ie7.css',true, true) ?>" />-->
    <!--[endif]-->
    <!-- JS and jQuery -->
    <!-- <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
     -->
    <!--[if lt IE 9]>
    <script src="<?php echo $module->getUrl('/js/respond.js',true, true) ?>"></script>
    <!--[endif]-->

    <!-- PLACING JSCRIPT IN HEAD OUT OF SIMPLICITY - http://stackoverflow.com/questions/10994335/javascript-head-body-or-jquery -->
    <!-- Latest compiled and minified JavaScript -->
    <!--
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.13.1/jquery.validate.min.js"></script>
    -->
    <!-- Local version for development here -->
    <script src="<?php echo $module->getUrl('/js/jquery.min.js',true, true) ?>"></script>
    <script src="<?php echo $module->getUrl('/js/jquery.validate.min.js',true, true) ?>"></script>
    <script src="<?php echo $module->getUrl('/js/bootstrap-formhelpers.min.js',true, true) ?>"></script>
    <!-- <script src="<?php echo $module->getUrl('/js/bootstrap.min.js',true, true) ?>"></script> -->

    <!-- custom JS -->
    <!-- <script src="<?php echo $module->getUrl('/js/custom.js',true, true) ?>"></script> -->
    <script type='text/javascript' src="<?php echo $module->getUrl('/js/intake.js', true, true) ?>"></script>

    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Crimson+Text:400,600,700' rel='stylesheet' type='text/css'>
<!--    <script src="https://www.google.com/recaptcha/api.js"></script>-->
</head>
<body class="login register">
<div id="su-wrap">
    <div id="su-content"><div id="content" class="container" role="main" tabindex="0">
            <div class="row">
                <div id="main-content" class="col-md-8 col-md-offset-2 registerAccount" role="main">
                    <div class="well row">
                        <form id="getstarted" action="" class="form-horizontal" method="POST" role="form">
                            <input type="hidden" name="lang_req" value="en"/>
                            <h2>Register at SNAPL</h2>
                            <div class="form-group">
                                <label for="email" class="control-label col-sm-3">Your Name:</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" name="firstname" id="firstname" placeholder="First Name" value="">
                                </div>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" name="lastname" id="lastname" placeholder="Last Name" value="">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="username" class="control-label col-sm-3">Your Email:</label>
                                <div class="col-sm-8">
                                    <input type="email" class="form-control" name="username" id="username" placeholder="Email Address" value="">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="usernametoo" class="control-label col-sm-3">Re-enter Email:</label>
                                <div class="col-sm-8">
                                    <input type="email" class="form-control" name="usernametoo" id="usernametoo" placeholder="Email Address" >
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="phone" class="control-label col-sm-3">Your Phone:</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control input-medium bfh-phone" data-format="+1 (ddd) ddd-dddd" name="phone" id="phone" placeholder="Phone Number" >
                                </div>
                            </div>


                            <aside class="eligibility">
                                <fieldset class="eli_one">
                                    <div class="form-group">
                                        <div class="col-sm-3">

                                        </div>
                                        <div class="col-sm-6"
                                            <label class="control-label col-sm-8">Would you like to be notified of developments in the
                                            Stanford Neuroscience & Pain Lab that may be of interest to you (e.g., free classes,
                                            free educational events, or research studies)? </label>
                                        </div>
                                        <div class="col-sm-2">
                                            <label><input name="osf_ok_contact" type="radio" value="1"> Yes</label><br>
                                            <label><input name="osf_ok_contact" type="radio" value="0"> No</label>
                                        </div>
                                    </div>
                                </fieldset>
                            </aside>

                            <div class="form-group">
                                <span class="control-label col-sm-3"></span>
                                <div class="col-sm-8">
                                    <em>Click SUBMIT to begin the intake survey.</em>
                                </div>
                            </div>
                            <div class="form-group">
                                <span class="control-label col-sm-3"></span>
                                <div class="col-sm-8">
                                    <!-- <div class="g-recaptcha" data-sitekey="6LcEIQoTAAAAAE5Nnibe4NGQHTNXzgd3tWYz8dlP"></div> -->
                                    <button type="submit" class="btn btn-primary" name="submit_new_user"  value="true">Submit</button>
                                    <input type="hidden" name="submit_new_user" value="true"/>
                                    <input type="hidden" name="optin" value="true"/>
                                </div>
                            </div>
<!--                            <div class="form-group">-->
<!--                                <span class="control-label col-sm-3"></span>-->
<!--                                <div class="col-sm-8">-->
<!--                                    <a href="login.php" class="showlogin">Already Registered?</a>-->
<!--                                </div>-->
<!--                            </div>-->
                        </form>
                        <script>

                            $("input[name='in_usa']").click(function(){
                                if($(this).val() == 1) {
                                    $(".eli_two").slideDown("medium");
                                }else{
                                    $(".eli_two").slideUp("fast");
                                }
                            });

                            $('#getstarted').validate({
                                rules: {
                                    firstname:{
                                        required: true
                                    },
                                    lastname:{
                                        required: true
                                    },
                                    username: {
                                        required: true, email: true        },
                                    usernametoo: {
                                        equalTo: "#username"
                                    },
                                    city:{
                                        required: true
                                    },
                                    zip: {
                                        required: true
                                    },
                                    nextyear: {
                                        required: function(){
                                            return $(".eligibility").is(':visible');
                                        }
                                    },
                                    oldenough: {
                                        required: function(){
                                            return $(".eli_two").is(':visible');
                                        }
                                    }

                                },
                                highlight: function(element) {
                                    $(element).closest('.form-group').addClass('has-error');
                                },
                                unhighlight: function(element) {
                                    $(element).closest('.form-group').removeClass('has-error');
                                },
                                errorElement: 'span',
                                errorClass: 'help-block',
                                errorPlacement: function(error, element) {
                                    if(element.parent('.input-group').length) {
                                        error.insertAfter(element.parent());
                                    } else {
                                        error.insertAfter(element);
                                    }
                                }
                            });

                            $("#getstarted").submit(function(){
                                var formValues = {};

                                $.each($(this).serializeArray(), function(i, field) {
                                    formValues[field.name] = field.value;
                                });

                                if(formValues.firstname == "" || formValues.lastname == "" || formValues.username == "" || $(this).find(".help-block").length){
                                    return;
                                }

                                //ADD LOADING DOTS
                                $("button[name='submit_new_user']").append("<img width=50 height=14 src='<?php echo $module->getUrl('img/loading_dots.gif',true,false) ?>'/>")
                            });
                        </script>	  	</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<script>
    $(document).on('click', function(event) {
        if ($(event.target).closest('.alert').length) {
            $(".alert").fadeOut("fast",function(){
                $(".alert").remove();
            });
        }

    });
</script>