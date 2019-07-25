<?php

namespace Drupal\single_sign_on\EventSubscriber;

use Drupal\single_sign_on\Controller\SingleSignOnController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SingleSignOnSubscriber implements EventSubscriberInterface
{
    // Adds the single sign-on handler.
    public static function getSubscribedEvents()
    {
        // Run after AuthenticationSubscriber.
        $events[KernelEvents::REQUEST][] = array('single_sign_on_init');
        return $events;
    }

    // Single sign-on handler.
    public function single_sign_on_init(GetResponseEvent $event)
    {
        // Read settings.
        $settings = single_sign_on_get_settings();

        // Check the callback.
        $single_sign_on = new SingleSignOnController();
        $status = $single_sign_on->process_callback();

        // Check what needs to be done.
        switch (strtolower($status->action))
        {
            // //////////////////////////////////////////////////////////////////////////
            // No user found and we cannot add users
            // //////////////////////////////////////////////////////////////////////////
            case 'new_user_no_login_autocreate_off':

                // Grace Period
                single_sign_on_set_login_wait_cookie($settings['blocked_wait_relogin']);

                // Add log.
                single_sign_on_dump('[INIT] @' . $status->action . '] Guest detected but account creation is disabled. Blocking automatic SSO re-login for [' . $settings['blocked_wait_relogin'] . '] seconds, until [' . date("d/m/y H:i:s", $settings['blocked_wait_relogin']) . ']');

                break;

            // //////////////////////////////////////////////////////////////////////////
            // User found and logged in
            // //////////////////////////////////////////////////////////////////////////

            // Created a new user.
            case 'new_user_created_login':

            // Logged in using the user_token.
            case 'existing_user_login_user_token':

            // Logged in using a verified email address.
            case 'existing_user_login_email_verified':

            // Logged in using an un-verified email address.
            case 'existing_user_login_email_unverified':

                // Add log.
                single_sign_on_dump('[INIT] @' . $status->action . ' - User is logged in');

                // Remove cookies.
                single_sign_on_unset_login_wait_cookie();

                // Login user.
                user_login_finalize($status->user);

                // Are we using HTTPs?
                $is_https = \Drupal::request()->isSecure();
                $current_uri = single_sign_on_get_current_url($is_https);

                return new \Symfony\Component\HttpFoundation\RedirectResponse($current_uri);

                break;

            // //////////////////////////////////////////////////////////////////////////
            // User found, but we cannot log him in
            // //////////////////////////////////////////////////////////////////////////

            // User found, but autolink disabled.
            case 'existing_user_no_login_autolink_off':

            // User found, but autolink not allowed.
            case 'existing_user_no_login_autolink_not_allowed':

            // Customer found, but autolink disabled for unverified emails.
            case 'existing_user_no_login_autolink_off_unverified_emails':

                // Grace period.
                single_sign_on_set_login_wait_cookie($settings['blocked_wait_relogin']);

                // Add a notice for the user.
                single_sign_on_enable_user_notice($status->user);

                // Add log.
                single_sign_on_dump('[INIT] @' . $status->action . '] - Blocking automatic SSO re-login for [' . $settings['blocked_wait_relogin'] . '] seconds, until [' . date("d/m/y H:i:s", $settings['blocked_wait_relogin']) . ']');

                break;

            // //////////////////////////////////////////////////////////////////////////
            // Default
            // //////////////////////////////////////////////////////////////////////////

            // No callback received
            case 'no_callback_data_received':
            default:

                  // The user is logged in.
                if (\Drupal::currentUser()->isAuthenticated())
                {
                    // Read the user's token.
                    $token = single_sign_on_get_user_token_information_for_uid(\Drupal::currentUser()->id());

                    // We have a session token, refresh it.
                    if (!empty($token->sso_session_token))
                    {
                        single_sign_on_dump('[INIT] @' . $status->action . '] [UID' . \Drupal::currentUser()->id() . '] - User is logged in, refreshing session token ['.$token->sso_session_token.']');
                    }
                    else
                    {
                        single_sign_on_dump('[INIT] @' . $status->action . '] [UID' . \Drupal::currentUser()->id() . '] - User is logged in but has no sso session token yet');
                    }
                }
                else
                {
                    // If this value is in the future, we should not try to login the user with SSO.
                    $login_wait = single_sign_on_get_login_wait_value_from_cookie();

                    // Wait time exceeded?
                    if ($login_wait < time())
                    {
                        single_sign_on_dump('[INIT] @' . $status->action . ' - User is logged out. Checking for valid SSO session');
                    }
                    else
                    {
                        single_sign_on_dump('[INIT] @' . $status->action . ' - User is logged out. Re-login disabled, ' . ($login_wait - time()) . ' seconds remaining');
                    }
                }
                break;
        }
    }
}
