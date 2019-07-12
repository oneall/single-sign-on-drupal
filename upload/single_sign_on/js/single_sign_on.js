jQuery(document).ready(function($){
    
    // register session
    $.ajax({
        url: "/ajax/SingleSignOn/get_user_sso_token", 
        method :'GET',
        dataType: "json", 
        success: function(result){

        	sso_session_token = result.val;

        	if (sso_session_token != undefined){

                // Check for session
                if (sso_session_token == 'check_session'){
                    _oneall.push(['single_sign_on', 'do_check_for_sso_session', window.location.href, true]);                

                //register SSO
                } else {
                    _oneall.push(['single_sign_on', 'do_register_sso_session', sso_session_token]);
                }
        	}
        }
    });
    
    // Get Notices
    $.ajax({
        url: "/ajax/SingleSignOn/get_user_notice", 
        method :'GET',
        dataType: "json", 
        success: function(result){
            notice = result.val;
            $('#single_sign_on_notice_container').html(notice);
        }
    });
});

// cancel modal
function reload_page(){
   location.reload();
}
