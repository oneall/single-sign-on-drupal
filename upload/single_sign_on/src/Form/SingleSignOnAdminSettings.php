<?php

namespace Drupal\single_sign_on\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin configuration form.
 */
class SingleSignOnAdminSettings extends ConfigFormBase
{
    /**
     * Determines the ID of a form.
     */
    public function getFormId()
    {
        return 'single_sign_on_admin_settings';
    }

    /**
     * Gets the configuration names that will be editable.
     */
    public function getEditableConfigNames()
    {
        return [];
    }

    /**
     * Form constructor.
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['#attached'] = [
            'library' => [
                'single_sign_on/configuration'
            ]
        ];

        // Read Settings.
        $settings = single_sign_on_get_settings();

        // API Connection.
        $form['single_sign_on_api_connection'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('API Communication'),
            '#id' => 'single_sign_on_api_connection'
        ];

        // Default value for handler.
        if ($form_state->getValue(['http_handler']))
        {
            $default = $form_state->getValue([
                'http_handler'
            ]);
        }
        elseif (!empty($settings['http_handler']))
        {
            $default = $settings['http_handler'];
        }
        else
        {
            $default = 'curl';
        }

        $form['single_sign_on_api_connection']['http_handler'] = [
            '#type' => 'select',
            '#title' => $this->t('API Communication Handler'),
            '#description' => $this->t('Either <a href="@link_curl" target="_blank">PHP cURL</a> or the <a href="@link_guzzle" target="_blank">Drupal Guzzle client</a> must be available on your server.', [
                '@link_curl' => 'http://www.php.net/manual/en/book.curl.php',
                '@link_guzzle' => 'http://docs.guzzlephp.org/en/latest/'
            ]),
            '#options' => [
                'curl' => $this->t('PHP cURL library'),
                'fsockopen' => $this->t('Drupal Guzzle client')
            ],
            '#default_value' => $default
        ];

        // Default value for protocol.
        if ($form_state->getValue([
            'http_protocol'
        ]))
        {
            $default = $form_state->getValue([
                'http_protocol'
            ]);
        }
        elseif (!empty($settings['http_protocol']))
        {
            $default = $settings['http_protocol'];
        }
        else
        {
            $default = 'https';
        }

        $form['single_sign_on_api_connection']['http_protocol'] = [
            '#type' => 'select',
            '#title' => $this->t('API Communication Protocol'),
            '#description' => $this->t('Your firewall must allow outbound requests either on port 443/HTTPS or on port 80/HTTP.'),
            '#options' => [
                'https' => $this->t('Port 443/HTTPS'),
                'http' => $this->t('Port 80/HTTP')
            ],
            '#default_value' => $default
        ];

        // AutoDetect Button.
        $form['single_sign_on_api_connection']['autodetect'] = [
            '#type' => 'button',
            '#value' => $this->t('Autodetect communication settings'),
            '#weight' => 30,
            '#ajax' => [
                'callback' => 'Drupal\single_sign_on\Form\ajax_api_connection_autodetect',
                'wrapper' => 'single_sign_on_api_connection',
                'method' => 'replace',
                'effect' => 'fade'
            ]
        ];

        // Existing account.
        if (!empty($settings['api_subdomain']))
        {
            $form['single_sign_on_api_settings'] = [
                '#type' => 'fieldset',
                '#title' => $this->t('API Connection'),
                '#id' => 'single_sign_on_api_settings',
                '#description' => $this->t('<br /><a href="@setup_single_sign_on" target="_blank"><strong>Access API credentials</strong></a>', [
                    '@setup_single_sign_on' => 'https://app.oneall.com/applications/'
                ])
            ];
        }
        // New account.
        else
        {
            $form['single_sign_on_api_settings'] = [
                '#type' => 'fieldset',
                '#title' => $this->t('API Connection'),
                '#id' => 'single_sign_on_api_settings',
                '#description' => $this->t('<br /><a href="@setup_single_sign_on" target="_blank" class="button button--primary"><strong>Create an account and generate my API credentials</strong></a>', [
                    '@setup_single_sign_on' => 'https://app.oneall.com/signup/dp'
                ])
            ];
        }

        // API Subdomain.
        $form['single_sign_on_api_settings']['api_subdomain'] = [
            '#id' => 'api_subdomain',
            '#type' => 'textfield',
            '#title' => $this->t('API Subdomain'),
            '#default_value' => (!empty($settings['api_subdomain']) ? $settings['api_subdomain'] : ''),
            '#size' => 60,
            '#maxlength' => 60
        ];

        // API Public Key.
        $form['single_sign_on_api_settings']['api_key'] = [
            '#id' => 'api_key',
            '#type' => 'textfield',
            '#title' => $this->t('API Public Key'),
            '#default_value' => (!empty($settings['api_key']) ? $settings['api_key'] : ''),
            '#size' => 60,
            '#maxlength' => 60
        ];

        // API Private Key.
        $form['single_sign_on_api_settings']['api_secret'] = [
            '#id' => 'api_secret',
            '#type' => 'textfield',
            '#title' => $this->t('API Private Key'),
            '#default_value' => (!empty($settings['api_secret']) ? $settings['api_secret'] : ''),
            '#size' => 60,
            '#maxlength' => 60
        ];

        // API Verify Settings Button.
        $form['single_sign_on_api_settings']['verify'] = [
            '#id' => 'single_sign_on_check_api_button',
            '#type' => 'button',
            '#value' => $this->t('Verify API Settings'),
            '#weight' => 1,
            '#ajax' => [
                'callback' => 'Drupal\single_sign_on\Form\ajax_check_api_connection_settings',
                'wrapper' => 'single_sign_on_api_settings',
                'method' => 'replace',
                'effect' => 'fade'
            ]
        ];

        // General settings.
        $form['single_sign_on_settings_general'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Single Sign-On Settings')
        ];

        $form['single_sign_on_settings_general']['auto_create_accounts'] = [
            '#type' => 'select',
            '#title' => $this->t('Automatic Account Creation'),
            '#description' => $this->t('If enabled, the plugin automatically creates new user accounts for SSO users that visit the website but do not have an account yet. These users are then automatically logged in with the new account.'),
            '#options' => [
                '1' => $this->t('Enable automatic account creation (Default)'),
                '0' => $this->t('Disable automatic account creation')
            ],
            '#default_value' => (!empty($settings['auto_create_accounts']) ? 1 : 0)
        ];

        $form['single_sign_on_settings_general']['auto_link_accounts'] = [
            '#type' => 'select',
            '#title' => $this->t('Automatic Account Link'),
            '#description' => $this->t('If enabled, the plugin tries to link SSO users that visit the website to already existing user accounts. To link accounts the email address of the SSO user is matched against the email addresses of the existing users.'),
            '#options' => [
                '0' => $this->t('Disable automatic account link'),
                '1' => $this->t('Enable automatic link for all types of accounts'),
                '2' => $this->t('Enable automatic link for all types of accounts, except the admin account (Default)')
            ],
            '#default_value' => (!empty($settings['auto_link_accounts']) ? $settings['auto_link_accounts'] : 0)
        ];

        $form['single_sign_on_settings_general']['use_account_reminder'] = [
            '#type' => 'select',
            '#title' => $this->t('Account Reminder'),
            '#description' => $this->t('If enabled, the plugin will display a popup reminding the SSO of his account if an existing account has been found, but the user could not be logged in by the plugin (eg. if Automatic Account Link is disabled).'),
            '#options' => [
                '1' => $this->t('Enable account reminder (Default)'),
                '0' => $this->t('Disable account reminder')
            ],
            '#default_value' => (!empty($settings['use_account_reminder']) ? 1 : 0)
        ];

        $form['single_sign_on_settings_general']['destroy_session_on_logout'] = [
            '#type' => 'select',
            '#title' => $this->t('Destroy Session On Logout'),
            '#description' => $this->t('If enabled, the plugin destroys the user\'s SSO session whenever he logs out from Drupal. If you disable this setting, then do not use an empty value for the login delay, otherwise the user will be re-logged in instantly.'),
            '#options' => [
                '1' => $this->t('Yes. Destroy the SSO session on logout (Default, Recommended)'),
                '0' => $this->t('No. Keep the SSO session on logout.')
            ],
            '#default_value' => (!empty($settings['destroy_session_on_logout']) ? 1 : 0)
        ];

        $form['single_sign_on_settings_general']['logout_wait_relogin'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Re-Login Delay (Seconds)'),
            '#description' => $this->t('Whenever a user logs out, the plugin will not retry to login that user for the entered period. Please enter a positive integer or leave empty in order to disable.'),
            '#default_value' => $settings['logout_wait_relogin']
        ];

        // Debuggin settings.
        $form['single_sign_on_settings_debug'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Single Sign-On Debugging')
        ];

        $form['single_sign_on_settings_debug']['enable_debug_logs'] = [
            '#type' => 'select',
            '#title' => $this->t('Log Single Sign-On actions'),
            '#description' => $this->t('If enabled, the extension will write a debug log that can be viewed under <a href="/admin/reports/dblog">Manage \ Reports \ Recent log messages</a>.'),
            '#options' => [
                '1' => $this->t('Yes, enable logging'),
                '0' => $this->t('No, disabled logging')
            ],
            '#default_value' => (!empty($settings['enable_debug_logs']) ? 1 : 0)
        ];

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save Settings')
        ];

        return $form;
    }

    /**
     * Form submission handler.
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // Remove Drupal stuff.
        $form_state->cleanValues();

        // Settings
        $settings = $form_state->getValues();

        // API Subdomain.
        if (!empty($settings['subdomain']))
        {
            // The subdomain is always in lower-case.
            $settings['subdomain'] = strtolower(trim($settings['subdomain']));

            // Wrapper for full domains.
            if (preg_match("/([a-z0-9\-]+)\.api\.oneall\.com/i", $settings['subdomain'], $matches))
            {
                $settings['subdomain'] = trim($matches[1]);
            }
        }

        // Redirection \ signin.
        if (!empty($settings['redirect_login_path']))
        {
            if ($settings['redirect_login_path'] != 'custom')
            {
                $settings['redirect_login_custom_uri'] = '';
            }
            else
            {
                if (empty($settings['redirect_login_custom_uri']))
                {
                    $settings['redirect_login_path'] = 'home';
                }
            }
        }

        // Redirection \ signup.
        if (!empty($settings['redirect_register_path']))
        {
            if ($settings['redirect_register_path'] != 'custom')
            {
                $settings['redirect_register_custom_uri'] = '';
            }
            else
            {
                if (empty($settings['redirect_register_custom_uri']))
                {
                    $settings['redirect_register_path'] = 'home';
                }
            }
        }

        if ($settings['logout_wait_relogin'] == '')
        {
            $settings['logout_wait_relogin'] = '';
        }
        else
        {
            $settings['logout_wait_relogin'] = $settings['logout_wait_relogin'];
        }

        // Save values.
        foreach ($settings as $setting => $value)
        {
            // Clean.
            $value = trim($value);

            // Check if settings already exists.
            $oaslsid = Database::getConnection()->select('oasl_settings', 'o')->fields('o', ['oaslsid'])->condition('setting', $setting, '=')->execute()->fetchField();
            if (is_numeric($oaslsid))
            {
                // Update setting.
                Database::getConnection()->update('oasl_settings')->fields(['value' => $value])->condition('oaslsid', $oaslsid, '=')->execute();
            }
            else
            {
                // Add setting.
                Database::getConnection()->insert('oasl_settings')->fields(['setting' => $setting, 'value' => $value])->execute();
            }
        }
        \Drupal::messenger()->addMessage(t('Settings saved successfully'));

        // Clear cache.
        \Drupal::cache()->deleteAll();
    }
}

///////////////////////////////////////////////////////////////////////////////
// AJAX CALLBACKS
///////////////////////////////////////////////////////////////////////////////

/**
 * Form callback to autodetect the API connection handler.
 */
function ajax_api_connection_autodetect($form, FormStateInterface $form_state)
{
    // Detected Settings.
    $http_handler = '';
    $http_protocol = '';

    // CURL+HTTPS Works.
    if (\single_sign_on_check_curl('https'))
    {
        $http_handler = 'curl';
        $http_protocol = 'https';
    }
    // FSOCKOPEN+HTTPS Works.
    elseif (\single_sign_on_check_fsockopen('https'))
    {
        $http_handler = 'fsockopen';
        $http_protocol = 'https';
    }
    // CURL+HTTP Works.
    elseif (\single_sign_on_check_curl('http'))
    {
        $http_handler = 'curl';
        $http_protocol = 'http';
    }
    // FSOCKOPEN+HTTP Works.
    elseif (\single_sign_on_check_fsockopen('http'))
    {
        $http_handler = 'fsockopen';
        $http_protocol = 'http';
    }

    // Working handler found.
    if (!empty($http_handler))
    {
        $form['single_sign_on_api_connection']['http_handler']['#value'] = $http_handler;
        $form['single_sign_on_api_connection']['http_protocol']['#value'] = $http_protocol;
        \Drupal::messenger()->addStatus(t('Autodetected @handler on port @port - do not forget to save your changes!', [
            '@handler' => ($http_handler == 'curl' ? 'PHP cURL' : 'PHP fsockopen'),
            '@port' => ($http_protocol == 'http' ? '80/HTTP' : '443/HTTPS')
        ]));
    }
    // Nothing works.
    else
    {
        \Drupal::messenger()->addError(t('Sorry, but the autodetection failed. Please try to open port 80/443 for outbound requests and install PHP cURL/fsockopen.'));
    }

    return $form['single_sign_on_api_connection'];
}

/**
 * Form callback Handler to verify the API Settings.
 */
function ajax_check_api_connection_settings($form, FormStateInterface $form_state)
{
    // Sanitize data.
    $api_subdomain = ($form_state->getValue('api_subdomain') !== null ? trim(strtolower($form_state->getValue('api_subdomain'))) : '');
    $api_key = ($form_state->getValue('api_key') !== null ? trim($form_state->getValue('api_key')) : '');
    $api_secret = ($form_state->getValue('api_secret') !== null ? trim($form_state->getValue('api_secret')) : '');

    $handler = ($form_state->getValue('http_handler') !== null ? $form_state->getValue('http_handler') : 'curl');
    $handler = ($handler == 'fsockopen' ? 'fsockopen' : 'curl');

    $protocol = ($form_state->getValue('http_protocol') !== null ? $form_state->getValue('http_protocol') : 'https');
    $protocol = ($protocol == 'http' ? 'http' : 'https');

    // Messages to be shown.
    $error_message = '';
    $success_message = '';

    // Some fields are empty.
    if (empty($api_subdomain) || empty($api_key) || empty($api_secret))
    {
        $error_message = t('Please fill out each of the fields below');
    }
    // All fields have been filled out.
    else
    {
        // Wrapper for full domains.
        if (preg_match("/([a-z0-9\-]+)\.api\.oneall\.com/i", $api_subdomain, $matches))
        {
            $api_subdomain = trim($matches[1]);
        }

        // Wrong syntax.
        if (!preg_match("/^[a-z0-9\-]+$/i", $api_subdomain))
        {
            $error_message = $this->t('The subdomain has a wrong syntax! Have you filled it out correctly?');
        }
        // Syntax seems to be OK.
        else
        {
            // Build API Settings.
            $api_domain = $protocol . '://' . $api_subdomain . '.' . SINGLE_SIGN_ON_API_DOMAIN . '/tools/ping.json';
            $api_options = [
                'api_key' => $api_key,
                'api_secret' => $api_secret
            ];

            // Send request.
            $result = \single_sign_on_do_api_request($api_domain, 'GET', $api_options);

            // Check result.
            if (is_object($result) && property_exists($result, 'http_code') && property_exists($result, 'http_data'))
            {
                switch ($result->http_code)
                {
                    case '401':
                        $error_message = t('The API credentials are wrong!');
                        break;

                    case '404':
                        $error_message = t('The subdomain does not exist. Have you filled it out correctly?');
                        break;

                    case '200':
                        $success_message = t('The settings are correct - do not forget to save your changes!');
                        break;

                    case 'n/a':
                        $error_message = is_null($result->http_data) ? t('Unknown API Error') : htmlspecialchars($result->http_data);
                        break;

                    default:
                        $error_message = t('Unknown API Error');
                        break;
                }
            }
            else
            {
                $error_message = t('Could not contact API. Your firewall probably blocks outoing requests on both ports (443 and 80)');
            }
        }
    }

    // Error.
    if (!empty($success_message))
    {
        \Drupal::messenger()->addStatus($success_message);
    }
    else
    {
        \Drupal::messenger()->addError($error_message);
    }

    return $form['single_sign_on_api_settings'];
}
