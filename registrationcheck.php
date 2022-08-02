<?php
/**
 * Template Name: Register
 *
 * Template for displaying the home page.
 *
 * @package understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
session_start();

require_once( wp_normalize_path(get_stylesheet_directory(). '/app/plugin-hubspot_integration.php'));
require_once( wp_normalize_path(get_stylesheet_directory(). '/custom-functions/check-user-company.php'));
require_once( wp_normalize_path(get_stylesheet_directory(). '/custom-functions/notification-new-user.php'));

$a = is_user_logged_in();

if ($a){
    header('Location: '.get_home_url().'/dashboard/');
}

$ClientFirstName = '';
$ClientLastName = '';
$ClientEmail = '';
$ClientCompanyName = '';
$CompanyCountry = '';
$CompanyIndustry = '';
$CompanyWebsite = '';
$PortalClientId = '';

if (isset($_GET['_q'])){

    $var = $_GET['_q'];
    $pass_parameter = $var;
    $var = base64_decode($var);
    $json = json_decode($var, true);

    $_SESSION['portalProspect'] = $json;


    $PortalClientId = $json['PortalClientId'];
    $ClientFirstName = $json['ClientFirstName'];
    $ClientLastName = $json['ClientLastName'];
    $ClientEmail = $json['ClientEmail'];
    $ClientCompanyName = $json['ClientCompanyName'];
    $CompanyAddressLine = $json['CompanyAddressLine'];
    $CompanyCity = $json['CompanyCity'];
    $CompanyCountry = $json['CompanyCountry'];
    $CompanyWebsite = $json['CompanyWebsite'];
    $CompanyIndustry = $json['CompanyIndustry'];
    $CompanySize = $json['CompanySize'];

}


if (isset($_POST['register_submit'])){

    // Google reCaptcha v3 -------------------------------------------------------
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $secret = "xxx--xxxx--xxx-xxxx";
    $response = $_POST['token_generate'];
    $remoteip = $_SERVER['REMOTE_ADDR'];

    $request = file_get_contents($url.'?secret='.$secret.'&response='.$response);
    $result = json_decode($request);
    // Google reCaptcha v3 -------------------------------------------------------

    
    $hiddespamfield = $_POST['hidden_spam_field'];

    $errors = array();

    $fname = sanitize_text_field($_POST['name_user_registration']);
    $email = $_POST['email_user_registration'];
    $pass = $_POST['password_register'];

    $fullname = explode(" ", $fname);

    if(!$fullname[1]){
        $fullname[1] = '';
    }

    $expressions = "/(gmail|googlemail|yahoo|icloud|hotmail|mail|aol|msn|live|vodafone|outlook|inbox|yandex|btinternet)/";

    // Validate password strength
    $uppercase = preg_match('@[A-Z]@', $pass);
    $lowercase = preg_match('@[a-z]@', $pass);
    $number    = preg_match('@[0-9]@', $pass);
    $specialChars = preg_match('@[^\w]@', $pass);


    if(!isset($_POST['checked'])){
        $errors['0'] = 'Please accept Privacy policy and Terms & conditions';
    }
    if($fname == '' || $pass == '' || $email == ''){
        $errors['1'] = 'Please enter your full name, e-mail and password';
    }

    if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($pass) < 6) {
        $errors['2'] = 'Password should be at least 6 characters in length and should include at least one upper case letter, one number, and one special character.';
    }

    if(preg_match($expressions, $email)) {
        $errors['3'] = 'Invalid email domain!';
    }

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['4'] = 'Invalid email format!';
    }

    if (username_exists($email)){
        $errors['5'] = 'An account with this email already exists. Please <a href="'.get_home_url().'/login">login</a> instead.';
    }

    if ($result->success == false){
        $errors['6'] = 'Are you a spam bot?';
    }

    if($hiddespamfield != ''){
        $errors['7'] = 'Are you a spam bot?';
    }

    
    if(empty($errors)) {

        $userdata = array (
            'user_login'    =>  $email,
            'user_email'    =>  $email,
            'user_pass'     =>  $pass,
            'first_name'    =>  $fullname[0],
            'last_name'     =>  $fullname[1]
        ) ;

        $user_id = wp_insert_user( $userdata ); ?>

            <script>

                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    'event' : 'WEBSITEHubRegistration',
                    'authenticationMethod' : 'FWEBSITEegistration',
                });

            </script>
        
        <?php
        if(isset($_SESSION["UTM"])){
            add_user_meta( $user_id, 'UTM', $_SESSION['UTM']);
        }

        setcookie('iduser',$user_id, time() + (86400 * 30), "/"); // 86400 = 1 day

        $creds = array(
            'user_login'    => $email,
            'user_password' => $pass,
            'remember'      => true
        );

        /**
         * Adding user to Hubspot
         */

        add_user_to_hubspot($email, $fullname[0], $fullname[1], $user_id);

        sleep(5);
        

        if(isset($_SESSION["portalProspect"])){

            add_user_meta( $user_id, 'portal_register', 0);

            check_company_exists($user_id); 

            $usermet = get_user_meta($user_id, 'portal_register', true);

            if($usermet == 0){

                $my_post = array(
                    'post_title'    => $ClientCompanyName,
                    'post_content'  => '',
                    'post_status'   => 'publish',
                    'post_type'     => 'companies',
                    'post_author'   =>  $user_id
                  );
                   
                $customPostID = wp_insert_post( $my_post );

                add_user_meta( $user_id, 'companyid', $customPostID );
    
                $firstlastname = $ClientFirstName . ' ' . $ClientLastName;
                update_field('company_name', $ClientCompanyName, $customPostID);
                update_field('company_location', $CompanyCountry, $customPostID);
                update_field('industries', $CompanyIndustry, $customPostID);
                update_field('email', $ClientEmail, $customPostID);
                update_field('field_606dc0f4ca6f2', $CompanyWebsite, $customPostID);
                update_field('first_last_name', $firstlastname, $customPostID);
                add_post_meta( $customPostID, '__portalClientID', $PortalClientId);
    
                if ($CompanyIndustry){
                    wp_set_object_terms( $customPostID, $CompanyIndustry, 'industires', false );
                }
                
            }

     
            $curl_url = "https://CUSTOMSAASURL.com/RegisterStatus?id=" . $PortalClientId;

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $curl_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Length: 0'
              ),
            ));

            $response = curl_exec($curl);
            $response_2 = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $errors = curl_error($curl);

            curl_close($curl);

            if ($response_2 == 200) {
                add_post_meta($customPostID, '__sent_to_portal', $PortalClientId);
            }
            
        }else{
            check_company_exists($user_id);
        }
     
        // $user = wp_signon( $creds, false );
     
        if ( is_wp_error( $user_id ) ) {
            echo $user_id->get_error_message();
        }
        else{

            $registerstatus = true;
        }

    }
}


get_header('white');
?>


<div class="loginWrap <?php if(isset($_GET['_q'])): ?>portal-class<?php endif; ?>">
    <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/iconLogin.svg" class="loginicon">
    <div class="container">
    <?php if ($registerstatus == true || isset($_GET['verification'])): ?>
        <div class="formWrap">
            <p>&nbsp;</p>
            <h2>Please verify your email</h2>
                <div class="mb-5">
                        Please verify your email by clicking the activation link we sent to <?php if($email): ?> <?php echo $email; ?> <?php else: ?> <?php echo $user_info->user_email; ?> <?php endif; ?>
                </div>
                <div class="text-center resend">
                    <p>Didn’t get the email? <a href="/register/?verification=resend">Resend</a></p>
                </div>
        </div>
   <?php else: ?>
        <div class="formWrap">
            <p>&nbsp;</p>
            <h2>Join for free</h2>
            <form method="post" id="registerForm">
                <div class="login-name">
                    <label for="user_name">Full Name</label>
                    
                    <?php if($ClientFirstName): ?>
                        <?php $name = $ClientFirstName . ' ' . $ClientLastName; ?>
                    <?php elseif(isset($_POST["name_user_registration"])): ?>
                        <?php $name = $_POST["name_user_registration"]; ?>
                    <?php else: ?>
                        <?php $name = ""; ?>
                    <?php endif; ?>

                    <input type="text" name="name_user_registration" id="user_name" class="input" value="<?php echo $name; ?>" size="20" autocomplete="off">
                </div>
                <div class="login-username">
                    <label for="user_reg">Email</label>

                    <?php if($ClientEmail): ?>
                        <?php $mail = $ClientEmail; ?>
                    <?php elseif(isset($_POST["email_user_registration"])): ?>
                        <?php $mail = $_POST["email_user_registration"]; ?>
                    <?php else: ?>
                        <?php $mail = ""; ?>
                    <?php endif; ?>

                    <input type="email" name="email_user_registration" id="user_reg" class="input" value="<?php echo $mail; ?>" size="20" autocomplete="off" >
                </div>
                <div class="login-password">
                    <label for="user_pass_registre">Password</label>
                    <input type="password" name="password_register" id="user_pass_registre"  class="input" value="" size="20">
                    <small><i id="eye" class="far fa-eye input-eye-icon"></i></small>
                </div>
                <div id="strengthMessage"></div>
                <input type="hidden" name="token_generate" id="token_generate" >
                <input type="text" name="hidden_spam_field" id="hidden_spam_field" class="d-none" >
                <label class="checkbox-inline">
                    <input type="checkbox" id="agree" value="" name="checked">I agree to the <a href="/privacy-policy/">Privacy Policy</a> and <a href="/terms-and-condition/">Terms and Services</a>
                </label>
                <div class="submit-register">
                    <input type="submit" name="register_submit" id="submit-register" class="button button-primary" value="Sign up">
                </div>


            </form>

            <!-- Error Messages -->
            <?php if(!empty($errors)): ?>
                <?php foreach($errors as $error): ?>
                    <div class="text-center">
                        <div class="alert alert-warning-custom alertToc" role="alert">                  
                            <p><?php echo $error; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <!-- Error Messages END -->


            <div class="text-center mt-3">
                <?php 
                    if (isset($_GET['form'])){ ?>
                        <p>Already have an account?<a href="<?php echo get_home_url(); ?>/login/?form=<?php echo $_GET['form']; ?>"> Login </a></p>
                    <?php
                    } else { ?>
                        <p>Already have an account?<a href="<?php echo get_home_url(); ?>/login"> Login </a></p>
                    <?php
                    }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://www.google.com/recaptcha/api.js?render=XXXXX-XXXX-XXXX"></script>

<script>

        grecaptcha.ready(function() {
          grecaptcha.execute('XXXXX_XXXXXX_XXXXX', {action: 'submit'}).then(function(token) {

            var response = document.getElementById('token_generate').value = token;

          });
        });
      
  </script>


<?php get_footer('loggedin'); ?>

<script>

jQuery(document).ready(function($){ 
    $('#user_pass_registre').keyup(function () {  
        
        $('#strengthMessage').html(checkStrength($('#user_pass_registre').val()))  

        if($('#user_pass_registre').val() == ''){
            $('#strengthMessage').removeClass()  
            $('#strengthMessage').html('') 
            
        } 
    })  

    function checkStrength(password) {  
        var strength = 0  
        if (password.length < 6) {  
            $('#strengthMessage').removeClass()  
            $('#strengthMessage').addClass('Short')  
            return 'Password should be at least 6 characters in length and should include at least one uppercase letter, one number, and one special character  ! " ? $ % ^ & )'  
        }  

        // If password contains both lower and uppercase characters, increase strength value.  
        if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1  
        // If it has numbers and characters, increase strength value.  
        if (password.match(/([0-9])/)) strength += 1  
        // If it has one special character, increase strength value.  
        if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength += 1  

        // Calculated strength value, we can return messages  

        // If value is less than 2  
        if (strength < 2) {  
            $('#strengthMessage').removeClass()  
            $('#strengthMessage').addClass('Weak')  
           
        } else if (strength == 2) {  
            $('#strengthMessage').removeClass()  
            $('#strengthMessage').addClass('Good')  
            
        } else {  
            $('#strengthMessage').removeClass()  
            $('#strengthMessage').addClass('Strong')  
        }  


    }   

        $("#eye").click(function() {
            var password = document.getElementById("user_pass_registre");
        if (password.type === "password") {
            password.type = "text";
            $('#eye').toggleClass("fa-eye fa-eye-slash");
        } else {
            password.type = "password";
            $('#eye').toggleClass("fa-eye-slash fa-eye");
        }
        });

    });
</script>

