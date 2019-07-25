jQuery(document).ready(function($){
    
    // Register Session.
    $.ajax({
        url: '/ajax/SingleSignOn/get_user_sso_token', 
        method :'GET',
        dataType: 'json', 
        success: function(result)
        {
           	if (result.hasOwnProperty('val') && typeof result['val'] === 'string' && result['val'].length)
           	{
                // Check for existing session.
                if (result.val == 'check_session')
                {
                    _oneall.push(['single_sign_on', 'do_check_for_sso_session', window.location.href, true]);                
                } 
                // Refresh current session.
                else
                {
                    if (result.val != 'no_token_found')
                    {
                        _oneall.push(['single_sign_on', 'do_register_sso_session', result.val]);
                    }
                }
        	}
        }
    });
    
    // Retrieve User Notices.
    $.ajax({
        url: '/ajax/SingleSignOn/get_user_notice', 
        method :'GET',
        dataType: '"json', 
        success: function(result)
        {
            if (result.hasOwnProperty('val') && typeof result['val'] === 'string' && result['val'].length)
            {
                $('#single_sign_on_notice_container').html(result.val);
            }
        }
    });
});
