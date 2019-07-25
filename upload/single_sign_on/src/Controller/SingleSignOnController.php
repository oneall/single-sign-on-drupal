<?php

namespace Drupal\single_sign_on\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Contains the callback handler used by the OneAll Single Sign-On extension.
 */
class SingleSignOnController extends ControllerBase
{
    // Get user notice.
    public function get_user_notice()
    {
        return single_sign_display_user_notice();
    }

    // Read user token (Ajax, see single_sign_on.js)
    public function get_user_sso_token()
    {
        // The user is currently logged in.
        if (\Drupal::currentUser()->isAuthenticated())
        {
            // Read the user's token.
            $token = single_sign_on_get_user_token_information_for_uid(\Drupal::currentUser()->id());

            // We have a session token, refresh it.
            if (!empty($token->sso_session_token))
            {
                // Add log.
                single_sign_on_dump('[SSO JS] [UID' . \Drupal::currentUser()->id() . '] Open session found, registering token [' . $token->sso_session_token . ']');

                // Return token.
                return $token->sso_session_token;
            }
        }
        // The user is currently not logged in.
        else
        {
             // If this value is in the future, we should not try to login the user with SSO.
             $login_wait = single_sign_on_get_login_wait_value_from_cookie();

             // Try to login the user.
             if ($login_wait < time())
             {
                // Add log.
                single_sign_on_dump('[SSO JS] No open session found, checking...');

                // Return status.
                return 'check_session';
            }
        }

        // No action.
        return 'no_token_found';
    }

    /**
     * Ajax call to retrieve the session token (referenced by routing.yml).
     */
    public function embed_single_sign_on_js($controller, $function)
    {
        $args = func_get_args();
        $r = array();
        $r['val'] = null;

        if ($controller != 'SingleSignOn')
        {
            $class = "\\Drupal\\" . $controller . "\\Controller\\" . ucfirst($controller) . "Controller";

            $obj = new $class();
        }
        else
        {
            $obj = $this;
        }

        if (method_exists($obj, $function))
        {
            $data = $obj->$function();
            $r['val'] = render($data);
        }

        return new JsonResponse($r);
    }

    /**
     * This is the callback handler (referenced by routing.yml).
     */
    public function process_callback()
    {
        // Read settings.
        $settings = single_sign_on_get_settings();

        // Result container.
        $status = new \stdClass();
        $status->action = 'error';

        // Callback Handler.
        if (isset($_POST) && !empty($_POST['oa_action']) && $_POST['oa_action'] == 'single_sign_on' && isset($_POST['connection_token']) && single_sign_on_is_uuid($_POST['connection_token']))
        {
            $connection_token = $_POST['connection_token'];

            // Add log.
            single_sign_on_dump('[SSO Callback] Callback for connection_token [' . $connection_token . '] detected');

            // We cannot make a connection without a subdomain.
            if (!empty($settings['api_subdomain']))
            {
                // See: http://docs.oneall.com/api/resources/connections/read-connection-details/
                $api_resource_url = get_api_url() . '/connections/' . $connection_token . '.json';

                // API options.
                $api_options = array(
                    'api_key' => $settings['api_key'],
                    'api_secret' => $settings['api_secret']
                );

                // Read connection details.
                $result = single_sign_on_do_api_request($api_resource_url, 'GET', $api_options);

                // Check result.
                if (is_object($result) && property_exists($result, 'http_code') && $result->http_code == 200 && property_exists($result, 'http_data'))
                {
                    // Decode result.
                    $decoded_result = @json_decode($result->http_data);

                    // Check data.
                    if (is_object($decoded_result) && isset($decoded_result->response->result->data->user))
                    {
                        // Extract user data.
                        $data = $decoded_result->response->result->data;

                        // The user_token uniquely identifies the user.
                        $user_token = $data->user->user_token;

                        // The identity_token uniquely identifies the user's data.
                        $identity_token = $data->user->identity->identity_token;

                        // provider name
                        $provider = !empty($data->user->identity->provider) ? $data->user->identity->provider : '';

                        // Add Log.
                        single_sign_on_dump('[CALLBACK] Token user_token [' . $user_token . '] / identity_token [' . $identity_token . '] retrieved for connection_token [' . $connection_token . ']');

                        // Add to status.
                        $status->user_token = $user_token;
                        $status->identity_token = $identity_token;

                        // Check if we have a customer for this user_token.
                        $user = single_sign_on_get_user_for_user_token($user_token);

                        // User found.
                        if (is_object($user) && !empty($user->id()))
                        {
                            $uid = $user->id();

                            // Add Log.
                            single_sign_on_dump('[CALLBACK] Customer [' . $uid . '] logged in for user_token [' . $user_token . ']');

                            // Update (This is just to make sure that the table is always correct).
                            single_sign_on_add_local_storage_tokens_for_uid($uid, $user_token, $identity_token, $provider);

                            // Update status.
                            $status->action = 'existing_user_login_user_token';
                            $status->user = $user;

                            return $status;
                        }

                        // Add Log.
                        single_sign_on_dump('[CALLBACK] No user found for user_token [' . $user_token . ']. Trying email lookup.');

                        // Retrieve email from identity.
                        if (isset($data->user->identity->emails) && is_array($data->user->identity->emails) && count($data->user->identity->emails) > 0)
                        {
                            // Email details.
                            $email = $data->user->identity->emails[0]->value;
                            $email_is_verified = ($data->user->identity->emails[0]->is_verified ? true : false);
                            $email_is_random = false;

                            // Check if we have a user for this email.
                            $user = user_load_by_mail($email);

                            // User found.
                            if (is_object($user) && !empty($user->id()))
                            {
                                $uid = $user->id();

                                // Update Status
                                $status->user = $user;

                                // Add log.
                                single_sign_on_dump('[CALLBACK] [U' . $uid . '] User found for email [' . $email . ']');

                                // Automatic link is disabled.
                                if (empty ($settings['auto_link_accounts']))
                                {
                                    // Add log.
                                    single_sign_on_dump('[CALLBACK] [U' . $uid . '] Autolink is disabled for everybody.');

                                    // Update status.
                                    $status->action = 'existing_user_no_login_autolink_off';

                                    // Done

                                    return $status;
                                }
                                // Automatic link is enabled.
                                else
                                {
                                    // Automatic link is enabled, but not available for admins.
                                    if ($settings['auto_link_accounts'] == 2 && $user->hasRole('administrator'))
                                    {
                                        // Add log.
                                        single_sign_on_dump('[CALLBACK] [U' . $uid . '] User is admin and autolink is disabled for admins');

                                        // Update status.
                                        $status->action = 'existing_user_no_login_autolink_not_allowed';

                                        // Done.
                                        return $status;
                                    }

                                    // The email has been verified.
                                    if ($email_is_verified)
                                    {
                                        // Add Log.
                                        single_sign_on_dump('[CALLBACK] [U' . $uid . '] Autolink enabled/Email verified. Linking user_token [' . $user_token . '] to user');

                                        // Add to database.
                                        single_sign_on_add_local_storage_tokens_for_uid($uid, $user_token, $identity_token, $provider);

                                        // Update Status.
                                        $status->action = 'existing_user_login_email_verified';

                                        return $status;
                                    }
                                    // The email has NOT been verified.
                                    else
                                    {
                                        // We can use unverified emails.
                                        if (! empty ($settings['link_unverified_accounts']))
                                        {
                                            // Add Log.
                                            single_sign_on_dump('[CALLBACK] [U' . $uid . '] Autolink enabled/Email unverified. Linking user_token [' . $user_token . '] to user');

                                            // Add to database.
                                            single_sign_on_add_local_storage_tokens_for_uid($uid, $user_token, $identity_token, $provider);

                                            // Update Status.
                                            $status->action = 'existing_user_login_email_unverified';

                                            return $status;
                                        }
                                        // We cannot use unverified emails.
                                        else
                                        {
                                            // Add Log.
                                            single_sign_on_dump('[CALLBACK] [U' . $uid . '] Autolink enabled/Unverified email not allowed. May not link user_token [' . $user_token . '] to user');

                                            // Update Status.
                                            $status->action = 'existing_user_no_login_autolink_off_unverified_emails';

                                            return $status;
                                        }
                                    }
                                }
                            }
                            // No customer found
                            else
                            {
                                // Add Log
                                single_sign_on_dump('[CALLBACK] No user found for email [' . $email . ']');
                            }
                        }
                        else
                        {
                            // Create Random email.
                            $email = single_sign_on_create_random_email();
                            $email_is_verified = false;
                            $email_is_random = true;

                            // Add Log.
                            single_sign_on_dump('[CALLBACK] Identity provides no email address. Random address [' . $email . '] generated.');
                        }

                        // /////////////////////////////////////////////////////////////////////////
                        // This is a new user
                        // /////////////////////////////////////////////////////////////////////////

                        // We cannot create new accounts
                        if ( ! empty ($settings['auto_create_accounts']))
                        {
                            // Add Log
                            single_sign_on_dump('[SSO Callback] New user, but account creation disabled. Cannot create user for user_token [' . $user_token . ']');

                            // Update Status
                            $status->action = 'new_user_no_login_autocreate_off';

                            // Done

                            return $status;
                        }

                        // Add Log
                        single_sign_on_dump('[SSO Callback] New user, account creation enabled. Creating user for user_token [' . $user_token . ']');
                        // Extract firstname.
                        $user_first_name = (!empty($data->user->identity->name->givenName) ? $data->user->identity->name->givenName : '');

                        // Extract lastname.
                        $user_last_name = (!empty($data->user->identity->name->familyName) ? $data->user->identity->name->familyName : '');

                        // provider name
                        $provider = !empty($data->user->identity->provider) ? $data->user->identity->provider : '';

                        // Forge login.
                        $user_login = '';
                        if (!empty($data->user->identity->preferredUsername))
                        {
                            $user_login = $data->user->identity->preferredUsername;
                        }
                        elseif (!empty($data->user->identity->displayName))
                        {
                            $user_login = $data->user->identity->displayName;
                        }
                        elseif (!empty($data->user->identity->name->formatted))
                        {
                            $user_login = $data->user->identity->name->formatted;
                        }
                        else
                        {
                            $user_login = trim($user_first_name . ' ' . $user_last_name);
                        }

                        // The username cannot begin/end with a space.
                        $user_login = trim($user_login);

                        // The username cannot contain multiple spaces in a row.
                        $user_login = preg_replace('!\s+!', ' ', $user_login);

                        // Forge unique username.
                        if (strlen(trim($user_login)) == 0 || single_sign_on_get_uid_for_name(trim($user_login)) !== false)
                        {
                            $i = 1;
                            $user_login = $provider . $this->t('User');
                            while (single_sign_on_get_uid_for_name($user_login) !== false)
                            {
                                $user_login = $provider . $this->t('User') . ($i++);
                            }
                        }

                        // Forge password.
                        $user_password = user_password(8);

                        $user_fields = [
                            'name' => $user_login,
                            'mail' => $email,
                            'pass' => $user_password,
                            'init' => $email,
                            'roles' => array ()
                        ];

                        // Create a new user.
                        $user = \Drupal\user\Entity\User::create($user_fields);
                        $user->save();

                        // The new user has been created correctly.
                        if ($user !== false)
                        {
                            $uid = $user->id();

                            //  Add log.
                            single_sign_on_dump('[SSO Callback] New user [' . $user->id() . '] created for user_token [' . $user_token . ']');

                            // Add to database.
                            $add_tokens = single_sign_on_add_local_storage_tokens_for_uid($uid, $user_token, $identity_token, $provider);

                            // Login user.
                            user_login_finalize($user);

                            // Update status.
                            $status->action = 'new_user_created_login';
                            $status->user_token = $user_token;
                            $status->identity_token = $identity_token;
                            $status->user = $user;
                        }
                        else
                        {
                            $status->action = 'user_creation_failed';
                        }
                    }
                    else
                    {
                        $status->action = 'api_data_decode_failed';
                    }
                }
                else
                {
                    $status->action = 'api_connection_failed';
                }
            }
            else
            {
                $status->action = 'extension_not_setup';
            }
        }
        else
        {
            $status->action = 'no_callback_data_received';
        }

        return $status;
    }
}
