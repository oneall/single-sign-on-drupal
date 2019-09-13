jQuery(document).ready(function($){
    
    // Register Session.
    $.ajax({
        url: '/ajax/single_sign_on/get_user_sso_token', 
        method :'GET',
        dataType: 'json', 
        success: function(result)
        {
           	if (result != undefined && typeof result === 'string')
           	{
                /* Initiates the OneAll asynchronous queue */
                var _oneall = window._oneall || [];
                
                // Check for existing session.
                if (result == 'check_session')
                {
                    _oneall.push(['single_sign_on', 'do_check_for_sso_session', window.location.href, true]);                
                } 
                // Refresh current session.
                else
                {   
                    if (result != 'no_token_found')
                    {
                        _oneall.push(['single_sign_on', 'do_register_sso_session', result]);
                    }
                }
        	}
        }
    });
    
    // Retrieve User Notices.
    $.ajax({
        url: '/ajax/single_sign_on/get_user_notice', 
        method :'GET',
        dataType: 'json', 
        success: function(result)
        {
            if (result != undefined && typeof result === 'string')
            {
                $('#single_sign_on_notice_container').html(result);
            }
        }
    });
});
