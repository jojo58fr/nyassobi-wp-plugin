<?php
/**
 * Main plugin class for Nyassobi WP Plugin.
 *
 * @package NyassobiWPPlugin
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Nyassobi_WP_Plugin
{
    private const OPTION_NAME = 'nyassobi_wp_plugin';
    private const OPTION_GROUP = 'nyassobi_wp_plugin_group';
    private const PAGE_SLUG = 'nyassobi-wp-plugin';
    private const CAPABILITY = 'manage_options';

    /** @var self|null */
    private static $instance = null;

    /**
     * Singleton accessor.
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor hooks.
     */
    private function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('graphql_register_types', [$this, 'register_graphql_types']);
        add_action('graphql_register_types', [$this, 'register_contact_mutation']);
    }

    /**
     * Registers the option and its fields using the Settings API.
     */
    public function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [],
            ]
        );

        add_settings_section(
            'nyassobi_wp_plugin_section',
            __('Paramètres Nyassobi', 'nyassobi-wp-plugin'),
            function (): void {
                echo '<p>' . esc_html__(
                    'Indiquez les informations qui seront utilisées par le site headless Nyassobi.',
                    'nyassobi-wp-plugin'
                ) . '</p>';
            },
            self::PAGE_SLUG
        );

        foreach ($this->get_fields_definition() as $field_key => $field) {
            add_settings_field(
                $field_key,
                esc_html($field['label']),
                [$this, 'render_field'],
                self::PAGE_SLUG,
                'nyassobi_wp_plugin_section',
                [
                    'key' => $field_key,
                    'type' => $field['type'],
                    'description' => $field['description'],
                ]
            );
        }
    }

    /**
     * Adds the Nyassobi settings page to the admin menu.
     */
    public function register_settings_page(): void
    {
        add_options_page(
            __('Paramètres Nyassobi', 'nyassobi-wp-plugin'),
            __('Paramètres Nyassobi', 'nyassobi-wp-plugin'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Outputs the settings page markup.
     */
    public function render_settings_page(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Paramètres Nyassobi', 'nyassobi-wp-plugin'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renders a field input.
     *
     * @param array<string,string> $args Context provided by add_settings_field.
     */
    public function render_field(array $args): void
    {
        $options = self::get_settings();
        $value = $options[$args['key']] ?? '';
        $description = $args['description'] ?? '';

        if ('email' === $args['type']) {
            printf(
                '<input type="email" name="%1$s[%2$s]" id="%2$s" value="%3$s" class="regular-text" />',
                esc_attr(self::OPTION_NAME),
                esc_attr($args['key']),
                esc_attr($value)
            );
        } else {
            printf(
                '<input type="url" name="%1$s[%2$s]" id="%2$s" value="%3$s" class="regular-text code" />',
                esc_attr(self::OPTION_NAME),
                esc_attr($args['key']),
                esc_attr($value)
            );
        }

        if ($description) {
            printf(
                '<p class="description">%s</p>',
                esc_html($description)
            );
        }
    }

    /**
     * Sanitizes option values before persisting them.
     *
     * @param array<string,mixed>|null $input Raw user input.
     *
     * @return array<string,string>
     */
    public function sanitize_settings(?array $input): array
    {
        $sanitized = [];
        $fields = $this->get_fields_definition();

        foreach ($fields as $key => $field) {
            $raw_value = isset($input[$key]) ? (string) $input[$key] : '';

            if ('email' === $field['type']) {
                $sanitized[$key] = sanitize_email($raw_value);
            } else {
                $sanitized[$key] = esc_url_raw($raw_value);
            }
        }

        return array_filter(
            $sanitized,
            static function (?string $value): bool {
                // Preserve empty strings to avoid PHP < 8.1 array_filter behavior differences.
                return null !== $value;
            }
        );
    }

    /**
     * Registers GraphQL type and field when WPGraphQL is active.
     */
    public function register_graphql_types(): void
    {
        if (! function_exists('register_graphql_object_type') || ! function_exists('register_graphql_field')) {
            return;
        }

        register_graphql_object_type(
            'NyassobiSettings',
            [
                'description' => __('Paramètres Nyassobi pour le site headless.', 'nyassobi-wp-plugin'),
                'fields' => [
                    'contactEmail' => [
                        'type' => 'String',
                        'description' => __('Adresse email de contact.', 'nyassobi-wp-plugin'),
                    ],
                    'signupFormUrl' => [
                        'type' => 'String',
                        'description' => __('URL du formulaire d\'inscription.', 'nyassobi-wp-plugin'),
                    ],
                    'parentalAgreementUrl' => [
                        'type' => 'String',
                        'description' => __('URL pour l\'accord parental.', 'nyassobi-wp-plugin'),
                    ],
                    'associationStatusUrl' => [
                        'type' => 'String',
                        'description' => __('URL pour les statuts associatifs.', 'nyassobi-wp-plugin'),
                    ],
                    'internalRulesUrl' => [
                        'type' => 'String',
                        'description' => __('URL pour le règlement intérieur.', 'nyassobi-wp-plugin'),
                    ],
                ],
            ]
        );

        register_graphql_field(
            'RootQuery',
            'nyassobiSettings',
            [
                'type' => 'NyassobiSettings',
                'description' => __('Paramètres disponibles pour le front-end Nyassobi.', 'nyassobi-wp-plugin'),
                'resolve' => static function (): array {
                    return Nyassobi_WP_Plugin::format_settings_for_graphql();
                },
            ]
        );
    }

    /**
     * Registers the WPGraphQL mutation used by the React front-end contact form.
     */
    public function register_contact_mutation(): void
    {
        if (! function_exists('register_graphql_mutation')) {
            return;
        }

        register_graphql_mutation(
            'sendNyassobiContactMessage',
            [
                'inputFields' => [
                    'fullname' => [
                        'type' => ['non_null' => 'String'],
                        'description' => __('Full name of the requester.', 'nyassobi-wp-plugin'),
                    ],
                    'email' => [
                        'type' => ['non_null' => 'String'],
                        'description' => __('Email address of the requester.', 'nyassobi-wp-plugin'),
                    ],
                    'subject' => [
                        'type' => ['non_null' => 'String'],
                        'description' => __('Subject of the contact request.', 'nyassobi-wp-plugin'),
                    ],
                    'message' => [
                        'type' => ['non_null' => 'String'],
                        'description' => __('Message body provided by the requester.', 'nyassobi-wp-plugin'),
                    ],
                    'token' => [
                        'type' => 'String',
                        'description' => __('Optional anti-spam token.', 'nyassobi-wp-plugin'),
                    ],
                ],
                'outputFields' => [
                    'success' => [
                        'type' => ['non_null' => 'Boolean'],
                        'description' => __('Flag indicating whether wp_mail succeeded.', 'nyassobi-wp-plugin'),
                        'resolve' => static function ($payload, array $args = [], $context = null, $info = null): bool {
                            $payload = is_array($payload) ? $payload : [];
                            return (bool) ($payload['success'] ?? false);
                        },
                    ],
                    'message' => [
                        'type' => ['non_null' => 'String'],
                        'description' => __('Human readable status returned by the mutation.', 'nyassobi-wp-plugin'),
                        'resolve' => static function ($payload, array $args = [], $context = null, $info = null): string {
                            $payload = is_array($payload) ? $payload : [];
                            return (string) ($payload['message'] ?? '');
                        },
                    ],
                ],
                'mutateAndGetPayload' => function (array $input, $context = null, $info = null): array {
                    return $this->handle_contact_mutation($input);
                },
            ]
        );
    }

    /**
     * Validates and sends the contact email.
     *
     * @param array<string,mixed> $input Raw mutation arguments.
     *
     * @return array{success:bool,message:string}
     */
    private function handle_contact_mutation(array $input): array
    {
        $user_error_class = '\GraphQL\Error\UserError';

        if (! class_exists($user_error_class)) {
            return [
                'success' => false,
                'message' => __('GraphQL error handler is not available.', 'nyassobi-wp-plugin'),
            ];
        }

        // Sanitize user supplied values.
        $fullname = sanitize_text_field((string) ($input['fullname'] ?? ''));
        $email = sanitize_email((string) ($input['email'] ?? ''));
        $subject = sanitize_text_field((string) ($input['subject'] ?? ''));
        $message = sanitize_textarea_field((string) ($input['message'] ?? ''));
        $token = isset($input['token']) ? sanitize_text_field((string) $input['token']) : '';

        $sanitized = [
            'fullname' => $fullname,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'token' => $token,
        ];

        if ('' === $fullname) {
            throw new $user_error_class(__('The fullname field is required.', 'nyassobi-wp-plugin'));
        }

        if ('' === $subject) {
            throw new $user_error_class(__('The subject field is required.', 'nyassobi-wp-plugin'));
        }

        if ('' === $message) {
            throw new $user_error_class(__('The message field is required.', 'nyassobi-wp-plugin'));
        }

        if (! is_email($email)) {
            throw new $user_error_class(__('Please supply a valid email address.', 'nyassobi-wp-plugin'));
        }

        // TODO: plug in reCAPTCHA or nonce validation via the filter below.
        $token_valid = apply_filters('nyassobi_wp_plugin_validate_contact_token', true, $token, $sanitized, $input);
        if (! $token_valid) {
            throw new $user_error_class(__('Security validation failed.', 'nyassobi-wp-plugin'));
        }

        $settings = self::get_settings();
        $recipient = isset($settings['contact_email']) ? sanitize_email((string) $settings['contact_email']) : '';

        if (! is_email($recipient)) {
            $recipient = sanitize_email((string) get_option('admin_email'));
        }

        if (! is_email($recipient)) {
            throw new $user_error_class(__('No valid recipient email is configured.', 'nyassobi-wp-plugin'));
        }

        $mail_subject = sprintf('[Nyassobi] %s', $subject);
        $body_lines = [
            'New Nyassobi contact request received:',
            '',
            'Full name: ' . $fullname,
            'Email: ' . $email,
            '',
            'Subject: ' . $subject,
            '',
            'Message:',
            $message,
        ];
        $body = implode("\n", $body_lines);

        // Compose headers with Reply-To so the team can answer quickly.
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        if ($email) {
            $reply_to_name = $fullname !== '' ? $fullname : $email;
            $headers[] = sprintf('Reply-To: %s <%s>', $reply_to_name, $email);
        }

        $headers = apply_filters('nyassobi_wp_plugin_contact_headers', $headers, $sanitized, $input);
        $body = apply_filters('nyassobi_wp_plugin_contact_body', $body, $sanitized, $input);
        $mail_subject = apply_filters('nyassobi_wp_plugin_contact_subject', $mail_subject, $sanitized, $input);
        $recipient = apply_filters('nyassobi_wp_plugin_contact_recipient', $recipient, $sanitized, $input);

        if (! is_email($recipient)) {
            throw new $user_error_class(__('No valid recipient email is configured.', 'nyassobi-wp-plugin'));
        }

        $sent = wp_mail($recipient, $mail_subject, $body, $headers);

        if (! $sent) {
            return [
                'success' => false,
                'message' => __('The message could not be sent. Please try again later.', 'nyassobi-wp-plugin'),
            ];
        }

        return [
            'success' => true,
            'message' => __('Thank you! Your message has been sent.', 'nyassobi-wp-plugin'),
        ];
    }

    /**
     * Retrieves plugin settings as stored.
     *
     * @return array<string,string>
     */
    public static function get_settings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        if (is_array($stored)) {
            return array_map(
                static function ($value): string {
                    return (string) $value;
                },
                $stored
            );
        }

        return [];
    }

    /**
     * Formats settings for GraphQL responses.
     *
     * @return array<string,?string>
     */
    public static function format_settings_for_graphql(): array
    {
        $options = self::get_settings();

        return [
            'contactEmail' => $options['contact_email'] ?? null,
            'signupFormUrl' => $options['signup_form_url'] ?? null,
            'parentalAgreementUrl' => $options['parental_agreement_url'] ?? null,
            'associationStatusUrl' => $options['association_status_url'] ?? null,
            'internalRulesUrl' => $options['internal_rules_url'] ?? null,
        ];
    }

    /**
     * Field metadata.
     *
     * @return array<string,array<string,string>>
     */
    private function get_fields_definition(): array
    {
        return [
            'contact_email' => [
                'label' => __('Adresse email de contact', 'nyassobi-wp-plugin'),
                'description' => __('Email principal pour les demandes entrantes.', 'nyassobi-wp-plugin'),
                'type' => 'email',
            ],
            'signup_form_url' => [
                'label' => __('URL du formulaire d\'inscription', 'nyassobi-wp-plugin'),
                'description' => __('Lien vers le formulaire d\'adhésion en ligne.', 'nyassobi-wp-plugin'),
                'type' => 'url',
            ],
            'parental_agreement_url' => [
                'label' => __('URL de l\'accord parental', 'nyassobi-wp-plugin'),
                'description' => __('Lien vers le document d\'accord parental.', 'nyassobi-wp-plugin'),
                'type' => 'url',
            ],
            'association_status_url' => [
                'label' => __('URL des statuts associatifs', 'nyassobi-wp-plugin'),
                'description' => __('Lien vers les statuts officiels de l\'association.', 'nyassobi-wp-plugin'),
                'type' => 'url',
            ],
            'internal_rules_url' => [
                'label' => __('URL du règlement intérieur', 'nyassobi-wp-plugin'),
                'description' => __('Lien vers le règlement intérieur actualisé.', 'nyassobi-wp-plugin'),
                'type' => 'url',
            ],
        ];
    }
}
